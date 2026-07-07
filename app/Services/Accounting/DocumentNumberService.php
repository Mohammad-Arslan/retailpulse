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
            $sequence = DocumentSequence::query()
                ->where('document_type', $documentType)
                ->where('branch_id', $branchId)
                ->where('fiscal_year_id', $fiscalYearId)
                ->lockForUpdate()
                ->first();

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

            $yearSuffix = '';
            if ($fiscalYearId) {
                $fy = FiscalYear::query()->find($fiscalYearId);
                $yearSuffix = $fy ? '-'.$fy->start_date->format('Y') : '';
            }

            return sprintf('%s%s-%05d', $prefix, $yearSuffix, $number);
        });
    }

    public function importBatchId(): string
    {
        return 'BSI-'.Str::upper(Str::random(8)).'-'.now()->format('YmdHis');
    }
}
