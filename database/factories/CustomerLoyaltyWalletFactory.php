<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerLoyaltyWallet;
use App\Models\LoyaltyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerLoyaltyWallet>
 */
final class CustomerLoyaltyWalletFactory extends Factory
{
    protected $model = CustomerLoyaltyWallet::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'program_id' => LoyaltyProgram::factory(),
            'branch_id' => null,
            'available_points' => 0,
            'pending_points' => 0,
            'redeemed_points' => 0,
            'expired_points' => 0,
            'lifetime_earned_points' => 0,
        ];
    }
}
