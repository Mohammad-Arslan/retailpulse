<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Http\Requests\Admin\Accounting\FiscalYearReopenRequest;

final readonly class FiscalYearReopenData
{
    public function __construct(public string $reason) {}

    public static function fromRequest(FiscalYearReopenRequest $request): self
    {
        return new self($request->validated('reason'));
    }
}
