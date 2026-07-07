<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Enums\ChequeStatus;
use App\Http\Requests\Admin\Accounting\UpdateChequeStatusRequest;

final readonly class UpdateChequeStatusData
{
    public function __construct(public ChequeStatus $status) {}

    public static function fromRequest(UpdateChequeStatusRequest $request): self
    {
        return new self(ChequeStatus::from($request->validated('status')));
    }
}
