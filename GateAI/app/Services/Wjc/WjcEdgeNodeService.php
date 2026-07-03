<?php

namespace App\Services\Wjc;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\EdgeNode;

class WjcEdgeNodeService
{
    /**
     * 6.1 获取节点列表
     */
    public function getNodeList(array $params)
    {
        $query = EdgeNode::query()->with('reservoir');

        if (!empty($params['reservoir_id'])) {
            $query->where('reservoir_id', $params['reservoir_id']);
        }
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        $pageSize = $params['page_size'] ?? 20;
        return $query->orderByDesc('id')->paginate($pageSize);
    }

    /**
     * 6.2 节点详情
     */
    public function getNodeDetail(int $id): EdgeNode
    {
        return EdgeNode::with(['reservoir', 'equipment'])->findOrFail($id);
    }

    /**
     * 6.3 注册节点
     */
    public function createNode(array $data): EdgeNode
    {
        return EdgeNode::create($data);
    }

    /**
     * 6.4 心跳上报
     */
    public function heartbeat(int $id, array $data): EdgeNode
    {
        $node = EdgeNode::findOrFail($id);

        $data['last_heartbeat'] = now();

        if (!empty($data['status'])) {
            $node->status = $data['status'];
        }

        $node->update($data);
        return $node;
    }

    /**
     * 6.5 删除节点
     */
    public function deleteNode(int $id): bool
    {
        $node = EdgeNode::findOrFail($id);

        if ($node->equipment()->count() > 0) {
            throw new BusinessException('该节点下挂载了设备，无法删除', ResponseCode::BUSINESS_ERROR);
        }

        return $node->delete();
    }
}
