<?php

namespace App\Http\Controllers\Api\Wjc;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wjc\WjcReservoirRequest;
use App\Services\Wjc\WjcReservoirService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

class WjcReservoirController extends Controller
{
    public function __construct(
        protected WjcReservoirService $reservoirService
    ) {}

    /**
     * 5.1 水库列表
     */
    public function index(WjcReservoirRequest $request): JsonResponse
    {
        $list = $this->reservoirService->getReservoirList($request->all());
        return Result::success('操作成功', [
            'total' => $list->total(),
            'list'  => $list->items(),
        ]);
    }

    /**
     * 5.2 水库详情
     */
    public function show(int $id): JsonResponse
    {
        $detail = $this->reservoirService->getReservoirDetail($id);
        return Result::success('操作成功', $detail);
    }

    /**
     * 5.3 新增水库
     */
    public function store(WjcReservoirRequest $request): JsonResponse
    {
        $reservoir = $this->reservoirService->createReservoir($request->all());
        return Result::success('创建成功', $reservoir);
    }

    /**
     * 5.4 更新水库
     */
    public function update(WjcReservoirRequest $request, int $id): JsonResponse
    {
        $this->reservoirService->updateReservoir($id, $request->all());
        return Result::success('更新成功');
    }

    /**
     * 5.5 删除水库
     */
    public function destroy(int $id): JsonResponse
    {
        $this->reservoirService->deleteReservoir($id);
        return Result::success('删除成功');
    }
}
