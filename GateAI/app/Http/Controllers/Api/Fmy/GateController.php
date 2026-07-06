<?php

namespace App\Http\Controllers\Api\Fmy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fmy\GateActionListRequest;
use App\Http\Requests\Fmy\GateListRequest;
use App\Services\Fmy\GateService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

/**
 * 监控大屏模块 —— 闸门监控
 */
class GateController extends Controller
{
    public function __construct(
        protected GateService $gateService,
    ) {}

    /**
     * 2.4 闸门列表 + 实时开度状态
     * GET /api/monitoring/gates
     */
    public function index(GateListRequest $request): JsonResponse
    {
        $list = $this->gateService->list(
            $request->input('reservoir_id') ? (int) $request->input('reservoir_id') : null
        );

        return Result::success('操作成功', $list);
    }

    /**
     * 2.5 闸门操作日志
     * GET /api/monitoring/gates/actions
     */
    public function actions(GateActionListRequest $request): JsonResponse
    {
        $data = $this->gateService->actionList($request->validated());

        return Result::success('操作成功', $data);
    }
}
