<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Settings\UpdateSettingsGroupData;
use App\Models\SystemSetting;
use App\Models\User;
use App\Repositories\Contracts\SystemSettingRepositoryInterface;
use App\Services\ImportExport\Storage\ImportExportStorageManager;
use App\Services\ImportExport\Storage\StorageConnectionTester;
use App\Support\Settings\SettingFieldType;
use App\Support\Settings\SettingGroupRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SystemSettingService
{
    public function __construct(
        private readonly SystemSettingRepositoryInterface $settings,
    ) {}

    /**
     * @return list<array{key: string, label: string, description: string, icon: string, can_update: bool}>
     */
    public function accessibleGroups(User $user): array
    {
        $groups = [];

        foreach (SettingGroupRegistry::all() as $key => $config) {
            $canView = $user->can('settings.view')
                || $user->can((string) $config['permission']);

            if (! $canView) {
                continue;
            }

            $groups[] = [
                'key' => $key,
                'label' => (string) $config['label'],
                'description' => (string) ($config['description'] ?? ''),
                'icon' => (string) ($config['icon'] ?? 'settings'),
                'can_update' => $user->can((string) $config['permission']),
            ];
        }

        return $groups;
    }

    /**
     * @return array{group: array<string, mixed>, fields: list<array<string, mixed>>, values: array<string, mixed>, can_update: bool}
     */
    public function groupForDisplay(string $group, User $user): array
    {
        $config = SettingGroupRegistry::get($group);
        $stored = $this->settings->keyedForGroup($group);
        $values = [];
        $fields = [];

        foreach (SettingGroupRegistry::fields($group) as $fieldKey => $fieldDef) {
            $uiType = (string) $fieldDef['type'];
            $storedRow = $stored[$fieldKey] ?? null;
            $raw = $storedRow?->value;
            $storageType = $storedRow?->type ?? SettingFieldType::storageType($uiType);

            $values[$fieldKey] = $this->displayValueFromRow(
                $uiType,
                $storageType,
                $raw,
                $fieldDef['default'] ?? null,
            );
            $fields[] = [
                'key' => $fieldKey,
                'type' => $uiType,
                'label' => (string) $fieldDef['label'],
                'description' => (string) ($fieldDef['description'] ?? ''),
                'options' => $this->selectOptions($fieldDef),
            ];
        }

        return [
            'group' => [
                'key' => $group,
                'label' => (string) $config['label'],
                'description' => (string) ($config['description'] ?? ''),
            ],
            'fields' => $fields,
            'values' => $values,
            'can_update' => $user->can((string) $config['permission']),
        ];
    }

    public function updateGroup(UpdateSettingsGroupData $data, User $user): void
    {
        $group = $data->group;
        $fieldDefs = SettingGroupRegistry::fields($group);
        $stored = $this->settings->keyedForGroup($group);

        DB::transaction(function () use ($data, $group, $fieldDefs, $stored, $user) {
            foreach ($fieldDefs as $fieldKey => $fieldDef) {
                if (! array_key_exists($fieldKey, $data->values)) {
                    continue;
                }

                $incoming = $data->values[$fieldKey];
                $uiType = (string) $fieldDef['type'];
                $storageType = SettingFieldType::storageType($uiType);

                if ($uiType === 'encrypted' && $incoming === '********') {
                    continue;
                }

                SystemSetting::set(
                    $group,
                    $fieldKey,
                    $this->normalizeIncoming($uiType, $incoming),
                    $storageType,
                );
            }

            SystemSetting::flushGroupCache($group);
        });

        if (SettingGroupRegistry::testsConnection($group)) {
            app()->forgetInstance(ImportExportStorageManager::class);
            $test = app(StorageConnectionTester::class)->test();

            if (! $test->success) {
                throw ValidationException::withMessages([
                    'values' => $test->error ?? 'Storage connection test failed.',
                ]);
            }
        }
    }

    public function ensureGroupDefaults(string $group): void
    {
        foreach (SettingGroupRegistry::fields($group) as $fieldKey => $fieldDef) {
            $exists = SystemSetting::query()
                ->where('group', $group)
                ->where('key', $fieldKey)
                ->exists();

            if ($exists) {
                continue;
            }

            $uiType = (string) $fieldDef['type'];
            $default = $fieldDef['default'] ?? null;

            SystemSetting::set($group, $fieldKey, $default, SettingFieldType::storageType($uiType));
        }
    }

    private function displayValueFromRow(
        string $uiType,
        string $storageType,
        ?string $raw,
        mixed $default,
    ): mixed {
        if ($uiType === 'encrypted') {
            return $raw !== null && $raw !== '' ? '********' : '';
        }

        if ($raw === null) {
            return $default;
        }

        return match ($storageType) {
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $raw,
            'json' => json_decode($raw, true),
            default => $raw,
        };
    }

    /**
     * @param  array<string, mixed>  $fieldDef
     * @return array<string, string>
     */
    private function selectOptions(array $fieldDef): array
    {
        $options = $fieldDef['options'] ?? [];

        return is_array($options) ? $options : [];
    }

    private function normalizeIncoming(string $type, mixed $value): mixed
    {
        if ($type === 'boolean') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $value;
    }
}
