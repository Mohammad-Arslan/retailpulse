<?php

declare(strict_types=1);

namespace App\DTOs\Hr;

use App\Http\Requests\Admin\Hr\StoreHolidayDateRequest;

final readonly class CreateHolidayDateData
{
    public function __construct(
        public string $holidayDate,
        public string $name,
        public string $holidayType,
        public bool $isPaid,
        public bool $isRecurring = false,
        public ?int $recurrenceMonth = null,
        public ?int $recurrenceDay = null,
    ) {}

    public static function fromRequest(StoreHolidayDateRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            holidayDate: (string) $validated['holiday_date'],
            name: (string) $validated['name'],
            holidayType: (string) ($validated['holiday_type'] ?? 'public'),
            isPaid: (bool) ($validated['is_paid'] ?? true),
            isRecurring: (bool) ($validated['is_recurring'] ?? false),
            recurrenceMonth: isset($validated['recurrence_month']) ? (int) $validated['recurrence_month'] : null,
            recurrenceDay: isset($validated['recurrence_day']) ? (int) $validated['recurrence_day'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'holiday_date' => $this->holidayDate,
            'name' => $this->name,
            'holiday_type' => $this->holidayType,
            'is_paid' => $this->isPaid,
            'is_recurring' => $this->isRecurring,
            'recurrence_month' => $this->recurrenceMonth,
            'recurrence_day' => $this->recurrenceDay,
        ];
    }
}
