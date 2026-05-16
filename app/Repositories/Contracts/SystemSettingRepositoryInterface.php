<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\SystemSetting;
use Illuminate\Support\Collection;

interface SystemSettingRepositoryInterface
{
    /**
     * @return Collection<int, SystemSetting>
     */
    public function forGroup(string $group): Collection;

    /**
     * @return array<string, SystemSetting>
     */
    public function keyedForGroup(string $group): array;
}
