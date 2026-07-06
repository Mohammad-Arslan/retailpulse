<?php

declare(strict_types=1);

namespace App\DTOs\Loyalty;

use App\Http\Requests\Api\Loyalty\RedeemLoyaltyPointsRequest;

final readonly class RedeemLoyaltyPointsData
{
    public function __construct(
        public int $points,
        public ?int $branchId,
        public ?int $saleId,
    ) {}

    public static function fromRequest(RedeemLoyaltyPointsRequest $request): self
    {
        $branchId = $request->validated('branch_id');
        $saleId = $request->validated('sale_id');

        return new self(
            points: (int) $request->validated('points'),
            branchId: $branchId !== null ? (int) $branchId : null,
            saleId: $saleId !== null ? (int) $saleId : null,
        );
    }
}
