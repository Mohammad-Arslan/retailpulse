<?php

declare(strict_types=1);

namespace App\Services\Procurement\Approval;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\PosPinService;
use App\Services\Procurement\ProcurementConfigService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class PinPrApprovalStrategy implements PrApprovalStrategyInterface
{
    public function __construct(
        private readonly PosPinService $posPin,
        private readonly ProcurementConfigService $config,
    ) {}

    public function approve(PurchaseRequest $request, User $approver, ?string $managerPin = null): void
    {
        if (! $approver->can('procurement.approve-pr')) {
            throw new AuthorizationException(__('You do not have permission to approve purchase requests.'));
        }

        $requiresPin = $this->config->requiresPrApproval((float) $request->total, $request->branch_id);

        if ($requiresPin) {
            if ($managerPin === null || trim($managerPin) === '') {
                throw ValidationException::withMessages([
                    'manager_pin' => __('Manager PIN is required for purchase requests above the approval threshold.'),
                ]);
            }

            $this->assertValidPin($approver, $managerPin);

            return;
        }

        if ($managerPin !== null && trim($managerPin) !== '') {
            $this->assertValidPin($approver, $managerPin);
        }
    }

    private function assertValidPin(User $approver, string $managerPin): void
    {
        try {
            if (! $this->posPin->verifyPin($approver, $managerPin)) {
                throw ValidationException::withMessages([
                    'manager_pin' => __('Invalid manager PIN.'),
                ]);
            }
        } catch (ValidationException $e) {
            $messages = $e->errors();
            if (isset($messages['pin'])) {
                throw ValidationException::withMessages([
                    'manager_pin' => $messages['pin'],
                ]);
            }

            throw $e;
        }
    }
}
