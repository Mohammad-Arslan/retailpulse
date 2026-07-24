<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\SystemSetting;
use App\Support\BranchOperationalOptions;

final class ProcurementConfigService
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(?int $branchId = null, ?int $tenantId = null): array
    {
        return [
            'po_approval_threshold' => $this->resolveThreshold($branchId, $tenantId),
            'pr_approval_threshold' => $this->resolvePrThreshold($branchId, $tenantId),
            'po_approval_escalation_hours' => (int) SystemSetting::get('procurement', 'po_approval_escalation_hours', 24),
            'matching_price_tolerance_percent' => (float) SystemSetting::get('procurement', 'matching_price_tolerance_percent', 2),
            'matching_quantity_tolerance_percent' => (float) SystemSetting::get('procurement', 'matching_quantity_tolerance_percent', 0),
            'allow_partial_receive' => (bool) SystemSetting::get('procurement', 'allow_partial_receive', true),
            'allow_over_receive' => (bool) SystemSetting::get('procurement', 'allow_over_receive', false),
            'auto_close_po' => (bool) SystemSetting::get('procurement', 'auto_close_po', true),
            'default_currency' => BranchOperationalOptions::normalizeCurrency(
                (string) SystemSetting::get(
                    'procurement',
                    'default_currency',
                    (string) SystemSetting::get('general', 'default_currency', 'USD'),
                ),
            ),
            'payment_methods' => $this->enabledPaymentMethods(),
            'landed_cost_charge_types' => $this->jsonSetting('landed_cost_charge_types', ['freight', 'duty', 'insurance', 'customs', 'handling', 'other']),
            'landed_cost_allocation_methods' => $this->jsonSetting('landed_cost_allocation_methods', ['quantity', 'weight', 'value', 'manual']),
            'workflow_approval_enabled' => (bool) SystemSetting::get('feature_flags', 'procurement.workflow_approval', false),
            'pr_workflow_approval_enabled' => (bool) SystemSetting::get('feature_flags', 'procurement.pr_workflow_approval', false),
            'purchase_requests_enabled' => (bool) SystemSetting::get('feature_flags', 'procurement.purchase_requests', true),
            'performance_on_time_weight' => (int) SystemSetting::get('procurement', 'performance_on_time_weight', 40),
            'performance_quality_weight' => (int) SystemSetting::get('procurement', 'performance_quality_weight', 30),
            'performance_lead_time_weight' => (int) SystemSetting::get('procurement', 'performance_lead_time_weight', 30),
        ];
    }

    public function purchaseRequestsEnabled(): bool
    {
        return (bool) SystemSetting::get('feature_flags', 'procurement.purchase_requests', true);
    }

    public function requiresApproval(float $total, ?int $branchId = null, ?int $tenantId = null): bool
    {
        $threshold = $this->resolveThreshold($branchId, $tenantId);

        return $total > $threshold;
    }

    public function requiresPrApproval(float $total, ?int $branchId = null, ?int $tenantId = null): bool
    {
        $threshold = $this->resolvePrThreshold($branchId, $tenantId);

        return $total > $threshold;
    }

    private function resolveThreshold(?int $branchId, ?int $tenantId): float
    {
        // Future: branch/tenant overrides via Phase 23 ConfigService
        return (float) SystemSetting::get('procurement', 'po_approval_threshold', 5000);
    }

    private function resolvePrThreshold(?int $branchId, ?int $tenantId): float
    {
        // Future: branch/tenant overrides via Phase 23 ConfigService
        return (float) SystemSetting::get('procurement', 'pr_approval_threshold', 5000);
    }

    /**
     * @return list<string>
     */
    private function enabledPaymentMethods(): array
    {
        $methods = [];
        $keys = [
            'cash' => 'payment_method_cash',
            'bank_transfer' => 'payment_method_bank_transfer',
            'cheque' => 'payment_method_cheque',
            'card' => 'payment_method_card',
        ];

        foreach ($keys as $method => $settingKey) {
            if ((bool) SystemSetting::get('procurement', $settingKey, $method !== 'card')) {
                $methods[] = $method;
            }
        }

        return $methods !== [] ? $methods : ['cash', 'bank_transfer'];
    }

    /**
     * @param  list<string>  $default
     * @return list<string>
     */
    private function jsonSetting(string $key, array $default): array
    {
        $value = SystemSetting::get('procurement', $key, $default);

        return is_array($value) ? $value : $default;
    }
}
