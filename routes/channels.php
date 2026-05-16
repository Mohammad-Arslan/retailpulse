<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\ImportExportJob;
use App\Models\User;
use App\Services\BranchContextService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('import-job.{ulid}', function (User $user, string $ulid): bool {
    $job = ImportExportJob::query()->byUlid($ulid)->first();

    return $job !== null && (int) $user->tenant_id === (int) $job->tenant_id;
});

Broadcast::channel('user.{userId}.import-jobs', function (User $user, string $userId): bool {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('admin.{userId}', function (User $user, string $userId): bool|array {
    if ((int) $user->id !== (int) $userId) {
        return false;
    }

    if (! $user->can('admin.access')) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->name];
});

Broadcast::channel('branch.{branchId}', function (User $user, string $branchId): bool|array {
    if (! $user->can('admin.access')) {
        return false;
    }

    $branchId = (int) $branchId;

    if (! Branch::query()->whereKey($branchId)->exists()) {
        return false;
    }

    $service = app(BranchContextService::class);
    $accessibleIds = $service->accessibleBranchIds($user);

    if ($accessibleIds === null) {
        return ['id' => $user->id];
    }

    return in_array($branchId, $accessibleIds, true)
        ? ['id' => $user->id]
        : false;
});
