<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Enums\ChartOfAccountType;
use App\Http\Requests\Admin\Accounting\StoreChartOfAccountRequest;

final readonly class CreateChartOfAccountData
{
    public function __construct(
        public string $code,
        public string $name,
        public ChartOfAccountType $type,
        public ?int $parentId,
        public bool $isGroup,
        public bool $isPostable,
        public ?int $branchId,
        public ?int $legalEntityId,
        public ?string $currencyCode,
        public string $status,
        public ?string $effectiveFrom,
        public ?string $effectiveTo,
    ) {}

    public static function fromRequest(StoreChartOfAccountRequest $request): self
    {
        $parentId = $request->validated('parent_id');

        return new self(
            code: $request->validated('code'),
            name: $request->validated('name'),
            type: ChartOfAccountType::from($request->validated('type')),
            parentId: $parentId !== null ? (int) $parentId : null,
            isGroup: $request->boolean('is_group'),
            isPostable: $request->boolean('is_postable', true),
            branchId: $request->validated('branch_id') !== null ? (int) $request->validated('branch_id') : null,
            legalEntityId: $request->validated('legal_entity_id') !== null ? (int) $request->validated('legal_entity_id') : null,
            currencyCode: $request->validated('currency_code'),
            status: $request->validated('status') ?? 'active',
            effectiveFrom: $request->validated('effective_from'),
            effectiveTo: $request->validated('effective_to'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'parent_id' => $this->parentId,
            'is_group' => $this->isGroup,
            'is_postable' => $this->isPostable,
            'branch_id' => $this->branchId,
            'legal_entity_id' => $this->legalEntityId,
            'currency_code' => $this->currencyCode,
            'status' => $this->status,
            'effective_from' => $this->effectiveFrom,
            'effective_to' => $this->effectiveTo,
        ];
    }
}
