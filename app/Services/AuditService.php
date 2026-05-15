<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AuditService
{
    public function log(
        string $event,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null,
        ?User $actor = null,
    ): AuditLog {
        $request ??= request();
        $actor ??= Auth::user();

        return AuditLog::query()->create([
            'user_type' => $actor ? $actor::class : null,
            'user_id' => $actor?->id,
            'event' => $event,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    public function logLogin(User $user, Request $request): AuditLog
    {
        return $this->log('login', $user, newValues: [
            'email' => $user->email,
        ], request: $request, actor: $user);
    }

    public function logLogout(User $user, Request $request): AuditLog
    {
        return $this->log('logout', $user, request: $request, actor: $user);
    }
}
