<?php

namespace App\Services\Wjc;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\EdgeNode;
use App\Support\LogHelper;
use Illuminate\Support\Str;

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
        $data['api_secret'] = hash('sha256', Str::random(32));
        $node = EdgeNode::create($data);

        LogHelper::business('边缘节点已注册', [
            'node_id'      => $node->id,
            'code'         => $node->code,
            'reservoir_id' => $node->reservoir_id,
        ], 'info', 'EDGE_NODE_CREATE');

        return $node;
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

        // 仅在状态变更时记录业务日志
        if (! empty($data['status']) && $data['status'] !== $node->getOriginal('status')) {
            LogHelper::business('边缘节点状态变更', [
                'node_id'   => $node->id,
                'code'      => $node->code,
                'status'    => $data['status'],
            ], 'info', 'EDGE_NODE_STATUS');
        }

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

        $result = $node->delete();

        LogHelper::business('边缘节点已删除', [
            'node_id' => $node->id,
            'code'    => $node->code,
        ], 'warning', 'EDGE_NODE_DELETE');

        return $result;
    }
}
