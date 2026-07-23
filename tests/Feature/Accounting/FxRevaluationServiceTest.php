<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Enums\ExchangeRateType;
use App\Enums\JournalEntryStatus;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\FinancialSetting;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\CurrencyConversionService;
use App\Services\Accounting\FxRevaluationService;
use App\Services\Accounting\JournalService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FxRevaluationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChartOfAccount $eurBankAccount;

    private ChartOfAccount $usdOffsetAccount;

    private ChartOfAccount $fxGainAccount;

    private ChartOfAccount $fxLossAccount;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eurBankAccount = ChartOfAccount::query()->create([
            'code' => '1050',
            'name' => 'EUR Bank Account',
            'type' => 'asset',
            'currency_code' => 'EUR',
        ]);

        $this->usdOffsetAccount = ChartOfAccount::query()->create([
            'code' => '3000',
            'name' => 'Opening Balance Equity',
            'type' => 'equity',
        ]);

        $this->fxGainAccount = ChartOfAccount::query()->create([
            'code' => '7100',
            'name' => 'FX Gain',
            'type' => 'revenue',
        ]);

        $this->fxLossAccount = ChartOfAccount::query()->create([
            'code' => '8100',
            'name' => 'FX Loss',
            'type' => 'expense',
        ]);

        FinancialSetting::query()->create([
            'functional_currency_code' => 'USD',
            'fiscal_year_start_month' => 1,
            'fx_gain_account_id' => $this->fxGainAccount->id,
            'fx_loss_account_id' => $this->fxLossAccount->id,
        ]);

        Currency::query()->create(['code' => 'EUR', 'name' => 'Euro', 'decimal_places' => 2, 'status' => 'active']);

        $this->user = User::factory()->create(['is_active' => true]);
    }

    /**
     * Books an EUR 1,000 balance at a 1.10 rate (booked functional = 1,100), bypassing
     * PostingRuleEngine since this is a service-level test of the revaluation logic itself.
     */
    private function postOpeningBalance(): void
    {
        $service = app(JournalService::class);

        $entry = $service->createDraft(
            ['journal_date' => '2026-01-01', 'description' => 'Opening EUR balance'],
            [
                [
                    'account_id' => $this->eurBankAccount->id,
                    'debit' => 1100,
                    'credit' => 0,
                    'transaction_currency_amount' => 1000,
                    'currency_code' => 'EUR',
                    'exchange_rate' => 1.10,
                ],
                [
                    'account_id' => $this->usdOffsetAccount->id,
                    'debit' => 0,
                    'credit' => 1100,
                ],
            ],
            $this->user->id,
        );

        $service->post($entry, $this->user->id);
    }

    public function test_revalue_posts_a_gain_when_the_foreign_currency_appreciates(): void
    {
        $this->postOpeningBalance();

        ExchangeRate::query()->create([
            'currency_id' => Currency::query()->where('code', 'EUR')->value('id'),
            'rate_date' => '2026-01-31',
            'rate' => 1.20,
            'status' => 'active',
        ]);

        $result = app(FxRevaluationService::class)->revalue(Carbon::parse('2026-01-31'), $this->user->id);

        $revaluationEntry = $result['revaluation_entry'];
        $reversalEntry = $result['reversal_entry'];

        $this->assertSame('fx_revaluation', $revaluationEntry->source_event);
        $this->assertSame(JournalEntryStatus::Reversed, $revaluationEntry->status);
        $this->assertSame(JournalEntryStatus::Posted, $reversalEntry->status);
        $this->assertSame($revaluationEntry->id, $reversalEntry->reversal_of_journal_entry_id);
        $this->assertSame('2026-02-01', $reversalEntry->journal_date->toDateString());

        $revaluationEntry->loadMissing('transactions');
        $this->assertSame(
            (float) $revaluationEntry->transactions->sum('debit'),
            (float) $revaluationEntry->transactions->sum('credit'),
        );

        $eurLine = $revaluationEntry->transactions->firstWhere('account_id', $this->eurBankAccount->id);
        $gainLine = $revaluationEntry->transactions->firstWhere('account_id', $this->fxGainAccount->id);

        $this->assertSame(100.0, (float) $eurLine->debit);
        $this->assertSame(100.0, (float) $gainLine->credit);

        $this->assertCount(1, $result['lines']);
        $this->assertSame('gain', $result['lines'][0]['direction']);
        $this->assertSame(100.0, $result['lines'][0]['delta']);
    }

    public function test_revalue_posts_a_loss_when_the_foreign_currency_depreciates(): void
    {
        $this->postOpeningBalance();

        ExchangeRate::query()->create([
            'currency_id' => Currency::query()->where('code', 'EUR')->value('id'),
            'rate_date' => '2026-01-31',
            'rate' => 1.00,
            'status' => 'active',
        ]);

        $result = app(FxRevaluationService::class)->revalue(Carbon::parse('2026-01-31'), $this->user->id);

        $revaluationEntry = $result['revaluation_entry'];
        $revaluationEntry->loadMissing('transactions');

        $eurLine = $revaluationEntry->transactions->firstWhere('account_id', $this->eurBankAccount->id);
        $lossLine = $revaluationEntry->transactions->firstWhere('account_id', $this->fxLossAccount->id);

        $this->assertSame(100.0, (float) $eurLine->credit);
        $this->assertSame(100.0, (float) $lossLine->debit);

        $this->assertSame(
            (float) $revaluationEntry->transactions->sum('debit'),
            (float) $revaluationEntry->transactions->sum('credit'),
        );

        $this->assertSame('loss', $result['lines'][0]['direction']);
        $this->assertSame(-100.0, $result['lines'][0]['delta']);
    }

    public function test_revalue_throws_when_already_posted_for_the_same_date(): void
    {
        $this->postOpeningBalance();

        ExchangeRate::query()->create([
            'currency_id' => Currency::query()->where('code', 'EUR')->value('id'),
            'rate_date' => '2026-01-31',
            'rate' => 1.20,
            'status' => 'active',
        ]);

        $service = app(FxRevaluationService::class);
        $service->revalue(Carbon::parse('2026-01-31'), $this->user->id);

        $this->expectException(DomainException::class);

        $service->revalue(Carbon::parse('2026-01-31'), $this->user->id);
    }

    public function test_revalue_prefers_closing_rate_over_spot_on_same_date(): void
    {
        $this->postOpeningBalance();

        $currencyId = (int) Currency::query()->where('code', 'EUR')->value('id');

        ExchangeRate::query()->create([
            'currency_id' => $currencyId,
            'rate_date' => '2026-01-31',
            'rate' => 1.50,
            'rate_type' => ExchangeRateType::Spot,
            'status' => 'active',
        ]);
        ExchangeRate::query()->create([
            'currency_id' => $currencyId,
            'rate_date' => '2026-01-31',
            'rate' => 1.20,
            'rate_type' => ExchangeRateType::Closing,
            'status' => 'active',
        ]);

        $result = app(FxRevaluationService::class)->revalue(Carbon::parse('2026-01-31'), $this->user->id);

        // Booked at 1.10 → 1100; closing 1.20 → 1200; gain 100 (not spot 400).
        $this->assertSame(100.0, $result['lines'][0]['delta']);
        $eurLine = $result['revaluation_entry']->transactions
            ->firstWhere('account_id', $this->eurBankAccount->id);
        $this->assertSame(1.20, (float) $eurLine->exchange_rate);
    }

    public function test_resolve_rate_is_deterministic_when_multiple_types_share_a_date(): void
    {
        $currencyId = (int) Currency::query()->where('code', 'EUR')->value('id');

        ExchangeRate::query()->create([
            'currency_id' => $currencyId,
            'rate_date' => '2026-01-31',
            'rate' => 1.11,
            'rate_type' => ExchangeRateType::Custom,
            'status' => 'active',
        ]);
        ExchangeRate::query()->create([
            'currency_id' => $currencyId,
            'rate_date' => '2026-01-31',
            'rate' => 1.05,
            'rate_type' => ExchangeRateType::Spot,
            'status' => 'active',
        ]);
        ExchangeRate::query()->create([
            'currency_id' => $currencyId,
            'rate_date' => '2026-01-31',
            'rate' => 1.09,
            'rate_type' => ExchangeRateType::Average,
            'status' => 'active',
        ]);

        $rate = app(CurrencyConversionService::class)
            ->resolveRate('EUR', '2026-01-31');

        $this->assertSame(1.05, $rate);
    }

    public function test_reversed_fx_pair_nets_zero_transaction_balance(): void
    {
        $this->postOpeningBalance();

        $journalService = app(JournalService::class);
        $original = JournalEntry::query()->where('description', 'Opening EUR balance')->firstOrFail();
        $journalService->reverse($original, $this->user->id, null, Carbon::parse('2026-01-15'));

        $balances = (new \ReflectionMethod(FxRevaluationService::class, 'accountBalances'))
            ->invoke(
                app(FxRevaluationService::class),
                $this->eurBankAccount,
                Carbon::parse('2026-01-31'),
                null,
                null,
            );

        $this->assertNotNull($balances);
        [$netTransaction, $bookedFunctional] = $balances;
        $this->assertSame(0.0, round($netTransaction, 2));
        $this->assertSame(0.0, round($bookedFunctional, 2));

        $reversal = JournalEntry::query()
            ->where('reversal_of_journal_entry_id', $original->id)
            ->firstOrFail();
        $reversalTca = (float) $reversal->transactions()
            ->where('account_id', $this->eurBankAccount->id)
            ->value('transaction_currency_amount');
        $this->assertGreaterThan(0, $reversalTca);
    }
}
