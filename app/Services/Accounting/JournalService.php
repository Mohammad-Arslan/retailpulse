<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\DTOs\Accounting\BackdatedPostingAssessment;
use App\Enums\FiscalYearStatus;
use App\Enums\JournalEntryStatus;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class JournalService
{
    private const BACKDATED_REASON = 'Journal date is before today; posted under the warn backdated-posting policy.';

    public function __construct(
        private readonly JournalValidationService $validation,
        private readonly JournalNumberService $numberService,
        private readonly FinancialSettingsService $settings,
        private readonly BackdatedPostingPolicyGuard $backdatedGuard,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function createDraft(array $attributes, array $lines, int $userId): JournalEntry
    {
        $assessment = $this->assessBackdated(
            $attributes['journal_date'] ?? now(),
            (bool) ($attributes['is_system_generated'] ?? false),
        );

        if ($assessment->shouldBlock) {
            throw new \DomainException('Journal date is backdated and the current posting policy blocks backdated entries.');
        }

        return DB::transaction(function () use ($attributes, $lines, $userId, $assessment) {
            $fiscalYearId = $attributes['fiscal_year_id'] ?? $this->resolveFiscalYearId($attributes['journal_date'] ?? now());

            $entry = JournalEntry::query()->create([
                ...$attributes,
                'fiscal_year_id' => $fiscalYearId,
                'journal_number' => $attributes['journal_number'] ?? $this->numberService->next(
                    $attributes['branch_id'] ?? null,
                    $fiscalYearId,
                ),
                'status' => JournalEntryStatus::Draft,
                'created_by' => $userId,
                'updated_by' => $userId,
                'backdated_at' => $assessment->shouldFlag ? now() : null,
                'backdated_reason' => $assessment->shouldFlag ? self::BACKDATED_REASON : null,
            ]);

            $this->syncLines($entry, $lines);
            $this->applyApprovalPolicy($entry->fresh(['transactions']));

            return $entry->fresh(['transactions.account']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function updateDraft(JournalEntry $entry, array $attributes, array $lines, int $userId): JournalEntry
    {
        $this->validation->assertCanEdit($entry);

        if ($entry->status !== JournalEntryStatus::Draft) {
            throw new \DomainException('Only draft journals can be edited.');
        }

        $assessment = null;

        if (array_key_exists('journal_date', $attributes)) {
            $assessment = $this->assessBackdated($attributes['journal_date'], $entry->is_system_generated);

            if ($assessment->shouldBlock) {
                throw new \DomainException('Journal date is backdated and the current posting policy blocks backdated entries.');
            }
        }

        return DB::transaction(function () use ($entry, $attributes, $lines, $userId, $assessment) {
            $entry->update([
                ...$attributes,
                'updated_by' => $userId,
                ...($assessment !== null ? [
                    'backdated_at' => $assessment->shouldFlag ? now() : $entry->backdated_at,
                    'backdated_reason' => $assessment->shouldFlag ? self::BACKDATED_REASON : $entry->backdated_reason,
                ] : []),
            ]);

            $entry->transactions()->delete();
            $this->syncLines($entry, $lines);
            $this->applyApprovalPolicy($entry->fresh(['transactions']));

            return $entry->fresh(['transactions.account']);
        });
    }

    public function deleteDraft(JournalEntry $entry): void
    {
        $this->validation->assertCanEdit($entry);

        if ($entry->status !== JournalEntryStatus::Draft) {
            throw new \DomainException('Only draft journals can be deleted.');
        }

        DB::transaction(function () use ($entry) {
            $entry->transactions()->delete();
            $entry->delete();
        });
    }

    public function post(JournalEntry $entry, int $userId): JournalEntry
    {
        $this->validation->assertCanPost($entry);
        $this->assertApprovalSatisfied($entry);

        $entry->update([
            'status' => JournalEntryStatus::Posted,
            'posted_at' => now(),
            'posted_by' => $userId,
            'updated_by' => $userId,
        ]);

        return $entry->fresh(['transactions.account']);
    }

    public function approve(JournalEntry $entry, int $userId): JournalEntry
    {
        if ($entry->status !== JournalEntryStatus::PendingApproval) {
            throw new \DomainException('Only journals pending approval can be approved.');
        }

        $entry->update([
            'status' => JournalEntryStatus::Approved,
            'approved_by' => $userId,
            'updated_by' => $userId,
        ]);

        return $entry->fresh(['transactions.account']);
    }

    public function reverse(
        JournalEntry $entry,
        int $userId,
        ?string $description = null,
        ?CarbonInterface $reversalDate = null,
    ): JournalEntry {
        $this->validation->assertCanReverse($entry);

        return DB::transaction(function () use ($entry, $userId, $description, $reversalDate) {
            $entry->load('transactions');

            $reversalLines = $entry->transactions->map(fn (JournalTransaction $line) => [
                'account_id' => $line->account_id,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'functional_currency_amount' => -1 * (float) $line->functional_currency_amount,
                // Magnitude only — sign is derived from debit/credit by consumers.
                'transaction_currency_amount' => $line->transaction_currency_amount !== null
                    ? abs((float) $line->transaction_currency_amount)
                    : null,
                'currency_code' => $line->currency_code,
                'exchange_rate' => $line->exchange_rate,
                'branch_id' => $line->branch_id,
                'warehouse_id' => $line->warehouse_id,
                'party_type' => $line->party_type,
                'party_id' => $line->party_id,
                'product_variant_id' => $line->product_variant_id,
                'reference_type' => $line->reference_type,
                'reference_id' => $line->reference_id,
                'description' => $description ?? 'Reversal of '.$entry->journal_number,
            ])->all();

            $reversal = $this->createDraft([
                'journal_date' => ($reversalDate ?? now())->toDateString(),
                'branch_id' => $entry->branch_id,
                'legal_entity_id' => $entry->legal_entity_id,
                'description' => $description ?? 'Reversal of '.$entry->journal_number,
                'source_module' => $entry->source_module,
                'source_event' => 'journal.reversed',
                'source_reference_type' => JournalEntry::class,
                'source_reference_id' => $entry->id,
                'is_system_generated' => true,
                'reversal_of_journal_entry_id' => $entry->id,
            ], $reversalLines, $userId);

            $this->post($reversal, $userId);

            $entry->update([
                'status' => JournalEntryStatus::Reversed,
                'updated_by' => $userId,
            ]);

            return $reversal->fresh(['transactions.account']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function syncLines(JournalEntry $entry, array $lines): void
    {
        $sequence = 1;
        foreach ($lines as $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            JournalTransaction::query()->create([
                'journal_entry_id' => $entry->id,
                'line_sequence' => $sequence++,
                'account_id' => $line['account_id'],
                'debit' => $debit,
                'credit' => $credit,
                'functional_currency_amount' => $debit > 0 ? $debit : -$credit,
                'transaction_currency_amount' => $line['transaction_currency_amount'] ?? null,
                'currency_code' => $line['currency_code'] ?? $this->settings->get()->functional_currency_code,
                'exchange_rate' => $line['exchange_rate'] ?? 1,
                'branch_id' => $line['branch_id'] ?? $entry->branch_id,
                'warehouse_id' => $line['warehouse_id'] ?? null,
                'party_type' => $line['party_type'] ?? null,
                'party_id' => $line['party_id'] ?? null,
                'product_variant_id' => $line['product_variant_id'] ?? null,
                'tax_type_id' => $line['tax_type_id'] ?? null,
                'cost_centre_id' => $line['cost_centre_id'] ?? null,
                'reference_type' => $line['reference_type'] ?? null,
                'reference_id' => $line['reference_id'] ?? null,
                'description' => $line['description'] ?? null,
            ]);
        }
    }

    private function assessBackdated(mixed $journalDate, bool $isSystemGenerated): BackdatedPostingAssessment
    {
        if ($isSystemGenerated) {
            return new BackdatedPostingAssessment(isBackdated: false, shouldBlock: false, shouldFlag: false);
        }

        $parsed = $journalDate instanceof CarbonInterface ? $journalDate : Carbon::parse($journalDate);
        $isBackdated = $parsed->copy()->startOfDay()->lt(Carbon::today());

        return $this->backdatedGuard->assess($isBackdated, $this->settings->get());
    }

    private function resolveFiscalYearId(CarbonInterface|string $date): ?int
    {
        $parsed = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        return FiscalYear::query()
            ->whereIn('status', [
                FiscalYearStatus::Open,
                FiscalYearStatus::Reopening,
                FiscalYearStatus::Closing,
            ])
            ->whereDate('start_date', '<=', $parsed)
            ->whereDate('end_date', '>=', $parsed)
            ->value('id');
    }

    private function applyApprovalPolicy(JournalEntry $entry): void
    {
        if ($entry->is_system_generated) {
            return;
        }

        $settings = $this->settings->get();
        $limit = $settings->manual_journal_approval_limit;

        if ($limit === null) {
            return;
        }

        $entry->loadMissing('transactions');
        $totalDebit = (float) $entry->transactions->sum('debit');

        if ($totalDebit > (float) $limit && $entry->status === JournalEntryStatus::Draft) {
            $entry->update(['status' => JournalEntryStatus::PendingApproval]);
        }
    }

    private function assertApprovalSatisfied(JournalEntry $entry): void
    {
        if ($entry->is_system_generated) {
            return;
        }

        $settings = $this->settings->get();
        $limit = $settings->manual_journal_approval_limit;

        if ($limit === null) {
            return;
        }

        $entry->loadMissing('transactions');
        $totalDebit = (float) $entry->transactions->sum('debit');

        if ($totalDebit <= (float) $limit) {
            return;
        }

        if ($entry->status !== JournalEntryStatus::Approved) {
            throw new \DomainException('Journal exceeds approval limit and must be approved before posting.');
        }
    }
}
