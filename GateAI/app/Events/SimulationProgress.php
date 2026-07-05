<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SimulationProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $simulationId;
    public float $progress;
    public array $metrics;
    public array $anomalies;
    public ?string $status;

    public function __construct(
        string $simulationId,
        float $progress,
        array $metrics,
        array $anomalies = [],
        ?string $status = null,
    ) {
        $this->simulationId = $simulationId;
        $this->progress     = $progress;
        $this->metrics      = $metrics;
        $this->anomalies    = $anomalies;
        $this->status       = $status;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("simulation.{$this->simulationId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'progress';
    }
}
