<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\Supplier;
use App\Models\SystemSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

final class SupplierStatementService
{
    public function __construct(
        private readonly SupplierLedgerService $ledger,
    ) {}

    public function generate(Supplier $supplier, ?int $branchId = null, ?string $from = null, ?string $to = null): string
    {
        $entries = $this->ledger->statement($supplier->id, $branchId, $from, $to);
        $balance = $this->ledger->getBalance($supplier->id, $branchId);

        $pdf = Pdf::loadView('procurement.supplier_statement', [
            'supplier' => $supplier,
            'entries' => $entries,
            'balance' => $balance,
            'company' => [
                'legal_name' => SystemSetting::get('company', 'legal_name', config('app.name')),
                'address' => SystemSetting::get('company', 'address', ''),
            ],
            'generatedAt' => now(),
            'from' => $from,
            'to' => $to,
        ]);

        $suffix = $branchId !== null ? "branch-{$branchId}" : 'all';
        $path = "procurement/supplier-{$supplier->id}-{$suffix}-".now()->format('YmdHis').'.pdf';
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }
}
