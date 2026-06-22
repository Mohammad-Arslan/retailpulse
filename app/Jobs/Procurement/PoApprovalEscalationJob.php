<?php

declare(strict_types=1);

namespace App\Jobs\Procurement;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\SystemSetting;
use App\Services\Procurement\ProcurementAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class PoApprovalEscalationJob implements ShouldQueue
{
    use Queueable;

    public function handle(ProcurementAlertService $alerts): void
    {
        $hours = (int) SystemSetting::get('procurement', 'po_approval_escalation_hours', 24);
        $cutoff = now()->subHours($hours);

        PurchaseOrder::query()
            ->where('status', PurchaseOrderStatus::Submitted)
            ->where('submitted_at', '<=', $cutoff)
            ->each(function (PurchaseOrder $order) use ($hours, $alerts): void {
                Log::warning('PO approval escalation', [
                    'purchase_order_id' => $order->id,
                    'reference_no' => $order->reference_no,
                    'submitted_at' => $order->submitted_at?->toIso8601String(),
                    'escalation_hours' => $hours,
                ]);

                $dedupeKey = 'po_escalation:'.$order->id.':'.now()->toDateString();

                $alerts->notifyUsersWithPermission(
                    'procurement.approve-po',
                    $order->branch_id,
                    'po_escalation',
                    $dedupeKey,
                    __('PO approval overdue'),
                    __('Purchase order :ref has been awaiting approval for more than :hours hours.', [
                        'ref' => $order->reference_no,
                        'hours' => $hours,
                    ]),
                    'admin.purchase-orders.show',
                    ['purchase_order' => $order->id],
                );
            });
    }
}
