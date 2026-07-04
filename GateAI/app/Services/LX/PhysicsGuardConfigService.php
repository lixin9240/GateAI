<?php
// 物理防护配置服务
namespace App\Services\LX;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\EdgeNode;
use App\Models\PhysicsGuardConfig;
use Illuminate\Support\Facades\DB;

class PhysicsGuardConfigService
{
    /**
     * 获取某水库当前启用的物理防护配置
     */
    public function getByReservoir(int $reservoirId): PhysicsGuardConfig
    {
        $config = PhysicsGuardConfig::where('reservoir_id', $reservoirId)
            ->where('is_active', 1)
            ->first();

        if (! $config) {
            throw new BusinessException('该水库未配置物理防护参数', ResponseCode::DATA_NOT_FOUND);
        }

        return $config;
    }

    /**
     * 通过边缘节点 → 水库，返回该节点应使用的配置（供边缘端 API 拉取用）
     */
    public function getByEdgeNode(int $edgeNodeId): PhysicsGuardConfig
    {
        $edgeNode = EdgeNode::find($edgeNodeId);

        if (! $edgeNode || ! $edgeNode->reservoir_id) {
            throw new BusinessException('边缘节点未关联水库', ResponseCode::DATA_NOT_FOUND);
        }

        return $this->getByReservoir($edgeNode->reservoir_id);
    }

    /**
     * 更新配置：自动递增版本号，旧版本标记 inactive
     */
    public function updateConfig(int $reservoirId, array $data, int $userId): PhysicsGuardConfig
    {
        $current = PhysicsGuardConfig::where('reservoir_id', $reservoirId)
            ->where('is_active', 1)
            ->first();

        if (! $current) {
            throw new BusinessException('该水库未配置物理防护参数', ResponseCode::DATA_NOT_FOUND);
        }

        $parts = explode('.', $current->config_version);
        $parts[2] = (int) ($parts[2] ?? 0) + 1;
        $newVersion = implode('.', $parts);

        unset($data['id'], $data['reservoir_id'], $data['config_version'], $data['is_active']);
        $data['updated_by'] = $userId;

        $newConfig = DB::transaction(function () use ($current, $data, $newVersion) {
            $current->update(['is_active' => 0]);

            $attrs = $current->only([
                'reservoir_id', 'upstream_danger', 'upstream_emergency', 'upstream_warning',
                'upstream_min', 'ideal_min', 'ideal_max', 'downstream_danger', 'downstream_max',
                'downstream_min', 'eco_flow_min', 'reservoir_area', 'max_level_change_per_hour',
                'shadow_lookahead_steps', 'shadow_danger_offset', 'deadband_percent',
                'max_rate_per_hour', 'fusion_l3_confidence', 'fusion_l3_risk',
                'fusion_l2_confidence', 'fusion_l2_risk', 'gate_max_discharge',
            ]);

            $newData = array_merge($attrs, $data, [
                'config_version' => $newVersion,
                'is_active'      => 1,
                'updated_by'     => $data['updated_by'],
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            return PhysicsGuardConfig::create($newData);
        });

        return $newConfig;
    }

    /**
     * 查询某水库的配置变更历史
     */
    public function getHistory(int $reservoirId): array
    {
        return PhysicsGuardConfig::where('reservoir_id', $reservoirId)
            ->with('updater:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    /**
     * 回滚到历史某一版本
     */
    public function rollback(int $configId): PhysicsGuardConfig
    {
        $target = PhysicsGuardConfig::find($configId);

        if (! $target) {
            throw new BusinessException('配置版本不存在', ResponseCode::DATA_NOT_FOUND);
        }

        $currentActive = PhysicsGuardConfig::where('reservoir_id', $target->reservoir_id)
            ->where('is_active', 1)
            ->first();
        $currentVersion = $currentActive ? $currentActive->config_version : '0.0.0';
        $parts = explode('.', $currentVersion);
        $parts[1] = (int) ($parts[1] ?? 0) + 1;
        $parts[2] = 0;
        $newVersion = implode('.', $parts);

        return DB::transaction(function () use ($target, $currentActive, $newVersion) {
            if ($currentActive) {
                $currentActive->update(['is_active' => 0]);
            }

            $attrs = $target->only([
                'reservoir_id', 'upstream_danger', 'upstream_emergency', 'upstream_warning',
                'upstream_min', 'ideal_min', 'ideal_max', 'downstream_danger', 'downstream_max',
                'downstream_min', 'eco_flow_min', 'reservoir_area', 'max_level_change_per_hour',
                'shadow_lookahead_steps', 'shadow_danger_offset', 'deadband_percent',
                'max_rate_per_hour', 'fusion_l3_confidence', 'fusion_l3_risk',
                'fusion_l2_confidence', 'fusion_l2_risk', 'gate_max_discharge',
            ]);

            return PhysicsGuardConfig::create(array_merge($attrs, [
                'config_version' => $newVersion,
                'is_active'      => 1,
                'description'    => "回滚至 v{$target->config_version}",
                'created_at'     => now(),
                'updated_at'     => now(),
            ]));
        });
    }

    /**
     * 从一个水库复制配置到另一个
     */
    public function cloneConfig(int $fromReservoirId, int $toReservoirId): PhysicsGuardConfig
    {
        $source = PhysicsGuardConfig::where('reservoir_id', $fromReservoirId)
            ->where('is_active', 1)
            ->first();

        if (! $source) {
            throw new BusinessException('源水库未配置物理防护参数', ResponseCode::DATA_NOT_FOUND);
        }

        $targetOld = PhysicsGuardConfig::where('reservoir_id', $toReservoirId)
            ->where('is_active', 1)
            ->first();

        return DB::transaction(function () use ($source, $fromReservoirId, $toReservoirId, $targetOld) {
            if ($targetOld) {
                $targetOld->update(['is_active' => 0]);
            }

            $attrs = $source->only([
                'upstream_danger', 'upstream_emergency', 'upstream_warning',
                'upstream_min', 'ideal_min', 'ideal_max', 'downstream_danger', 'downstream_max',
                'downstream_min', 'eco_flow_min', 'reservoir_area', 'max_level_change_per_hour',
                'shadow_lookahead_steps', 'shadow_danger_offset', 'deadband_percent',
                'max_rate_per_hour', 'fusion_l3_confidence', 'fusion_l3_risk',
                'fusion_l2_confidence', 'fusion_l2_risk', 'gate_max_discharge',
            ]);

            return PhysicsGuardConfig::create(array_merge($attrs, [
                'reservoir_id'   => $toReservoirId,
                'config_version' => '1.0.0',
                'is_active'      => 1,
                'description'    => "从水库 #{$fromReservoirId}（v{$source->config_version}）克隆",
                'created_at'     => now(),
                'updated_at'     => now(),
            ]));
        });
    }

    /**
     * 返回当前配置的 MD5 哈希（供边缘端条件请求比对版本是否变更）
     */
    public function getVersionHash(int $reservoirId): string
    {
        $config = $this->getByReservoir($reservoirId);

        return md5(json_encode($config->toArray()));
    }

    /**
     * 导出供边缘端拉取的完整配置字典
     */
    public function exportForEdge(int $edgeNodeId): array
    {
        $config = $this->getByEdgeNode($edgeNodeId);

        return [
            'version_hash' => md5(json_encode($config->toArray())),
            'config'       => $config->toArray(),
        ];
    }
}
