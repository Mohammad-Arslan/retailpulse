<?php

declare(strict_types=1);

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            'reservation_ttl_minutes' => (string) config('inventory.reservation_ttl_minutes', 30),
            'count_variance_threshold_pct' => (string) config('inventory.count_variance_threshold_pct', 5),
            'count_variance_threshold_value' => (string) config('inventory.count_variance_threshold_value', 1000),
        ];

        foreach ($defaults as $key => $value) {
            SystemSetting::query()->updateOrCreate(
                ['group' => 'inventory', 'key' => $key],
                ['value' => $value],
            );
        }
    }

    public function down(): void
    {
        SystemSetting::query()->where('group', 'inventory')->delete();
    }
};
