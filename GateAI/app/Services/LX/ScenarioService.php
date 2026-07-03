<?php

namespace App\Services\LX;

use App\Models\SimulationScenario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ScenarioService
{
    public function list(array $filters): LengthAwarePaginator
    {
        $query = SimulationScenario::query();

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
}
