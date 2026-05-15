<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class DashboardService
{
    /**
     * @return array{
     *     users: int,
     *     roles: int,
     *     permissions: int,
     *     active_users: int,
     *     inactive_users: int,
     * }
     */
    public function stats(): array
    {
        $users = $this->modelCount(User::class);
        $activeUsers = (int) User::query()->where('is_active', true)->toBase()->count('*');

        return [
            'users' => $users,
            'roles' => $this->modelCount(Role::class),
            'permissions' => $this->modelCount(Permission::class),
            'active_users' => $activeUsers,
            'inactive_users' => max(0, $users - $activeUsers),
        ];
    }

    /**
     * @return array{
     *     user_growth: list<array{label: string, count: int}>,
     *     users_by_role: list<array{role: string, count: int}>,
     *     permissions_by_group: list<array{group: string, count: int}>,
     *     user_status: list<array{status: string, count: int}>,
     * }
     */
    public function charts(): array
    {
        return [
            'user_growth' => $this->userGrowthSeries(),
            'users_by_role' => $this->usersByRoleSeries(),
            'permissions_by_group' => $this->permissionsByGroupSeries(),
            'user_status' => $this->userStatusSeries(),
        ];
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    private function userGrowthSeries(int $days = 7): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $counts = User::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(0, $days - 1))
            ->map(function (int $offset) use ($start, $counts): array {
                $date = $start->copy()->addDays($offset);
                $key = $date->toDateString();

                return [
                    'label' => $date->format('M j'),
                    'count' => (int) ($counts[$key] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * @return list<array{role: string, count: int}>
     */
    private function usersByRoleSeries(): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->withCount('users')
            ->orderByDesc('users_count')
            ->get()
            ->map(fn (Role $role): array => [
                'role' => $role->name,
                'count' => (int) $role->users_count,
            ])
            ->all();
    }

    /**
     * @return list<array{group: string, count: int}>
     */
    private function permissionsByGroupSeries(): array
    {
        return Permission::query()
            ->select([
                DB::raw("COALESCE(NULLIF(`group`, ''), 'General') as permission_group"),
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('permission_group')
            ->orderBy('permission_group')
            ->get()
            ->map(fn (object $row): array => [
                'group' => (string) $row->permission_group,
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return list<array{status: string, count: int}>
     */
    private function userStatusSeries(): array
    {
        $active = (int) User::query()->where('is_active', true)->toBase()->count('*');
        $inactive = (int) User::query()->where('is_active', false)->toBase()->count('*');

        return [
            ['status' => 'Active', 'count' => $active],
            ['status' => 'Inactive', 'count' => $inactive],
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function modelCount(string $modelClass): int
    {
        /** @var Model $model */
        $model = new $modelClass;

        return (int) $model->newQuery()->toBase()->count('*');
    }
}
