<?php

namespace App\Http\Controllers\Api\Wjc;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wjc\WjcEdgeNodeRequest;
use App\Services\Wjc\WjcEdgeNodeService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

class WjcEdgeNodeController extends Controller
{
    public function __construct(
        protected WjcEdgeNodeService $nodeService
    ) {}

    /**
     * 6.1 边缘节点列表
     */
    public function index(WjcEdgeNodeRequest $request): JsonResponse
    {
        $list = $this->nodeService->getNodeList($request->all());
        return Result::success('操作成功', [
            'total' => $list->total(),
            'list'  => $list->items(),
        ]);
    }

    /**
     * 6.2 边缘节点详情
     */
    public function show(int $id): JsonResponse
    {
        $detail = $this->nodeService->getNodeDetail($id);
        return Result::success('操作成功', $detail);
    }

    /**
     * 6.3 注册边缘节点
     */
    public function store(WjcEdgeNodeRequest $request): JsonResponse
    {
        $node = $this->nodeService->createNode($request->all());
        return Result::success('注册成功', $node);
    }

    /**
     * 6.4 心跳上报
     */
    public function heartbeat(WjcEdgeNodeRequest $request, int $id): JsonResponse
    {
        $this->nodeService->heartbeat($id, $request->all());
        return Result::success('心跳更新成功');
    }

    /**
     * 6.5 删除边缘节点
     */
    public function destroy(int $id): JsonResponse
    {
        $this->nodeService->deleteNode($id);
        return Result::success('删除成功');
    }
}
