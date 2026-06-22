<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

final class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('procurement.view');
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $user->can('procurement.view');
    }

    public function create(User $user): bool
    {
        return $user->can('procurement.create');
    }

    public function update(User $user, PurchaseOrder $order): bool
    {
        return $user->can('procurement.update') && $order->status->canEdit();
    }

    public function submit(User $user, PurchaseOrder $order): bool
    {
        return $user->can('procurement.create') && $order->status->canSubmit();
    }

    public function approve(User $user, PurchaseOrder $order): bool
    {
        return $user->can('procurement.approve-po') && $order->status->canApprove();
    }

    public function receive(User $user, PurchaseOrder $order): bool
    {
        return $user->can('procurement.receive-grn') && $order->status->canReceive();
    }

    public function reject(User $user, PurchaseOrder $order): bool
    {
        return $user->can('procurement.approve-po') && $order->status->canReject();
    }

    public function cancel(User $user, PurchaseOrder $order): bool
    {
        return $user->can('procurement.update') && $order->status->canCancel();
    }

    public function close(User $user, PurchaseOrder $order): bool
    {
        return $user->can('procurement.update') && $order->status->canClose();
    }

    public function resolveMatch(User $user, PurchaseOrder $order): bool
    {
        return $user->can('procurement.resolve-match-exception');
    }

    public function manageReturns(User $user, PurchaseOrder $order): bool
    {
        return $user->can('procurement.manage-returns');
    }
}
