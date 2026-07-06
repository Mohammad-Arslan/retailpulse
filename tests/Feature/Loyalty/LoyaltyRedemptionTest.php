<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use App\Enums\LoyaltyRuleType;
use App\Enums\LoyaltyTransactionStatus;
use App\Enums\LoyaltyTransactionType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyRule;
use App\Services\Loyalty\LoyaltyRedemptionService;
use App\Services\Loyalty\LoyaltyWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsLoyaltyEngine;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class LoyaltyRedemptionTest extends TestCase
{
    use RefreshDatabase;
    use SeedsLoyaltyEngine;
    use SeedsRbac;

    private LoyaltyProgram $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->program = $this->seedLoyaltyEngine();
    }

    public function test_redemption_debits_wallet(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Redeem Branch',
            'code' => 'RDM',
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Redeem Customer',
            'phone' => '03001112222',
            'is_active' => true,
        ]);

        $wallet = app(LoyaltyWalletService::class)->getOrCreateWallet($customer->id, $this->program);
        app(LoyaltyWalletService::class)->credit(
            $wallet,
            1000,
            LoyaltyTransactionType::Earn,
            LoyaltyTransactionStatus::Completed,
        );

        $wallet->refresh();
        $this->assertSame(1000, $wallet->available_points);

        $transaction = app(LoyaltyRedemptionService::class)->redeem(
            $wallet->fresh(),
            $this->program,
            500,
            $branch->id,
        );

        $this->assertSame(LoyaltyTransactionType::Redeem, $transaction->transaction_type);
        $wallet->refresh();
        $this->assertSame(500, $wallet->available_points);
        $this->assertSame(500, $wallet->redeemed_points);
    }

    public function test_redemption_respects_minimum_points(): void
    {
        LoyaltyRule::query()->where('program_id', $this->program->id)
            ->where('rule_type', LoyaltyRuleType::Redemption)
            ->update([
                'conditions_json' => [
                    'min_redeem_points' => 200,
                    'points_per_unit' => 100,
                    'currency_per_unit' => 100,
                ],
            ]);

        $customer = Customer::query()->create(['name' => 'Min Redeem', 'is_active' => true]);
        $wallet = app(LoyaltyWalletService::class)->getOrCreateWallet($customer->id, $this->program);
        app(LoyaltyWalletService::class)->credit(
            $wallet,
            500,
            LoyaltyTransactionType::Earn,
            LoyaltyTransactionStatus::Completed,
        );

        $this->expectException(ValidationException::class);

        app(LoyaltyRedemptionService::class)->redeem(
            $wallet->fresh(),
            $this->program,
            100,
            1,
        );
    }
}
