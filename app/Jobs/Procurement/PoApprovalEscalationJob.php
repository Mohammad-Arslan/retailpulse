<?php

declare(strict_types=1);

namespace App\Jobs\Procurement;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\SystemSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class PoApprovalEscalationJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $hours = (int) SystemSetting::get('procurement', 'po_approval_escalation_hours', 24);
        $cutoff = now()->subHours($hours);

        PurchaseOrder::query()
            ->where('status', PurchaseOrderStatus::Submitted)
            ->where('submitted_at', '<=', $cutoff)
            ->each(function (PurchaseOrder $order) use ($hours): void {
                Log::warning('PO approval escalation', [
                    'purchase_order_id' => $order->id,
                    'reference_no' => $order->reference_no,
                    'submitted_at' => $order->submitted_at?->toIso8601String(),
                    'escalation_hours' => $hours,
                ]);
            });
    }
}
