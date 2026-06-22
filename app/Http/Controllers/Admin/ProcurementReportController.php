<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Services\Procurement\ProcurementReportService;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProcurementReportController extends Controller
{
    public function __construct(
        private readonly ProcurementReportService $reports,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $branchId = app(BranchContext::class)->branchId;
        $tab = (string) $request->query('tab', 'open-pos');

        return Inertia::render('Admin/Procurement/Reports', [
            'tab' => $tab,
            'openPos' => $this->reports->openPurchaseOrders($branchId)->map(fn ($o) => [
                'id' => $o->id,
                'reference_no' => $o->reference_no,
                'status' => $o->status->value,
                'supplier' => $o->supplier?->name,
                'total' => number_format((float) $o->total, 2, '.', ''),
            ]),
            'pendingApprovals' => $this->reports->pendingApprovals($branchId)->map(fn ($o) => [
                'id' => $o->id,
                'reference_no' => $o->reference_no,
                'supplier' => $o->supplier?->name,
                'total' => number_format((float) $o->total, 2, '.', ''),
                'submitted_at' => $o->submitted_at?->toIso8601String(),
            ]),
            'grns' => $this->reports->grnReport($branchId)->map(fn ($g) => [
                'id' => $g->id,
                'reference_no' => $g->reference_no,
                'supplier' => $g->supplier?->name,
                'po' => $g->purchaseOrder?->reference_no,
                'received_at' => $g->received_at?->toIso8601String(),
            ]),
            'invoices' => $this->reports->invoiceReport($branchId)->map(fn ($i) => [
                'id' => $i->id,
                'reference_no' => $i->reference_no,
                'supplier' => $i->supplier?->name,
                'status' => $i->status->value,
                'total' => number_format((float) $i->total, 2, '.', ''),
                'match_status' => $i->matchResult?->match_status->value,
            ]),
            'supplierBalances' => $this->reports->supplierBalances($branchId)->map(fn ($s) => [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'balance' => number_format((float) $s->balance, 2, '.', ''),
                'on_time_delivery_rate' => $s->on_time_delivery_rate,
            ]),
            'matchExceptions' => $this->reports->matchExceptions($branchId)->map(fn ($m) => [
                'id' => $m->id,
                'invoice' => $m->supplierInvoice?->reference_no,
                'po' => $m->purchaseOrder?->reference_no,
                'match_status' => $m->match_status->value,
                'exception_reason' => $m->exception_reason,
            ]),
            'returns' => $this->reports->purchaseReturnsReport($branchId)->map(fn ($r) => [
                'id' => $r->id,
                'reference_no' => $r->reference_no,
                'supplier' => $r->supplier?->name,
                'status' => $r->status->value,
            ]),
        ]);
    }
}
