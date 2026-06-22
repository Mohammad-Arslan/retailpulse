<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\DebitNote;
use App\Models\SystemSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

final class DebitNotePdfService
{
    public function generate(DebitNote $debitNote): string
    {
        $debitNote->load(['supplier', 'branch', 'purchaseReturn']);

        $pdf = Pdf::loadView('procurement.debit_note', [
            'debitNote' => $debitNote,
            'company' => [
                'legal_name' => SystemSetting::get('company', 'legal_name', config('app.name')),
                'address' => SystemSetting::get('company', 'address', ''),
            ],
            'generatedAt' => now(),
        ]);

        $path = 'procurement/dn-'.$debitNote->reference_no.'-'.now()->format('YmdHis').'.pdf';
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }
}
