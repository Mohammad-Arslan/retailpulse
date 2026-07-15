<?php

declare(strict_types=1);

namespace App\DTOs\Hr;

use App\Http\Requests\Admin\Hr\StoreHolidayCalendarRequest;

final readonly class CreateHolidayCalendarData
{
    public function __construct(
        public string $code,
        public string $name,
        public ?int $legalEntityId,
        public ?int $branchId,
        public string $status,
    ) {}

    public static function fromRequest(StoreHolidayCalendarRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            code: (string) $validated['code'],
            name: (string) $validated['name'],
            legalEntityId: isset($validated['legal_entity_id']) ? (int) $validated['legal_entity_id'] : null,
            branchId: isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
            status: (string) ($validated['status'] ?? 'active'),
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
            'legal_entity_id' => $this->legalEntityId,
            'branch_id' => $this->branchId,
            'status' => $this->status,
        ];
    }
}
