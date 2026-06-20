<?php

declare(strict_types=1);

use App\Models\LoyaltyTier;
use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * @var list<array{name: string, slug: string, multiplier: float, min_points: int, sort: int}>
     */
    private const TIERS = [
        ['name' => 'Bronze', 'slug' => 'bronze', 'multiplier' => 1.0, 'min_points' => 0, 'sort' => 1],
        ['name' => 'Silver', 'slug' => 'silver', 'multiplier' => 1.25, 'min_points' => 500, 'sort' => 2],
        ['name' => 'Gold', 'slug' => 'gold', 'multiplier' => 1.5, 'min_points' => 2000, 'sort' => 3],
        ['name' => 'Platinum', 'slug' => 'platinum', 'multiplier' => 2.0, 'min_points' => 5000, 'sort' => 4],
    ];

    /**
     * @var array<int, array{0: string, 1: string, 2: mixed, 3: string}>
     */
    private const SETTINGS = [
        ['customers', 'wallet_expiry_days', '0', 'integer'],
        ['customers', 'loyalty_points_per_100', '1', 'integer'],
        ['customers', 'ar_reminder_days', [7, 30, 60], 'json'],
        ['customers', 'loyalty_auto_tier', 'true', 'boolean'],
    ];

    public function up(): void
    {
        foreach (self::TIERS as $tier) {
            LoyaltyTier::query()->firstOrCreate(
                ['slug' => $tier['slug']],
                [
                    'name' => $tier['name'],
                    'points_multiplier' => $tier['multiplier'],
                    'min_points' => $tier['min_points'],
                    'auto_upgrade' => true,
                    'sort_order' => $tier['sort'],
                    'is_active' => true,
                ],
            );
        }

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
        LoyaltyTier::query()->whereIn('slug', array_column(self::TIERS, 'slug'))->delete();

        foreach (self::SETTINGS as [$group, $key]) {
            SystemSetting::query()->where('group', $group)->where('key', $key)->delete();
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
