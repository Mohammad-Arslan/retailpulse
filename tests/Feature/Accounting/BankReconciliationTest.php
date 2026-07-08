<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\BankStatementLineStatus;
use App\Enums\JournalEntryStatus;
use App\Models\BankAccount;
use App\Models\BankReconciliationMatch;
use App\Models\BankStatementLine;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use App\Models\User;
use App\Services\Accounting\BankReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\SeedsAccounting;
use Tests\TestCase;

final class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAccounting;

    private BankAccount $bankAccount;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccounting();

        $branch = Branch::query()->create([
            'name' => 'Bank Branch',
            'code' => 'BNK',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
        ]);

        $coa = ChartOfAccount::query()->where('code', '1210')->firstOrFail();

        $this->bankAccount = BankAccount::query()->create([
            'branch_id' => $branch->id,
            'coa_account_id' => $coa->id,
            'bank_name' => 'Test Bank',
            'account_title' => 'Operating',
            'account_number_masked' => '****1234',
            'currency_code' => 'USD',
            'status' => 'active',
        ]);

        $this->user = User::factory()->create(['is_active' => true]);
    }

    public function test_csv_import_skips_malformed_rows(): void
    {
        $csv = "date,reference,description,amount\n"
            ."2026-06-01,REF-1,Valid row,100.00\n"
            ."2026-06-02,REF-2,Missing amount column\n";

        $file = UploadedFile::fake()->createWithContent('statement.csv', $csv);
        $result = app(BankReconciliationService::class)->importCsv($this->bankAccount, $file);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['skipped']);
    }

    public function test_csv_import_parses_all_valid_rows_and_skips_duplicates(): void
    {
        $csv = "date,reference,description,amount\n"
            ."2026-06-01,REF-1,Payment received,500.00\n"
            ."2026-06-02,REF-2,Supplier payment,-200.00\n";

        $file = UploadedFile::fake()->createWithContent('statement.csv', $csv);

        $service = app(BankReconciliationService::class);
        $result = $service->importCsv($this->bankAccount, $file);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(2, BankStatementLine::query()->count());

        $duplicateCsv = "date,reference,description,amount\n"
            ."2026-06-01,REF-1,Payment received,500.00\n"
            ."2026-06-02,REF-2,Supplier payment,-200.00\n";
        $duplicateFile = UploadedFile::fake()->createWithContent('statement.csv', $duplicateCsv);
        $second = $service->importCsv($this->bankAccount, $duplicateFile);

        $this->assertSame(0, $second['imported']);
        $this->assertSame(2, $second['skipped']);
    }

    public function test_suggest_matches_ranks_exact_date_and_amount_highest(): void
    {
        $line = BankStatementLine::query()->create([
            'bank_account_id' => $this->bankAccount->id,
            'statement_date' => '2026-06-10',
            'transaction_date' => '2026-06-10',
            'reference' => 'CHQ-9001',
            'description' => 'Customer deposit',
            'debit' => 750,
            'credit' => 0,
            'status' => BankStatementLineStatus::Unmatched,
        ]);

        $journal = JournalEntry::query()->create([
            'journal_number' => 'JRN-TEST-1',
            'journal_date' => '2026-06-10',
            'status' => JournalEntryStatus::Posted,
            'description' => 'CHQ-9001 deposit',
            'is_system_generated' => true,
        ]);

        JournalTransaction::query()->create([
            'journal_entry_id' => $journal->id,
            'line_sequence' => 1,
            'account_id' => $this->bankAccount->coa_account_id,
            'debit' => 750,
            'credit' => 0,
            'description' => 'CHQ-9001 deposit',
        ]);

        $suggestions = app(BankReconciliationService::class)->suggestMatches($this->bankAccount);

        $this->assertNotEmpty($suggestions);
        $top = $suggestions->first();
        $this->assertSame($line->id, $top['statement_line']->id);
        $this->assertGreaterThanOrEqual(70, $top['score']);
    }

    public function test_confirm_match_creates_reconciliation_match_and_marks_line_matched(): void
    {
        $line = BankStatementLine::query()->create([
            'bank_account_id' => $this->bankAccount->id,
            'statement_date' => '2026-06-11',
            'transaction_date' => '2026-06-11',
            'reference' => 'TX-1',
            'description' => 'Wire',
            'debit' => 300,
            'credit' => 0,
            'status' => BankStatementLineStatus::Unmatched,
        ]);

        $journal = JournalEntry::query()->create([
            'journal_number' => 'JRN-TEST-2',
            'journal_date' => '2026-06-11',
            'status' => JournalEntryStatus::Posted,
            'description' => 'Wire TX-1',
            'is_system_generated' => true,
        ]);

        $transaction = JournalTransaction::query()->create([
            'journal_entry_id' => $journal->id,
            'line_sequence' => 1,
            'account_id' => $this->bankAccount->coa_account_id,
            'debit' => 300,
            'credit' => 0,
            'description' => 'Wire TX-1',
        ]);

        $match = app(BankReconciliationService::class)->match($line, $transaction, $this->user->id);

        $this->assertInstanceOf(BankReconciliationMatch::class, $match);
        $this->assertSame(BankStatementLineStatus::Matched, $line->fresh()->status);
    }
}
