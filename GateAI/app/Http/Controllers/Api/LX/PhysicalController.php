<?php

namespace App\Http\Controllers\Api\LX;

use App\Http\Controllers\Controller;
use App\Http\Requests\LX\LXPhysicalRequest;
use App\Services\LX\PhysicalService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

class PhysicalController extends Controller
{
    public function __construct(
        protected PhysicalService $service
    ) {}

    public function edgeConfig(int $reservoirId): JsonResponse
    {
        $data = $this->service->edgeConfig($reservoirId);

        return Result::success('获取物理参数成功', $data);
    }

    public function index(LXPhysicalRequest $request): JsonResponse
    {
        $data = $this->service->list($request->validated());

        return Result::success('获取物理参数列表成功', [
            'total' => $data->total(),
            'list'  => $data->items(),
        ]);
    }

    public function upsert(LXPhysicalRequest $request): JsonResponse
    {
        $data = $this->service->upsert($request->validated());

        return Result::success('保存物理参数成功', $data);
    }

    public function delete(int $id): JsonResponse
    {
        $this->service->delete($id);

        return Result::success('删除物理参数成功');
    }
}
