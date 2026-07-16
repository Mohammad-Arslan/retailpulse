<?php

declare(strict_types=1);

namespace App\DTOs\Hr;

use App\Http\Requests\Admin\Hr\StoreDepartmentRequest;

final readonly class CreateDepartmentData
{
    public function __construct(
        public int $legalEntityId,
        public string $name,
        public ?int $parentId,
        public ?int $headEmployeeId,
        public ?int $costCentreId,
        public string $status,
    ) {}

    public static function fromRequest(StoreDepartmentRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            legalEntityId: (int) $validated['legal_entity_id'],
            name: (string) $validated['name'],
            parentId: isset($validated['parent_id']) ? (int) $validated['parent_id'] : null,
            headEmployeeId: isset($validated['head_employee_id']) ? (int) $validated['head_employee_id'] : null,
            costCentreId: isset($validated['cost_centre_id']) ? (int) $validated['cost_centre_id'] : null,
            status: (string) ($validated['status'] ?? 'active'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'legal_entity_id' => $this->legalEntityId,
            'name' => $this->name,
            'parent_id' => $this->parentId,
            'head_employee_id' => $this->headEmployeeId,
            'cost_centre_id' => $this->costCentreId,
            'status' => $this->status,
        ];
    }
}
