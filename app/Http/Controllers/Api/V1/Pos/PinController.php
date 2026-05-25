<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\SetPinRequest;
use App\Http\Requests\Api\Pos\VerifyPinRequest;
use App\Models\User;
use App\Services\PosPinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PinController extends Controller
{
    public function __construct(
        private readonly PosPinService $pinService,
    ) {}

    public function verify(VerifyPinRequest $request): JsonResponse
    {
        $valid = $this->pinService->verifyPin(
            user: $request->user(),
            pin: $request->validated('pin'),
        );

        if (! $valid) {
            $lockout = $this->pinService->getLockoutStatus($request->user());

            return response()->json([
                'verified' => false,
                'lockout' => $lockout,
            ], 422);
        }

        return response()->json([
            'verified' => true,
            'lockout' => null,
        ]);
    }

    public function setPin(SetPinRequest $request): JsonResponse
    {
        $this->pinService->setPin($request->user(), $request->validated('pin'));

        return response()->json(['message' => __('PIN updated successfully.')]);
    }

    public function resetLockout(Request $request, int $userId): JsonResponse
    {
        $this->authorize('pos.admin');

        $target = User::query()->findOrFail($userId);
        $this->pinService->resetLockout($target);

        return response()->json(['message' => __('Lockout cleared.')]);
    }

    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'has_pin' => $this->pinService->hasPin($request->user()),
            'lockout' => $this->pinService->getLockoutStatus($request->user()),
        ]);
    }
}
