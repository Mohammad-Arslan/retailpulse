<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

final class FiscalYearReopenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.reopen-fiscal-year') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
