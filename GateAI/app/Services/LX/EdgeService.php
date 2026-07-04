<?php
// 边缘端数据上报服务
namespace App\Services\LX;

use App\Models\Alarm;
use App\Models\ControlCommand;
use App\Models\DispatchDecision;
use App\Models\GateAction;
use App\Models\MonitoringData;
use App\Support\LogHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class EdgeService
{
    public function reportMonitoringData(array $data): array
    {
        $reservoirId = $data['reservoir_id'];
        $edgeNodeId  = $data['edge_node_id'];
        $rows        = $data['data'];
        $now          = now();

        // 1. 批量写入 MySQL（单条 INSERT，N 行 VALUES）
        $values = [];
        $bindings = [];
        foreach ($rows as $row) {
            $values[] = '(?,?,?,?,?,?,?,?,?,?,?,?,?)';
            $ts = \Carbon\Carbon::parse($row['timestamp'])->format('Y-m-d H:i:s');
            $bindings = array_merge($bindings, [
                $ts, $reservoirId, $edgeNodeId,
                $row['upstream_level'], $row['downstream_level'], $row['water_head'],
                $row['inflow_rate'], $row['outflow_rate'], $row['gate_opening'],
                $row['power_output'], $row['cumulative_energy'] ?? 0,
                'sensor', $now,
            ]);
        }

        \DB::insert(
            'INSERT INTO monitoring_data (timestamp, reservoir_id, edge_node_id, upstream_level, downstream_level, water_head, inflow_rate, outflow_rate, gate_opening, power_output, cumulative_energy, data_source, created_at) VALUES ' . implode(',', $values),
            $bindings
        );

        // 2. 最新一帧写入 Redis（覆盖，监控大屏秒级读取）
        $latest = end($rows);
        $cacheKey = "monitoring:latest:{$reservoirId}";
        Cache::put($cacheKey, [
            'reservoir_id'   => $reservoirId,
            'upstream_level'   => $latest['upstream_level'] ?? null,
            'downstream_level' => $latest['downstream_level'] ?? null,
            'water_head'       => $latest['water_head'] ?? null,
            'inflow_rate'      => $latest['inflow_rate'] ?? null,
            'outflow_rate'     => $latest['outflow_rate'] ?? null,
            'gate_opening'     => $latest['gate_opening'] ?? null,
            'power_output'     => $latest['power_output'] ?? null,
            'timestamp'        => $latest['timestamp'] ?? null,
        ], 300);

        return ['inserted' => count($rows)];
    }

    /**
     * 从 Redis 读取最新监测数据（监控大屏用，秒级延迟）
     */
    public static function getLatest(int $reservoirId): ?array
    {
        return Cache::get("monitoring:latest:{$reservoirId}");
    }

    public function reportDispatchDecision(array $data): array
    {
        $decision = DispatchDecision::create([
            'trace_id'            => $data['trace_id'],
            'reservoir_id'        => $data['reservoir_id'],
            'edge_node_id'        => $data['edge_node_id'],
            'prediction_id'       => $data['prediction_id'],
            'decision_time'       => $data['decision_time'],
            'decision_mode'       => $data['decision_mode'],
            'risk_rank'           => $data['risk_rank'],
            'upstream_level'      => $data['upstream_level'],
            'downstream_level'    => $data['downstream_level'],
            'inflow_rate'         => $data['inflow_rate'],
            'current_opening'     => $data['current_opening'],
            'lstm_predictions'    => $data['lstm_predictions'],
            'recommended_opening' => $data['recommended_opening'],
            'confidence'          => $data['confidence'],
            'factors'             => $data['factors'],
            'alternatives'        => $data['alternatives'],
            'weights_used'        => $data['weights_used'],
            'reward_score'        => $data['reward_score'] ?? null,
            'physics_validation'  => $data['physics_validation'] ?? null,
            'execution_status'    => 'pending',
        ]);

        LogHelper::business('[AI决策] 边缘端上报决策', [
            'decision_id'        => $decision->id,
            'decision_mode'      => $data['decision_mode'],
            'recommended_opening' => $data['recommended_opening'],
            'confidence'         => $data['confidence'],
            'reward_score'       => $data['reward_score'] ?? null,
            'risk_rank'          => $data['risk_rank'],
            'physics_validation' => $data['physics_validation'] ?? null,
        ], 'info', 'ai_decision');

        return ['decision_id' => $decision->id];
    }

    public function reportFeedback(string $commandId, array $data): array
    {
        $command = ControlCommand::where('command_id', $commandId)->firstOrFail();

        $feedbackAt = now();

        // 全链路耗时：下发 → 回执（ms）
        $fullDelayMs = $command->sent_at
            ? $feedbackAt->diffInMilliseconds($command->sent_at)
            : null;

        $command->update([
            'status'           => $data['status'],
            'executed_at'      => $data['executed_at'],
            'feedback_at'      => $feedbackAt,
            'full_delay_ms'    => $fullDelayMs,
            'execution_result' => $data['execution_result'] ?? null,
        ]);

        GateAction::create([
            'equipment_id'     => $command->target_equipment ?? 0,
            'decision_id'      => $command->decision_id,
            'command_id'       => $commandId,
            'previous_opening' => 0,
            'target_opening'   => $command->target_opening ?? 0,
            'actual_opening'   => $data['actual_opening'] ?? 0,
            'action_type'      => $command->command_type ?? 'maintain',
            'action_source'    => 'dqn_auto',
            'duration_ms'      => $data['duration_ms'] ?? 0,
            'actuator_current' => $data['actuator_current'] ?? null,
            'is_smoothed'      => $data['is_smoothed'] ?? false,
            'acted_at'         => $data['executed_at'],
        ]);

        LogHelper::business('[指令回执] 全链路追踪', [
            'command_id'    => $commandId,
            'status'        => $data['status'],
            'full_delay_ms' => $fullDelayMs,
            'duration_ms'   => $data['duration_ms'] ?? null,
        ], 'info', 'command_feedback');

        return ['command_id' => $commandId, 'status' => $data['status'], 'full_delay_ms' => $fullDelayMs];
    }

    public function reportAlarm(array $data): array
    {
        $alarm = Alarm::create([
            'alarm_no'        => 'ALM-' . date('YmdHis') . '-' . strtoupper(Str::random(4)),
            'reservoir_id'    => $data['reservoir_id'],
            'edge_node_id'    => $data['edge_node_id'],
            'equipment_id'    => $data['equipment_id'],
            'type'            => $data['type'],
            'level'           => $data['level'],
            'message'         => $data['message'],
            'threshold_id'    => $data['threshold_id'] ?? null,
            'metric_value'    => $data['metric_value'],
            'threshold_value' => $data['threshold_value'],
            'duration'        => $data['duration'],
            'exceed_start'    => $data['exceed_start'],
            'trace_id'        => $data['trace_id'] ?? null,
            'status'          => 'unhandled',
        ]);

        LogHelper::business('边缘端上报告警', [
            'alarm_id'        => $alarm->id,
            'alarm_no'        => $alarm->alarm_no,
            'type'            => $data['type'],
            'level'           => $data['level'],
            'message'         => $data['message'],
            'metric_value'    => $data['metric_value'],
            'threshold_value' => $data['threshold_value'],
            'reservoir_id'    => $data['reservoir_id'],
        ], 'warning', 'EDGE_ALARM');

        return ['alarm_id' => $alarm->id, 'alarm_no' => $alarm->alarm_no];
    }
}
