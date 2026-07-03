<?php

namespace App\Http\Controllers\Api\Fmy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fmy\EquipmentListRequest;
use App\Http\Requests\Fmy\EquipmentRestartRequest;
use App\Http\Requests\Fmy\EquipmentShowRequest;
use App\Http\Requests\Fmy\EquipmentStatusRequest;
use App\Services\Fmy\EquipmentService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

/**
 * 设备管理模块 —— 四层架构：Request → Controller → Service → Model
 * Controller 仅收请求、调 Service、返回 Result，禁止直接操作 DB
 */
class EquipmentController extends Controller
{
    public function __construct(
        protected EquipmentService $equipmentService,
    ) {}

    /**
     * 7.1 设备列表
     * GET /api/equipment
     */
    public function index(EquipmentListRequest $request): JsonResponse
    {
        $data = $this->equipmentService->list($request->validated());
        return Result::success('操作成功', $data);
    }

    /**
     * 7.2 设备详情（含告警 + 最新监测）
     * GET /api/equipment/{id}
     */
    public function show(EquipmentShowRequest $request): JsonResponse
    {
        $id = (int) $request->route('id');
        $data = $this->equipmentService->detail($id);
        return Result::success('操作成功', $data);
    }

    /**
     * 7.3 远程重启设备
     * POST /api/equipment/{id}/restart
     */
    public function restart(int $id, EquipmentRestartRequest $request): JsonResponse
    {
        $result = $this->equipmentService->restart($id, $request->validated());
        return Result::success('重启指令已下发', $result);
    }

    /**
     * 7.4 更新设备状态
     * PUT /api/equipment/{id}/status
     */
    public function updateStatus(EquipmentStatusRequest $request): JsonResponse
    {
        $id = (int) $request->route('id');
        $result = $this->equipmentService->updateStatus(
            $id, $request->input('status'), $request->input('reason')
        );
        return Result::success('状态已更新', $result);
    }

    /**
     * 7.5 导出设备台账
     * GET /api/equipment/export?format=csv|xlsx
     */
    public function export(EquipmentListRequest $request)
    {
        $format = $request->input('format', 'xlsx');
        return $this->equipmentService->export($format, $request->validated());
    }
}
