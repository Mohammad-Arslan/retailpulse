<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Enums\PaymentMethod;
use App\Models\PaymentGatewayConfig;
use App\Models\SystemSetting;

final class CheckoutConfigService
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(int $branchId): array
    {
        $paymentMethods = $this->enabledPaymentMethods($branchId);

        return [
            'tax_mode' => (string) SystemSetting::get('tax', 'mode', 'exclusive'),
            'default_tax_rate' => number_format((float) SystemSetting::get('tax', 'default_rate', 0), 2, '.', ''),
            'tax_enabled' => (bool) SystemSetting::get('tax', 'enabled', true),
            'cash_change_enabled' => (bool) SystemSetting::get('checkout', 'cash_change_enabled', true),
            'layaway_enabled' => (bool) SystemSetting::get('checkout', 'layaway_enabled', false),
            'split_tender_enabled' => (bool) SystemSetting::get('checkout', 'split_tender_enabled', true),
            'fbr_enabled' => (bool) SystemSetting::get('fbr', 'enabled', false),
            'payment_methods' => $paymentMethods,
            'invoice_templates' => ['thermal_80mm', 'a4'],
            'default_invoice_template' => (string) SystemSetting::get('checkout', 'default_invoice_template', 'a4'),
            'receipt_print_mode' => (string) SystemSetting::get('checkout', 'receipt_print_mode', 'manual'),
            'invoice_number_prefix' => (string) SystemSetting::get('checkout', 'invoice_number_prefix', 'INV'),
            'invoice_number_digits' => (int) SystemSetting::get('checkout', 'invoice_number_digits', 8),
            'max_layaway_balance_days' => (int) SystemSetting::get('checkout', 'layaway_max_balance_days', 30),
            'layaway_min_deposit_percent' => (float) SystemSetting::get('checkout', 'layaway_min_deposit_percent', 0),
            'change_rounding_mode' => (string) SystemSetting::get('checkout', 'cash_change_rounding_mode', 'none'),
            'inventory_deduct_on' => (string) SystemSetting::get('checkout', 'inventory_deduct_on', 'sale_completed'),
            'currency' => (string) SystemSetting::get('general', 'currency', 'PKR'),
        ];
    }

    /**
     * @return list<string>
     */
    private function enabledPaymentMethods(int $branchId): array
    {
        $methodKeys = [
            'cash' => 'payment_method_cash',
            'card' => 'payment_method_card',
            'mobile_wallet' => 'payment_method_mobile_wallet',
            'bank_transfer' => 'payment_method_bank_transfer',
            'credit' => 'payment_method_credit',
            'wallet' => 'payment_method_wallet',
            'store_credit' => 'payment_method_store_credit',
        ];

        $defaults = ['cash', 'card', 'mobile_wallet', 'bank_transfer'];
        $configured = [];

        foreach ($methodKeys as $method => $settingKey) {
            $default = in_array($method, $defaults, true);
            if ((bool) SystemSetting::get('checkout', $settingKey, $default)) {
                $configured[] = $method;
            }
        }

        if ($configured === []) {
            /** @var list<string> $legacy */
            $legacy = SystemSetting::get('payment_methods', 'enabled', ['cash']);
            $configured = $legacy;
        }

        $methods = [];

        foreach ($configured as $method) {
            if ($method === PaymentMethod::Card->value || $method === PaymentMethod::MobileWallet->value) {
                if ($this->gatewayAvailable($branchId, $method)) {
                    $methods[] = $method;
                }

                continue;
            }

            $methods[] = $method;
        }

        return array_values(array_unique($methods));
    }

    private function gatewayAvailable(int $branchId, string $method): bool
    {
        $gateway = match ($method) {
            PaymentMethod::Card->value => 'stripe',
            PaymentMethod::MobileWallet->value => 'jazzcash',
            default => null,
        };

        if ($gateway === null) {
            return true;
        }

        $config = PaymentGatewayConfig::query()
            ->where('gateway', $gateway)
            ->where(function ($query) use ($branchId) {
                $query->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->orderByRaw('branch_id IS NULL')
            ->first();

        if ($config === null) {
            return true;
        }

        return $config->mode !== 'disabled';
    }
}
