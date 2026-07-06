<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use App\Enums\LoyaltyApprovalActionType;
use App\Enums\LoyaltyTransactionStatus;
use App\Enums\LoyaltyTransactionType;
use App\Models\Customer;
use App\Models\CustomerLoyaltyEvent;
use App\Models\LoyaltyApprovalPolicy;
use App\Models\LoyaltyProgram;
use App\Models\User;
use App\Services\Loyalty\LoyaltyApprovalService;
use App\Services\Loyalty\LoyaltyRedemptionService;
use App\Services\Loyalty\LoyaltyWalletService;
use App\Services\PosPinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsLoyaltyEngine;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

final class LoyaltyApprovalTest extends TestCase
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

    public function test_large_adjustment_requires_approval(): void
    {
        $customer = Customer::query()->create(['name' => 'Approval Customer', 'is_active' => true]);
        $wallet = app(LoyaltyWalletService::class)->getOrCreateWallet($customer->id, $this->program);
        $manager = User::factory()->create(['is_active' => true]);
        $manager->givePermissionTo('loyalty.approve');

        $transaction = app(LoyaltyRedemptionService::class)->adjustPoints(
            $wallet,
            1500,
            'Large bonus',
            $manager->id,
            $this->program,
        );

        $this->assertSame(LoyaltyTransactionStatus::PendingApproval, $transaction->status);
        $wallet->refresh();
        $this->assertSame(1500, $wallet->pending_points);
        $this->assertSame(0, $wallet->available_points);
    }

    public function test_pin_approval_completes_pending_credit(): void
    {
        $customer = Customer::query()->create(['name' => 'PIN Customer', 'is_active' => true]);
        $wallet = app(LoyaltyWalletService::class)->getOrCreateWallet($customer->id, $this->program);

        $result = app(LoyaltyWalletService::class)->credit(
            $wallet,
            2000,
            LoyaltyTransactionType::Bonus,
            LoyaltyTransactionStatus::PendingApproval,
            reason: 'Pending bonus',
        );

        $approver = User::factory()->create(['is_active' => true]);
        $approver->givePermissionTo('loyalty.approve');
        app(PosPinService::class)->setPin($approver, '1234');

        $approved = app(LoyaltyApprovalService::class)->approveWithPin(
            $result['transaction'],
            $approver,
            '1234',
        );

        $this->assertSame(LoyaltyTransactionStatus::Completed, $approved->status);
        $wallet->refresh();
        $this->assertSame(2000, $wallet->available_points);

        $this->assertTrue(
            CustomerLoyaltyEvent::query()
                ->where('customer_id', $customer->id)
                ->where('event_type', 'approval')
                ->exists()
        );
    }

    public function test_requires_approval_respects_policy_threshold(): void
    {
        LoyaltyApprovalPolicy::query()
            ->where('program_id', $this->program->id)
            ->where('action_type', LoyaltyApprovalActionType::ManualAdjustment)
            ->update(['threshold_value' => 500]);

        $service = app(LoyaltyApprovalService::class);

        $this->assertFalse($service->requiresApproval($this->program, LoyaltyApprovalActionType::ManualAdjustment, 400));
        $this->assertTrue($service->requiresApproval($this->program, LoyaltyApprovalActionType::ManualAdjustment, 600));
    }
}
