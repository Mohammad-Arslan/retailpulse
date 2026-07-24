<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;

final class PurchaseRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('procurement.view');
    }

    public function view(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('procurement.view');
    }

    public function create(User $user): bool
    {
        return $user->can('procurement.create');
    }

    public function update(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('procurement.update') && $purchaseRequest->status->canEdit();
    }

    public function submit(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('procurement.create') && $purchaseRequest->status->canSubmit();
    }

    public function approve(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('procurement.approve-pr') && $purchaseRequest->status->canApprove();
    }

    public function reject(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('procurement.approve-pr') && $purchaseRequest->status->canReject();
    }

    public function cancel(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('procurement.update') && $purchaseRequest->status->canCancel();
    }

    public function convert(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->can('procurement.convert-pr') && $purchaseRequest->status->canConvert();
    }
}
