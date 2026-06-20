<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CustomerCreditLimitWarning implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $customerId,
        public readonly int $branchId,
        public readonly string $customerName,
        public readonly float $creditLimit,
        public readonly float $outstanding,
        public readonly float $projected,
        public readonly bool $limitExceeded,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('branch.'.$this->branchId)];
    }

    public function broadcastAs(): string
    {
        return 'customer.credit-limit-warning';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'customer_id' => $this->customerId,
            'branch_id' => $this->branchId,
            'customer_name' => $this->customerName,
            'credit_limit' => number_format($this->creditLimit, 2, '.', ''),
            'outstanding' => number_format($this->outstanding, 2, '.', ''),
            'projected' => number_format($this->projected, 2, '.', ''),
            'limit_exceeded' => $this->limitExceeded,
            'at' => now()->toIso8601String(),
        ];
    }
}
