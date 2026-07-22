<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\User\BranchAssignmentData;
use App\DTOs\User\CreateUserData;
use App\DTOs\User\UpdateUserData;
use App\Models\Employee;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly BranchService $branches,
        private readonly PosPinService $posPin,
    ) {}

    public function create(User $actor, CreateUserData $data): User
    {
        return DB::transaction(function () use ($actor, $data) {
            $user = $this->users->create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => Hash::make($data->password),
                'phone' => $data->phone,
                'is_active' => $data->isActive,
            ]);

            if ($data->roleNames !== []) {
                $this->syncRolesIfAllowed($actor, $user, $data->roleNames);
            }

            if ($data->branchAssignments !== []) {
                $this->syncBranchesIfAllowed($actor, $user, $data->branchAssignments);
            }

            if ($data->posPin !== null && $data->posPin !== '') {
                $this->posPin->setPin($user, $data->posPin);
            }

            $this->syncEmployeeLink($user, $data->employeeId);

            return $user->load(['roles', 'branches']);
        });
    }

    public function update(User $actor, User $user, UpdateUserData $data): User
    {
        return DB::transaction(function () use ($actor, $user, $data) {
            $attributes = [
                'name' => $data->name,
                'email' => $data->email,
                'phone' => $data->phone,
                'is_active' => $data->isActive,
            ];

            if ($data->password !== null && $data->password !== '') {
                $attributes['password'] = Hash::make($data->password);
            }

            $user = $this->users->update($user, $attributes);

            if ($data->roleNames !== null) {
                $this->syncRolesIfAllowed($actor, $user, $data->roleNames);
            }

            if ($data->branchAssignments !== null) {
                $this->syncBranchesIfAllowed($actor, $user, $data->branchAssignments);
            }

            if ($data->posPin !== null && $data->posPin !== '') {
                $this->posPin->setPin($user, $data->posPin);
            } elseif ($data->clearPosPin) {
                $user->update(['pos_pin_hash' => null, 'pos_pin_updated_at' => null]);
                $this->posPin->resetLockout($user);
            }

            $this->syncEmployeeLink($user, $data->employeeId);

            return $user->load(['roles', 'branches']);
        });
    }

    /**
     * @return Collection<int, array{id: int, code: string, name: string}>
     */
    public function linkableEmployeeOptions(?User $user): Collection
    {
        return Employee::query()
            ->where(function ($query) use ($user): void {
                $query->whereNull('user_id');
                if ($user !== null) {
                    $query->orWhere('user_id', $user->id);
                }
            })
            ->orderBy('first_name')
            ->get(['id', 'employee_code', 'first_name', 'last_name'])
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'code' => $employee->employee_code,
                'name' => $employee->fullName(),
            ])
            ->values();
    }

    /**
     * One user <-> at most one employee. Unlinks the previously linked employee (if
     * changed) before linking the newly chosen one; mirrors the unique constraint the
     * employee-side form relies on so both write paths enforce the same invariant.
     */
    private function syncEmployeeLink(User $user, ?int $employeeId): void
    {
        $currentEmployee = $user->employee;

        if ($currentEmployee !== null && $currentEmployee->id !== $employeeId) {
            $currentEmployee->update(['user_id' => null]);
        }

        if ($employeeId === null) {
            return;
        }

        $employee = Employee::query()->find($employeeId);
        if ($employee === null || $employee->user_id === $user->id) {
            return;
        }

        $employee->update(['user_id' => $user->id]);
    }

    public function deactivate(User $user): void
    {
        DB::transaction(fn () => $this->users->update($user, ['is_active' => false]));
    }

    public function recordSuccessfulLogin(User $user, string $ip): void
    {
        $this->users->update($user, [
            'last_login_at' => now(),
            'last_login_ip' => $ip,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    public function recordFailedLogin(User $user): void
    {
        $attempts = $user->failed_login_attempts + 1;
        $attributes = ['failed_login_attempts' => $attempts];

        if ($attempts >= 5) {
            $attributes['locked_until'] = now()->addMinutes(15);
        }

        $this->users->update($user, $attributes);
    }

    /**
     * @param  list<string>  $roleNames
     */
    private function syncRolesIfAllowed(User $actor, User $user, array $roleNames): void
    {
        if (! $actor->can('users.assign-roles')) {
            throw new AuthorizationException(__('You are not allowed to assign roles.'));
        }

        $user->syncRoles($roleNames);
    }

    /**
     * @param  list<array{branch_id: int, is_primary: bool}>  $branchAssignments
     */
    private function syncBranchesIfAllowed(User $actor, User $user, array $branchAssignments): void
    {
        if (! $actor->can('users.assign-branches')) {
            throw new AuthorizationException(__('You are not allowed to assign branches.'));
        }

        $this->branches->syncUserBranches(
            $user,
            new BranchAssignmentData($branchAssignments),
        );
    }
}
