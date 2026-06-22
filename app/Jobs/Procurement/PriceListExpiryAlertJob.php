<?php

declare(strict_types=1);

namespace App\Jobs\Procurement;

use App\Models\SupplierPriceList;
use App\Models\SystemSetting;
use App\Services\Procurement\ProcurementAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class PriceListExpiryAlertJob implements ShouldQueue
{
    use Queueable;

    public function handle(ProcurementAlertService $alerts): void
    {
        $days = (int) SystemSetting::get('procurement', 'price_list_expiry_alert_days', 30);
        $threshold = now()->addDays($days)->toDateString();

        $expiring = SupplierPriceList::query()
            ->where('is_active', true)
            ->whereNotNull('valid_to')
            ->whereDate('valid_to', '<=', $threshold)
            ->whereDate('valid_to', '>=', now()->toDateString())
            ->with('supplier')
            ->get();

        foreach ($expiring as $list) {
            Log::warning('Supplier price list expiring soon.', [
                'price_list_id' => $list->id,
                'supplier' => $list->supplier?->name,
                'valid_to' => $list->valid_to?->toDateString(),
            ]);

            $dedupeKey = 'price_list_expiry:'.$list->id.':'.now()->toDateString();

            $alerts->notifyUsersWithPermission(
                'procurement.manage-suppliers',
                null,
                'price_list_expiry',
                $dedupeKey,
                __('Supplier price list expiring'),
                __('Price list :name for :supplier expires on :date.', [
                    'name' => $list->name,
                    'supplier' => $list->supplier?->name ?? __('Unknown supplier'),
                    'date' => $list->valid_to?->toDateString() ?? '—',
                ]),
                'admin.supplier-price-lists.edit',
                ['supplier_price_list' => $list->id],
            );
        }
    }
}
