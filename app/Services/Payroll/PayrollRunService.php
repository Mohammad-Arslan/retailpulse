<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\PayrollApprovalSetting;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Services\Accounting\AccountingEventService;
use App\Services\Accounting\DocumentNumberService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Payroll run lifecycle. GL posting only on approved → posted via payroll.posted.
 * Approving alone never writes to the ledger.
 */
final class PayrollRunService
{
    public function __construct(
        private readonly PayrollCalculationService $calculation,
        private readonly AccountingEventService $accountingEvents,
        private readonly DocumentNumberService $documentNumbers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data): PayrollRun
    {
        return PayrollRun::query()->create([
            ...$data,
            'status' => 'draft',
            'payroll_number' => $this->documentNumbers->next(
                'payroll_run',
                'PR',
                isset($data['branch_id']) ? (int) $data['branch_id'] : null,
            ),
        ]);
    }

    public function calculate(PayrollRun $run): PayrollRun
    {
        return $this->calculation->processRun($run);
    }

    public function submitForApproval(PayrollRun $run, int $userId): PayrollRun
    {
        if ($run->status !== 'draft') {
            throw new DomainException('Only Draft Payroll Runs Can Be Submitted.');
        }

        if (($run->totals_json['calculated_at'] ?? null) === null) {
            $run = $this->calculate($run);
        }

        $settings = $this->settingsFor($run->legal_entity_id);
        $totalNet = (string) ($run->totals_json['total_net'] ?? '0');

        if (! $settings->requires_approval) {
            return $this->markApproved($run, $userId);
        }

        if ($settings->approval_limit !== null && bccomp($totalNet, (string) $settings->approval_limit, 4) < 0) {
            return $this->markApproved($run, $userId);
        }

        // Phase 29 hook: use_workflow_engine is recorded but lands on the same state.
        $run->update(['status' => 'pending_approval']);

        return $run->fresh();
    }

    /**
     * State-only transition. Never publishes accounting events.
     */
    public function approve(PayrollRun $run, int $userId): PayrollRun
    {
        if (! in_array($run->status, ['draft', 'pending_approval'], true)) {
            throw new DomainException('Payroll Run Is Not Awaiting Approval.');
        }

        if ($run->status === 'draft' && ($run->totals_json['calculated_at'] ?? null) === null) {
            $run = $this->calculate($run);
        }

        return $this->markApproved($run, $userId);
    }

    /**
     * Sole GL-posting transition: publishes payroll.posted.
     */
    public function post(PayrollRun $run, int $userId): PayrollRun
    {
        if ($run->status !== 'approved') {
            throw new DomainException('Only Approved Payroll Runs Can Be Posted.');
        }

        return DB::transaction(function () use ($run, $userId): PayrollRun {
            $existing = $this->accountingEvents->findForSource('payroll.posted', PayrollRun::class, $run->id);
            if ($existing?->journal_entry_id) {
                $run->update([
                    'status' => 'posted',
                    'posted_by' => $userId,
                    'accounting_event_id' => $existing->id,
                    'journal_entry_id' => $existing->journal_entry_id,
                ]);

                return $run->fresh(['items.lines']);
            }

            $event = $this->accountingEvents->process(
                'payroll.posted',
                PayrollRun::class,
                $run->id,
                $this->buildPostingPayload($run, $userId),
                $userId,
            );

            $run->update([
                'status' => 'posted',
                'posted_by' => $userId,
                'accounting_event_id' => $event->id,
                'journal_entry_id' => $event->journal_entry_id,
            ]);

            return $run->fresh(['items.lines']);
        });
    }

    public function reverse(PayrollRun $run, int $userId): PayrollRun
    {
        if ($run->status !== 'posted' || $run->journal_entry_id === null) {
            throw new DomainException('Only Posted Payroll Runs With A Journal Can Be Reversed.');
        }

        return DB::transaction(function () use ($run, $userId): PayrollRun {
            $this->accountingEvents->reverseLinkedJournal(
                'payroll.posted',
                'payroll.reversed',
                PayrollRun::class,
                $run->id,
                [
                    'date' => now()->toDateString(),
                    'branch_id' => $run->branch_id,
                    'legal_entity_id' => $run->legal_entity_id,
                    'currency_code' => $run->currency_code,
                    'description' => "Payroll Reversed {$run->payroll_number}",
                    'source_module' => 'payroll',
                    'source_number' => $run->payroll_number,
                    'user_id' => $userId,
                ],
                $userId,
                "Reversal Of Payroll {$run->payroll_number}",
            );

            $run->update(['status' => 'reversed']);

            return $run->fresh();
        });
    }

    private function markApproved(PayrollRun $run, int $userId): PayrollRun
    {
        $run->update([
            'status' => 'approved',
            'approved_by' => $userId,
        ]);

        return $run->fresh();
    }

    private function settingsFor(int $legalEntityId): PayrollApprovalSetting
    {
        return PayrollApprovalSetting::query()->firstOrCreate(
            ['legal_entity_id' => $legalEntityId],
            [
                'requires_approval' => true,
                'approval_limit' => null,
                'use_workflow_engine' => false,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPostingPayload(PayrollRun $run, int $userId): array
    {
        $run->loadMissing(['items.lines']);

        $gross = '0.0000';
        $employer = '0.0000';
        $tax = '0.0000';
        $employeeStatutoryAndOther = '0.0000';
        $net = '0.0000';

        foreach ($run->items as $item) {
            /** @var PayrollItem $item */
            $gross = bcadd($gross, (string) $item->gross, 4);
            $employer = bcadd($employer, (string) $item->total_employer_contributions, 4);
            $net = bcadd($net, (string) $item->net_pay, 4);

            foreach ($item->lines as $line) {
                $snapshot = $line->component_snapshot_json ?? [];
                $type = $snapshot['type'] ?? null;
                $mapping = $snapshot['account_mapping_key'] ?? null;
                $code = $snapshot['code'] ?? null;

                if ($mapping === 'tax_withheld_payable' || $code === 'INCOME_TAX') {
                    $tax = bcadd($tax, (string) $line->amount, 4);
                } elseif (in_array($type, ['statutory', 'deduction'], true)) {
                    if ($code !== 'INCOME_TAX' && $mapping !== 'tax_withheld_payable') {
                        $employeeStatutoryAndOther = bcadd($employeeStatutoryAndOther, (string) $line->amount, 4);
                    }
                }
            }
        }

        return [
            'date' => $run->period_end?->toDateString() ?? now()->toDateString(),
            'branch_id' => $run->branch_id,
            'legal_entity_id' => $run->legal_entity_id,
            'currency_code' => $run->currency_code,
            'gross_amount' => (float) $gross,
            'inventory_cost' => (float) $employer,
            'tax_amount' => (float) $tax,
            'settlement_amount' => (float) $net,
            'discount_amount' => (float) $employeeStatutoryAndOther,
            'description' => "Payroll {$run->payroll_number}",
            'source_module' => 'payroll',
            'source_number' => $run->payroll_number,
            'user_id' => $userId,
            'totals' => $run->totals_json,
        ];
    }
}
