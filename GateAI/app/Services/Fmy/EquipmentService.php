<?php

namespace App\Services\Fmy;

use App\Models\Alarm;
use App\Models\ControlCommand;
use App\Models\Equipment;
use App\Models\EquipmentStatusLog;
use App\Models\MonitoringData;
use App\Support\LogHelper;
use Illuminate\Support\Facades\Log;

class EquipmentService
{
    /**
     * 7.1 设备分页列表
     */
    public function list(array $params): array
    {
        Log::channel('business')->info('设备管理-查询设备列表', [
            'params' => [
                'reservoir_id' => $params['reservoir_id'] ?? null,
                'type'         => $params['type'] ?? null,
                'status'       => $params['status'] ?? null,
                'keyword'      => isset($params['keyword']) ? substr($params['keyword'], 0, 50) : null,
                'page'         => $params['page'] ?? 1,
                'page_size'    => $params['page_size'] ?? 20,
            ],
            'user_id'  => auth()->id(),
            'trace_id' => request()->attributes->get('trace_id'),
        ]);

        $query = Equipment::query()->with('reservoir:id,name');

        if (!empty($params['reservoir_id'])) {
            $query->where('reservoir_id', $params['reservoir_id']);
        }
        if (!empty($params['type'])) {
            $query->where('type', $params['type']);
        }
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }
        if (!empty($params['keyword'])) {
            $keyword = $params['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%");
            });
        }

        $pageSize = $params['page_size'] ?? 20;
        $paginator = $query->orderBy('id')->paginate($pageSize);

        $list = collect($paginator->items())->map(fn (Equipment $eq) => [
            'id'             => $eq->id,
            'name'           => $eq->name,
            'code'           => $eq->code,
            'type'           => $eq->type,
            'reservoir_id'   => $eq->reservoir_id,
            'reservoir_name' => $eq->reservoir?->name,
            'status'         => $eq->status,
            'manufacturer'   => $eq->manufacturer,
            'model'          => $eq->model,
            'health_score'   => $eq->health_score,
            'last_online'    => $eq->last_online?->toDateTimeString(),
        ]);

        return [
            'total' => $paginator->total(),
            'list'  => $list,
        ];
    }

    /**
     * 7.2 设备详情（含告警 + 最新监测）
     */
    public function detail(int $id): array
    {
        Log::channel('business')->info('设备管理-查看设备详情', [
            'equipment_id' => $id,
            'user_id'      => auth()->id(),
            'trace_id'     => request()->attributes->get('trace_id'),
        ]);

        $eq = Equipment::with(['reservoir:id,name', 'edgeNode:id,name,ip,cpu_usage,memory_usage'])
            ->findOrFail($id);

        // 当前告警（未处理+已确认）
        $alarms = Alarm::where('equipment_id', $id)
            ->whereIn('status', ['unhandled', 'acknowledged'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (Alarm $a) => [
                'id'              => $a->id,
                'alarm_no'        => $a->alarm_no,
                'equipment_id'    => $a->equipment_id,
                'reservoir_id'    => $a->reservoir_id,
                'type'            => $a->type,
                'level'           => $a->level,
                'message'         => $a->message,
                'metric_value'    => $a->metric_value,
                'threshold_value' => $a->threshold_value,
                'status'          => $a->status,
                'acknowledged_by' => $a->acknowledged_by,
                'acknowledged_at' => $a->acknowledged_at?->toDateTimeString(),
                'created_at'      => $a->created_at?->toDateTimeString(),
            ]);

        // 最新监测数据
        $latestMonitor = MonitoringData::where('edge_node_id', $eq->edge_node_id)
            ->orderByDesc('timestamp')
            ->first(['reservoir_id', 'edge_node_id', 'upstream_level', 'downstream_level',
                     'water_head', 'inflow_rate', 'outflow_rate', 'gate_opening',
                     'power_output', 'cumulative_energy', 'data_source', 'is_anomaly', 'timestamp']);

        return [
            'id'                    => $eq->id,
            'name'                  => $eq->name,
            'code'                  => $eq->code,
            'type'                  => $eq->type,
            'reservoir_id'          => $eq->reservoir_id,
            'reservoir_name'        => $eq->reservoir?->name,
            'status'                => $eq->status,
            'location'              => $eq->location,
            'manufacturer'          => $eq->manufacturer,
            'model'                 => $eq->model,
            'serial_number'         => $eq->serial_number,
            'purchase_date'         => $eq->purchase_date?->toDateString(),
            'warranty_expire'       => $eq->warranty_expire?->toDateString(),
            'specs'                 => $eq->specs,
            'health_score'          => $eq->health_score,
            'tags'                  => $eq->tags,
            'edge_node_id'          => $eq->edge_node_id,
            'edge_node'             => $eq->edgeNode ? [
                'id'   => $eq->edgeNode->id,
                'name' => $eq->edgeNode->name,
                'ip'   => $eq->edgeNode->ip,
                'cpu_usage'    => $eq->edgeNode->cpu_usage,
                'memory_usage' => $eq->edgeNode->memory_usage,
            ] : null,
            'communication_protocol'=> $eq->communication_protocol,
            'heartbeat_interval'    => $eq->heartbeat_interval,
            'offline_threshold'     => $eq->offline_threshold,
            'firmware_version'      => $eq->firmware_version,
            'maintenance_count'     => $eq->maintenance_count,
            'last_maintenance_at'   => $eq->last_maintenance_at?->toDateTimeString(),
            'next_maintenance_at'   => $eq->next_maintenance_at?->toDateTimeString(),
            'total_runtime'         => $eq->total_runtime,
            'ip_address'            => $eq->ip_address,
            'port'                  => $eq->port,
            'last_online'           => $eq->last_online?->toDateTimeString(),
            'alarms'                => $alarms,
            'latest_monitor'        => $latestMonitor ? [
                'reservoir_id'      => $latestMonitor->reservoir_id,
                'edge_node_id'      => $latestMonitor->edge_node_id,
                'upstream_level'    => $latestMonitor->upstream_level,
                'downstream_level'  => $latestMonitor->downstream_level,
                'water_head'        => $latestMonitor->water_head,
                'inflow_rate'       => $latestMonitor->inflow_rate,
                'outflow_rate'      => $latestMonitor->outflow_rate,
                'gate_opening'      => $latestMonitor->gate_opening,
                'power_output'      => $latestMonitor->power_output,
                'cumulative_energy' => $latestMonitor->cumulative_energy,
                'data_source'       => $latestMonitor->data_source,
                'is_anomaly'        => $latestMonitor->is_anomaly,
                'timestamp'         => $latestMonitor->timestamp?->toDateTimeString(),
            ] : null,
            'created_at'            => $eq->created_at?->toDateTimeString(),
            'updated_at'            => $eq->updated_at?->toDateTimeString(),
        ];
    }

    /**
     * 7.3 远程重启设备 —— 生成指令并写入 control_commands
     */
    public function restart(int $id, array $data): array
    {
        $eq = Equipment::findOrFail($id);

        if (!$eq->edge_node_id) {
            throw new \App\Exceptions\BusinessException('设备未关联边缘节点，无法下发重启指令', \App\Enums\ResponseCode::OPERATION_FORBIDDEN);
        }

        $commandId = 'RST-' . now()->format('YmdHis') . '-' . str_pad($id, 4, '0', STR_PAD_LEFT);
        $traceId = request()->attributes->get('trace_id', (string) \Illuminate\Support\Str::uuid());
        $nonce = \Illuminate\Support\Str::random(32);
        $expireAt = now()->addMinutes(5);

        // 写入指令表
        ControlCommand::create([
            'command_id'       => $commandId,
            'trace_id'         => $traceId,
            'edge_node_id'     => $eq->edge_node_id,
            'operator_id'      => request()->user()?->id,
            'command_type'     => 'equipment_restart',
            'payload'          => [
                'equipment_id'   => $id,
                'equipment_code' => $eq->code,
                'force'          => $data['force'] ?? false,
                'delay_seconds'  => (int) ($data['delay'] ?? 0),
                'reason'         => $data['reason'] ?? '',
            ],
            'target_equipment' => $eq->code,
            'target_opening'   => 0,
            'sign'             => hash_hmac('sha256', $commandId . $nonce, config('app.key')),
            'nonce'            => $nonce,
            'expire_at'        => $expireAt,
            'status'           => 'pending',
            'sent_at'          => now(),
        ]);

        LogHelper::business('远程重启设备', [
            'equipment_id' => $id,
            'code'         => $eq->code,
            'force'        => $data['force'] ?? false,
            'delay'        => (int) ($data['delay'] ?? 0),
            'reason'       => $data['reason'] ?? '',
            'command_id'   => $commandId,
        ], 'info', 'EQUIPMENT');

        return [
            'command_id' => $commandId,
            'status'     => 'pending',
        ];
    }

    /**
     * 7.4 更新设备状态
     */
    public function updateStatus(int $id, string $newStatus, ?string $reason): array
    {
        $eq = Equipment::findOrFail($id);
        $previousStatus = $eq->status;

        $eq->update(['status' => $newStatus]);

        // 写入状态变更日志
        EquipmentStatusLog::create([
            'equipment_id'    => $id,
            'previous_status' => $previousStatus,
            'current_status'  => $newStatus,
            'reason'          => $reason,
            'operator'        => request()->user()?->account,
            'changed_at'      => now(),
            'changed_by'      => request()->user()?->id,
            'ip_address'      => request()->ip(),
        ]);

        LogHelper::business('更新设备状态', [
            'equipment_id'    => $id,
            'code'            => $eq->code,
            'previous_status' => $previousStatus,
            'current_status'  => $newStatus,
            'reason'          => $reason,
        ], 'info', 'EQUIPMENT');

        return [
            'id'              => $eq->id,
            'previous_status' => $previousStatus,
            'current_status'  => $newStatus,
            'changed_at'      => now()->toDateTimeString(),
        ];
    }

    /**
     * 7.5 导出设备台账 —— 查询数据 + 调用 ExportService 生成文件
     */
    public function export(string $format, array $params): \Illuminate\Http\Response
    {
        LogHelper::business('设备管理-导出设备台账', [
            'format'       => $format,
            'reservoir_id' => $params['reservoir_id'] ?? null,
            'type'         => $params['type'] ?? null,
            'status'       => $params['status'] ?? null,
        ], 'info', 'EXPORT');

        $query = Equipment::with('reservoir:id,name');

        if (!empty($params['reservoir_id'])) {
            $query->where('reservoir_id', $params['reservoir_id']);
        }
        if (!empty($params['type'])) {
            $query->where('type', $params['type']);
        }
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        $items = $query->orderBy('id')->get();

        $headers = ['设备名称', '设备编号', '类型', '所属水库', '水库ID', '状态',
                    '制造商', '型号', '序列号', '健康评分', '安装位置', '最后在线时间',
                    '通信协议', '固件版本', 'IP地址', '端口'];

        $rows = $items->map(fn (Equipment $eq) => [
            $eq->name,
            $eq->code,
            $eq->type,
            $eq->reservoir?->name ?? '',
            $eq->reservoir_id,
            $eq->status,
            $eq->manufacturer ?? '',
            $eq->model ?? '',
            $eq->serial_number ?? '',
            $eq->health_score ?? '',
            $eq->location ?? '',
            $eq->last_online?->toDateTimeString() ?? '',
            $eq->communication_protocol ?? '',
            $eq->firmware_version ?? '',
            $eq->ip_address ?? '',
            $eq->port ?? '',
        ]);

        $filename = 'equipment_' . now()->format('Ymd');

        return $format === 'csv'
            ? app(ExportService::class)->csv($headers, $rows->toArray(), $filename)
            : app(ExportService::class)->xlsx($headers, $rows->toArray(), $filename);
    }
}
