<?php
// 新告警事件
namespace App\Events\LX;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlarmNewEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $alarmId,
        public string $alarmNo,
        public string $level,
        public string $type,
        public string $message,
        public int $reservoirId,
        public string $createdAt,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('alarms');
    }

    public function broadcastAs(): string
    {
        return 'alarm_new';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'alarm_new',
            'data' => [
                'alarm_id'      => $this->alarmId,
                'alarm_no'      => $this->alarmNo,
                'level'         => $this->level,
                'type'          => $this->type,
                'message'       => $this->message,
                'reservoir_id'  => $this->reservoirId,
                'created_at'    => $this->createdAt,
            ],
        ];
    }
}
