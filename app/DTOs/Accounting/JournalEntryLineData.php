<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

final readonly class JournalEntryLineData
{
    public function __construct(
        public int $accountId,
        public float $debit,
        public float $credit,
        public ?string $currencyCode,
        public ?float $exchangeRate,
        public ?int $branchId,
        public ?int $warehouseId,
        public ?string $partyType,
        public ?int $partyId,
        public ?int $productVariantId,
        public ?string $description,
    ) {}

    /**
     * @param  array<string, mixed>  $line
     */
    public static function fromArray(array $line): self
    {
        return new self(
            accountId: (int) $line['account_id'],
            debit: (float) ($line['debit'] ?? 0),
            credit: (float) ($line['credit'] ?? 0),
            currencyCode: $line['currency_code'] ?? null,
            exchangeRate: isset($line['exchange_rate']) ? (float) $line['exchange_rate'] : null,
            branchId: isset($line['branch_id']) ? (int) $line['branch_id'] : null,
            warehouseId: isset($line['warehouse_id']) ? (int) $line['warehouse_id'] : null,
            partyType: $line['party_type'] ?? null,
            partyId: isset($line['party_id']) ? (int) $line['party_id'] : null,
            productVariantId: isset($line['product_variant_id']) ? (int) $line['product_variant_id'] : null,
            description: $line['description'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'debit' => $this->debit,
            'credit' => $this->credit,
            'currency_code' => $this->currencyCode,
            'exchange_rate' => $this->exchangeRate,
            'branch_id' => $this->branchId,
            'warehouse_id' => $this->warehouseId,
            'party_type' => $this->partyType,
            'party_id' => $this->partyId,
            'product_variant_id' => $this->productVariantId,
            'description' => $this->description,
        ];
    }
}
