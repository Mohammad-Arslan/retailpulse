<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\ProcurementDocumentType;
use App\Models\ProcurementDocumentSequence;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

final class ProcurementDocumentNumberService
{
    public function next(int $branchId, ProcurementDocumentType $type): string
    {
        return DB::transaction(function () use ($branchId, $type) {
            $sequence = ProcurementDocumentSequence::query()
                ->where('branch_id', $branchId)
                ->where('document_type', $type->value)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $sequence = ProcurementDocumentSequence::query()->create([
                    'branch_id' => $branchId,
                    'document_type' => $type->value,
                    'last_sequence' => 0,
                ]);
                $sequence = ProcurementDocumentSequence::query()
                    ->where('id', $sequence->id)
                    ->lockForUpdate()
                    ->first();
            }

            $next = ($sequence?->last_sequence ?? 0) + 1;

            ProcurementDocumentSequence::query()
                ->where('id', $sequence->id)
                ->update(['last_sequence' => $next]);

            $prefix = $this->prefixFor($type);

            return $prefix.'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        });
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
