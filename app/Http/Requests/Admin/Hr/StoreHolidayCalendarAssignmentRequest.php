<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreHolidayCalendarAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('holiday.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assignable_type' => ['required', Rule::in(['employee', 'branch', 'legal_entity'])],
            'assignable_id' => ['required', 'integer', 'min:1'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:999'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        $data['assignable_type'] = match ($data['assignable_type']) {
            'employee' => \App\Models\Employee::class,
            'branch' => \App\Models\Branch::class,
            'legal_entity' => \App\Models\OrganizationEntity::class,
            default => $data['assignable_type'],
        };

        return $data;
    }
}
