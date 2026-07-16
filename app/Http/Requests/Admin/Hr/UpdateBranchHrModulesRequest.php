<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateBranchHrModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $known = array_keys(config('hr_payroll_modules', []));

        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['required', 'string', Rule::in($known)],
        ];
    }
}
