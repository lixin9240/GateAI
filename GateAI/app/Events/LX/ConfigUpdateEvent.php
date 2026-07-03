<?php
// 配置更新事件
namespace App\Events\LX;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConfigUpdateEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $module,
        public int $version,
        public string $message,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('edge.config');
    }

    public function broadcastAs(): string
    {
        return 'config_update';
    }

    public function broadcastWith(): array
    {
        return [
            'type'    => 'config_update',
            'module'  => $this->module,
            'version' => $this->version,
            'message' => $this->message,
        ];
    }
}
