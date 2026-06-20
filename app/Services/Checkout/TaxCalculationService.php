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

    /**
     * @param  list<array{line_total: float, product_id: int, variant_id: int|null}>  $lines
     * @return list<array{
     *     tax_rate: float,
     *     tax_amount: float,
     *     line_total_inc_tax: float
     * }>
     */
    public function computeCartTax(array $lines, ?TaxMode $taxMode = null): array
    {
        if ($lines === []) {
            return [];
        }

        if (! (bool) SystemSetting::get('tax', 'enabled', true)) {
            return array_map(fn (array $line): array => [
                'tax_rate' => 0.0,
                'tax_amount' => 0.0,
                'line_total_inc_tax' => round($line['line_total'], 2),
            ], $lines);
        }

        $mode = $taxMode ?? TaxMode::tryFrom((string) SystemSetting::get('tax', 'mode', 'exclusive'))
            ?? TaxMode::Exclusive;
        $rounding = (string) SystemSetting::get('tax', 'rounding', 'half_up');
        $rate = $this->resolveCartTaxRate($lines);

        $subtotal = array_sum(array_column($lines, 'line_total'));

        if ($subtotal <= 0) {
            return array_map(fn (array $line): array => [
                'tax_rate' => $rate,
                'tax_amount' => 0.0,
                'line_total_inc_tax' => round($line['line_total'], 2),
            ], $lines);
        }

        $cartTax = $mode === TaxMode::Exclusive
            ? $this->round($subtotal * $rate, $rounding)
            : $this->round($subtotal - ($subtotal / (1 + $rate)), $rounding);

        $results = [];
        $allocated = 0.0;
        $lastIndex = count($lines) - 1;

        foreach ($lines as $index => $line) {
            $lineTotal = (float) $line['line_total'];

            if ($index === $lastIndex) {
                $taxAmount = round($cartTax - $allocated, 2);
            } else {
                $share = $lineTotal / $subtotal;
                $taxAmount = $this->round($cartTax * $share, $rounding);
                $allocated += $taxAmount;
            }

            $lineTotalIncTax = $mode === TaxMode::Exclusive
                ? round($lineTotal + $taxAmount, 2)
                : round($lineTotal, 2);

            $results[] = [
                'tax_rate' => $rate,
                'tax_amount' => $taxAmount,
                'line_total_inc_tax' => $lineTotalIncTax,
            ];
        }

        return $results;
    }

    /**
     * @param  list<array{line_total: float, product_id: int, variant_id: int|null}>  $lines
     */
    private function resolveCartTaxRate(array $lines): float
    {
        if ((bool) SystemSetting::get('fbr', 'enabled', false)) {
            return (float) SystemSetting::get('fbr', 'gst_rate', 0.16);
        }

        return (float) SystemSetting::get('tax', 'default_rate', 0);
    }

    public function isPerItem(): bool
    {
        return (bool) SystemSetting::get('tax', 'per_item', true);
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
