<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\WjcRequest;
use App\Services\WjcService;
use Illuminate\Http\JsonResponse;

class WjcController extends Controller
{
    protected $alarmService;

    public function __construct(WjcService $alarmService)
    {
        $this->alarmService = $alarmService;
    }

    /**
     * 3.1 正式告警分页列表
     */
    public function index(WjcRequest $request): JsonResponse
    {
        $list = $this->alarmService->getAlarmList($request->all());
        return response()->json([
            'code' => 0,
            'msg' => '操作成功',
            'data' => ['total' => $list->total(), 'list' => $list->items()],
            'success' => true
        ]);
    }

    /**
     * 3.2 确认告警
     */
    public function acknowledge(WjcRequest $request, int $id): JsonResponse
    {
        $result = $this->alarmService->acknowledgeAlarm($id, $request->user()->id);
        
        if (!$result) {
            return response()->json(['code' => 40002, 'msg' => '告警不存在或已处置', 'success' => false], 400);
        }

        return response()->json(['code' => 0, 'msg' => '确认成功', 'success' => true]);
    }

    /**
     * 3.3 处置告警
     */
    public function dispose(WjcRequest $request, int $id): JsonResponse
    {
        $result = $this->alarmService->disposeAlarm($id, $request->input('dispose_note'));
        
        if (!$result) {
            return response()->json(['code' => 30001, 'msg' => '告警不存在', 'success' => false], 404);
        }

        return response()->json(['code' => 0, 'msg' => '处置成功', 'success' => true]);
    }

    /**
     * 3.4 瞬时超限日志
     */
    public function exceedLogs(WjcRequest $request): JsonResponse
    {
        $logs = $this->alarmService->getExceedLogs($request->all());
        return response()->json([
            'code' => 0,
            'msg' => '操作成功',
            'data' => ['total' => $logs->total(), 'list' => $logs->items()],
            'success' => true
        ]);
    }
}