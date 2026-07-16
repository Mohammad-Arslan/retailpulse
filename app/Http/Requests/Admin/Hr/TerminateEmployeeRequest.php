<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Hr;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

final class TerminateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.manage-employees') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $employee */
        $employee = $this->route('employee');

        return [
            'termination_date' => [
                'required',
                'date',
                'after_or_equal:'.$employee->hire_date?->toDateString(),
            ],
        ];
    }
}
