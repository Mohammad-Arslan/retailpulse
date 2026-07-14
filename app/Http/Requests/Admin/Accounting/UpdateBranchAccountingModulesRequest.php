<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateBranchAccountingModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.manage-modules') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $knownKeys = array_keys(config('accounting_modules', []));

        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['required', 'string', Rule::in($knownKeys)],
        ];
    }
}
