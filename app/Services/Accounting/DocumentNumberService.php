<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\DocumentSequence;
use App\Models\FiscalYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DocumentNumberService
{
    public function next(
        string $documentType,
        string $prefix,
        ?int $branchId = null,
        ?int $fiscalYearId = null,
    ): string {
        return DB::transaction(function () use ($documentType, $prefix, $branchId, $fiscalYearId) {
            $sequence = $this->lockSequence($documentType, $branchId, $fiscalYearId);

            if ($sequence === null) {
                $number = 1;

                DocumentSequence::query()->create([
                    'document_type' => $documentType,
                    'branch_id' => $branchId,
                    'fiscal_year_id' => $fiscalYearId,
                    'prefix' => $prefix,
                    'next_number' => 2,
                    'status' => 'active',
                ]);
            } else {
                $prefix = $sequence->prefix;
                $number = (int) $sequence->next_number;
                $sequence->update(['next_number' => $number + 1]);
            }

            return $this->format($prefix, $number, $fiscalYearId);
        });
    }

    /**
     * Preview the next code without consuming the sequence.
     */
    public function peek(
        string $documentType,
        string $prefix,
        ?int $branchId = null,
        ?int $fiscalYearId = null,
    ): string {
        $sequence = DocumentSequence::query()
            ->where('document_type', $documentType)
            ->where('branch_id', $branchId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->first();

        $number = $sequence === null ? 1 : (int) $sequence->next_number;
        $resolvedPrefix = $sequence?->prefix ?: $prefix;

        return $this->format($resolvedPrefix, $number, $fiscalYearId);
    }

    public function importBatchId(): string
    {
        return 'BSI-'.Str::upper(Str::random(8)).'-'.now()->format('YmdHis');
    }

    private function lockSequence(string $documentType, ?int $branchId, ?int $fiscalYearId): ?DocumentSequence
    {
        return DocumentSequence::query()
            ->where('document_type', $documentType)
            ->where('branch_id', $branchId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->lockForUpdate()
            ->first();
    }

    private function format(string $prefix, int $number, ?int $fiscalYearId): string
    {
        $yearSuffix = '';
        if ($fiscalYearId) {
            $fy = FiscalYear::query()->find($fiscalYearId);
            $yearSuffix = $fy ? '-'.$fy->start_date->format('Y') : '';
        }

        return sprintf('%s%s-%05d', $prefix, $yearSuffix, $number);
    }
}
