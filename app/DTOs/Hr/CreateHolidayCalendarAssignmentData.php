<?php

declare(strict_types=1);

namespace App\DTOs\Hr;

use App\Http\Requests\Admin\Hr\StoreHolidayCalendarAssignmentRequest;

final readonly class CreateHolidayCalendarAssignmentData
{
    public function __construct(
        public int $holidayCalendarId,
        public string $assignableType,
        public int $assignableId,
        public string $effectiveFrom,
        public ?string $effectiveTo,
        public int $priority,
        public string $status,
    ) {}

    public static function fromRequest(StoreHolidayCalendarAssignmentRequest $request, int $holidayCalendarId): self
    {
        $validated = $request->validated();

        return new self(
            holidayCalendarId: $holidayCalendarId,
            assignableType: (string) $validated['assignable_type'],
            assignableId: (int) $validated['assignable_id'],
            effectiveFrom: (string) $validated['effective_from'],
            effectiveTo: $validated['effective_to'] ?? null,
            priority: (int) ($validated['priority'] ?? 0),
            status: (string) ($validated['status'] ?? 'active'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'holiday_calendar_id' => $this->holidayCalendarId,
            'assignable_type' => $this->assignableType,
            'assignable_id' => $this->assignableId,
            'effective_from' => $this->effectiveFrom,
            'effective_to' => $this->effectiveTo,
            'priority' => $this->priority,
            'status' => $this->status,
        ];
    }
}
