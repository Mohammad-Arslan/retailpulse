<?php

declare(strict_types=1);

namespace Tests\Integration\Loyalty;

use App\Enums\LoyaltyEventType;
use App\Enums\LoyaltyProgramScopeType;
use App\Enums\LoyaltyScopeMode;
use App\Enums\LoyaltyTransactionStatus;
use App\Enums\LoyaltyTransactionType;
use App\Enums\SaleStatus;
use App\Events\SaleCompleted;
use App\Listeners\ProcessLoyaltyOnSaleCompleted;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerLoyaltyEvent;
use App\Models\CustomerLoyaltyTransaction;
use App\Models\CustomerLoyaltyWallet;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyProgramTier;
use App\Models\Sale;
use App\Models\User;
use App\Services\Checkout\CheckoutService;
use App\Services\Loyalty\CheckoutLoyaltyService;
use App\Services\Loyalty\LoyaltyTierService;
use App\Services\Loyalty\LoyaltyWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsLoyaltyEngine;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class LoyaltyCheckoutIntegrationTest extends TestCase
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

    public function test_full_checkout_flow_awards_points_timeline_and_tier(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Integration Branch',
            'code' => 'INT',
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Integration Customer',
            'phone' => '03009998877',
            'is_active' => true,
        ]);

        $cashier = User::factory()->create(['is_active' => true]);

        $sale = Sale::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'cashier_id' => $cashier->id,
            'status' => SaleStatus::Completed,
            'subtotal' => 10000,
            'grand_total' => 10000,
            'balance_due' => 0,
            'completed_at' => now(),
        ]);

        $listener = app(ProcessLoyaltyOnSaleCompleted::class);
        $listener->handle(new SaleCompleted($sale));

        $wallet = CustomerLoyaltyWallet::query()
            ->where('customer_id', $customer->id)
            ->where('program_id', $this->program->id)
            ->first();

        $this->assertNotNull($wallet);
        $this->assertGreaterThan(0, $wallet->available_points);

        $this->assertTrue(
            CustomerLoyaltyTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->where('transaction_type', LoyaltyTransactionType::Earn)
                ->exists()
        );

        $this->assertTrue(
            CustomerLoyaltyEvent::query()
                ->where('customer_id', $customer->id)
                ->where('event_type', LoyaltyEventType::Purchase)
                ->exists()
        );
    }

    public function test_branch_restricted_program_blocks_other_branches(): void
    {
        $allowed = Branch::query()->create([
            'name' => 'Allowed',
            'code' => 'ALW',
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
            'is_active' => true,
        ]);

        $blocked = Branch::query()->create([
            'name' => 'Blocked',
            'code' => 'BLK',
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
            'is_active' => true,
        ]);

        $this->program->update([
            'scope_type' => LoyaltyProgramScopeType::SelectedBranches,
            'earn_scope' => LoyaltyScopeMode::Branch,
            'allow_cross_branch_earn' => false,
        ]);
        $this->program->branches()->sync([$allowed->id]);

        $customer = Customer::query()->create(['name' => 'Branch Customer', 'is_active' => true]);
        $cashier = User::factory()->create(['is_active' => true]);

        $sale = Sale::query()->create([
            'branch_id' => $blocked->id,
            'customer_id' => $customer->id,
            'cashier_id' => $cashier->id,
            'status' => SaleStatus::Completed,
            'grand_total' => 5000,
            'balance_due' => 0,
            'completed_at' => now(),
        ]);

        app(ProcessLoyaltyOnSaleCompleted::class)->handle(new SaleCompleted($sale));

        $this->assertNull(
            CustomerLoyaltyWallet::query()
                ->where('customer_id', $customer->id)
                ->where('program_id', $this->program->id)
                ->first()
        );
    }

    public function test_tier_upgrade_is_tracked_in_timeline(): void
    {
        $customer = Customer::query()->create(['name' => 'Tier Customer', 'is_active' => true]);
        $wallet = CustomerLoyaltyWallet::query()->create([
            'customer_id' => $customer->id,
            'program_id' => $this->program->id,
            'available_points' => 6000,
            'lifetime_earned_points' => 6000,
        ]);

        $gold = LoyaltyProgramTier::query()
            ->where('program_id', $this->program->id)
            ->where('name', 'Gold')
            ->first();

        $this->assertNotNull($gold);

        app(LoyaltyTierService::class)->recalculateForWallet($wallet);

        $wallet->refresh();
        $this->assertSame($gold->id, $wallet->tier_id);

        $this->assertTrue(
            CustomerLoyaltyEvent::query()
                ->where('customer_id', $customer->id)
                ->where('event_type', LoyaltyEventType::TierChange)
                ->exists()
        );
    }

    public function test_checkout_loyalty_redemption_is_reversed_when_pending_sale_is_voided(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Void Redemption Branch',
            'code' => 'VRB',
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Void Redemption Customer',
            'phone' => '03001234567',
            'is_active' => true,
        ]);

        $cashier = User::factory()->create(['is_active' => true]);

        $wallet = app(LoyaltyWalletService::class)->getOrCreateWallet($customer->id, $this->program);
        app(LoyaltyWalletService::class)->credit(
            $wallet,
            1000,
            LoyaltyTransactionType::Earn,
            LoyaltyTransactionStatus::Completed,
        );

        $sale = Sale::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'cashier_id' => $cashier->id,
            'status' => SaleStatus::PendingPayment,
            'subtotal' => 1000,
            'total_discount' => 0,
            'grand_total' => 1000,
            'balance_due' => 1000,
        ]);

        app(CheckoutLoyaltyService::class)->applyRedemptionToSale($sale, 500, $cashier->id);

        $wallet->refresh();
        $sale->refresh();

        $this->assertSame(500, $wallet->available_points);
        $this->assertSame(500, $wallet->redeemed_points);
        $this->assertSame('500.00', (string) $sale->balance_due);

        app(CheckoutService::class)->voidSale($sale);

        $wallet->refresh();

        $this->assertSame(1000, $wallet->available_points);
        $this->assertSame(0, $wallet->redeemed_points);
        $this->assertTrue(
            CustomerLoyaltyTransaction::query()
                ->where('transaction_type', LoyaltyTransactionType::Reversal)
                ->where('customer_id', $customer->id)
                ->exists()
        );
    }
}
