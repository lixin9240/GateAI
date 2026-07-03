<?php
// 边缘端数据上报控制器
namespace App\Http\Controllers\Api\LX;

use App\Http\Controllers\Controller;
use App\Http\Requests\LX\LXEdgeRequest;
use App\Services\LX\EdgeService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

class EdgeController extends Controller
{
    public function __construct(
        protected EdgeService $service
    ) {}

    public function reportData(LXEdgeRequest $request): JsonResponse
    {
        $data = $this->service->reportMonitoringData($request->validated());

        return Result::success('监测数据上报成功', $data);
    }

    public function reportDecision(LXEdgeRequest $request): JsonResponse
    {
        $data = $this->service->reportDispatchDecision($request->validated());

        return Result::success('调度决策上报成功', $data);
    }

    public function feedback(string $commandId, LXEdgeRequest $request): JsonResponse
    {
        $data = $this->service->reportFeedback($commandId, $request->validated());

        return Result::success('执行回执上报成功', $data);
    }

    public function reportAlarm(LXEdgeRequest $request): JsonResponse
    {
        $data = $this->service->reportAlarm($request->validated());

        return Result::success('告警上报成功', $data);
    }
}
