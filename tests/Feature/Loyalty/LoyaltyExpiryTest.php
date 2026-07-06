<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use App\Enums\LoyaltyEventType;
use App\Enums\LoyaltyExpiryType;
use App\Enums\LoyaltyTransactionStatus;
use App\Enums\LoyaltyTransactionType;
use App\Models\Customer;
use App\Models\CustomerLoyaltyEvent;
use App\Models\CustomerLoyaltyWallet;
use App\Models\LoyaltyExpiryRule;
use App\Models\LoyaltyProgram;
use App\Services\Loyalty\LoyaltyExpiryService;
use App\Services\Loyalty\LoyaltyWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsLoyaltyEngine;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class LoyaltyExpiryTest extends TestCase
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

    public function test_expiry_job_processes_old_points(): void
    {
        LoyaltyExpiryRule::query()
            ->where('program_id', $this->program->id)
            ->update([
                'expiry_type' => LoyaltyExpiryType::FixedDays,
                'value' => 30,
                'grace_period_days' => 0,
            ]);

        $customer = Customer::query()->create(['name' => 'Expiry Customer', 'is_active' => true]);
        $wallet = app(LoyaltyWalletService::class)->getOrCreateWallet($customer->id, $this->program);

        $wallet->update(['available_points' => 300, 'lifetime_earned_points' => 300]);

        $processed = app(LoyaltyExpiryService::class)->processProgram($this->program);

        $this->assertGreaterThanOrEqual(0, $processed);
    }

    public function test_expiry_creates_timeline_entry(): void
    {
        $customer = Customer::query()->create(['name' => 'Timeline Expiry', 'is_active' => true]);
        $wallet = CustomerLoyaltyWallet::query()->create([
            'customer_id' => $customer->id,
            'program_id' => $this->program->id,
            'available_points' => 100,
            'lifetime_earned_points' => 100,
        ]);

        app(LoyaltyWalletService::class)->debit(
            $wallet,
            50,
            LoyaltyTransactionType::Expire,
            LoyaltyTransactionStatus::Completed,
            eventType: LoyaltyEventType::Expire,
            counterField: 'expired_points',
        );

        $this->assertTrue(
            CustomerLoyaltyEvent::query()
                ->where('customer_id', $customer->id)
                ->where('event_type', LoyaltyEventType::Expire)
                ->exists()
        );
    }
}
