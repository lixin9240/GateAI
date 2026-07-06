<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * 边缘数据推送事件 — 边缘端上报传感器数据后推送到前端
 */
class EdgeDataUpdated implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public string $edgeId;
    public string $type;
    public array $payload;
    public string $timestamp;

    public function __construct(string $edgeId, string $type, array $payload)
    {
        $this->edgeId   = $edgeId;
        $this->type     = $type;
        $this->payload  = $payload;
        $this->timestamp = now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [new Channel('edge.' . $this->edgeId)];
    }

    public function broadcastWith(): array
    {
        return [
            'type'      => $this->type,
            'edge_id'   => $this->edgeId,
            'payload'   => $this->payload,
            'timestamp' => $this->timestamp,
        ];
    }

    public function broadcastAs(): string
    {
        return 'edge.data';
    }
}
