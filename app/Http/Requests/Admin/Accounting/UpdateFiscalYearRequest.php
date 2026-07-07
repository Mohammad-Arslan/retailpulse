<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use App\Enums\FiscalYearStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateFiscalYearRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'legal_entity_id' => ['nullable', 'integer', 'exists:organization_entities,id'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date', 'after:start_date'],
            'status' => ['nullable', Rule::enum(FiscalYearStatus::class)],
        ];
    }
}
