<?php

namespace App\Services;

use App\Models\Alarm;
use App\Models\AlarmExceedLog;
use App\Models\DispatchPlan;
use App\Models\DispatchExecution;
use App\Models\DispatchDecision;
use App\Models\GateAction;
use App\Models\EmergencyStop;
use App\Models\LstmPrediction;
use App\Models\ControlCommand;
use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;

class WjcService
{
    /**
     * 获取正式告警分页列表
     */
    public function getAlarmList(array $params)
    {
        $query = Alarm::query();

        if (!empty($params['reservoir_id'])) {
            $query->where('reservoir_id', $params['reservoir_id']);
        }
        if (!empty($params['level'])) {
            $query->where('level', $params['level']);
        }
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        $pageSize = $params['page_size'] ?? 20;
        return $query->orderByDesc('created_at')->paginate($pageSize);
    }

    /**
     * 确认告警
     */
    public function acknowledgeAlarm(int $id, int $userId): Alarm
    {
        $alarm = Alarm::find($id);
        if (!$alarm) {
            throw new BusinessException('告警不存在', ResponseCode::DATA_NOT_FOUND);
        }
        if ($alarm->status === 'disposed') {
            throw new BusinessException('已处置的告警无法确认', ResponseCode::BUSINESS_ERROR);
        }

        $alarm->update([
            'status'          => 'acknowledged',
            'acknowledged_by' => $userId,
            'acknowledged_at' => now(),
        ]);

        return $alarm;
    }

    /**
     * 处置告警
     */
    public function disposeAlarm(int $id, string $note): Alarm
    {
        $alarm = Alarm::find($id);
        if (!$alarm) {
            throw new BusinessException('告警不存在', ResponseCode::DATA_NOT_FOUND);
        }

        $alarm->update([
            'status'        => 'disposed',
            'disposed_note' => $note,
        ]);

        return $alarm;
    }

    /**
     * 获取瞬时超限日志
     */
    public function getExceedLogs(array $params)
    {
        $query = AlarmExceedLog::query();

        if (!empty($params['equipment_id'])) {
            $query->where('equipment_id', $params['equipment_id']);
        }
        if (!empty($params['start_time'])) {
            $query->where('exceed_start', '>=', $params['start_time']);
        }
        if (!empty($params['end_time'])) {
            $query->where('exceed_start', '<=', $params['end_time']);
        }

        $pageSize = $params['page_size'] ?? 20;
        return $query->orderByDesc('exceed_start')->paginate($pageSize);
    }

    /**
     * 获取调度计划分页列表
     */
    public function getPlanList(array $params)
    {
        $query = DispatchPlan::query();

        if (!empty($params['reservoir_id'])) {
            $query->where('reservoir_id', $params['reservoir_id']);
        }
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        $pageSize = $params['page_size'] ?? 20;
        return $query->orderByDesc('start_time')->paginate($pageSize);
    }

    /**
     * 创建调度计划
     */
    public function createPlan(array $data): DispatchPlan
    {
        $data['status'] = 'pending';
        return DispatchPlan::create($data);
    }

    /**
     * 执行调度操作
     */
    public function executeDispatch(array $data, int $operatorId): DispatchExecution
    {
        $plan = DispatchPlan::findOrFail($data['plan_id']);
        if (!in_array($plan->status, ['pending', 'active'])) {
            throw new BusinessException('该调度计划当前状态不允许执行');
        }

        $plan->update(['status' => 'active']);

        return DispatchExecution::create(array_merge($data, [
            'executed_at' => now(),
            'operator_id' => $operatorId,
        ]));
    }

    /**
     * LSTM预测分页列表
     */
    public function getPredictionList(array $params)
    {
        $query = LstmPrediction::query();

        if (!empty($params['reservoir_id'])) {
            $query->where('reservoir_id', $params['reservoir_id']);
        }

        $pageSize = $params['page_size'] ?? 20;
        return $query->orderByDesc('created_at')->paginate($pageSize);
    }

    /**
     * 调度决策历史列表
     */
    public function getDecisionList(array $params)
    {
        $query = DispatchDecision::query();

        if (!empty($params['reservoir_id'])) {
            $query->where('reservoir_id', $params['reservoir_id']);
        }
        if (!empty($params['decision_mode'])) {
            $query->where('decision_mode', $params['decision_mode']);
        }

        $pageSize = $params['page_size'] ?? 20;
        return $query->orderByDesc('decision_time')->paginate($pageSize);
    }

    /**
     * 决策详情
     */
    public function getDecisionDetail(int $id): DispatchDecision
    {
        return DispatchDecision::findOrFail($id);
    }

    /**
     * 闸门动作历史
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
     * 指令追踪
     */
    public function traceCommand(string $commandId)
    {
        return ControlCommand::where('command_id', $commandId)->firstOrFail();
    }

    /**
     * 全局急停
     */
    public function emergencyStop(array $data, int $userId): EmergencyStop
    {
        return EmergencyStop::create([
            'trigger_user_id' => $userId,
            'stop_reason'     => $data['stop_reason'] ?? null,
            'trigger_time'    => now(),
        ]);
    }

    /**
     * 恢复自动
     */
    public function stopRecover(int $id, int $userId): EmergencyStop
    {
        $stop = EmergencyStop::findOrFail($id);
        if ($stop->recover_time) {
            throw new BusinessException('该急停记录已恢复', ResponseCode::BUSINESS_ERROR);
        }
        $stop->update([
            'recover_user_id' => $userId,
            'recover_time'    => now(),
        ]);

        return $stop;
    }

    /**
     * 急停日志列表
     */
    public function getEmergencyStops(array $params)
    {
        $query = EmergencyStop::query();

        $pageSize = $params['page_size'] ?? 20;
        return $query->orderByDesc('trigger_time')->paginate($pageSize);
    }
}
