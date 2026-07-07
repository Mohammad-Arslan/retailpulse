<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\LoyaltyApprovalPolicy;
use App\Models\LoyaltyCampaign;
use App\Models\LoyaltyExpiryRule;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyProgramTier;
use App\Models\LoyaltyRule;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Support\AccountingAuditTypes;
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
        if (! $this->shouldAudit($model)) {
            return;
        }

        $this->audit->log($event, $model, $old, $new);
    }

    private function shouldAudit(Model $model): bool
    {
        if (AccountingAuditTypes::includes($model)) {
            return true;
        }

        return $model instanceof User
            || $model instanceof Role
            || $model instanceof Permission
            || $model instanceof Branch
            || $model instanceof Category
            || $model instanceof Brand
            || $model instanceof Product
            || $model instanceof LoyaltyProgram
            || $model instanceof LoyaltyRule
            || $model instanceof LoyaltyProgramTier
            || $model instanceof LoyaltyApprovalPolicy
            || $model instanceof LoyaltyExpiryRule
            || $model instanceof LoyaltyCampaign;
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
