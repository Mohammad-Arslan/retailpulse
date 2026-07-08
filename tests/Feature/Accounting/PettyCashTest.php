<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\PettyCashApprovalStatus;
use App\Enums\PettyCashVoucherType;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\PettyCashRegister;
use App\Models\User;
use App\Services\Accounting\PettyCashApprovalService;
use App\Services\Accounting\PettyCashService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccounting;
use Tests\TestCase;

final class PettyCashTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAccounting;

    private PettyCashRegister $register;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounting();

        $branch = Branch::query()->create([
            'name' => 'Petty Branch',
            'code' => 'PTY',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $coa = ChartOfAccount::query()->where('code', '1110')->firstOrFail();

        $this->register = PettyCashRegister::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Front Desk',
            'coa_account_id' => $coa->id,
            'opening_balance' => 1000,
            'current_balance' => 1000,
            'approval_threshold_amount' => 100,
            'status' => 'active',
        ]);

        $this->user = User::factory()->create(['is_active' => true]);
    }

    public function test_voucher_above_threshold_requires_approval(): void
    {
        $voucher = app(PettyCashService::class)->createVoucher($this->register, [
            'voucher_type' => PettyCashVoucherType::Disbursement->value,
            'amount' => 250,
            'date' => now()->toDateString(),
            'description' => 'Office supplies',
        ], $this->user->id);

        $this->assertTrue($voucher->approval_required);
        $this->assertSame(PettyCashApprovalStatus::Pending, $voucher->approval_status);
        $this->assertNull($voucher->journal_entry_id);
        $this->assertSame(1000.0, (float) $this->register->fresh()->current_balance);
    }

    public function test_approve_voucher_posts_journal_and_updates_balance(): void
    {
        $voucher = app(PettyCashService::class)->createVoucher($this->register, [
            'voucher_type' => PettyCashVoucherType::Disbursement->value,
            'amount' => 150,
            'date' => now()->toDateString(),
            'description' => 'Courier',
        ], $this->user->id);

        $approved = app(PettyCashApprovalService::class)->approve($voucher, $this->user);

        $this->assertSame(PettyCashApprovalStatus::Approved, $approved->approval_status);
        $this->assertNotNull($approved->journal_entry_id);
        $this->assertSame(850.0, (float) $this->register->fresh()->current_balance);
        $this->assertSame(1, JournalEntry::query()->count());
    }

    public function test_reject_voucher_keeps_it_unposted_until_resubmitted(): void
    {
        $voucher = app(PettyCashService::class)->createVoucher($this->register, [
            'voucher_type' => PettyCashVoucherType::Disbursement->value,
            'amount' => 200,
            'date' => now()->toDateString(),
        ], $this->user->id);

        $rejected = app(PettyCashApprovalService::class)->reject($voucher, 'Missing receipt');

        $this->assertSame(PettyCashApprovalStatus::Rejected, $rejected->approval_status);

        $this->expectException(DomainException::class);
        app(PettyCashApprovalService::class)->approve($rejected->fresh(), $this->user);
    }
}
