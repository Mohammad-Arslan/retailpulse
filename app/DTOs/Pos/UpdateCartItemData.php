<?php

declare(strict_types=1);

namespace App\DTOs\Pos;

use Illuminate\Foundation\Http\FormRequest;

final readonly class UpdateCartItemData
{
    public function __construct(
        public ?int $quantity,
        public ?string $discountType,
        public ?float $discountValue,
        public ?string $notes,
        public bool $approvedByUserId = false,
        public ?int $approverId = null,
        public bool $discountProvided = false,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        $discountProvided = $request->has('discount_type') || $request->has('discount_value');

        return new self(
            quantity: $request->validated('quantity') !== null
                ? (int) $request->validated('quantity')
                : null,
            discountType: $discountProvided ? $request->validated('discount_type') : null,
            discountValue: $discountProvided && $request->validated('discount_value') !== null
                ? (float) $request->validated('discount_value')
                : null,
            notes: $request->validated('notes'),
            approvedByUserId: (bool) $request->validated('approved', false),
            approverId: $request->validated('approver_id') !== null
                ? (int) $request->validated('approver_id')
                : null,
            discountProvided: $discountProvided,
        );
    }
}
