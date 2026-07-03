<?php
// 边缘端数据上报服务
namespace App\Services\LX;

use App\Models\Alarm;
use App\Models\ControlCommand;
use App\Models\DispatchDecision;
use App\Models\GateAction;
use App\Models\MonitoringData;
use Illuminate\Support\Str;

class EdgeService
{
    public function reportMonitoringData(array $data): array
    {
        $inserted = 0;

        foreach ($data['data'] as $row) {
            MonitoringData::create([
                'timestamp'        => $row['timestamp'],
                'reservoir_id'     => $data['reservoir_id'],
                'edge_node_id'     => $data['edge_node_id'],
                'upstream_level'   => $row['upstream_level'],
                'downstream_level' => $row['downstream_level'],
                'water_head'       => $row['water_head'],
                'inflow_rate'      => $row['inflow_rate'],
                'outflow_rate'     => $row['outflow_rate'],
                'gate_opening'     => $row['gate_opening'],
                'power_output'     => $row['power_output'],
                'cumulative_energy' => $row['cumulative_energy'] ?? 0,
                'data_source'      => 'sensor',
            ]);
            $inserted++;
        }

        return ['inserted' => $inserted];
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

        return ['decision_id' => $decision->id];
    }

    public function reportFeedback(string $commandId, array $data): array
    {
        $command = ControlCommand::where('command_id', $commandId)->firstOrFail();

        $command->update([
            'status'           => $data['status'],
            'executed_at'      => $data['executed_at'],
            'feedback_at'      => now(),
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

        return ['command_id' => $commandId, 'status' => $data['status']];
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

        return ['alarm_id' => $alarm->id, 'alarm_no' => $alarm->alarm_no];
    }
}
