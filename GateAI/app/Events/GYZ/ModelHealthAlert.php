<?php

namespace App\Events\GYZ;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * 模型健康度告警 — 评分降至 C/D 级时推送
 */
class ModelHealthAlert implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public string $modelType;
    public string $modelVersion;
    public string $healthGrade;
    public float  $overallScore;
    public string $message;

    public function __construct(string $modelType, string $modelVersion, string $healthGrade, float $overallScore, string $message)
    {
        $this->modelType    = $modelType;
        $this->modelVersion = $modelVersion;
        $this->healthGrade  = $healthGrade;
        $this->overallScore = $overallScore;
        $this->message      = $message;
    }

    public function broadcastOn(): array
    {
        return [new Channel('settings.models.health')];
    }

    public function broadcastWith(): array
    {
        return [
            'model_type'    => $this->modelType,
            'model_version' => $this->modelVersion,
            'health_grade'  => $this->healthGrade,
            'overall_score' => $this->overallScore,
            'message'       => $this->message,
            'timestamp'     => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'model.health';
    }
}
