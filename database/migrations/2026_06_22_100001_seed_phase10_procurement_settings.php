<?php

declare(strict_types=1);

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * @var array<int, array{0: string, 1: string, 2: mixed, 3: string}>
     */
    private const SETTINGS = [
        ['procurement', 'po_approval_threshold', 5000, 'integer'],
        ['procurement', 'po_approval_escalation_hours', 24, 'integer'],
        ['procurement', 'matching_price_tolerance_percent', 2, 'integer'],
        ['procurement', 'matching_quantity_tolerance_percent', 0, 'integer'],
        ['procurement', 'allow_partial_receive', true, 'boolean'],
        ['procurement', 'allow_over_receive', false, 'boolean'],
        ['procurement', 'auto_close_po', true, 'boolean'],
        ['procurement', 'default_currency', 'USD', 'string'],
        ['procurement', 'po_number_prefix', 'PO', 'string'],
        ['procurement', 'grn_number_prefix', 'GRN', 'string'],
        ['procurement', 'invoice_number_prefix', 'SINV', 'string'],
        ['procurement', 'payment_number_prefix', 'SPAY', 'string'],
        ['procurement', 'return_number_prefix', 'PR', 'string'],
        ['procurement', 'debit_note_prefix', 'DN', 'string'],
        ['procurement', 'supplier_code_format', 'SUP-{seq:6}', 'string'],
        ['procurement', 'price_list_expiry_alert_days', 30, 'integer'],
        ['procurement', 'payment_method_cash', true, 'boolean'],
        ['procurement', 'payment_method_bank_transfer', true, 'boolean'],
        ['procurement', 'payment_method_cheque', true, 'boolean'],
        ['procurement', 'payment_method_card', false, 'boolean'],
        ['procurement', 'landed_cost_charge_types', '["freight","duty","insurance","customs","handling","other"]', 'json'],
        ['procurement', 'landed_cost_allocation_methods', '["quantity","weight","value","manual"]', 'json'],
        ['procurement', 'performance_on_time_weight', 40, 'integer'],
        ['procurement', 'performance_quality_weight', 30, 'integer'],
        ['procurement', 'performance_lead_time_weight', 30, 'integer'],
        ['feature_flags', 'procurement.workflow_approval', false, 'boolean'],
    ];

    public function up(): void
    {
        foreach (self::SETTINGS as [$group, $key, $value, $type]) {
            $exists = SystemSetting::query()
                ->where('group', $group)
                ->where('key', $key)
                ->exists();

            if ($exists) {
                continue;
            }

            SystemSetting::query()->create([
                'group' => $group,
                'key' => $key,
                'value' => $this->serialize($value, $type),
                'type' => $type,
                'updated_by' => null,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (self::SETTINGS as [$group, $key]) {
            SystemSetting::query()
                ->where('group', $group)
                ->where('key', $key)
                ->delete();
        }
    }

    private function serialize(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean' => $value ? 'true' : 'false',
            'json' => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };
    }
};
