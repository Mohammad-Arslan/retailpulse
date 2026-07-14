<?php

declare(strict_types=1);

use App\Models\AttendanceSource;
use Illuminate\Database\Migrations\Migration;

/**
 * Idempotent upsert of default Phase 12 attendance sources for existing installs.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->defaults() as $source) {
            AttendanceSource::query()->firstOrCreate(
                [
                    'driver' => $source['driver'],
                    'branch_id' => null,
                ],
                [
                    'name' => $source['name'],
                    'config_json' => $source['config_json'],
                    'status' => $source['status'],
                ],
            );
        }
    }

    public function down(): void
    {
        AttendanceSource::query()
            ->whereNull('branch_id')
            ->whereIn('driver', array_column($this->defaults(), 'driver'))
            ->delete();
    }

    /**
     * @return list<array{driver: string, name: string, config_json: array<string, mixed>, status: string}>
     */
    private function defaults(): array
    {
        return [
            [
                'driver' => 'pos_pin',
                'name' => 'POS PIN',
                'config_json' => [],
                'status' => 'active',
            ],
            [
                'driver' => 'manual',
                'name' => 'Manual Entry',
                'config_json' => [],
                'status' => 'active',
            ],
            [
                'driver' => 'biometric',
                'name' => 'Biometric Device',
                'config_json' => [],
                'status' => 'inactive',
            ],
            [
                'driver' => 'mobile',
                'name' => 'Mobile App',
                'config_json' => [],
                'status' => 'inactive',
            ],
            [
                'driver' => 'import',
                'name' => 'Bulk Import',
                'config_json' => [],
                'status' => 'inactive',
            ],
        ];
    }
};
