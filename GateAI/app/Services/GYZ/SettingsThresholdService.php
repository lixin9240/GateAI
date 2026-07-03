<?php

namespace App\Services\Gyz;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\SettingsThreshold;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettingsThresholdService
{
    /**
     * 获取阈值列表
     */
    public function list(?int $reservoirId, ?string $metric): array
    {
        $query = SettingsThreshold::query()
            ->select([
                'id', 'reservoir_id', 'metric', 'equipment_type',
                'warning_upper', 'warning_lower', 'critical_upper', 'critical_lower',
                'debounce_seconds', 'enabled',
            ]);

        if ($reservoirId !== null) {
            $query->where('reservoir_id', $reservoirId);
        }

        if ($metric !== null) {
            $query->where('metric', $metric);
        }

        return $query->get()->toArray();
    }

    /**
     * 更新阈值配置
     */
    public function update(int $id, array $data, int $userId): SettingsThreshold
    {
        $threshold = SettingsThreshold::find($id);

        if (! $threshold) {
            throw new BusinessException('阈值配置不存在', ResponseCode::DATA_NOT_FOUND);
        }

        $data['updated_by'] = $userId;

        DB::transaction(function () use ($threshold, $data) {
            $threshold->update($data);

            Log::channel('business')->info('阈值配置已更新', [
                'threshold_id' => $threshold->id,
                'user_id'      => $userId,
                'changes'      => $data,
            ]);
        });

        return $threshold->fresh();
    }
}
