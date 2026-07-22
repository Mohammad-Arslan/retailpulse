<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\DTOs\Hr\CreateEmployeeData;
use App\DTOs\Hr\CreateHolidayCalendarAssignmentData;
use App\DTOs\Hr\TerminateEmployeeData;
use App\DTOs\Hr\UpdateEmployeeData;
use App\Events\EmployeeCreated;
use App\Events\EmployeeReactivated;
use App\Events\EmployeeTerminated;
use App\Events\EmployeeUpdated;
use App\Events\OrgAssignmentChanged;
use App\Models\Branch;
use App\Models\CostCentre;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeBranchAssignment;
use App\Models\Grade;
use App\Models\HolidayCalendar;
use App\Models\OrganizationEntity;
use App\Models\SalaryStructure;
use App\Models\User;
use App\Repositories\Contracts\CurrencyRepositoryInterface;
use App\Services\Accounting\DocumentNumberService;
use App\Services\ImageService;
use App\Support\EmployeePresenter;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class EmployeeService
{
    /**
     * @var list<string>
     */
    private const ASSIGNMENT_TRACKED_FIELDS = EmployeeAssignmentService::TRACKED_FIELDS;

    /**
     * Fields outside EmployeeAssignmentService::TRACKED_FIELDS that leave
     * eligibility also keys on. Not schedulable/history-tracked like the
     * TRACKED_FIELDS set — these apply immediately, so the change is
     * detected and the shared OrgAssignmentChanged event dispatched directly
     * here rather than piggybacking on applyOrgChanges()'s scheduling.
     *
     * @var list<string>
     */
    private const ELIGIBILITY_ONLY_TRACKED_FIELDS = ['legal_entity_id', 'employment_type'];

    /**
     * @var list<string>
     */
    private const DETAIL_RELATIONS = [
        'legalEntity',
        'primaryBranch',
        'defaultCostCentre',
        'user',
        'department',
        'designation',
        'grade',
        'reportingManager',
        'salaryStructure',
        'profile',
        'medicalProfile',
        'shiftPreference',
        'dependents',
        'bankAccounts',
        'images',
        'branchAssignments.branch',
        'holidayAssignments.calendar',
    ];

    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly ReportingHierarchyService $hierarchy,
        private readonly CurrencyRepositoryInterface $currencies,
        private readonly HolidayCalendarService $holidayCalendars,
        private readonly ImageService $images,
        private readonly HrEmploymentTypeService $employmentTypes,
        private readonly EmployeeAssignmentService $assignments,
        private readonly HrEntitySettingsService $entitySettings,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function indexPayload(array $filters, int $perPage): array
    {
        return [
            'employees' => EmployeePresenter::paginated($this->paginate($filters, $perPage)),
            'filters' => $filters,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'departments' => Department::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createPayload(): array
    {
        return [
            ...$this->formOptions(),
            'nextCode' => $this->documentNumbers->peek('employee', 'EMP'),
        ];
    }

    /**
     * @return array{employee: array<string, mixed>}
     */
    public function showPayload(Employee $employee): array
    {
        $this->assignments->applyDueScheduledChanges($employee);
        $employee->load(self::DETAIL_RELATIONS);

        return [
            'employee' => EmployeePresenter::detail($employee),
            'assignmentHistory' => $this->assignments->historyForEmployee($employee),
            ...$this->formOptions($employee),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function editPayload(Employee $employee): array
    {
        $this->assignments->applyDueScheduledChanges($employee);
        $employee->load(self::DETAIL_RELATIONS);

        return [
            'employee' => EmployeePresenter::detail($employee),
            'assignmentHistory' => $this->assignments->historyForEmployee($employee),
            ...$this->formOptions($employee),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Employee::query()->with(['legalEntity', 'primaryBranch', 'department', 'designation']);

        if (($filters['search'] ?? '') !== '') {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search): void {
                $q->where('employee_code', 'like', $search)
                    ->orWhere('first_name', 'like', $search)
                    ->orWhere('last_name', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        if (($filters['status'] ?? '') !== '') {
            $query->where('status', $filters['status']);
        }

        if (($filters['branch_id'] ?? null) !== null && $filters['branch_id'] !== '') {
            $query->where('primary_branch_id', (int) $filters['branch_id']);
        }

        if (($filters['department_id'] ?? null) !== null && $filters['department_id'] !== '') {
            $query->where('department_id', (int) $filters['department_id']);
        }

        $sort = in_array($filters['sort'] ?? '', ['employee_code', 'first_name', 'hire_date', 'status'], true)
            ? $filters['sort']
            : 'employee_code';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sort, $direction)->paginate($perPage);
    }

    public function create(CreateEmployeeData $data): Employee
    {
        return DB::transaction(function () use ($data): Employee {
            $attributes = $data->employee;
            $sequenceKey = 'employee';
            $legalEntityId = isset($attributes['legal_entity_id']) ? (int) $attributes['legal_entity_id'] : null;
            if ($legalEntityId !== null) {
                $entitySetting = $this->entitySettings->forEntity($legalEntityId);
                $configuredKey = $entitySetting?->employee_code_sequence_key;
                if (is_string($configuredKey) && $configuredKey !== '') {
                    $sequenceKey = $configuredKey;
                }
            }

            $code = $this->documentNumbers->next(
                $sequenceKey,
                'EMP',
                isset($attributes['primary_branch_id']) ? (int) $attributes['primary_branch_id'] : null,
            );

            $employee = Employee::query()->create([
                ...$attributes,
                'employee_code' => $code,
                'status' => $attributes['status'] ?? 'active',
            ]);

            if ($employee->reporting_manager_employee_id !== null) {
                $this->hierarchy->assertNoCycle($employee->id, (int) $employee->reporting_manager_employee_id);
                $this->hierarchy->recordManagerChange(
                    $employee,
                    (int) $employee->reporting_manager_employee_id,
                    (int) Auth::id(),
                );
            }

            $this->syncNested($employee, $data->profile, $data->shift, $data->medical, $data->dependents, $data->bankAccounts, $data->branchAssignments);
            $this->syncHolidayAssignment($employee, $data->holidayCalendarId, true);
            $this->syncImages($employee, $data->imageUploads, []);

            $this->assignments->recordBaseline($employee, $employee->hire_date?->toDateString() ?? now()->toDateString());

            $employee = $employee->fresh(self::DETAIL_RELATIONS) ?? $employee;

            event(new EmployeeCreated($employee));

            return $employee;
        });
    }

    public function update(Employee $employee, UpdateEmployeeData $data): Employee
    {
        return DB::transaction(function () use ($employee, $data): Employee {
            $attributes = $data->employee;

            if (array_key_exists('reporting_manager_employee_id', $attributes)) {
                $newManagerId = $attributes['reporting_manager_employee_id'] !== null
                    ? (int) $attributes['reporting_manager_employee_id']
                    : null;

                if ($newManagerId !== null) {
                    $this->hierarchy->assertNoCycle($employee->id, $newManagerId);
                }

                if ($newManagerId !== $employee->reporting_manager_employee_id) {
                    $this->hierarchy->recordManagerChange($employee, $newManagerId, (int) Auth::id());
                }
            }

            $this->assignments->applyOrgChanges($employee, $attributes, $data->orgEffectiveFrom);

            $before = $employee->only(self::ELIGIBILITY_ONLY_TRACKED_FIELDS);

            $employee->update($attributes);

            foreach (self::ELIGIBILITY_ONLY_TRACKED_FIELDS as $field) {
                if (! array_key_exists($field, $attributes)) {
                    continue;
                }

                $oldValue = $before[$field] !== null ? (string) $before[$field] : null;
                $newValue = $employee->{$field} !== null ? (string) $employee->{$field} : null;

                if ($oldValue !== $newValue) {
                    event(new OrgAssignmentChanged($employee, $field, $oldValue, $newValue, now()->toDateString()));
                }
            }

            $this->syncNested(
                $employee,
                $data->profile,
                $data->shift,
                $data->medical,
                $data->dependents,
                $data->bankAccounts,
                $data->branchAssignments,
            );

            if ($data->holidayCalendarProvided) {
                $this->syncHolidayAssignment($employee, $data->holidayCalendarId, true);
            }

            $this->syncImages($employee, $data->imageUploads, $data->removeImageIds);

            $employee = $employee->fresh(self::DETAIL_RELATIONS) ?? $employee;

            event(new EmployeeUpdated($employee));

            return $employee;
        });
    }

    public function terminate(Employee $employee, TerminateEmployeeData $data): Employee
    {
        if ($employee->status === 'terminated') {
            throw new DomainException(__('Employee Is Already Terminated.'));
        }

        return DB::transaction(function () use ($employee, $data): Employee {
            $employee->update([
                'status' => 'terminated',
                'termination_date' => $data->terminationDate,
            ]);

            $employee = $employee->fresh(self::DETAIL_RELATIONS) ?? $employee;

            event(new EmployeeTerminated($employee));

            return $employee;
        });
    }

    public function reactivate(Employee $employee): Employee
    {
        if ($employee->status !== 'terminated') {
            throw new DomainException(__('Only Terminated Employees Can Be Reactivated.'));
        }

        return DB::transaction(function () use ($employee): Employee {
            $employee->update([
                'status' => 'active',
                'termination_date' => null,
            ]);

            $employee = $employee->fresh(self::DETAIL_RELATIONS) ?? $employee;

            event(new EmployeeReactivated($employee));

            return $employee;
        });
    }

    /**
     * @param  list<array{type: string, images: list<UploadedFile>, cnic_front: ?UploadedFile, cnic_back: ?UploadedFile}>  $imageUploads
     * @param  list<int>  $removeImageIds
     */
    private function syncImages(Employee $employee, array $imageUploads, array $removeImageIds): void
    {
        if ($removeImageIds !== []) {
            $this->images->removeMany($employee, $removeImageIds);
        }

        foreach ($imageUploads as $upload) {
            $type = (string) ($upload['type'] ?? 'other');

            if ($type === 'cnic') {
                $cnicFront = $upload['cnic_front'] ?? null;
                $cnicBack = $upload['cnic_back'] ?? null;
                if ($cnicFront !== null) {
                    $this->images->replaceByAlt($employee, $cnicFront, 'cnic_front');
                }
                if ($cnicBack !== null) {
                    $this->images->replaceByAlt($employee, $cnicBack, 'cnic_back');
                }

                continue;
            }

            $images = $upload['images'] ?? [];
            if ($images !== []) {
                $this->images->attachMany($employee, $images, $type !== '' ? $type : 'other');
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $profile
     * @param  array<string, mixed>|null  $shift
     * @param  array<string, mixed>|null  $medical
     * @param  list<array<string, mixed>>  $dependents
     * @param  list<array<string, mixed>>  $bankAccounts
     * @param  list<array<string, mixed>>  $branchAssignments
     */
    private function syncNested(
        Employee $employee,
        ?array $profile,
        ?array $shift,
        ?array $medical,
        array $dependents,
        array $bankAccounts,
        array $branchAssignments,
    ): void {
        if ($profile !== null) {
            $employee->profile()->updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'address_line1' => $profile['address_line1'] ?? null,
                    'address_line2' => $profile['address_line2'] ?? null,
                    'city' => $profile['city'] ?? null,
                    'state' => $profile['state'] ?? null,
                    'postal_code' => $profile['postal_code'] ?? null,
                    'country' => $profile['country'] ?? null,
                    'emergency_contact_name' => $profile['emergency_contact_name'] ?? null,
                    'emergency_contact_phone' => $profile['emergency_contact_phone'] ?? null,
                    'emergency_contact_relation' => $profile['emergency_contact_relation'] ?? null,
                    'attendance_grace_minutes' => (int) ($profile['attendance_grace_minutes'] ?? 0),
                    'overtime_eligible' => (bool) ($profile['overtime_eligible'] ?? true),
                ],
            );
        }

        if ($shift !== null) {
            $weekendDaysEnabled = (bool) ($shift['weekend_days_enabled'] ?? false);

            $employee->shiftPreference()->updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'shift_label' => $shift['shift_label'] ?? null,
                    'start_time' => $shift['start_time'] ?: null,
                    'end_time' => $shift['end_time'] ?: null,
                    'rest_days' => $shift['rest_days'] ?? [],
                    'weekend_days_enabled' => $weekendDaysEnabled,
                    'weekend_days' => $weekendDaysEnabled
                        ? array_values(array_map('intval', $shift['weekend_days'] ?? []))
                        : null,
                    'notes' => $shift['notes'] ?? null,
                ],
            );
        }

        if ($medical !== null) {
            $employee->medicalProfile()->updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'blood_group' => $medical['blood_group'] ?? null,
                    'allergies' => $medical['allergies'] ?? null,
                    'conditions' => $medical['conditions'] ?? null,
                    'insurance_provider' => $medical['insurance_provider'] ?? null,
                    'insurance_policy_no' => $medical['insurance_policy_no'] ?? null,
                    'emergency_notes' => $medical['emergency_notes'] ?? null,
                ],
            );
        }

        $keepDependentIds = [];
        foreach ($dependents as $index => $row) {
            $payload = [
                'name' => $row['name'],
                'relation' => $row['relation'],
                'date_of_birth' => $row['date_of_birth'] ?? null,
                'gender' => $row['gender'] ?? null,
                'national_id' => $row['national_id'] ?? null,
                'phone' => $row['phone'] ?? null,
                'is_emergency_contact' => (bool) ($row['is_emergency_contact'] ?? false),
                'sort_order' => $index,
            ];

            if (! empty($row['id'])) {
                $dependent = $employee->dependents()->whereKey((int) $row['id'])->first();
                if ($dependent !== null) {
                    $dependent->update($payload);
                    $keepDependentIds[] = $dependent->id;

                    continue;
                }
            }

            $keepDependentIds[] = $employee->dependents()->create($payload)->id;
        }
        $employee->dependents()->whereNotIn('id', $keepDependentIds ?: [0])->delete();

        $keepBankIds = [];
        foreach ($bankAccounts as $row) {
            $payload = [
                'label' => $row['label'] ?? null,
                'bank_name' => $row['bank_name'],
                'account_number' => $row['account_number'],
                'iban' => $row['iban'] ?? null,
                'currency_code' => $row['currency_code'] ?? null,
                'payment_method' => $row['payment_method'] ?? null,
                'is_primary' => (bool) ($row['is_primary'] ?? false),
                'status' => 'active',
            ];

            if (! empty($row['id'])) {
                $bank = $employee->bankAccounts()->whereKey((int) $row['id'])->first();
                if ($bank !== null) {
                    $bank->update($payload);
                    $keepBankIds[] = $bank->id;

                    continue;
                }
            }

            $keepBankIds[] = $employee->bankAccounts()->create($payload)->id;
        }
        $employee->bankAccounts()->whereNotIn('id', $keepBankIds ?: [0])->delete();

        $employee->branchAssignments()->delete();
        foreach ($branchAssignments as $row) {
            EmployeeBranchAssignment::query()->create([
                'employee_id' => $employee->id,
                'branch_id' => (int) $row['branch_id'],
                'is_primary' => false,
                'effective_from' => $row['effective_from'],
                'effective_to' => $row['effective_to'] ?? null,
                'status' => $row['status'] ?? 'active',
            ]);
        }
    }

    private function syncHolidayAssignment(Employee $employee, ?int $calendarId, bool $replace): void
    {
        if (! $replace) {
            return;
        }

        $employee->holidayAssignments()->delete();

        if ($calendarId === null) {
            return;
        }

        $calendar = HolidayCalendar::query()->find($calendarId);
        if ($calendar === null) {
            return;
        }

        $this->holidayCalendars->assignCalendar(new CreateHolidayCalendarAssignmentData(
            holidayCalendarId: $calendar->id,
            assignableType: Employee::class,
            assignableId: $employee->id,
            effectiveFrom: now()->toDateString(),
            effectiveTo: null,
            priority: 100,
            status: 'active',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(?Employee $excludeFromManagers = null): array
    {
        $managerQuery = Employee::query()->where('status', 'active')->orderBy('first_name');
        if ($excludeFromManagers !== null) {
            $managerQuery->where('id', '!=', $excludeFromManagers->id);
        }

        return [
            'legalEntities' => OrganizationEntity::query()
                ->where('status', 'active')
                ->orderBy('legal_name')
                ->get(['id', 'legal_name', 'functional_currency_code']),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'costCentres' => CostCentre::query()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name']),
            'departments' => Department::query()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name', 'legal_entity_id']),
            'designations' => Designation::query()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name', 'legal_entity_id']),
            'grades' => Grade::query()->where('status', 'active')->orderBy('rank')->orderBy('name')->get(['id', 'code', 'name', 'legal_entity_id']),
            'managers' => $managerQuery->get(['id', 'first_name', 'last_name', 'employee_code', 'legal_entity_id']),
            'salaryStructures' => SalaryStructure::query()->orderBy('name')->get(['id', 'name', 'code']),
            'currencies' => $this->currencies->activeOptions(),
            'holidayCalendars' => HolidayCalendar::query()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name']),
            'employmentTypes' => $this->employmentTypeOptions($excludeFromManagers?->legal_entity_id),
            'genders' => ['male', 'female', 'other', 'undisclosed'],
            'maritalStatuses' => ['single', 'married', 'divorced', 'widowed', 'other'],
            'attachmentTypes' => ['cnic', 'photo', 'id_copy', 'other'],
            'maxImages' => (int) config('media.max_images_per_model', 10),
            'weekDays' => [0, 1, 2, 3, 4, 5, 6],
            'linkableUsers' => $this->linkableUserOptions($excludeFromManagers),
        ];
    }

    /**
     * Users not already linked to a different employee, plus this employee's own linked
     * user (if editing) so the current selection still resolves in the dropdown.
     *
     * @return list<array{id: int, name: string, email: ?string, role: ?string}>
     */
    private function linkableUserOptions(?Employee $employee): array
    {
        $linkedUserIds = Employee::query()
            ->whereNotNull('user_id')
            ->when($employee !== null, fn ($query) => $query->where('id', '!=', $employee->id))
            ->pluck('user_id');

        return User::query()
            ->whereNotIn('id', $linkedUserIds)
            ->with('roles')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    private function employmentTypeOptions(?int $legalEntityId): array
    {
        $options = $this->employmentTypes->optionsForEntity($legalEntityId);

        if ($options === []) {
            return [
                ['code' => 'full_time', 'name' => 'Full Time'],
                ['code' => 'part_time', 'name' => 'Part Time'],
                ['code' => 'contract', 'name' => 'Contract'],
                ['code' => 'hourly', 'name' => 'Hourly'],
            ];
        }

        return $options;
    }
}
