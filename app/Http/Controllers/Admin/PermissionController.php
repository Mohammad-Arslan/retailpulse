<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Permission\CreatePermissionData;
use App\DTOs\Permission\UpdatePermissionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePermissionRequest;
use App\Http\Requests\Admin\UpdatePermissionRequest;
use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class PermissionController extends Controller
{
    public function __construct(
        private readonly PermissionRepositoryInterface $permissions,
        private readonly PermissionService $permissionService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Permission::class);

        $grouped = $this->permissions->allGrouped();

        return Inertia::render('Admin/Permissions/Index', [
            'permissionGroups' => $grouped->map(fn ($group, $key) => [
                'group' => $key,
                'permissions' => $group->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'description' => $p->description,
                ])->values(),
            ])->values(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Permission::class);

        return Inertia::render('Admin/Permissions/Create');
    }

    public function store(StorePermissionRequest $request): RedirectResponse
    {
        $this->authorize('create', Permission::class);

        $this->permissionService->create(CreatePermissionData::fromRequest($request));

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', __('Permission created successfully.'));
    }

    public function edit(Permission $permission): Response
    {
        $this->authorize('update', $permission);

        return Inertia::render('Admin/Permissions/Edit', [
            'permission' => [
                'id' => $permission->id,
                'name' => $permission->name,
                'group' => $permission->group,
                'description' => $permission->description,
            ],
        ]);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): RedirectResponse
    {
        $this->authorize('update', $permission);

        $this->permissionService->update($permission, UpdatePermissionData::fromRequest($request));

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', __('Permission updated successfully.'));
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $this->authorize('delete', $permission);

        $this->permissionService->delete($permission);

        return redirect()
            ->route('admin.permissions.index')
            ->with('success', __('Permission deleted successfully.'));
    }
}
