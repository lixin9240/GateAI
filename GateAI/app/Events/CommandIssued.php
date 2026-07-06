<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * 云端→边缘 指令下发事件
 */
class CommandIssued implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public string $edgeId;
    public string $commandId;
    public array  $payload;
    public string $sign;
    public int    $expireAt;
    public string $nonce;

    public function __construct(string $edgeId, array $payload)
    {
        $this->edgeId   = $edgeId;
        $this->commandId = 'cmd-' . now()->format('Ymd-His') . '-' . substr(md5(uniqid()), 0, 6);
        $this->payload  = $payload;
        $this->expireAt = now()->addSeconds(30)->timestamp;
        $this->nonce    = bin2hex(random_bytes(10));

        $signRaw = $this->commandId . json_encode($payload) . $this->expireAt . $this->nonce;
        $this->sign = hash_hmac('sha256', $signRaw, env('EDGE_SHARED_SECRET', ''));
    }

    public function broadcastOn(): array
    {
        return [new Channel('edge.' . $this->edgeId)];
    }

    public function broadcastWith(): array
    {
        return [
            'type'       => 'command',
            'command_id' => $this->commandId,
            'payload'    => $this->payload,
            'sign'       => $this->sign,
            'expire_at'  => $this->expireAt,
            'nonce'      => $this->nonce,
        ];
    }
}
