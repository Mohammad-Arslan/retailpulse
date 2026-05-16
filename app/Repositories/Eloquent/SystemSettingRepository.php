<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\SystemSetting;
use App\Repositories\Contracts\SystemSettingRepositoryInterface;
use Illuminate\Support\Collection;

final class SystemSettingRepository implements SystemSettingRepositoryInterface
{
    public function forGroup(string $group): Collection
    {
        return SystemSetting::query()
            ->where('group', $group)
            ->orderBy('key')
            ->get();
    }

    public function keyedForGroup(string $group): array
    {
        return $this->forGroup($group)
            ->keyBy('key')
            ->all();
    }
}
