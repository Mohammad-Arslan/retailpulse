<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use App\Enums\FiscalYearStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreFiscalYearRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_entity_id' => ['nullable', 'integer', 'exists:organization_entities,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['nullable', Rule::enum(FiscalYearStatus::class)],
        ];
    }
}
