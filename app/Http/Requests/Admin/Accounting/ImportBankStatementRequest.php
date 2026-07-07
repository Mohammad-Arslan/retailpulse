<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

final class ImportBankStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.manage-bank-accounts') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ];
    }
}
