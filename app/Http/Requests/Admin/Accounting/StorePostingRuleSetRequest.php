<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Accounting;

use App\Enums\AccountResolutionType;
use App\Enums\AmountSource;
use App\Enums\PostingRuleEntrySide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StorePostingRuleSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nullableInts = ['branch_id', 'legal_entity_id', 'priority'];

        foreach ($nullableInts as $field) {
            if ($this->input($field) === '' || $this->input($field) === null) {
                $this->merge([$field => null]);
            }
        }

        if ($this->input('effective_to') === '') {
            $this->merge(['effective_to' => null]);
        }

        $lines = $this->input('lines');

        if (is_array($lines)) {
            foreach ($lines as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }

                if (($line['account_id'] ?? '') === '') {
                    $lines[$index]['account_id'] = null;
                }

                if (($line['account_mapping_key'] ?? '') === '') {
                    $lines[$index]['account_mapping_key'] = null;
                }
            }

            $this->merge(['lines' => $lines]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'unique:posting_rule_sets,code'],
            'name' => ['required', 'string', 'max:255'],
            'duplicate_from_id' => ['required', 'integer', 'exists:posting_rule_sets,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'legal_entity_id' => ['nullable', 'integer', 'exists:organization_entities,id'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'effective_from' => ['required', 'date'],
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
