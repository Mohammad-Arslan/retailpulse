<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

final class AuditObserver
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function created(Model $model): void
    {
        $this->record('created', $model, null, $this->snapshot($model));
    }

    public function updated(Model $model): void
    {
        $this->record('updated', $model, $this->changedOld($model), $this->changedNew($model));
    }

    public function deleted(Model $model): void
    {
        $this->record('deleted', $model, $this->snapshot($model), null);
    }

    private function record(string $event, Model $model, ?array $old, ?array $new): void
    {
        if (
            ! $model instanceof User
            && ! $model instanceof Role
            && ! $model instanceof Permission
            && ! $model instanceof Branch
        ) {
            return;
        }

        $this->audit->log($event, $model, $old, $new);
    }

  /**
   * @return array<string, mixed>
   */
    private function snapshot(Model $model): array
    {
        return collect($model->getAttributes())
            ->except(['password', 'remember_token'])
            ->all();
    }

  /**
   * @return array<string, mixed>
   */
    private function changedOld(Model $model): array
    {
        return collect($model->getOriginal())
            ->only(array_keys($model->getChanges()))
            ->except(['password', 'remember_token'])
            ->all();
    }

  /**
   * @return array<string, mixed>
   */
    private function changedNew(Model $model): array
    {
        return collect($model->getChanges())
            ->except(['password', 'remember_token'])
            ->all();
    }
}
