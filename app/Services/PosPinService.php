<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PosPinLockout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class PosPinService
{
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_MINUTES = 15;

    public function setPin(User $user, string $pin): void
    {
        $this->assertValidPin($pin);

        $user->update([
            'pos_pin_hash' => Hash::make($pin),
            'pos_pin_updated_at' => now(),
        ]);
    }

    public function verifyPin(User $user, string $pin): bool
    {
        $lockout = $this->getLockout($user);

        if ($lockout !== null && $lockout->isLocked()) {
            throw ValidationException::withMessages([
                'pin' => __('Too many failed attempts. Try again in :n minutes.', [
                    'n' => $lockout->minutesRemaining(),
                ]),
            ]);
        }

        if ($user->pos_pin_hash === null) {
            throw ValidationException::withMessages([
                'pin' => __('No PIN has been configured for this account.'),
            ]);
        }

        if (! Hash::check($pin, $user->pos_pin_hash)) {
            $this->recordFailedAttempt($user, $lockout);

            return false;
        }

        $this->clearLockout($user);

        return true;
    }

    public function resetLockout(User $target): void
    {
        PosPinLockout::query()
            ->where('user_id', $target->id)
            ->delete();
    }

    public function getLockoutStatus(User $user): ?array
    {
        $lockout = $this->getLockout($user);

        if ($lockout === null) {
            return null;
        }

        return [
            'is_locked' => $lockout->isLocked(),
            'failed_attempts' => $lockout->failed_attempts,
            'minutes_remaining' => $lockout->minutesRemaining(),
            'locked_until' => $lockout->locked_until?->toIso8601String(),
        ];
    }

    public function hasPin(User $user): bool
    {
        return $user->pos_pin_hash !== null;
    }

    private function getLockout(User $user): ?PosPinLockout
    {
        return PosPinLockout::query()->where('user_id', $user->id)->first();
    }

    private function recordFailedAttempt(User $user, ?PosPinLockout $lockout): void
    {
        DB::transaction(function () use ($user, $lockout) {
            if ($lockout === null) {
                $lockout = PosPinLockout::query()->create([
                    'user_id' => $user->id,
                    'failed_attempts' => 1,
                    'locked_until' => null,
                ]);
            } else {
                $newAttempts = $lockout->failed_attempts + 1;
                $lockedUntil = null;

                if ($newAttempts >= self::MAX_ATTEMPTS) {
                    $lockedUntil = now()->addMinutes(self::LOCKOUT_MINUTES);
                }

                $lockout->update([
                    'failed_attempts' => $newAttempts,
                    'locked_until' => $lockedUntil,
                ]);
            }
        });
    }

    private function clearLockout(User $user): void
    {
        PosPinLockout::query()->where('user_id', $user->id)->delete();
    }

    private function assertValidPin(string $pin): void
    {
        if (! preg_match('/^\d{6}$/', $pin)) {
            throw ValidationException::withMessages([
                'pin' => __('PIN must be exactly 6 digits.'),
            ]);
        }
    }
}
