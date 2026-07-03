<?php
// 模型重新加载事件
namespace App\Events\LX;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ModelReloadEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $modelName,
        public string $modelVersion,
        public string $message,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('edge.model');
    }

    public function broadcastAs(): string
    {
        return 'model_reload';
    }

    public function broadcastWith(): array
    {
        return [
            'type'          => 'model_reload',
            'model_name'    => $this->modelName,
            'model_version' => $this->modelVersion,
            'message'       => $this->message,
        ];
    }
}
