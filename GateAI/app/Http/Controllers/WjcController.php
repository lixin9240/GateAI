<?php

namespace App\Http\Controllers;

use App\Http\Requests\WjcRequest;
use App\Services\WjcService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

class WjcController extends Controller
{
    public function __construct(
        protected WjcService $wjcService
    ) {}

    /**
     * 3.1 正式告警分页列表
     */
    public function index(WjcRequest $request): JsonResponse
    {
        $list = $this->wjcService->getAlarmList($request->all());
        return Result::success('操作成功', [
            'total' => $list->total(),
            'list'  => $list->items(),
        ]);
    }

    /**
     * 3.2 确认告警
     */
    public function acknowledge(WjcRequest $request, int $id): JsonResponse
    {
        $this->wjcService->acknowledgeAlarm($id, $request->user()->id);
        return Result::success('确认成功');
    }

    /**
     * 3.3 处置告警
     */
    public function dispose(WjcRequest $request, int $id): JsonResponse
    {
        $this->wjcService->disposeAlarm($id, $request->input('dispose_note'));
        return Result::success('处置成功');
    }

    /**
     * 3.4 瞬时超限日志
     */
    public function exceedLogs(WjcRequest $request): JsonResponse
    {
        $logs = $this->wjcService->getExceedLogs($request->all());
        return Result::success('操作成功', [
            'total' => $logs->total(),
            'list'  => $logs->items(),
        ]);
    }

    /**
     * 4.1 LSTM预测
     */
    public function predictions(WjcRequest $request): JsonResponse
    {
        $list = $this->wjcService->getPredictionList($request->all());
        return Result::success('操作成功', [
            'total' => $list->total(),
            'list'  => $list->items(),
        ]);
    }

    /**
     * 4.2 调度计划分页列表
     */
    public function plans(WjcRequest $request): JsonResponse
    {
        $list = $this->wjcService->getPlanList($request->all());
        return Result::success('操作成功', [
            'total' => $list->total(),
            'list'  => $list->items(),
        ]);
    }

    /**
     * 4.3 创建调度计划
     */
    public function createPlan(WjcRequest $request): JsonResponse
    {
        $plan = $this->wjcService->createPlan($request->all());
        return Result::success('计划创建成功', $plan);
    }

    /**
     * 4.4 调度决策历史列表
     */
    public function decisions(WjcRequest $request): JsonResponse
    {
        $list = $this->wjcService->getDecisionList($request->all());
        return Result::success('操作成功', [
            'total' => $list->total(),
            'list'  => $list->items(),
        ]);
    }

    /**
     * 4.5 决策详情
     */
    public function decisionDetail(int $id): JsonResponse
    {
        $detail = $this->wjcService->getDecisionDetail($id);
        return Result::success('获取决策详情成功', $detail);
    }

    /**
     * 4.6 执行调度操作
     */
    public function execute(WjcRequest $request): JsonResponse
    {
        $execution = $this->wjcService->executeDispatch(
            $request->all(),
            $request->user()->id
        );

        return Result::success('调度执行成功', $execution);
    }

    /**
     * 4.7 指令追踪
     */
    public function traceCommand(string $command_id): JsonResponse
    {
        $command = $this->wjcService->traceCommand($command_id);
        return Result::success('获取指令追踪成功', $command);
    }

    /**
     * 4.8 闸门动作历史
     */
    public function gateActions(WjcRequest $request): JsonResponse
    {
        $list = $this->wjcService->getGateActions($request->all());
        return Result::success('操作成功', [
            'total' => $list->total(),
            'list'  => $list->items(),
        ]);
    }

    /**
     * 4.9 全局急停
     */
    public function emergencyStop(WjcRequest $request): JsonResponse
    {
        $stop = $this->wjcService->emergencyStop($request->all(), $request->user()->id);
        return Result::success('急停指令已下发', $stop);
    }

    /**
     * 4.10 恢复自动
     */
    public function stopRecover(int $id): JsonResponse
    {
        $this->wjcService->stopRecover($id, request()->user()->id);
        return Result::success('已恢复自动控制');
    }

    /**
     * 4.11 急停日志列表
     */
    public function emergencyStops(WjcRequest $request): JsonResponse
    {
        $list = $this->wjcService->getEmergencyStops($request->all());
        return Result::success('操作成功', [
            'total' => $list->total(),
            'list'  => $list->items(),
        ]);
    }
}
