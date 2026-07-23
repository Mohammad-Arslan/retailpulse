<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use App\Services\BranchContextService;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

final class UserLoggedIn implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  list<int>  $branchIds
     */
    public function __construct(
        public readonly User $user,
        public readonly string $ipAddress,
        private readonly array $branchIds,
    ) {}

    public static function fromRequest(User $user, Request $request): self
    {
        $branchIds = self::resolveBroadcastBranchIds($user, $request);

        return new self(
            user: $user,
            ipAddress: (string) $request->ip(),
            branchIds: $branchIds,
        );
    }

    /**
     * @return list<int>
     */
    private static function resolveBroadcastBranchIds(User $user, Request $request): array
    {
        if ($user->hasBranchRestrictions()) {
            return $user->branches()->pluck('branches.id')->unique()->values()->all();
        }

        $service = app(BranchContextService::class);
        $sessionBranch = $service->sessionBranchId($request);

        if ($sessionBranch !== null) {
            return [$sessionBranch];
        }

        $primary = $user->primaryBranch();

        if ($primary !== null) {
            return [$primary->id];
        }

        return [];
    }

    public function broadcastWhen(): bool
    {
        return $this->branchIds !== [];
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(
            fn (int $branchId) => new PrivateChannel('branch.'.$branchId),
            $this->branchIds,
        );
    }

    public function broadcastAs(): string
    {
        return 'user.logged-in';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user' => $this->user->only('id', 'name'),
            'ip' => $this->ipAddress,
            'at' => now()->toIso8601String(),
        ];
    }
}
