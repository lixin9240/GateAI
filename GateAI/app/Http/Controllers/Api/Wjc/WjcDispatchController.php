<?php

namespace App\Http\Controllers\Api\Wjc;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wjc\WjcDispatchRequest;
use App\Services\Wjc\WjcDispatchService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

class WjcDispatchController extends Controller
{
    public function __construct(
        protected WjcDispatchService $dispatchService
    ) {}

    /**
     * 4.1 LSTM 预测数据
     */
    public function predictions(WjcDispatchRequest $request): JsonResponse
    {
        $data = $this->dispatchService->getPredictions(
            $request->input('reservoir_id'),
            $request->input('predict_term')
        );
        return Result::success('成功', $data);
    }

    /**
     * 4.2 AI 决策详情
     */
    public function decisionDetail(int $id): JsonResponse
    {
        $data = $this->dispatchService->getDecisionDetail($id);
        return Result::success('成功', $data);
    }

    /**
     * 4.3 调度决策历史列表
     */
    public function decisions(WjcDispatchRequest $request): JsonResponse
    {
        $list = $this->dispatchService->getDecisionHistory($request->all());
        return Result::success('成功', [
            'total' => $list->total(),
            'list'  => $list->items(),
        ]);
    }

    /**
     * 4.4 人工下发调度指令
     */
    public function execute(WjcDispatchRequest $request): JsonResponse
    {
        $commandId = $this->dispatchService->executeDispatch($request->all());
        return Result::success('指令下发成功', ['command_id' => $commandId]);
    }

    /**
     * 4.5 指令追踪
     */
    public function traceCommand(string $command_id): JsonResponse
    {
        $command = $this->dispatchService->traceCommand($command_id);
        return Result::success('获取指令追踪成功', $command);
    }

    /**
     * 4.6 闸门动作历史
     */
    public function gateActions(WjcDispatchRequest $request): JsonResponse
    {
        $list = $this->dispatchService->getGateActions($request->all());
        return Result::success('成功', [
            'total' => $list->total(),
            'list'  => $list->items(),
        ]);
    }

    /**
     * 4.7 全局急停
     */
    public function emergencyStop(WjcDispatchRequest $request): JsonResponse
    {
        $result = $this->dispatchService->emergencyStop($request->all());
        return Result::success('急停指令已触发', $result);
    }

    /**
     * 4.8 恢复闸门自动模式
     */
    public function stopRecover(int $id): JsonResponse
    {
        $this->dispatchService->recoverFromStop($id);
        return Result::success('恢复成功');
    }

    /**
     * 4.9 急停日志列表
     */
    public function emergencyStops(WjcDispatchRequest $request): JsonResponse
    {
        $list = $this->dispatchService->getEmergencyStops($request->all());
        return Result::success('成功', [
            'total' => $list->total(),
            'list'  => $list->items(),
        ]);
    }
}
