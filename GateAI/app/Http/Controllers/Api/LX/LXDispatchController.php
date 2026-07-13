<?php

namespace App\Http\Controllers\Api\LX;

use App\Http\Controllers\Controller;
use App\Http\Requests\LX\LXDispatchRequest;
use App\Services\LX\LXDispatchService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

class LXDispatchController extends Controller
{
    public function __construct(
        protected LXDispatchService $service
    ) {}

    /**
     * 取消执行中指令
     */
    public function cancelCommand(string $command_id): JsonResponse
    {
        $this->service->cancelCommand($command_id);

        return Result::success('指令已取消');
    }

    /**
     * 单孔开度下发
     */
    public function gateExecute(LXDispatchRequest $request): JsonResponse
    {
        $commandId = $this->service->gateExecute($request->validated());

        return Result::success('单孔指令下发成功', ['command_id' => $commandId]);
    }

    /**
     * 批量孔开度下发
     */
    public function gateExecuteBatch(LXDispatchRequest $request): JsonResponse
    {
        $results = $this->service->gateExecuteBatch($request->validated());

        return Result::success('批量指令下发成功', ['commands' => $results]);
    }

    /**
     * 切换手动/自动模式
     */
    public function switchMode(LXDispatchRequest $request): JsonResponse
    {
        $result = $this->service->switchMode($request->validated());

        return Result::success('模式切换成功', $result);
    }
}
