<?php

namespace App\Http\Controllers\Api\Wjc;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wjc\WjcAlarmRequest;
use App\Services\Wjc\WjcAlarmService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

class WjcAlarmController extends Controller
{
    public function __construct(
        protected WjcAlarmService $alarmService
    ) {}

    /**
     * 3.1 告警详情
     */
    public function show(int $id): JsonResponse
    {
        $detail = $this->alarmService->getAlarmDetail($id);
        return Result::success('操作成功', $detail);
    }

    /**
     * 3.1 正式告警分页列表
     */
    public function index(WjcAlarmRequest $request): JsonResponse
    {
        $list = $this->alarmService->getAlarmList($request->all());
        return Result::success('操作成功', [
            'total' => $list->total(),
            'list'  => $list->items(),
        ]);
    }

    /**
     * 3.2 确认告警
     */
    public function acknowledge(int $id): JsonResponse
    {
        $this->alarmService->acknowledgeAlarm($id, auth()->id());
        return Result::success('确认成功');
    }

    /**
     * 3.3 处置告警
     */
    public function dispose(WjcAlarmRequest $request, int $id): JsonResponse
    {
        $this->alarmService->disposeAlarm($id, $request->input('dispose_note'));
        return Result::success('处置成功');
    }

    /**
     * 3.4 瞬时超限日志
     */
    public function exceedLogs(WjcAlarmRequest $request): JsonResponse
    {
        $logs = $this->alarmService->getExceedLogs($request->all());
        return Result::success('操作成功', [
            'total' => $logs->total(),
            'list'  => $logs->items(),
        ]);
    }
}
