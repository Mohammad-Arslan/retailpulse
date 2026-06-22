<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\DTOs\Customer\CreateCustomerData;
use App\DTOs\Customer\UpdateCustomerData;
use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomerService
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
    ) {}

    public function create(CreateCustomerData $data): Customer
    {
        $this->assertUniqueContact($data->phone, $data->email);

        return DB::transaction(fn () => $this->customers->create([
            'name' => $data->name,
            'phone' => $data->phone,
            'email' => $data->email,
            'ntn' => $data->ntn,
            'cnic' => $data->cnic,
            'is_active' => $data->isActive,
            'loyalty_tier_id' => $data->loyaltyTierId,
            'customer_group_id' => $data->customerGroupId,
            'credit_limit' => $data->creditLimit,
            'preferred_payment_method' => $data->preferredPaymentMethod,
            'notes' => $data->notes,
        ]));
    }

    public function update(Customer $customer, UpdateCustomerData $data): Customer
    {
        $this->assertUniqueContact($data->phone, $data->email, $customer->id);

        return DB::transaction(fn () => $this->customers->update($customer, [
            'name' => $data->name,
            'phone' => $data->phone,
            'email' => $data->email,
            'ntn' => $data->ntn,
            'cnic' => $data->cnic,
            'is_active' => $data->isActive,
            'loyalty_tier_id' => $data->loyaltyTierId,
            'customer_group_id' => $data->customerGroupId,
            'credit_limit' => $data->creditLimit,
            'preferred_payment_method' => $data->preferredPaymentMethod,
            'notes' => $data->notes,
        ]));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertByPhoneOrEmail(array $attributes): Customer
    {
        $phone = isset($attributes['phone']) ? (string) $attributes['phone'] : null;
        $email = isset($attributes['email']) ? (string) $attributes['email'] : null;

        if (($phone === null || $phone === '') && ($email === null || $email === '')) {
            throw ValidationException::withMessages([
                'phone' => __('Phone or email is required for customer import.'),
            ]);
        }

        $existing = null;

        if ($phone !== null && $phone !== '') {
            $existing = $this->customers->findByPhone($phone);
        }

        if ($existing === null && $email !== null && $email !== '') {
            $existing = $this->customers->findByEmail($email);
        }

        if ($existing !== null) {
            $this->assertUniqueContact(
                phone: $phone,
                email: $email,
                ignoreId: $existing->id,
            );

            return $this->customers->update($existing, array_filter([
                'name' => $attributes['name'] ?? $existing->name,
                'phone' => $phone ?: $existing->phone,
                'email' => $email ?: $existing->email,
                'ntn' => $attributes['ntn'] ?? $existing->ntn,
                'cnic' => $attributes['cnic'] ?? $existing->cnic,
                'is_active' => $attributes['is_active'] ?? $existing->is_active,
                'loyalty_tier_id' => $attributes['loyalty_tier_id'] ?? $existing->loyalty_tier_id,
                'customer_group_id' => $attributes['customer_group_id'] ?? $existing->customer_group_id,
                'credit_limit' => $attributes['credit_limit'] ?? $existing->credit_limit,
                'preferred_payment_method' => $attributes['preferred_payment_method'] ?? $existing->preferred_payment_method,
                'notes' => $attributes['notes'] ?? $existing->notes,
            ], fn ($value) => $value !== null));
        }

        $this->assertUniqueContact($phone, $email);

        return $this->customers->create([
            'name' => (string) ($attributes['name'] ?? 'Customer'),
            'phone' => $phone,
            'email' => $email,
            'ntn' => $attributes['ntn'] ?? null,
            'cnic' => $attributes['cnic'] ?? null,
            'is_active' => $attributes['is_active'] ?? true,
            'loyalty_tier_id' => $attributes['loyalty_tier_id'] ?? null,
            'customer_group_id' => $attributes['customer_group_id'] ?? null,
            'credit_limit' => $attributes['credit_limit'] ?? null,
            'preferred_payment_method' => $attributes['preferred_payment_method'] ?? null,
            'notes' => $attributes['notes'] ?? null,
        ]);
    }

    private function assertUniqueContact(?string $phone, ?string $email, ?int $ignoreId = null): void
    {
        if ($phone !== null && $phone !== '') {
            $existing = $this->customers->findByPhone($phone);

            if ($existing !== null && $existing->id !== $ignoreId) {
                throw ValidationException::withMessages([
                    'phone' => __('A customer with this phone number already exists.'),
                ]);
            }
        }

        if ($email !== null && $email !== '') {
            $existing = $this->customers->findByEmail($email);

            if ($existing !== null && $existing->id !== $ignoreId) {
                throw ValidationException::withMessages([
                    'email' => __('A customer with this email address already exists.'),
                ]);
            }
        }
    }
}
