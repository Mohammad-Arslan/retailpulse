<?php

declare(strict_types=1);

namespace App\DTOs\Hr;

use App\Http\Requests\Admin\Hr\StoreDesignationRequest;

final readonly class CreateDesignationData
{
    public function __construct(
        public ?int $legalEntityId,
        public string $name,
        public ?int $defaultGradeId,
        public string $status,
    ) {}

    public static function fromRequest(StoreDesignationRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            legalEntityId: isset($validated['legal_entity_id']) ? (int) $validated['legal_entity_id'] : null,
            name: (string) $validated['name'],
            defaultGradeId: isset($validated['default_grade_id']) ? (int) $validated['default_grade_id'] : null,
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
            'default_grade_id' => $this->defaultGradeId,
            'status' => $this->status,
        ];
    }
}
