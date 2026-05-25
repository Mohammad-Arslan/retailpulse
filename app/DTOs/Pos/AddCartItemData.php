<?php

declare(strict_types=1);

namespace App\DTOs\Pos;

use Illuminate\Foundation\Http\FormRequest;

final readonly class AddCartItemData
{
    public function __construct(
        public int $productVariantId,
        public int $quantity,
        public ?string $notes,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            productVariantId: (int) $request->validated('product_variant_id'),
            quantity: (int) $request->validated('quantity', 1),
            notes: $request->validated('notes'),
        );
    }
}
