<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Http\Requests\Admin\Accounting\StoreCostCentreRequest;

final readonly class CreateCostCentreData
{
    public function __construct(
        public string $code,
        public string $name,
        public ?int $parentId,
        public ?int $branchId,
        public ?int $legalEntityId,
        public string $status,
        public ?int $headcount = null,
        public ?float $floorArea = null,
    ) {}

    public static function fromRequest(StoreCostCentreRequest $request): self
    {
        $parentId = $request->validated('parent_id');

        return new self(
            code: $request->validated('code'),
            name: $request->validated('name'),
            parentId: $parentId !== null ? (int) $parentId : null,
            branchId: $request->validated('branch_id') !== null ? (int) $request->validated('branch_id') : null,
            legalEntityId: $request->validated('legal_entity_id') !== null ? (int) $request->validated('legal_entity_id') : null,
            status: $request->validated('status') ?? 'active',
            headcount: $request->validated('headcount') !== null ? (int) $request->validated('headcount') : null,
            floorArea: $request->validated('floor_area') !== null ? (float) $request->validated('floor_area') : null,
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
            'parent_id' => $this->parentId,
            'branch_id' => $this->branchId,
            'legal_entity_id' => $this->legalEntityId,
            'status' => $this->status,
            'headcount' => $this->headcount,
            'floor_area' => $this->floorArea,
        ];
    }
}
