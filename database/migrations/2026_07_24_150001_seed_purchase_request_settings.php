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
        ['procurement', 'pr_approval_threshold', 5000, 'integer'],
        ['procurement', 'pr_number_prefix', 'PREQ', 'string'],
        ['feature_flags', 'procurement.pr_workflow_approval', false, 'boolean'],
        ['feature_flags', 'procurement.purchase_requests', true, 'boolean'],
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
