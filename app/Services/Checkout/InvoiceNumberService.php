<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Branch;
use App\Models\FbrInvoiceSequence;
use App\Models\SaleInvoiceSequence;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

final class InvoiceNumberService
{
    public function generate(int $branchId, ?\DateTimeInterface $saleDate = null): string
    {
        if ((bool) SystemSetting::get('fbr', 'enabled', false)) {
            return $this->generateFbrNumber($branchId, $saleDate);
        }

        return $this->generateStandardNumber($branchId, $saleDate);
    }

    private function generateStandardNumber(int $branchId, ?\DateTimeInterface $saleDate): string
    {
        $branch = Branch::query()->findOrFail($branchId);
        $timezone = $branch->timezone ?? config('app.timezone');
        $saleMoment = ($saleDate ?? now())->setTimezone($timezone);
        $dateKey = $saleMoment->format('Y-m-d');
        $dateLabel = $saleMoment->format('Ymd');
        $prefix = (string) SystemSetting::get('checkout', 'invoice_number_prefix', 'INV');
        $digits = (int) SystemSetting::get('checkout', 'invoice_number_digits', 8);
        $scope = (string) SystemSetting::get('checkout', 'invoice_sequence_scope', 'branch');
        $sequenceBranchId = $scope === 'branch' ? $branchId : $branchId;

        $sequence = DB::transaction(function () use ($sequenceBranchId, $dateKey) {
            $row = SaleInvoiceSequence::query()
                ->where('branch_id', $sequenceBranchId)
                ->where('date', $dateKey)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                SaleInvoiceSequence::query()->insert([
                    'branch_id' => $sequenceBranchId,
                    'date' => $dateKey,
                    'last_sequence' => 1,
                ]);

                return 1;
            }

            $next = $row->last_sequence + 1;
            SaleInvoiceSequence::query()
                ->where('branch_id', $sequenceBranchId)
                ->where('date', $dateKey)
                ->update(['last_sequence' => $next]);

            return $next;
        });

        return sprintf('%s-%s-%s', $prefix, $dateLabel, str_pad((string) $sequence, $digits, '0', STR_PAD_LEFT));
    }

    private function generateFbrNumber(int $branchId, ?\DateTimeInterface $saleDate): string
    {
        $posId = (string) SystemSetting::get('fbr', 'pos_id', 'POS');
        $saleMoment = ($saleDate ?? now())->utc();
        $dateKey = $saleMoment->format('Y-m-d');
        $dateLabel = $saleMoment->format('Ymd');

        $sequence = DB::transaction(function () use ($branchId, $dateKey) {
            $row = FbrInvoiceSequence::query()
                ->where('branch_id', $branchId)
                ->where('date', $dateKey)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                FbrInvoiceSequence::query()->insert([
                    'branch_id' => $branchId,
                    'date' => $dateKey,
                    'last_sequence' => 1,
                ]);

                return 1;
            }

            $next = $row->last_sequence + 1;
            FbrInvoiceSequence::query()
                ->where('branch_id', $branchId)
                ->where('date', $dateKey)
                ->update(['last_sequence' => $next]);

            return $next;
        });

        return sprintf('%s-%s-%08d', $posId, $dateLabel, $sequence);
    }
}
