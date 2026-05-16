<?php

declare(strict_types=1);

namespace App\Events\ImportExport;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ImportCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        public string $jobUlid,
        public int $userId,
        public array $summary,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("import-job.{$this->jobUlid}"),
            new PrivateChannel("user.{$this->userId}.import-jobs"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'import.completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_merge($this->summary, ['job_ulid' => $this->jobUlid]);
    }
}
