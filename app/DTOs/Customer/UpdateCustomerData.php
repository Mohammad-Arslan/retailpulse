<?php

declare(strict_types=1);

namespace App\DTOs\Customer;

use App\Http\Requests\Admin\UpdateCustomerRequest;

final readonly class UpdateCustomerData
{
    public function __construct(
        public string $name,
        public ?string $phone,
        public ?string $email,
        public ?string $ntn,
        public ?string $cnic,
        public bool $isActive,
        public ?int $loyaltyTierId,
        public ?int $customerGroupId,
        public ?float $creditLimit,
        public ?string $preferredPaymentMethod,
        public ?string $notes,
    ) {}

    public static function fromRequest(UpdateCustomerRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            phone: $request->validated('phone'),
            email: $request->validated('email'),
            ntn: $request->validated('ntn'),
            cnic: $request->validated('cnic'),
            isActive: $request->boolean('is_active', true),
            loyaltyTierId: $request->validated('loyalty_tier_id') !== null
                ? (int) $request->validated('loyalty_tier_id')
                : null,
            customerGroupId: $request->validated('customer_group_id') !== null
                ? (int) $request->validated('customer_group_id')
                : null,
            creditLimit: $request->validated('credit_limit') !== null
                ? (float) $request->validated('credit_limit')
                : null,
            preferredPaymentMethod: $request->validated('preferred_payment_method'),
            notes: $request->validated('notes'),
        );
    }
}
