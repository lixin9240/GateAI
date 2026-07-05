<?php

namespace App\Http\Controllers\Api\LX;

use App\Http\Controllers\Controller;
use App\Http\Requests\LX\LXPhysicsGuardRequest;
use App\Services\LX\PhysicsGuardConfigService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

class PhysicsGuardConfigController extends Controller
{
    public function __construct(
        protected PhysicsGuardConfigService $service
    ) {}

    /**
     * 获取物理防护配置
     */
    public function show(LXPhysicsGuardRequest $request): JsonResponse
    {
        $reservoirId = (int) $request->validated('reservoir_id', 1);

        return Result::success('获取成功', $this->service->getByReservoir($reservoirId));
    }

    /**
     * 更新物理防护配置（自动版本递增）
     */
    public function update(int $id, LXPhysicsGuardRequest $request): JsonResponse
    {
        $config = $this->service->getByReservoir(
            (int) $request->query('reservoir_id', 1)
        );

        $updated = $this->service->updateConfig(
            $config->reservoir_id,
            $request->validated(),
            (int) auth('api')->id()
        );

        return Result::success('物理防护配置更新成功', $updated);
    }

    /**
     * 物理防护配置变更历史
     */
    public function history(LXPhysicsGuardRequest $request): JsonResponse
    {
        $reservoirId = (int) $request->validated('reservoir_id', 1);

        return Result::success('获取成功', $this->service->getHistory($reservoirId));
    }

    /**
     * 回滚到历史版本
     */
    public function rollback(int $id): JsonResponse
    {
        $config = $this->service->rollback($id);

        return Result::success("已回滚至 v{$config->config_version}", $config);
    }

    /**
     * 跨水库复制物理防护配置
     */
    public function cloneConfig(LXPhysicsGuardRequest $request): JsonResponse
    {
        $config = $this->service->cloneConfig(
            (int) $request->validated('from_reservoir_id'),
            (int) $request->validated('to_reservoir_id')
        );

        return Result::success('配置克隆成功', $config);
    }
}
