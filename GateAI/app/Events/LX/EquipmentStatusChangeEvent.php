<?php
// 设备状态变更事件
namespace App\Events\LX;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EquipmentStatusChangeEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $equipmentId,
        public string $previousStatus,
        public string $currentStatus,
        public string $changedAt,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('equipment');
    }

    public function broadcastAs(): string
    {
        return 'equipment_status_change';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'equipment_status_change',
            'data' => [
                'equipment_id'    => $this->equipmentId,
                'previous_status' => $this->previousStatus,
                'current_status'  => $this->currentStatus,
                'changed_at'      => $this->changedAt,
            ],
        ];
    }
}
