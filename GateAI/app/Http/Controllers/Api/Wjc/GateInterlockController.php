<?php

namespace App\Http\Controllers\Api\Wjc;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wjc\GateInterlockRequest;
use App\Services\Wjc\GateInterlockService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

class GateInterlockController extends Controller
{
    public function __construct(
        protected GateInterlockService $interlockService
    ) {}

    /**
     * 规则列表
     * GET /api/v1/settings/gate-interlock/rules?reservoir_id=1
     */
    public function rules(GateInterlockRequest $request): JsonResponse
    {
        $data = $this->interlockService->getAllRules(
            $request->input('reservoir_id')
        );
        return Result::success('成功', $data);
    }

    /**
     * 更新规则
     * PUT /api/v1/settings/gate-interlock/rules/{id}
     */
    public function updateRule(int $id, GateInterlockRequest $request): JsonResponse
    {
        $rule = $this->interlockService->updateRule($id, $request->validated());
        return Result::success('规则已更新', $rule);
    }

    /**
     * 启用/禁用
     * POST /api/v1/settings/gate-interlock/rules/{id}/toggle
     */
    public function toggleRule(int $id, GateInterlockRequest $request): JsonResponse
    {
        $rule = $this->interlockService->toggleRule(
            $id,
            $request->boolean('enabled')
        );
        $status = $request->boolean('enabled') ? '已启用' : '已禁用';
        return Result::success("规则{$status}", $rule);
    }

    /**
     * 触发日志
     * GET /api/v1/settings/gate-interlock/logs?reservoir_id=1&rule_id=2&start_time=&end_time=
     */
    public function logs(GateInterlockRequest $request): JsonResponse
    {
        $list = $this->interlockService->getRuleLogs($request->all());
        return Result::success('成功', [
            'total' => $list->total(),
            'list'  => $list->items(),
        ]);
    }

    /**
     * 触发统计
     * GET /api/v1/settings/gate-interlock/stats?reservoir_id=1&days=7
     */
    public function stats(GateInterlockRequest $request): JsonResponse
    {
        $stats = $this->interlockService->getRuleStats(
            $request->input('reservoir_id'),
            $request->input('days', 7)
        );
        return Result::success('成功', $stats);
    }

    /**
     * 边缘端上报互锁触发事件
     * POST /api/edge/gate-interlock-logs
     */
    public function receiveLog(GateInterlockRequest $request): JsonResponse
    {
        $log = $this->interlockService->receiveInterlockLog($request->validated());
        return Result::success('上报成功', ['id' => $log->id]);
    }
}
