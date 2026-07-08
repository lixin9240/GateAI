<?php

namespace App\Services\Gyz;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\SettingsWeight;
use App\Support\LogHelper;
use Illuminate\Support\Facades\DB;

class SettingsWeightService
{
    /**
     * 获取当前权重配置（取启用的最新一条）
     */
    public function current(): ?SettingsWeight
    {
        return SettingsWeight::query()
            ->select([
                'id', 'version', 'enabled', 'power_weight', 'safety_weight',
                'ecology_weight', 'preset_name', 'is_preset',
                'sync_status', 'synced_at', 'synced_nodes', 'description',
                'updated_by', 'updated_at',
            ])
            ->where('enabled', 1)
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * 更新权重配置
     */
    public function update(array $data, int $userId): SettingsWeight
    {
        $total = ($data['power_weight'] ?? 0)
               + ($data['safety_weight'] ?? 0)
               + ($data['ecology_weight'] ?? 0);

        if (abs($total - 1.0) > 0.001) {
            throw new BusinessException('权重之和必须等于 1.0', ResponseCode::PARAM_ERROR);
        }

        $weight = SettingsWeight::query()
            ->where('enabled', 1)
            ->orderByDesc('updated_at')
            ->first();

        if (! $weight) {
            throw new BusinessException('权重配置不存在', ResponseCode::DATA_NOT_FOUND);
        }

        // 如果修改了权重值，更新版本号
        if (isset($data['power_weight']) || isset($data['safety_weight']) || isset($data['ecology_weight'])) {
            $data['version'] = date('YmdHis');
        }
        $data['updated_by'] = $userId;
        $data['sync_status'] = 'pending';

        DB::transaction(function () use ($weight, $data, $userId) {
            $weight->update($data);

            LogHelper::business('权重配置已更新', [
                'weight_id' => $weight->id,
                'user_id'   => $userId,
                'changes'   => $data,
            ], 'info', 'WEIGHT_UPDATE');
        });

        try { broadcast(new \App\Events\LX\ConfigUpdateEvent('weights', (int) now()->timestamp, '权重配置已更新'))->toOthers(); } catch (\Exception $e) {}

        return $weight->fresh();
    }
}
