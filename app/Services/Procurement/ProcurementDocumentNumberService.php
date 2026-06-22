<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\ProcurementDocumentType;
use App\Models\DebitNote;
use App\Models\GoodsReceivingNote;
use App\Models\ProcurementDocumentSequence;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class ProcurementDocumentNumberService
{
    public function next(int $branchId, ProcurementDocumentType $type): string
    {
        return DB::transaction(function () use ($branchId, $type) {
            // Reference numbers are globally unique; lock all rows for this document type.
            ProcurementDocumentSequence::query()
                ->where('document_type', $type->value)
                ->lockForUpdate()
                ->get();

            $current = max(
                $this->maxSequenceFromExistingReferences($type),
                (int) ProcurementDocumentSequence::query()
                    ->where('document_type', $type->value)
                    ->max('last_sequence'),
            );

            $next = $current + 1;

            $canonical = ProcurementDocumentSequence::query()
                ->where('document_type', $type->value)
                ->orderBy('id')
                ->first();

            if ($canonical === null) {
                ProcurementDocumentSequence::query()->create([
                    'branch_id' => $branchId,
                    'document_type' => $type->value,
                    'last_sequence' => $next,
                ]);
            } else {
                ProcurementDocumentSequence::query()
                    ->where('document_type', $type->value)
                    ->update(['last_sequence' => $next]);
            }

            $prefix = $this->prefixFor($type);

            return $prefix.'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        });
    }

    private function maxSequenceFromExistingReferences(ProcurementDocumentType $type): int
    {
        $prefix = $this->prefixFor($type);
        $model = $this->modelFor($type);

        if ($model === null) {
            return 0;
        }

        $max = 0;

        $model::query()
            ->where('reference_no', 'like', $prefix.'-%')
            ->pluck('reference_no')
            ->each(function (string $referenceNo) use (&$max) {
                if (preg_match('/-(\d+)$/', $referenceNo, $matches) === 1) {
                    $max = max($max, (int) $matches[1]);
                }
            });

        return $max;
    }

    /**
     * @return class-string<Model>|null
     */
    private function modelFor(ProcurementDocumentType $type): ?string
    {
        return match ($type) {
            ProcurementDocumentType::PurchaseOrder => PurchaseOrder::class,
            ProcurementDocumentType::Grn => GoodsReceivingNote::class,
            ProcurementDocumentType::SupplierInvoice => SupplierInvoice::class,
            ProcurementDocumentType::SupplierPayment => SupplierPayment::class,
            ProcurementDocumentType::PurchaseReturn => PurchaseReturn::class,
            ProcurementDocumentType::DebitNote => DebitNote::class,
        };
    }

    private function prefixFor(ProcurementDocumentType $type): string
    {
        return match ($type) {
            ProcurementDocumentType::PurchaseOrder => (string) SystemSetting::get('procurement', 'po_number_prefix', 'PO'),
            ProcurementDocumentType::Grn => (string) SystemSetting::get('procurement', 'grn_number_prefix', 'GRN'),
            ProcurementDocumentType::SupplierInvoice => (string) SystemSetting::get('procurement', 'invoice_number_prefix', 'SINV'),
            ProcurementDocumentType::SupplierPayment => (string) SystemSetting::get('procurement', 'payment_number_prefix', 'SPAY'),
            ProcurementDocumentType::PurchaseReturn => (string) SystemSetting::get('procurement', 'return_number_prefix', 'PR'),
            ProcurementDocumentType::DebitNote => (string) SystemSetting::get('procurement', 'debit_note_prefix', 'DN'),
        };
    }
}
