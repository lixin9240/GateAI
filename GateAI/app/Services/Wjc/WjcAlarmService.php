<?php

namespace App\Services\Wjc;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\Alarm;
use App\Models\AlarmExceedLog;
use App\Support\LogHelper;

class WjcAlarmService
{
    /**
     * 3.1 获取告警分页列表
     */
    public function getAlarmList(array $params)
    {
        $query = Alarm::query()->with('equipment');

        if (!empty($params['reservoir_id'])) {
            $query->whereHas('equipment', function($q) use ($params) {
                $q->where('reservoir_id', $params['reservoir_id']);
            });
        }
        if (!empty($params['level'])) {
            $query->where('level', $params['level']);
        }
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }
        if (!empty($params['type'])) {
            $query->where('type', $params['type']);
        }

        $pageSize = $params['page_size'] ?? 20;
        return $query->orderByDesc('created_at')->paginate($pageSize);
    }

    /**
     * 3.2 确认告警
     */
    public function acknowledgeAlarm(int $id, int $userId): Alarm
    {
        $alarm = Alarm::findOrFail($id);

        if ($alarm->status !== 'unhandled') {
            throw new BusinessException('告警已处置，不可重复确认', ResponseCode::BUSINESS_ERROR);
        }

        $alarm->update([
            'status'          => 'acknowledged',
            'acknowledged_by' => $userId,
            'acknowledged_at' => now(),
        ]);

        LogHelper::business('告警已确认', [
            'alarm_id'    => $alarm->id,
            'alarm_level' => $alarm->level,
            'alarm_type'  => $alarm->type,
            'user_id'     => $userId,
        ], 'info', 'ALARM_ACKNOWLEDGE');

        return $alarm;
    }

    /**
     * 3.3 处置告警
     */
    public function disposeAlarm(int $id, ?string $note): Alarm
    {
        $alarm = Alarm::findOrFail($id);

        if ($alarm->status === 'disposed') {
            throw new BusinessException('告警已处置', ResponseCode::BUSINESS_ERROR);
        }

        $alarm->update([
            'status'       => 'disposed',
            'dispose_note' => $note,
            'disposed_at'  => now(),
        ]);

        LogHelper::business('告警已处置', [
            'alarm_id'    => $alarm->id,
            'alarm_level' => $alarm->level,
            'alarm_type'  => $alarm->type,
            'note'        => $note,
        ], 'info', 'ALARM_DISPOSE');

        return $alarm;
    }

    /**
     * 3.4 瞬时超限日志
     */
    public function getExceedLogs(array $params)
    {
        $query = AlarmExceedLog::query();

        if (!empty($params['equipment_id'])) {
            $query->where('equipment_id', $params['equipment_id']);
        }
        if (!empty($params['metric'])) {
            $query->where('metric', $params['metric']);
        }

        $pageSize = $params['page_size'] ?? 20;
        return $query->orderByDesc('exceed_start')->paginate($pageSize);
    }
}
