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
        ['checkout', 'payment_method_wallet', true, 'boolean'],
        ['checkout', 'payment_method_store_credit', true, 'boolean'],
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
                'value' => $value ? 'true' : 'false',
                'type' => $type,
                'updated_by' => null,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (self::SETTINGS as [$group, $key]) {
            SystemSetting::query()->where('group', $group)->where('key', $key)->delete();
        }
    }
};
