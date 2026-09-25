<?php

namespace App\Events;

use App\Services\RealtimeAudience;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

class DomainChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public readonly string $eventId;

    public readonly string $occurredAt;

    public function __construct(
        public readonly string $resource,
        public readonly string $action,
        public readonly int $resourceId,
        public readonly ?int $groupId,
        /** @var list<int>|null */
        public readonly ?array $audience = null,
    ) {
        $this->eventId = (string) Str::uuid();
        $this->occurredAt = now()->toISOString();
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return array_map(
            fn (int $userId): PrivateChannel => new PrivateChannel("users.{$userId}"),
            $this->audience ?? app(RealtimeAudience::class)->for($this->resource, $this->resourceId),
        );
    }

    public function broadcastAs(): string
    {
        return 'domain.changed';
    }

    /** @return array{version: int, event_id: string, resource: string, action: string, resource_id: int, group_id: int|null, occurred_at: string} */
    public function broadcastWith(): array
    {
        return [
            'version' => 1,
            'event_id' => $this->eventId,
            'resource' => $this->resource,
            'action' => $this->action,
            'resource_id' => $this->resourceId,
            'group_id' => $this->groupId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
