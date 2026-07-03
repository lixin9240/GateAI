<?php

namespace App\Http\Controllers\Api\Fmy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fmy\AllListRequest;
use App\Http\Requests\Fmy\RealtimeRequest;
use App\Http\Requests\Fmy\TrendRequest;
use App\Services\Fmy\MonitoringService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

/**
 * 监控大屏模块 —— 设备列表、实时数据、趋势图表
 */
class MonitorController extends Controller
{
    public function __construct(
        protected MonitoringService $monitoringService,
    ) {}

    /**
     * 2.1 获取全部设备列表
     * GET /api/equipment/all-list
     */
    public function allList(AllListRequest $request): JsonResponse
    {
        $list = $this->monitoringService->getEquipmentAllList($request->input('reservoir_id'));
        return Result::success('操作成功', $list);
    }

    /**
     * 2.2 实时采集数据
     * GET /api/monitoring/realtime
     */
    public function realtime(RealtimeRequest $request): JsonResponse
    {
        $data = $this->monitoringService->getRealtimeData(
            (int) $request->input('reservoir_id'),
            $request->input('equipment_id') ? (int) $request->input('equipment_id') : null
        );
        return Result::success('操作成功', $data);
    }

    /**
     * 2.3 趋势图表数据
     * GET /api/monitoring/trend
     */
    public function trend(TrendRequest $request): JsonResponse
    {
        $list = $this->monitoringService->getTrendData(
            (int) $request->input('reservoir_id'),
            $request->input('range'),
            $request->input('data_type')
        );
        return Result::success('操作成功', $list);
    }
}
