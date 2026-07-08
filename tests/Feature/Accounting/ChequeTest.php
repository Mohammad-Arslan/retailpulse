<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\DTOs\Accounting\CreateChequeData;
use App\Enums\ChequeStatus;
use App\Enums\ChequeType;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\ChequeService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsAccounting;
use Tests\TestCase;

final class ChequeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAccounting;

    private Branch $branch;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounting();

        $this->branch = Branch::query()->create([
            'name' => 'Cheque Branch',
            'code' => 'CHQ',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create(['is_active' => true]);

        $this->customer = Customer::query()->create([
            'name' => 'Cheque Customer',
            'is_active' => true,
        ]);
    }

    public function test_received_cheque_posts_to_cheques_in_hand(): void
    {
        $cheque = app(ChequeService::class)->create(new CreateChequeData(
            type: ChequeType::Received->value,
            partyType: Customer::class,
            partyId: $this->customer->id,
            amount: 1000,
            currencyCode: 'USD',
            chequeNo: 'RC-1001',
            bank: 'Test Bank',
            dueDate: now()->addDays(7)->toDateString(),
            branchId: $this->branch->id,
        ), $this->user->id);

        $this->assertSame(ChequeStatus::Pending, $cheque->status);
        $this->assertNotNull($cheque->related_journal_entry_id);

        $inHand = ChartOfAccount::query()->where('code', '1500')->firstOrFail();
        $journal = JournalEntry::query()->with('transactions')->findOrFail($cheque->related_journal_entry_id);

        $this->assertSame(
            1000.0,
            (float) $journal->transactions->where('account_id', $inHand->id)->sum('debit'),
        );
    }

    public function test_deposit_and_clear_cheque_post_expected_gl_entries(): void
    {
        $service = app(ChequeService::class);

        $cheque = $service->create(new CreateChequeData(
            type: ChequeType::Received->value,
            partyType: Customer::class,
            partyId: $this->customer->id,
            amount: 500,
            currencyCode: 'USD',
            chequeNo: 'RC-2002',
            bank: 'Test Bank',
            dueDate: now()->addDays(5)->toDateString(),
            branchId: $this->branch->id,
        ), $this->user->id);

        $deposited = $service->applyStatus($cheque, ChequeStatus::Deposited, $this->user->id);
        $this->assertSame(ChequeStatus::Deposited, $deposited->status);

        $cleared = $service->applyStatus($deposited, ChequeStatus::Cleared, $this->user->id);
        $this->assertSame(ChequeStatus::Cleared, $cleared->status);

        $this->assertGreaterThanOrEqual(2, JournalEntry::query()->count());
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $cheque = app(ChequeService::class)->create(new CreateChequeData(
            type: ChequeType::Received->value,
            partyType: Customer::class,
            partyId: $this->customer->id,
            amount: 200,
            currencyCode: 'USD',
            chequeNo: 'RC-3003',
            bank: 'Test Bank',
            dueDate: now()->addDays(3)->toDateString(),
            branchId: $this->branch->id,
        ), $this->user->id);

        $this->expectException(DomainException::class);

        app(ChequeService::class)->applyStatus($cheque, ChequeStatus::Cleared, $this->user->id);
    }
}
