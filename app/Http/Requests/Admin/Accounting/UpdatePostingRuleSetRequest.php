<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use App\Enums\AccountResolutionType;
use App\Enums\AmountSource;
use App\Enums\PostingRuleEntrySide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePostingRuleSetRequest extends FormRequest
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
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'effective_from' => ['sometimes', 'required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sequence' => ['required', 'integer', 'min:1'],
            'lines.*.entry_side' => ['required', Rule::enum(PostingRuleEntrySide::class)],
            'lines.*.account_resolution_type' => ['required', Rule::enum(AccountResolutionType::class)],
            'lines.*.account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'lines.*.account_mapping_key' => ['nullable', 'string', 'max:64'],
            'lines.*.amount_source' => ['required', Rule::enum(AmountSource::class)],
            'lines.*.narration_template' => ['nullable', 'string', 'max:255'],
            'lines.*.required' => ['boolean'],
            'lines.*.status' => ['nullable', 'string', 'in:active,inactive'],
        ];
    }
}
