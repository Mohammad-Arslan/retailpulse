<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Enums\TaxMode;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SystemSetting;

final class TaxCalculationService
{
    /**
     * @return array{
     *     tax_rate: float,
     *     tax_amount: float,
     *     line_total_inc_tax: float
     * }
     */
    public function computeLineTax(
        float $lineTotal,
        int $productId,
        ?int $variantId,
        ?TaxMode $taxMode = null,
    ): array {
        if (! (bool) SystemSetting::get('tax', 'enabled', true)) {
            return [
                'tax_rate' => 0.0,
                'tax_amount' => 0.0,
                'line_total_inc_tax' => round($lineTotal, 2),
            ];
        }

        $mode = $taxMode ?? TaxMode::tryFrom((string) SystemSetting::get('tax', 'mode', 'exclusive'))
            ?? TaxMode::Exclusive;
        $rate = $this->resolveTaxRate($productId, $variantId);
        $rounding = (string) SystemSetting::get('tax', 'rounding', 'half_up');

        if ($mode === TaxMode::Exclusive) {
            $taxAmount = $this->round($lineTotal * $rate, $rounding);

            return [
                'tax_rate' => $rate,
                'tax_amount' => $taxAmount,
                'line_total_inc_tax' => round($lineTotal + $taxAmount, 2),
            ];
        }

        $taxAmount = $this->round($lineTotal - ($lineTotal / (1 + $rate)), $rounding);

        return [
            'tax_rate' => $rate,
            'tax_amount' => $taxAmount,
            'line_total_inc_tax' => round($lineTotal, 2),
        ];
    }

    public function resolveTaxRate(int $productId, ?int $variantId): float
    {
        if ((bool) SystemSetting::get('fbr', 'enabled', false)) {
            return (float) SystemSetting::get('fbr', 'gst_rate', 0.16);
        }

        if ($variantId !== null) {
            $variant = ProductVariant::query()->find($variantId);
            if ($variant?->tax_rate !== null) {
                return (float) $variant->tax_rate;
            }
        }

        $product = Product::query()->with('category')->find($productId);

        if ($product?->tax_rate !== null) {
            return (float) $product->tax_rate;
        }

        if ($product?->category_id !== null) {
            $category = Category::query()->find($product->category_id);
            if ($category?->tax_rate !== null) {
                return (float) $category->tax_rate;
            }
        }

        return (float) SystemSetting::get('tax', 'default_rate', 0);
    }

    private function round(float $value, string $mode): float
    {
        return match ($mode) {
            'truncate' => floor($value * 100) / 100,
            'half_even' => round($value, 2, PHP_ROUND_HALF_EVEN),
            default => round($value, 2, PHP_ROUND_HALF_UP),
        };
    }
}
