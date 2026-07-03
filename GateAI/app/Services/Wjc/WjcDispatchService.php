<?php

namespace App\Services\Wjc;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\ControlCommand;
use App\Models\DispatchDecision;
use App\Models\EmergencyStop;
use App\Models\GateAction;
use App\Models\LstmPrediction;
use Illuminate\Support\Str;

class WjcDispatchService
{
    /**
     * 4.1 获取 LSTM 预测数据
     */
    public function getPredictions(?int $reservoirId = null, ?int $term = null)
    {
        $query = LstmPrediction::query();

        if ($reservoirId !== null) {
            $query->whereHas('equipment', function ($q) use ($reservoirId) {
                $q->where('reservoir_id', $reservoirId);
            });
        }

        if ($term !== null) {
            $query->where('predict_term', $term);
        }

        return $query->orderByDesc('base_time')->first();
    }

    /**
     * 4.2 获取 AI 决策详情
     */
    public function getDecisionDetail(int $id)
    {
        return DispatchDecision::with('reservoir')->findOrFail($id);
    }

    /**
     * 4.3 调度决策历史列表
     */
    public function getDecisionHistory(array $params)
    {
        $query = DispatchDecision::query();

        if (!empty($params['reservoir_id'])) {
            $query->where('reservoir_id', $params['reservoir_id']);
        }
        if (!empty($params['execution_status'])) {
            $query->where('execution_status', $params['execution_status']);
        }

        $pageSize = $params['page_size'] ?? 20;
        return $query->orderByDesc('decision_time')->paginate($pageSize);
    }

    /**
     * 4.4 人工下发调度指令
     */
    public function executeDispatch(array $data): string
    {
        $commandId = 'CMD-' . date('Ymd') . '-' . Str::random(6);

        ControlCommand::create([
            'trace_id'       => request()->attributes->get('trace_id', (string) Str::uuid()),
            'edge_node_id'   => $data['edge_node_id'] ?? 1,
            'command_id'     => $commandId,
            'command_type'   => 'manual_adjust',
            'payload'        => json_encode($data),
            'target_opening' => $data['target_opening'],
            'status'         => 'pending',
            'sent_at'        => now(),
            'nonce'          => Str::random(32),
            'sign'           => '',
            'expire_at'      => now()->addMinutes(5),
        ]);

        return $commandId;
    }

    /**
     * 4.5 指令追踪
     */
    public function traceCommand(string $commandId)
    {
        return ControlCommand::where('command_id', $commandId)->firstOrFail();
    }

    /**
     * 4.6 闸门动作历史
     */
    public function getGateActions(array $params)
    {
        $query = GateAction::query();

        if (!empty($params['equipment_id'])) {
            $query->where('equipment_id', $params['equipment_id']);
        }

        $pageSize = $params['page_size'] ?? 20;
        return $query->orderByDesc('acted_at')->paginate($pageSize);
    }

    /**
     * 4.7 全局急停
     */
    public function emergencyStop(array $data): array
    {
        $commandId = 'STOP-' . date('YmdHis') . '-' . Str::random(4);

        $command = ControlCommand::create([
            'trace_id'       => request()->attributes->get('trace_id', (string) Str::uuid()),
            'edge_node_id'   => $data['edge_node_id'] ?? 1,
            'command_id'     => $commandId,
            'command_type'   => 'emergency_stop',
            'payload'        => json_encode($data),
            'target_opening' => 0,
            'is_emergency'   => 1,
            'status'         => 'sent',
            'sent_at'        => now(),
            'nonce'          => Str::random(32),
            'sign'           => '',
            'expire_at'      => now()->addMinutes(5),
        ]);

        $stop = EmergencyStop::create([
            'trigger_user_id' => auth()->id(),
            'trigger_time'    => now(),
            'stop_reason'     => $data['stop_reason'],
            'command_id'      => $command->id,
        ]);

        return [
            'stop_log_id' => $stop->id,
            'command_id'  => $commandId,
        ];
    }

    /**
     * 4.8 恢复自动模式
     */
    public function recoverFromStop(int $id): EmergencyStop
    {
        $stop = EmergencyStop::findOrFail($id);
        if ($stop->recover_time) {
            throw new BusinessException('该急停记录已恢复', ResponseCode::BUSINESS_ERROR);
        }
        $stop->update(['recover_time' => now()]);
        return $stop;
    }

    /**
     * 4.9 急停日志列表
     */
    public function getEmergencyStops(array $params)
    {
        $query = EmergencyStop::query()->with('decision.reservoir');

        $pageSize = $params['page_size'] ?? 20;
        return $query->orderByDesc('trigger_time')->paginate($pageSize);
    }
}
