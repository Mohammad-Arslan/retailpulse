<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Http\Requests\Admin\Accounting\StoreAccountMappingRequest;

final readonly class CreateAccountMappingData
{
    public function __construct(
        public string $mappingKey,
        public int $accountId,
        public ?int $branchId,
        public ?int $warehouseId,
        public ?int $productCategoryId,
        public ?string $paymentMethod,
        public ?string $currencyCode,
        public ?int $legalEntityId,
        public ?string $effectiveFrom,
        public ?string $effectiveTo,
        public string $status,
        public int $priority,
    ) {}

    public static function fromRequest(StoreAccountMappingRequest $request): self
    {
        return new self(
            mappingKey: $request->validated('mapping_key'),
            accountId: (int) $request->validated('account_id'),
            branchId: $request->validated('branch_id') !== null ? (int) $request->validated('branch_id') : null,
            warehouseId: $request->validated('warehouse_id') !== null ? (int) $request->validated('warehouse_id') : null,
            productCategoryId: $request->validated('product_category_id') !== null ? (int) $request->validated('product_category_id') : null,
            paymentMethod: $request->validated('payment_method'),
            currencyCode: $request->validated('currency_code'),
            legalEntityId: $request->validated('legal_entity_id') !== null ? (int) $request->validated('legal_entity_id') : null,
            effectiveFrom: $request->validated('effective_from'),
            effectiveTo: $request->validated('effective_to'),
            status: $request->validated('status') ?? 'active',
            priority: (int) ($request->validated('priority') ?? 100),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mapping_key' => $this->mappingKey,
            'account_id' => $this->accountId,
            'branch_id' => $this->branchId,
            'warehouse_id' => $this->warehouseId,
            'product_category_id' => $this->productCategoryId,
            'payment_method' => $this->paymentMethod,
            'currency_code' => $this->currencyCode,
            'legal_entity_id' => $this->legalEntityId,
            'effective_from' => $this->effectiveFrom,
            'effective_to' => $this->effectiveTo,
            'status' => $this->status,
            'priority' => $this->priority,
        ];
    }
}
