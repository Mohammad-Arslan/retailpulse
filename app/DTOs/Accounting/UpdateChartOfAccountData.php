<?php

declare(strict_types=1);

namespace App\DTOs\Accounting;

use App\Http\Requests\Admin\Accounting\UpdateChartOfAccountRequest;

final readonly class UpdateChartOfAccountData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes) {}

    public static function fromRequest(UpdateChartOfAccountRequest $request): self
    {
        return new self($request->validated());
    }
}
