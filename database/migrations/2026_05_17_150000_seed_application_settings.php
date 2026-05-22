<?php

declare(strict_types=1);

use App\Models\SystemSetting;
use App\Support\Settings\SettingFieldType;
use App\Support\Settings\SettingGroupRegistry;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['general', 'company', 'notifications'] as $group) {
            if (! SettingGroupRegistry::exists($group)) {
                continue;
            }

            foreach (SettingGroupRegistry::fields($group) as $fieldKey => $fieldDef) {
                $exists = SystemSetting::query()
                    ->where('group', $group)
                    ->where('key', $fieldKey)
                    ->exists();

                if ($exists) {
                    continue;
                }

                SystemSetting::query()->create([
                    'group' => $group,
                    'key' => $fieldKey,
                    'value' => $this->serializeDefault($fieldDef),
                    'type' => SettingFieldType::storageType((string) $fieldDef['type']),
                    'updated_by' => null,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        SystemSetting::query()
            ->whereIn('group', ['general', 'company', 'notifications'])
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $fieldDef
     */
    private function serializeDefault(array $fieldDef): string
    {
        $default = $fieldDef['default'] ?? '';
        $type = SettingFieldType::storageType((string) $fieldDef['type']);

        return match ($type) {
            'boolean' => $default ? 'true' : 'false',
            'integer' => (string) (int) $default,
            'json' => json_encode($default, JSON_THROW_ON_ERROR),
            default => (string) $default,
        };
    }
};
