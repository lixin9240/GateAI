<?php
// 仿真场景服务
namespace App\Services\LX;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\SimulationScenario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ScenarioService
{
    public function list(array $filters): LengthAwarePaginator
    {
        $query = SimulationScenario::with(['creator', 'updater']);

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['keyword'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['keyword']}%")
                  ->orWhere('description', 'like', "%{$filters['keyword']}%");
            });
        }

        return $query->latest()->paginate(
            perPage: (int) ($filters['page_size'] ?? 20),
            page: (int) ($filters['page'] ?? 1)
        );
    }

    public function create(array $data): SimulationScenario
    {
        return SimulationScenario::create([
            'name'            => $data['name'],
            'type'            => $data['type'],
            'description'     => $data['description'] ?? null,
            'status'          => $data['status'] ?? 'draft',
            'model_id'        => $data['model_id'] ?? null,
            'scenario_config'  => $data['scenario_config'] ?? null,
            'duration'        => $data['duration'] ?? 3600,
            'speed'           => $data['speed'] ?? 1.0,
            'created_by'      => auth()->id(),
        ]);
    }

    public function update(int $id, array $data): SimulationScenario
    {
        $scenario = SimulationScenario::findOrFail($id);

        $fillable = [
            'name', 'type', 'description', 'status',
            'model_id', 'scenario_config', 'duration', 'speed',
        ];

        foreach ($fillable as $field) {
            if (array_key_exists($field, $data)) {
                $scenario->{$field} = $data[$field];
            }
        }

        $scenario->updated_by = auth()->id();
        $scenario->save();

        return $scenario->fresh();
    }

    public function delete(int $id): void
    {
        $scenario = SimulationScenario::findOrFail($id);

        if ($scenario->usage_count > 0) {
            throw new BusinessException('该场景已有仿真记录，不可删除', ResponseCode::DATA_HAS_RELATION);
        }

        $scenario->delete();
    }
}
