<?php

namespace App\Services\Wjc;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\Reservoir;
use App\Support\LogHelper;

class WjcReservoirService
{
    /**
     * 5.1 获取水库列表
     */
    public function getReservoirList(array $params)
    {
        $query = Reservoir::query();

        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }
        if (!empty($params['keyword'])) {
            $query->where(function ($q) use ($params) {
                $q->where('name', 'like', "%{$params['keyword']}%")
                  ->orWhere('code', 'like', "%{$params['keyword']}%");
            });
        }

        $pageSize = $params['page_size'] ?? 20;
        return $query->orderByDesc('id')->paginate($pageSize);
    }

    /**
     * 5.2 水库详情
     */
    public function getReservoirDetail(int $id): Reservoir
    {
        return Reservoir::findOrFail($id);
    }

    /**
     * 5.3 新增水库
     */
    public function createReservoir(array $data): Reservoir
    {
        $reservoir = Reservoir::create($data);

        LogHelper::business('水库已创建', [
            'reservoir_id' => $reservoir->id,
            'name'         => $reservoir->name,
            'code'         => $reservoir->code,
        ], 'info', 'RESERVOIR_CREATE');

        return $reservoir;
    }

    /**
     * 5.4 更新水库
     */
    public function updateReservoir(int $id, array $data): Reservoir
    {
        $reservoir = Reservoir::findOrFail($id);
        $reservoir->update($data);

        LogHelper::business('水库信息已更新', [
            'reservoir_id' => $id,
            'name'         => $reservoir->name,
            'changes'      => $data,
        ], 'info', 'RESERVOIR_UPDATE');

        return $reservoir;
    }

    /**
     * 5.5 删除水库
     */
    public function deleteReservoir(int $id): bool
    {
        $reservoir = Reservoir::findOrFail($id);

        $relatedCount = $reservoir->edgeNodes()->count() + $reservoir->equipment()->count();
        if ($relatedCount > 0) {
            throw new BusinessException('该水库下存在关联的设备或节点，无法删除', ResponseCode::BUSINESS_ERROR);
        }

        $result = $reservoir->delete();

        LogHelper::business('水库已删除', [
            'reservoir_id' => $reservoir->id,
            'name'         => $reservoir->name,
        ], 'warning', 'RESERVOIR_DELETE');

        return $result;
    }
}
