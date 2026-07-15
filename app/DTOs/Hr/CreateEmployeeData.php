<?php

declare(strict_types=1);

namespace App\DTOs\Hr;

use App\Http\Requests\Admin\Hr\StoreEmployeeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final readonly class CreateEmployeeData
{
    /**
     * @param  array<string, mixed>  $employee
     * @param  array<string, mixed>|null  $profile
     * @param  array<string, mixed>|null  $shift
     * @param  array<string, mixed>|null  $medical
     * @param  list<array<string, mixed>>  $dependents
     * @param  list<array<string, mixed>>  $bankAccounts
     * @param  list<array<string, mixed>>  $branchAssignments
     * @param  list<array{type: string, images: list<UploadedFile>, cnic_front: ?UploadedFile, cnic_back: ?UploadedFile}>  $imageUploads
     * @param  list<int>  $removeImageIds
     */
    public function __construct(
        public array $employee,
        public ?array $profile,
        public ?array $shift,
        public ?array $medical,
        public array $dependents,
        public array $bankAccounts,
        public array $branchAssignments,
        public ?int $holidayCalendarId,
        public array $imageUploads,
        public array $removeImageIds = [],
    ) {}

    public static function fromRequest(StoreEmployeeRequest $request): self
    {
        return self::fromValidated(
            $request->validated(),
            self::parseImageUploads($request),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<array{type: string, images: list<UploadedFile>, cnic_front: ?UploadedFile, cnic_back: ?UploadedFile}>  $imageUploads
     */
    public static function fromValidated(array $validated, array $imageUploads = []): self
    {
        $employeeKeys = [
            'title', 'first_name', 'middle_name', 'last_name', 'preferred_name', 'gender', 'date_of_birth',
            'marital_status', 'nationality', 'email', 'phone', 'user_id', 'legal_entity_id', 'primary_branch_id',
            'department_id', 'designation_id', 'grade_id', 'reporting_manager_employee_id', 'salary_structure_id',
            'hire_date', 'termination_date', 'probation_end_date', 'confirmation_date', 'contract_end_date',
            'employment_type', 'joined_as', 'default_cost_centre_id', 'payment_method', 'status',
        ];

        $employee = [];
        foreach ($employeeKeys as $key) {
            if (array_key_exists($key, $validated)) {
                $employee[$key] = $validated[$key];
            }
        }

        if (array_key_exists('national_id', $validated)) {
            $employee['national_id_encrypted'] = $validated['national_id'];
        }

        return new self(
            employee: $employee,
            profile: $validated['profile'] ?? null,
            shift: $validated['shift'] ?? null,
            medical: $validated['medical'] ?? null,
            dependents: array_values($validated['dependents'] ?? []),
            bankAccounts: array_values($validated['bank_accounts'] ?? []),
            branchAssignments: array_values($validated['branch_assignments'] ?? []),
            holidayCalendarId: isset($validated['holiday_calendar_id']) ? (int) $validated['holiday_calendar_id'] : null,
            imageUploads: $imageUploads,
            removeImageIds: array_map('intval', $validated['remove_image_ids'] ?? []),
        );
    }

    /**
     * @return list<array{type: string, images: list<UploadedFile>, cnic_front: ?UploadedFile, cnic_back: ?UploadedFile}>
     */
    public static function parseImageUploads(Request $request): array
    {
        $inputs = $request->input('image_uploads', []);
        $files = $request->file('image_uploads', []);

        if (! is_array($inputs)) {
            $inputs = [];
        }
        if (! is_array($files)) {
            $files = [];
        }

        $indexes = array_unique(array_merge(array_keys($inputs), array_keys($files)));
        sort($indexes);

        $uploads = [];
        foreach ($indexes as $index) {
            $inputRow = is_array($inputs[$index] ?? null) ? $inputs[$index] : [];
            $fileRow = is_array($files[$index] ?? null) ? $files[$index] : [];

            $type = (string) ($inputRow['type'] ?? $fileRow['type'] ?? 'other');
            if (! in_array($type, ['cnic', 'photo', 'id_copy', 'other'], true)) {
                $type = 'other';
            }

            $cnicFront = ($fileRow['cnic_front'] ?? null) instanceof UploadedFile
                ? $fileRow['cnic_front']
                : null;
            $cnicBack = ($fileRow['cnic_back'] ?? null) instanceof UploadedFile
                ? $fileRow['cnic_back']
                : null;
            $images = self::normalizeFileList($fileRow['images'] ?? null);

            if ($type === 'cnic') {
                if ($cnicFront === null && $cnicBack === null) {
                    continue;
                }
            } elseif ($images === []) {
                continue;
            }

            $uploads[] = [
                'type' => $type,
                'images' => $images,
                'cnic_front' => $cnicFront,
                'cnic_back' => $cnicBack,
            ];
        }

        return $uploads;
    }

    /**
     * @return list<UploadedFile>
     */
    public static function normalizeFileList(mixed $files): array
    {
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (! is_array($files)) {
            return [];
        }

        return array_values(array_filter(
            $files,
            static fn ($file): bool => $file instanceof UploadedFile,
        ));
    }
}
