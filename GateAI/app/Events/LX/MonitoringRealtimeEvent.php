<?php
// 实时监控事件
namespace App\Events\LX;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MonitoringRealtimeEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $reservoirId,
        public array $data,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('monitoring.' . $this->reservoirId);
    }

    public function broadcastAs(): string
    {
        return 'monitoring_realtime';
    }

    public function broadcastWith(): array
    {
        return [
            'type'         => 'monitoring_realtime',
            'reservoir_id' => $this->reservoirId,
            'data'         => $this->data,
        ];
    }
}
