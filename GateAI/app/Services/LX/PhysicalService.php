<?php

namespace App\Services\LX;

use App\Models\PhysicalParameter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class PhysicalService
{
    public function edgeConfig(int $reservoirId): array
    {
        $cacheKey = "physics_config:{$reservoirId}";

        return Cache::remember($cacheKey, 1800, function () use ($reservoirId) {
            $params = PhysicalParameter::where('reservoir_id', $reservoirId)
                ->orderBy('water_level')
                ->get();

            $levelAreaMap = $params->map(fn ($p) => [
                'water_level'   => $p->water_level,
                'surface_area'  => $p->surface_area,
                'max_discharge' => $p->max_discharge,
            ])->toArray();

            return [
                'level_area_map' => $levelAreaMap,
                'validation'     => [
                    'enabled'            => true,
                    'max_deviation_m'    => 0.1,
                    'confidence_penalty' => 0.2,
                ],
                'version'   => $params->max('updated_at')?->timestamp ?? time(),
                'fetched_at' => now()->toISOString(),
            ];
        });
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return PhysicalParameter::where('reservoir_id', $filters['reservoir_id'])
            ->orderBy('water_level')
            ->paginate(
                perPage: (int) ($filters['page_size'] ?? 50),
                page: (int) ($filters['page'] ?? 1)
            );
    }

    public function upsert(array $data): array
    {
        $param = PhysicalParameter::updateOrCreate(
            [
                'reservoir_id' => $data['reservoir_id'],
                'water_level'  => $data['water_level'],
            ],
            [
                'surface_area'  => $data['surface_area'],
                'max_discharge' => $data['max_discharge'] ?? null,
                'remark'        => $data['remark'] ?? null,
            ]
        );

        Cache::forget("physics_config:{$data['reservoir_id']}");

        return ['id' => $param->id, 'updated' => $param->wasRecentlyCreated ? 'created' : 'updated'];
    }

    public function delete(int $id): void
    {
        $param = PhysicalParameter::findOrFail($id);
        $reservoirId = $param->reservoir_id;
        $param->delete();

        Cache::forget("physics_config:{$reservoirId}");
    }
}
