<?php

declare(strict_types=1);

namespace App\Jobs\Procurement;

use App\Models\SupplierPriceList;
use App\Models\SystemSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class PriceListExpiryAlertJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
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
        }
    }
}
