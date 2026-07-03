<?php
// 新决策事件
namespace App\Events\LX;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DecisionNewEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $decisionId,
        public int $reservoirId,
        public string $decisionMode,
        public float $recommendedOpening,
        public float $confidence,
        public int $riskRank,
        public bool $physicsValidationPassed,
        public string $createdAt,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('dispatch.' . $this->reservoirId);
    }

    public function broadcastAs(): string
    {
        return 'decision_new';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'decision_new',
            'data' => [
                'decision_id'               => $this->decisionId,
                'reservoir_id'              => $this->reservoirId,
                'decision_mode'             => $this->decisionMode,
                'recommended_opening'       => $this->recommendedOpening,
                'confidence'                => $this->confidence,
                'risk_rank'                 => $this->riskRank,
                'physics_validation_passed' => $this->physicsValidationPassed,
                'created_at'                => $this->createdAt,
            ],
        ];
    }
}
