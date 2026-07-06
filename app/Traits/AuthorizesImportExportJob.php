<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\ImportExportJob;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait AuthorizesImportExportJob
{
    protected function findOwnedJob(Request $request, string $ulid): ImportExportJob
    {
        $job = ImportExportJob::query()
            ->forCurrentTenant()
            ->byUlid($ulid)
            ->firstOrFail();

        $this->assertJobOwnership($job, $request->user());

        return $job;
    }

    protected function assertJobOwnership(ImportExportJob $job, ?User $user): void
    {
        if ($user === null || $job->user_id !== $user->id) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }
}
