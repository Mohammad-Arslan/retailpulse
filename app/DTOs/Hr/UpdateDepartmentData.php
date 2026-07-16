<?php

declare(strict_types=1);

namespace App\DTOs\Hr;

use App\Http\Requests\Admin\Hr\UpdateDepartmentRequest;

final readonly class UpdateDepartmentData
{
    public function __construct(
        public ?int $legalEntityId,
        public ?string $name,
        public ?int $parentId,
        public bool $parentIdProvided,
        public ?int $headEmployeeId,
        public bool $headEmployeeIdProvided,
        public ?int $costCentreId,
        public bool $costCentreIdProvided,
        public ?string $status,
    ) {}

    public static function fromRequest(UpdateDepartmentRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            legalEntityId: array_key_exists('legal_entity_id', $validated) ? (int) $validated['legal_entity_id'] : null,
            name: array_key_exists('name', $validated) ? (string) $validated['name'] : null,
            parentId: array_key_exists('parent_id', $validated) && $validated['parent_id'] !== null
                ? (int) $validated['parent_id']
                : null,
            parentIdProvided: array_key_exists('parent_id', $validated),
            headEmployeeId: array_key_exists('head_employee_id', $validated) && $validated['head_employee_id'] !== null
                ? (int) $validated['head_employee_id']
                : null,
            headEmployeeIdProvided: array_key_exists('head_employee_id', $validated),
            costCentreId: array_key_exists('cost_centre_id', $validated) && $validated['cost_centre_id'] !== null
                ? (int) $validated['cost_centre_id']
                : null,
            costCentreIdProvided: array_key_exists('cost_centre_id', $validated),
            status: array_key_exists('status', $validated) ? (string) $validated['status'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->legalEntityId !== null) {
            $data['legal_entity_id'] = $this->legalEntityId;
        }
        if ($this->name !== null) {
            $data['name'] = $this->name;
        }
        if ($this->parentIdProvided) {
            $data['parent_id'] = $this->parentId;
        }
        if ($this->headEmployeeIdProvided) {
            $data['head_employee_id'] = $this->headEmployeeId;
        }
        if ($this->costCentreIdProvided) {
            $data['cost_centre_id'] = $this->costCentreId;
        }
        if ($this->status !== null) {
            $data['status'] = $this->status;
        }

        return $data;
    }
}
