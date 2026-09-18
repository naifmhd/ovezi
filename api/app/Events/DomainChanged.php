<?php

namespace App\Events;

use App\Services\RealtimeAudience;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class DomainChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $resource,
        public readonly string $action,
        public readonly int $resourceId,
        public readonly ?int $groupId,
    ) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return array_map(
            fn (int $userId): PrivateChannel => new PrivateChannel("users.{$userId}"),
            app(RealtimeAudience::class)->for($this->resource, $this->resourceId),
        );
    }

    public function broadcastAs(): string
    {
        return 'domain.changed';
    }

    /** @return array{resource: string, action: string, resource_id: int, group_id: int|null} */
    public function broadcastWith(): array
    {
        return [
            'resource' => $this->resource,
            'action' => $this->action,
            'resource_id' => $this->resourceId,
            'group_id' => $this->groupId,
        ];
    }
}
