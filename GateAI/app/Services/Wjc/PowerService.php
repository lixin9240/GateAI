<?php

namespace App\Services\Wjc;

use App\Models\MonitoringData;
use App\Models\PowerUnit;
use Illuminate\Support\Facades\DB;

class PowerService
{
    /**
     * 发电机组列表 + 实时出力
     */
    public function getUnits(?int $reservoirId = null): array
    {
        $query = PowerUnit::query()->with('reservoir:id,name');

        if ($reservoirId !== null) {
            $query->where('reservoir_id', $reservoirId);
        }

        $units = $query->orderBy('id')->get();

        // 同步最新出力：从 monitoring_data 取每个水库最新的 power_output
        $reservoirIds = $units->pluck('reservoir_id')->unique()->toArray();
        $latestOutputs = [];
        if (!empty($reservoirIds)) {
            $subQuery = MonitoringData::query()
                ->select('reservoir_id', DB::raw('MAX(timestamp) as max_ts'))
                ->whereIn('reservoir_id', $reservoirIds)
                ->groupBy('reservoir_id');

            $latest = MonitoringData::query()
                ->joinSub($subQuery, 'latest', function ($join) {
                    $join->on('monitoring_data.reservoir_id', '=', 'latest.reservoir_id')
                        ->on('monitoring_data.timestamp', '=', 'latest.max_ts');
                })
                ->select('monitoring_data.reservoir_id', 'monitoring_data.power_output')
                ->get();

            foreach ($latest as $row) {
                $latestOutputs[$row->reservoir_id] = $row->power_output;
            }
        }

        // 按水库均分出力到各机组
        $result = [];
        $reservoirUnitCounts = $units->groupBy('reservoir_id')->map->count();

        foreach ($units as $unit) {
            $totalOutput = $latestOutputs[$unit->reservoir_id] ?? 0;
            $count = $reservoirUnitCounts[$unit->reservoir_id] ?? 1;
            $perUnitOutput = round($totalOutput / $count, 2);

            $result[] = [
                'id'                 => $unit->id,
                'reservoir_id'       => $unit->reservoir_id,
                'reservoir_name'     => $unit->reservoir->name ?? '',
                'name'               => $unit->name,
                'code'               => $unit->code,
                'type'               => $unit->type,
                'installed_capacity' => (float) $unit->installed_capacity,
                'status'             => $unit->status,
                'current_output'     => $perUnitOutput,
                'manufacturer'       => $unit->manufacturer,
                'model'              => $unit->model,
                'commission_date'    => $unit->commission_date?->toDateString(),
                'utilization_rate'   => $unit->installed_capacity > 0
                    ? round($perUnitOutput / (float) $unit->installed_capacity * 100, 1)
                    : 0,
            ];
        }

        return $result;
    }

    /**
     * 发电出力趋势
     */
    public function getTrend(array $params): array
    {
        $reservoirId = $params['reservoir_id'] ?? null;
        $startTime   = $params['start_time'] ?? now()->subDays(7)->toDateTimeString();
        $endTime     = $params['end_time'] ?? now()->toDateTimeString();
        $granularity = $params['granularity'] ?? 'hour';

        $query = MonitoringData::query()
            ->with('reservoir:id,name')
            ->whereBetween('timestamp', [$startTime, $endTime])
            ->where('is_anomaly', false);

        if ($reservoirId !== null) {
            $query->where('reservoir_id', $reservoirId);
        }

        $format = $granularity === 'day' ? '%Y-%m-%d' : '%Y-%m-%d %H:00';
        $label  = 'time_label';

        $rows = $query
            ->selectRaw("DATE_FORMAT(timestamp, '{$format}') as {$label}")
            ->selectRaw('reservoir_id')
            ->selectRaw('AVG(power_output) as avg_power')
            ->selectRaw('MAX(power_output) as max_power')
            ->selectRaw('MIN(power_output) as min_power')
            ->selectRaw('SUM(cumulative_energy) as total_energy')
            ->groupBy('reservoir_id', DB::raw("DATE_FORMAT(timestamp, '{$format}')"))
            ->orderBy($label)
            ->get();

        // 按水库分组
        $grouped = $rows->groupBy('reservoir_id');

        $series = [];
        foreach ($grouped as $rid => $items) {
            $reservoirName = $items->first()->reservoir->name ?? "水库#{$rid}";
            $series[] = [
                'reservoir_id'   => $rid,
                'reservoir_name' => $reservoirName,
                'data'           => $items->map(fn($r) => [
                    'time'       => $r->time_label,
                    'avg_power'  => round((float) $r->avg_power, 2),
                    'max_power'  => round((float) $r->max_power, 2),
                    'min_power'  => round((float) $r->min_power, 2),
                    'total_energy' => round((float) $r->total_energy, 2),
                ])->values()->toArray(),
            ];
        }

        return [
            'granularity' => $granularity,
            'start_time'  => $startTime,
            'end_time'    => $endTime,
            'series'      => $series,
        ];
    }
}
