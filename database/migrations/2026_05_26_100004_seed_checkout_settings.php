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
        // Tax group
        ['tax', 'enabled', true, 'boolean'],
        ['tax', 'mode', 'exclusive', 'string'],
        ['tax', 'default_rate', '0.16', 'string'],
        ['tax', 'per_item', true, 'boolean'],
        ['tax', 'rounding', 'half_up', 'string'],
        // Checkout group (unified)
        ['checkout', 'cash_change_enabled', true, 'boolean'],
        ['checkout', 'cash_change_rounding_mode', 'none', 'string'],
        ['checkout', 'split_tender_enabled', true, 'boolean'],
        ['checkout', 'layaway_enabled', false, 'boolean'],
        ['checkout', 'layaway_min_deposit_percent', '0', 'string'],
        ['checkout', 'layaway_max_balance_days', '30', 'integer'],
        ['checkout', 'invoice_number_prefix', 'INV', 'string'],
        ['checkout', 'invoice_number_digits', '8', 'integer'],
        ['checkout', 'invoice_sequence_scope', 'branch', 'string'],
        ['checkout', 'default_invoice_template', 'a4', 'string'],
        ['checkout', 'receipt_print_mode', 'manual', 'string'],
        ['checkout', 'inventory_deduct_on', 'sale_completed', 'string'],
        // FBR group
        ['fbr', 'enabled', false, 'boolean'],
        ['fbr', 'iris_endpoint', '', 'string'],
        ['fbr', 'pos_id', '', 'string'],
        ['fbr', 'user_id', '', 'string'],
        ['fbr', 'password', '', 'encrypted'],
        ['fbr', 'gst_rate', '0.16', 'string'],
        ['fbr', 'failure_mode', 'queue', 'string'],
        ['fbr', 'retry_max_attempts', '3', 'integer'],
        ['fbr', 'retry_backoff_sec', '60', 'integer'],
        // Payment methods & general
        ['payment_methods', 'enabled', ['cash', 'card', 'mobile_wallet', 'bank_transfer'], 'json'],
        ['general', 'currency', 'PKR', 'string'],
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
            'integer' => (string) (int) $value,
            'json' => json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) $value,
        };
    }
};
