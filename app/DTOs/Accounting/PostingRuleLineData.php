<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Enums\AccountResolutionType;
use App\Enums\AmountSource;
use App\Enums\PostingRuleEntrySide;

final readonly class PostingRuleLineData
{
    public function __construct(
        public int $sequence,
        public PostingRuleEntrySide $entrySide,
        public AccountResolutionType $accountResolutionType,
        public ?int $accountId,
        public ?string $accountMappingKey,
        public AmountSource $amountSource,
        public ?string $narrationTemplate,
        public bool $required,
        public string $status,
    ) {}

    /**
     * @param  array<string, mixed>  $line
     */
    public static function fromArray(array $line): self
    {
        return new self(
            sequence: (int) $line['sequence'],
            entrySide: PostingRuleEntrySide::from($line['entry_side']),
            accountResolutionType: AccountResolutionType::from($line['account_resolution_type']),
            accountId: isset($line['account_id']) ? (int) $line['account_id'] : null,
            accountMappingKey: $line['account_mapping_key'] ?? null,
            amountSource: AmountSource::from($line['amount_source']),
            narrationTemplate: $line['narration_template'] ?? null,
            required: (bool) ($line['required'] ?? true),
            status: $line['status'] ?? 'active',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sequence' => $this->sequence,
            'entry_side' => $this->entrySide->value,
            'account_resolution_type' => $this->accountResolutionType->value,
            'account_id' => $this->accountId,
            'account_mapping_key' => $this->accountMappingKey,
            'amount_source' => $this->amountSource->value,
            'narration_template' => $this->narrationTemplate,
            'required' => $this->required,
            'status' => $this->status,
        ];
    }
}
