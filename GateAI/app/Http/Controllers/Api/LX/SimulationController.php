<?php
// 仿真场景控制器
namespace App\Http\Controllers\Api\LX;

use App\Http\Controllers\Controller;
use App\Http\Requests\LX\LXSimulationRequest;
use App\Services\LX\SimulationService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

class SimulationController extends Controller
{
    public function __construct(
        protected SimulationService $service
    ) {}

    public function start(LXSimulationRequest $request): JsonResponse
    {
        $data = $this->service->start($request->validated());

        return Result::success('仿真任务已启动', $data);
    }

    public function result(string $id, LXSimulationRequest $request): JsonResponse
    {
        $data = $this->service->result($id, $request->validated());

        return Result::success('获取仿真结果成功', $data);
    }

    public function pause(string $id): JsonResponse
    {
        $data = $this->service->pause($id);

        return Result::success('仿真任务已暂停', $data);
    }

    public function resume(string $id): JsonResponse
    {
        $data = $this->service->resume($id);

        return Result::success('仿真任务已恢复', $data);
    }

    public function reset(string $id): JsonResponse
    {
        $data = $this->service->reset($id);

        return Result::success('仿真任务已重置', $data);
    }

    public function adjustGate(string $id, LXSimulationRequest $request): JsonResponse
    {
        $data = $this->service->adjustGate($id, $request->validated());

        return Result::success('闸门开度已调节', $data);
    }

    public function report(string $id, LXSimulationRequest $request): JsonResponse
    {
        $data = $this->service->report($id, $request->validated());

        return Result::success('仿真报告生成任务已提交', $data);
    }
}
