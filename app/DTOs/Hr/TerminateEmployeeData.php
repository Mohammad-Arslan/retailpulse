<?php

declare(strict_types=1);

namespace App\DTOs\Hr;

use App\Http\Requests\Admin\Hr\TerminateEmployeeRequest;

final readonly class TerminateEmployeeData
{
    public function __construct(
        public string $terminationDate,
    ) {}

    public static function fromRequest(TerminateEmployeeRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            terminationDate: (string) $validated['termination_date'],
        );
    }
}
