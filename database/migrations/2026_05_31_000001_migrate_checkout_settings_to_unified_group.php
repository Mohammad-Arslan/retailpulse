<?php

declare(strict_types=1);

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Maps old (group, key) → new checkout.{key}
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    private const MOVES = [
        // old_group, old_key, new_key, type
        ['cash_change', 'enabled', 'cash_change_enabled', 'boolean'],
        ['cash_change', 'rounding_mode', 'cash_change_rounding_mode', 'string'],
        ['split_tender', 'enabled', 'split_tender_enabled', 'boolean'],
        ['layaway', 'enabled', 'layaway_enabled', 'boolean'],
        ['layaway', 'min_deposit_percent', 'layaway_min_deposit_percent', 'string'],
        ['layaway', 'max_balance_days', 'layaway_max_balance_days', 'integer'],
        ['invoice', 'number_prefix', 'invoice_number_prefix', 'string'],
        ['invoice', 'number_digits', 'invoice_number_digits', 'integer'],
        ['invoice', 'default_template', 'default_invoice_template', 'string'],
        ['inventory', 'deduct_on', 'inventory_deduct_on', 'string'],
    ];

    // Also rename fbr.password_hash → fbr.password
    public function up(): void
    {
        foreach (self::MOVES as [$oldGroup, $oldKey, $newKey]) {
            $old = SystemSetting::query()
                ->where('group', $oldGroup)
                ->where('key', $oldKey)
                ->first();

            if ($old === null) {
                continue;
            }

            SystemSetting::query()->updateOrCreate(
                ['group' => 'checkout', 'key' => $newKey],
                ['value' => $old->value, 'type' => $old->type, 'updated_by' => $old->updated_by, 'updated_at' => now()],
            );

            $old->delete();
        }

        // Rename fbr.password_hash → fbr.password
        $fbrPass = SystemSetting::query()
            ->where('group', 'fbr')
            ->where('key', 'password_hash')
            ->first();

        if ($fbrPass !== null) {
            SystemSetting::query()->updateOrCreate(
                ['group' => 'fbr', 'key' => 'password'],
                ['value' => $fbrPass->value, 'type' => 'encrypted', 'updated_by' => null, 'updated_at' => now()],
            );
            $fbrPass->delete();
        }
    }

    public function down(): void
    {
        foreach (self::MOVES as [$oldGroup, $oldKey, $newKey, $type]) {
            $new = SystemSetting::query()
                ->where('group', 'checkout')
                ->where('key', $newKey)
                ->first();

            if ($new === null) {
                continue;
            }

            SystemSetting::query()->updateOrCreate(
                ['group' => $oldGroup, 'key' => $oldKey],
                ['value' => $new->value, 'type' => $type, 'updated_by' => null, 'updated_at' => now()],
            );

            $new->delete();
        }

        $fbrPass = SystemSetting::query()
            ->where('group', 'fbr')
            ->where('key', 'password')
            ->first();

        if ($fbrPass !== null) {
            SystemSetting::query()->updateOrCreate(
                ['group' => 'fbr', 'key' => 'password_hash'],
                ['value' => $fbrPass->value, 'type' => 'encrypted', 'updated_by' => null, 'updated_at' => now()],
            );
            $fbrPass->delete();
        }
    }
};
