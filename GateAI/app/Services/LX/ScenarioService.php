<?php
// 仿真场景服务
namespace App\Services\LX;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\SimulationScenario;
use App\Support\LogHelper;
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
        $scenario = SimulationScenario::create([
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

        LogHelper::business('仿真场景已创建', [
            'scenario_id' => $scenario->id,
            'name'        => $scenario->name,
            'type'        => $scenario->type,
        ], 'info', 'SCENARIO_CREATE');

        return $scenario;
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

        LogHelper::business('仿真场景已更新', [
            'scenario_id' => $id,
            'name'        => $scenario->name,
            'changes'     => $data,
        ], 'info', 'SCENARIO_UPDATE');

        return $scenario->fresh();
    }

    public function delete(int $id): void
    {
        $scenario = SimulationScenario::findOrFail($id);

        if ($scenario->usage_count > 0) {
            throw new BusinessException('该场景已有仿真记录，不可删除', ResponseCode::DATA_HAS_RELATION);
        }

        $scenario->delete();

        LogHelper::business('仿真场景已删除', [
            'scenario_id' => $id,
            'name'        => $scenario->name,
        ], 'warning', 'SCENARIO_DELETE');
    }
}
