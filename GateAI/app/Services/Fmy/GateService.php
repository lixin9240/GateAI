<?php

namespace App\Services\Fmy;

use Illuminate\Support\Facades\Log;

class GateService
{
    /**
     * 2.4 闸门列表 + 实时开度状态
     */
    public function list(?int $reservoirId): array
    {
        Log::channel('business')->info('监控大屏-闸门列表', [
            'reservoir_id' => $reservoirId,
            'user_id'      => auth()->id(),
            'trace_id'     => request()->attributes->get('trace_id'),
        ]);

        $statuses = ['online', 'online', 'online', 'online', 'offline', 'fault', 'maintenance'];
        $modes    = ['auto', 'auto', 'auto', 'manual', 'manual', 'emergency'];
        $gates    = [];

        for ($i = 1; $i <= 12; $i++) {
            $opening       = $this->deterministicFloat($i, 0, 100, 1);
            $targetOpening = $this->deterministicFloat($i + 50, 0, 100, 1);

            $gates[] = [
                'id'             => $i,
                'name'           => "{$i}#闸门",
                'code'           => 'GATE-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'status'         => $statuses[$i % count($statuses)],
                'opening'        => $opening,
                'target_opening' => $targetOpening,
                'mode'           => $modes[$i % count($modes)],
                'flow_rate'      => round($opening * 0.85, 2),
                'last_action_at' => now()->subMinutes($this->deterministicInt($i, 5, 180))
                    ->toDateTimeString(),
            ];
        }

        return $gates;
    }

    /**
     * 2.5 闸门操作日志
     */
    public function actionList(array $params): array
    {
        Log::channel('business')->info('监控大屏-闸门操作日志', [
            'params'  => [
                'reservoir_id' => $params['reservoir_id'] ?? null,
                'gate_id'      => $params['gate_id'] ?? null,
                'page'         => $params['page'] ?? 1,
                'page_size'    => $params['page_size'] ?? 20,
            ],
            'user_id'  => auth()->id(),
            'trace_id' => request()->attributes->get('trace_id'),
        ]);

        $page      = $params['page'] ?? 1;
        $pageSize  = $params['page_size'] ?? 20;
        $gateId    = $params['gate_id'] ?? null;
        $startTime = $params['start_time'] ?? null;
        $endTime   = $params['end_time'] ?? null;

        $all = $this->generateMockActions();

        if ($gateId) {
            $all = array_filter($all, fn ($item) => $item['gate_id'] == $gateId);
        }

        if ($startTime) {
            $all = array_filter($all, fn ($item) => $item['acted_at'] >= $startTime);
        }

        if ($endTime) {
            $all = array_filter($all, fn ($item) => $item['acted_at'] <= $endTime);
        }

        $all  = array_values($all);
        $total = count($all);
        $offset = ($page - 1) * $pageSize;
        $list  = array_slice($all, $offset, $pageSize);

        return [
            'total'      => $total,
            'page'       => $page,
            'page_size'  => $pageSize,
            'list'       => $list,
        ];
    }

    /**
     * 生成模拟闸门动作日志
     */
    private function generateMockActions(): array
    {
        $actionTypes = ['open', 'close', 'maintain', 'emergency'];
        $sources     = ['dqn_auto', 'manual', 'emergency_override', 'physics_corrected'];
        $operators   = ['系统', 'admin', '调度员A', '调度员B'];
        $results     = ['success', 'success', 'success', 'failed'];
        $actions     = [];
        $id          = 1;

        for ($day = 0; $day < 30; $day++) {
            for ($hour = 0; $hour < 8; $hour++) {
                $gateId = $this->deterministicInt($day * 10 + $hour, 1, 12);
                $previous = $this->deterministicFloat($day * 20 + $hour, 0, 100, 1);
                $target   = $this->deterministicFloat($day * 30 + $hour, 0, 100, 1);

                $actions[] = [
                    'id'              => $id++,
                    'gate_id'         => $gateId,
                    'gate_name'       => "{$gateId}#闸门",
                    'action_type'     => $actionTypes[$this->deterministicInt($day * 5 + $hour, 0, count($actionTypes) - 1)],
                    'previous_opening'=> $previous,
                    'target_opening'  => $target,
                    'actual_opening'  => $results[$this->deterministicInt($day * 7 + $hour, 0, count($results) - 1)] === 'success'
                        ? $target
                        : round($target * 0.95, 2),
                    'action_source'   => $sources[$this->deterministicInt($day * 3 + $hour, 0, count($sources) - 1)],
                    'operator'        => $operators[$this->deterministicInt($day * 4 + $hour, 0, count($operators) - 1)],
                    'acted_at'        => now()->subDays($day)->subHours($hour)->toDateTimeString(),
                    'duration_ms'     => $this->deterministicInt($day * 6 + $hour, 800, 5000),
                    'result'          => $results[$this->deterministicInt($day * 7 + $hour, 0, count($results) - 1)],
                ];
            }
        }

        return $actions;
    }

    private function deterministicFloat(int $seed, float $min, float $max, int $precision): float
    {
        $rand = fmod(sin($seed * 12.9898) * 43758.5453, 1.0);
        $rand = $rand < 0 ? -$rand : $rand;

        return round($min + $rand * ($max - $min), $precision);
    }

    private function deterministicInt(int $seed, int $min, int $max): int
    {
        return (int) round($this->deterministicFloat($seed, (float) $min, (float) $max, 0));
    }
}
