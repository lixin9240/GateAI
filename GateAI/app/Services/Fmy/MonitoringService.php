<?php

namespace App\Services\Fmy;

use App\Models\Equipment;
use App\Models\MonitoringData;

class MonitoringService
{
    /**
     * 2.1 获取全部设备列表（监控大屏）
     */
    public function getEquipmentAllList(?int $reservoirId): array
    {
        $query = Equipment::query()
            ->with('edgeNode:id,cpu_usage,memory_usage')
            ->select(['id', 'name', 'type', 'status', 'edge_node_id', 'last_online']);

        if ($reservoirId) {
            $query->where('reservoir_id', $reservoirId);
        }

        return $query->orderBy('id')->get()->map(function (Equipment $eq) {
            return [
                'id'           => $eq->id,
                'name'         => $eq->name,
                'type'         => $eq->type,
                'status'       => $eq->status,
                'cpu_usage'    => $eq->isEdgeGateway() ? optional($eq->edgeNode)->cpu_usage : null,
                'memory_usage' => $eq->isEdgeGateway() ? optional($eq->edgeNode)->memory_usage : null,
                'last_online'  => $eq->last_online?->toDateTimeString(),
            ];
        })->toArray();
    }

    /**
     * 2.2 实时采集数据
     */
    public function getRealtimeData(int $reservoirId, ?int $equipmentId): array
    {
        $query = MonitoringData::where('reservoir_id', $reservoirId);

        if ($equipmentId) {
            $equipment = Equipment::findOrFail($equipmentId);
            $query->where('edge_node_id', $equipment->edge_node_id);
        }

        $record = $query->orderByDesc('timestamp')->first();

        if (!$record) {
            return [];
        }

        return [
            'upstream_level'   => $record->upstream_level,
            'downstream_level' => $record->downstream_level,
            'water_head'       => $record->water_head,
            'inflow_rate'      => $record->inflow_rate,
            'outflow_rate'     => $record->outflow_rate,
            'gate_opening'     => $record->gate_opening,
            'power_output'     => $record->power_output,
            'timestamp'        => $record->timestamp->toDateTimeString(),
        ];
    }

    /**
     * 2.3 趋势图表数据
     */
    public function getTrendData(int $reservoirId, string $range, string $dataType): array
    {
        $startTime = match ($range) {
            '1h'  => now()->subHour(),
            '6h'  => now()->subHours(6),
            '24h' => now()->subDay(),
            default => now()->subHour(),
        };

        $column = MonitoringData::columnByDataType($dataType);

        $records = MonitoringData::where('reservoir_id', $reservoirId)
            ->where('timestamp', '>=', $startTime)
            ->orderBy('timestamp')
            ->limit(1000)
            ->get(['timestamp', $column]);

        return $records->map(function (MonitoringData $row) use ($column) {
            return [
                'timestamp' => $row->timestamp->toDateTimeString(),
                'value'     => (float) $row->{$column},
            ];
        })->toArray();
    }
}
