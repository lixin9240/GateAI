<?php

namespace App\Services;

use App\Models\Alarm;
use App\Models\AlarmExceedLog;
use Illuminate\Support\Facades\DB;
use Exception;

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
    public function acknowledgeAlarm(int $id, int $userId): bool
    {
        $alarm = Alarm::find($id);
        if (!$alarm || $alarm->status === 'disposed') {
            return false;
        }

        return $alarm->update([
            'status' => 'acknowledged',
            'acknowledged_by' => $userId,
            'acknowledged_at' => now(),
        ]);
    }

    /**
     * 处置告警
     */
    public function disposeAlarm(int $id, string $note): bool
    {
        $alarm = Alarm::find($id);
        if (!$alarm) {
            return false;
        }

        return $alarm->update([
            'status' => 'disposed',
            'disposed_note' => $note,
        ]);
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
}