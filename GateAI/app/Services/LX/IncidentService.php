<?php
// 故障复盘服务
namespace App\Services\LX;

use App\Models\SimulationIncident;
use App\Models\SimulationScenario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class IncidentService
{
    public function list(array $filters): LengthAwarePaginator
    {
        $query = SimulationIncident::query();

        if (! empty($filters['reservoir_id'])) {
            $query->whereHas('equipment', function ($q) use ($filters) {
                $q->where('reservoir_id', $filters['reservoir_id']);
            });
        }
        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (! empty($filters['start_time'])) {
            $query->where('occurred_at', '>=', $filters['start_time']);
        }
        if (! empty($filters['end_time'])) {
            $query->where('occurred_at', '<=', $filters['end_time']);
        }

        return $query->latest('occurred_at')->paginate(
            perPage: (int) ($filters['page_size'] ?? 20),
            page: (int) ($filters['page'] ?? 1)
        );
    }

    public function import(array $data): array
    {
        $importId = 'IMP-' . date('YmdHis') . '-' . strtoupper(Str::random(4));

        $incident = SimulationIncident::create([
            'incident_name' => $data['incident_name'],
            'description'   => $data['description'] ?? null,
            'severity'      => $data['severity'],
            'equipment_id'  => $data['equipment_id'],
            'occurred_at'   => $data['occurred_at'],
            'resolved_at'   => $data['resolved_at'] ?? null,
            'raw_data'      => $data['raw_data'],
            'scenario_config' => $data['scenario_config'] ?? null,
            'import_id'     => $importId,
            'status'        => 'imported',
            'created_by'    => auth()->id(),
        ]);

        if (! empty($data['scenario_config']['auto_run'])) {
            $scenario = SimulationScenario::create([
                'name'            => $data['scenario_config']['name'] ?? ('复盘-' . $data['incident_name']),
                'type'            => 'fault',
                'status'          => 'active',
                'scenario_config' => $data['scenario_config'],
                'created_by'      => auth()->id(),
            ]);

            $incident->update(['replayed_scenario_id' => $scenario->id]);
        }

        return [
            'incident_id' => $incident->id,
            'import_id'   => $importId,
            'status'      => $incident->status,
        ];
    }
}
