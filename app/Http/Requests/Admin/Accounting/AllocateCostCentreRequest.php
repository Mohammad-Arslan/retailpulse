<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use App\Enums\CostCentreAllocationMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AllocateCostCentreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.manage-cost-centres') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_journal_transaction_id' => ['required', 'integer', 'exists:journal_transactions,id'],
            'method' => ['required', 'string', Rule::in(CostCentreAllocationMethod::values())],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*.cost_centre_id' => ['required', 'integer', 'exists:cost_centres,id'],
            'targets.*.percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date', 'after_or_equal:period_from'],
        ];
    }
}
