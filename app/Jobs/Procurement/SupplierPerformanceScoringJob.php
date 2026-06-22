<?php

declare(strict_types=1);

namespace App\Jobs\Procurement;

use App\Models\Supplier;
use App\Models\SupplierPerformanceScore;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class SupplierPerformanceScoringJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $periodEnd = now()->startOfMonth()->subDay();
        $periodStart = $periodEnd->copy()->startOfMonth();

        $onTimeWeight = (int) SystemSetting::get('procurement', 'performance_on_time_weight', 40);
        $qualityWeight = (int) SystemSetting::get('procurement', 'performance_quality_weight', 30);
        $leadTimeWeight = (int) SystemSetting::get('procurement', 'performance_lead_time_weight', 30);

        Supplier::query()->where('is_active', true)->chunkById(50, function ($suppliers) use (
            $periodStart, $periodEnd, $onTimeWeight, $qualityWeight, $leadTimeWeight
        ) {
            foreach ($suppliers as $supplier) {
                $metrics = $this->calculateMetrics($supplier->id, $periodStart->toDateString(), $periodEnd->toDateString());

                $score = round(
                    ($metrics['on_time_rate'] * $onTimeWeight / 100)
                    + ((100 - $metrics['rejection_rate']) * $qualityWeight / 100)
                    + (max(0, 100 - $metrics['avg_lead_time']) * $leadTimeWeight / 100),
                    2,
                );

                SupplierPerformanceScore::query()->create([
                    'supplier_id' => $supplier->id,
                    'tenant_id' => $supplier->tenant_id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'on_time_delivery_rate' => $metrics['on_time_rate'],
                    'quality_rejection_rate' => $metrics['rejection_rate'],
                    'average_lead_time_days' => $metrics['avg_lead_time'],
                    'score' => $score,
                    'created_at' => now(),
                ]);

                $supplier->update([
                    'on_time_delivery_rate' => $metrics['on_time_rate'],
                    'quality_rejection_rate' => $metrics['rejection_rate'],
                    'last_scored_at' => now(),
                ]);
            }
        });
    }

    /**
     * @return array{on_time_rate: float, rejection_rate: float, avg_lead_time: float}
     */
    private function calculateMetrics(int $supplierId, string $from, string $to): array
    {
        $grns = DB::table('goods_receiving_notes as g')
            ->join('purchase_orders as po', 'po.id', '=', 'g.purchase_order_id')
            ->where('g.supplier_id', $supplierId)
            ->where('g.status', 'posted')
            ->whereBetween('g.received_at', [$from, $to.' 23:59:59'])
            ->select('g.received_at', 'po.expected_delivery_date')
            ->get();

        $total = $grns->count();
        $onTime = 0;
        $leadDays = [];

        foreach ($grns as $grn) {
            if ($grn->expected_delivery_date !== null && $grn->received_at !== null) {
                $received = Carbon::parse($grn->received_at)->startOfDay();
                $expected = Carbon::parse($grn->expected_delivery_date)->startOfDay();
                if ($received->lte($expected)) {
                    $onTime++;
                }
                $leadDays[] = $received->diffInDays($expected, false);
            }
        }

        $returnCount = DB::table('purchase_returns')
            ->where('supplier_id', $supplierId)
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->count();

        return [
            'on_time_rate' => $total > 0 ? round($onTime / $total * 100, 2) : 100.0,
            'rejection_rate' => $total > 0 ? round($returnCount / $total * 100, 2) : 0.0,
            'avg_lead_time' => $leadDays !== [] ? round(array_sum($leadDays) / count($leadDays), 2) : 0.0,
        ];
    }
}
