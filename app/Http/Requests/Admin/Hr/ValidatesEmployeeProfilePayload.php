<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Hr;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesEmployeeProfilePayload
{
    /**
     * @return array<string, mixed>
     */
    protected function employeeCoreRules(?int $ignoreEmployeeId = null): array
    {
        return [
            'title' => ['nullable', 'string', 'max:32'],
            'first_name' => ['required', 'string', 'max:120'],
            'middle_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'preferred_name' => ['nullable', 'string', 'max:120'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other', 'undisclosed'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed', 'other'])],
            'nationality' => ['nullable', 'string', 'max:64'],
            'national_id' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'legal_entity_id' => ['required', 'integer', 'exists:organization_entities,id'],
            'primary_branch_id' => ['required', 'integer', 'exists:branches,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'designation_id' => ['nullable', 'integer', 'exists:designations,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'reporting_manager_employee_id' => array_values(array_filter([
                'nullable',
                'integer',
                'exists:employees,id',
                $ignoreEmployeeId !== null ? Rule::notIn([$ignoreEmployeeId]) : null,
            ])),
            'salary_structure_id' => ['nullable', 'integer', 'exists:salary_structures,id'],
            'hire_date' => ['required', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'probation_end_date' => ['nullable', 'date'],
            'confirmation_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'employment_type' => ['required', Rule::in(['full_time', 'part_time', 'contract', 'hourly'])],
            'joined_as' => ['nullable', 'string', 'max:120'],
            'default_cost_centre_id' => ['nullable', 'integer', 'exists:cost_centres,id'],
            'payment_method' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'terminated'])],
            'profile' => ['nullable', 'array'],
            'profile.address_line1' => ['nullable', 'string', 'max:255'],
            'profile.address_line2' => ['nullable', 'string', 'max:255'],
            'profile.city' => ['nullable', 'string', 'max:120'],
            'profile.state' => ['nullable', 'string', 'max:120'],
            'profile.postal_code' => ['nullable', 'string', 'max:32'],
            'profile.country' => ['nullable', 'string', 'max:120'],
            'profile.emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'profile.emergency_contact_phone' => ['nullable', 'string', 'max:64'],
            'profile.emergency_contact_relation' => ['nullable', 'string', 'max:64'],
            'profile.attendance_grace_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'profile.overtime_eligible' => ['nullable', 'boolean'],
            'shift' => ['nullable', 'array'],
            'shift.shift_label' => ['nullable', 'string', 'max:120'],
            'shift.start_time' => ['nullable', 'date_format:H:i'],
            'shift.end_time' => ['nullable', 'date_format:H:i'],
            'shift.rest_days' => ['nullable', 'array'],
            'shift.rest_days.*' => ['integer', 'between:0,6'],
            'shift.notes' => ['nullable', 'string', 'max:2000'],
            'medical' => ['nullable', 'array'],
            'medical.blood_group' => ['nullable', 'string', 'max:16'],
            'medical.allergies' => ['nullable', 'string', 'max:5000'],
            'medical.conditions' => ['nullable', 'string', 'max:5000'],
            'medical.insurance_provider' => ['nullable', 'string', 'max:120'],
            'medical.insurance_policy_no' => ['nullable', 'string', 'max:120'],
            'medical.emergency_notes' => ['nullable', 'string', 'max:5000'],
            'dependents' => ['nullable', 'array'],
            'dependents.*.id' => ['nullable', 'integer'],
            'dependents.*.name' => ['required_with:dependents', 'string', 'max:120'],
            'dependents.*.relation' => ['required_with:dependents', 'string', 'max:64'],
            'dependents.*.date_of_birth' => ['nullable', 'date', 'before:today'],
            'dependents.*.gender' => ['nullable', Rule::in(['male', 'female', 'other', 'undisclosed'])],
            'dependents.*.national_id' => ['nullable', 'string', 'max:64'],
            'dependents.*.phone' => ['nullable', 'string', 'max:64'],
            'dependents.*.is_emergency_contact' => ['nullable', 'boolean'],
            'bank_accounts' => ['nullable', 'array'],
            'bank_accounts.*.id' => ['nullable', 'integer'],
            'bank_accounts.*.label' => ['nullable', 'string', 'max:120'],
            'bank_accounts.*.bank_name' => ['required_with:bank_accounts', 'string', 'max:120'],
            'bank_accounts.*.account_number' => ['required_with:bank_accounts', 'string', 'max:64'],
            'bank_accounts.*.iban' => ['nullable', 'string', 'max:64'],
            'bank_accounts.*.currency_code' => [
                'nullable',
                'string',
                'size:3',
                Rule::exists('currencies', 'code')->where('status', 'active'),
            ],
            'bank_accounts.*.payment_method' => ['nullable', 'string', 'max:32'],
            'bank_accounts.*.is_primary' => ['nullable', 'boolean'],
            'branch_assignments' => ['nullable', 'array'],
            'branch_assignments.*.branch_id' => ['required_with:branch_assignments', 'integer', 'exists:branches,id'],
            'branch_assignments.*.effective_from' => ['required_with:branch_assignments', 'date'],
            'branch_assignments.*.effective_to' => ['nullable', 'date', 'after_or_equal:branch_assignments.*.effective_from'],
            'branch_assignments.*.status' => ['nullable', Rule::in(['active', 'inactive'])],
            'holiday_calendar_id' => ['nullable', 'integer', 'exists:holiday_calendars,id'],
            'image_uploads' => ['nullable', 'array', 'max:10'],
            'image_uploads.*.type' => ['required', Rule::in(['cnic', 'photo', 'id_copy', 'other'])],
            'image_uploads.*.images' => ['nullable', 'array', 'max:10'],
            'image_uploads.*.images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) config('media.max_upload_kb', 5120)],
            'image_uploads.*.cnic_front' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) config('media.max_upload_kb', 5120)],
            'image_uploads.*.cnic_back' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) config('media.max_upload_kb', 5120)],
            'remove_image_ids' => ['nullable', 'array'],
            'remove_image_ids.*' => ['integer'],
        ];
    }

    protected function withEmployeeProfileValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hire = $this->input('hire_date');
            $probation = $this->input('probation_end_date');
            $confirmation = $this->input('confirmation_date');

            if ($hire && $probation && $confirmation && $probation > $confirmation) {
                $validator->errors()->add('probation_end_date', __('Probation End Must Be On Or Before Confirmation Date.'));
            }

            $banks = $this->input('bank_accounts', []);
            if (is_array($banks) && $banks !== []) {
                $primaryCount = collect($banks)->filter(fn ($b) => ($b['is_primary'] ?? false) === true || ($b['is_primary'] ?? '') === '1' || ($b['is_primary'] ?? '') === 1)->count();
                if ($primaryCount === 0) {
                    $validator->errors()->add('bank_accounts', __('Select Exactly One Primary Bank Account.'));
                }
                if ($primaryCount > 1) {
                    $validator->errors()->add('bank_accounts', __('Only One Primary Bank Account Is Allowed.'));
                }
            }
        });
    }

    protected function normalizeEmployeeProfileBooleans(): void
    {
        $profile = $this->input('profile');
        if (is_array($profile) && array_key_exists('overtime_eligible', $profile)) {
            $profile['overtime_eligible'] = filter_var(
                $profile['overtime_eligible'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            ) ?? false;
            $this->merge(['profile' => $profile]);
        }

        $banks = $this->input('bank_accounts');
        if (is_array($banks)) {
            $this->merge([
                'bank_accounts' => array_map(static function ($row) {
                    if (! is_array($row)) {
                        return $row;
                    }
                    if (array_key_exists('is_primary', $row)) {
                        $row['is_primary'] = filter_var($row['is_primary'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
                    }

                    return $row;
                }, $banks),
            ]);
        }

        $dependents = $this->input('dependents');
        if (is_array($dependents)) {
            $this->merge([
                'dependents' => array_map(static function ($row) {
                    if (! is_array($row)) {
                        return $row;
                    }
                    if (array_key_exists('is_emergency_contact', $row)) {
                        $row['is_emergency_contact'] = filter_var(
                            $row['is_emergency_contact'],
                            FILTER_VALIDATE_BOOLEAN,
                            FILTER_NULL_ON_FAILURE,
                        ) ?? false;
                    }

                    return $row;
                }, $dependents),
            ]);
        }
    }
}
