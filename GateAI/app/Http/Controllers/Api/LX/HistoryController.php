<?php
// 历史查询控制器
namespace App\Http\Controllers\Api\LX;

use App\Http\Controllers\Controller;
use App\Http\Requests\LX\LXHistoryRequest;
use App\Services\LX\HistoryService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

class HistoryController extends Controller
{
    public function __construct(
        protected HistoryService $service
    ) {}

    public function data(LXHistoryRequest $request): JsonResponse
    {
        $data = $this->service->queryData($request->validated());

        return Result::success('查询历史数据成功', $data);
    }

    public function export(LXHistoryRequest $request): JsonResponse
    {
        $data = $this->service->export($request->validated());

        return Result::success('导出任务已提交', $data);
    }

    public function exportStatus(string $taskId): JsonResponse
    {
        $data = $this->service->exportStatus($taskId);

        return Result::success('查询导出任务成功', $data);
    }
}
