<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use App\Enums\LoyaltyTransactionType;
use App\Enums\SaleStatus;
use App\Events\SaleCompleted;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerLoyaltyWallet;
use App\Models\LoyaltyProgram;
use App\Models\Sale;
use App\Models\User;
use App\Services\Loyalty\LoyaltyEarnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\SeedsLoyaltyEngine;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class LoyaltyEarnTest extends TestCase
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

    public function test_earn_on_sale_creates_wallet_and_transaction(): void
    {
        $sale = $this->createCompletedSale(1000.00);

        $transaction = app(LoyaltyEarnService::class)->earnOnSaleComplete($sale);

        $this->assertNotNull($transaction);
        $this->assertSame(LoyaltyTransactionType::Earn, $transaction->transaction_type);

        $wallet = CustomerLoyaltyWallet::query()
            ->where('customer_id', $sale->customer_id)
            ->where('program_id', $this->program->id)
            ->first();

        $this->assertNotNull($wallet);
        $this->assertGreaterThan(0, $wallet->available_points);
    }

    public function test_sale_completed_event_triggers_earning(): void
    {
        Event::fake([SaleCompleted::class]);

        $sale = $this->createCompletedSale(500.00);
        SaleCompleted::dispatch($sale);

        Event::assertDispatched(SaleCompleted::class);
    }

    private function createCompletedSale(float $total): Sale
    {
        $branch = Branch::query()->create([
            'name' => 'Earn Branch',
            'code' => 'ERN',
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Earn Customer',
            'phone' => '03007654321',
            'is_active' => true,
        ]);

        $cashier = User::factory()->create(['is_active' => true]);

        return Sale::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'cashier_id' => $cashier->id,
            'status' => SaleStatus::Completed,
            'subtotal' => $total,
            'grand_total' => $total,
            'balance_due' => 0,
            'completed_at' => now(),
        ]);
    }
}
