<?php

namespace App\Services\Fmy;

use App\Models\ModelMetric;
use App\Support\LogHelper;
use Illuminate\Support\Facades\Log;

class ModelMetricService
{
    /**
     * 查询某水库最新一条模型评判指标
     */
    public function getLatest(int $reservoirId): ?array
    {
        Log::channel('business')->info('模型评判-查询最新指标', [
            'reservoir_id' => $reservoirId,
            'user_id'      => auth()->id(),
            'trace_id'     => request()->attributes->get('trace_id'),
        ]);

        $metric = ModelMetric::where('reservoir_id', $reservoirId)
            ->orderByDesc('metric_time')
            ->first();

        if (! $metric) {
            return null;
        }

        return $this->formatMetric($metric);
    }

    /**
     * 查询某水库历史趋势（供前端折线图）
     */
    public function getHistory(int $reservoirId, int $days = 7): array
    {
        Log::channel('business')->info('模型评判-查询历史趋势', [
            'reservoir_id' => $reservoirId,
            'days'         => $days,
            'user_id'      => auth()->id(),
            'trace_id'     => request()->attributes->get('trace_id'),
        ]);

        $startTime = now()->subDays($days);

        $metrics = ModelMetric::where('reservoir_id', $reservoirId)
            ->where('metric_time', '>=', $startTime)
            ->orderBy('metric_time')
            ->get([
                'metric_time',
                'prediction_score',
                'decision_score',
                'compliance_score',
                'overall_score',
                'health_grade',
                'water_level_mae_24h',
                'physics_correction_rate',
                'safety_override_rate',
                'avg_physics_violation',
            ]);

        return $metrics->map(fn (ModelMetric $m) => [
            'metric_time'             => $m->metric_time->toDateTimeString(),
            'prediction_score'        => (float) $m->prediction_score,
            'decision_score'          => (float) $m->decision_score,
            'compliance_score'        => (float) $m->compliance_score,
            'overall_score'           => (float) $m->overall_score,
            'health_grade'            => $m->health_grade,
            'water_level_mae_24h'     => (float) $m->water_level_mae_24h,
            'physics_correction_rate' => (float) $m->physics_correction_rate,
            'safety_override_rate'    => (float) $m->safety_override_rate,
            'avg_physics_violation'   => (float) $m->avg_physics_violation,
        ])->toArray();
    }

    /**
     * 汇总所有水库的模型健康状态
     * 使用 joinSub 取每个水库最新一条指标，防止 whereIn 在多水库同时间点时的误匹配
     */
    public function getHealthSummary(): array
    {
        Log::channel('business')->info('模型评判-查询全局健康概览', [
            'user_id'  => auth()->id(),
            'trace_id' => request()->attributes->get('trace_id'),
        ]);

        // 每个水库的最新 metric_time
        $latestPerReservoir = ModelMetric::query()
            ->selectRaw('reservoir_id, MAX(metric_time) AS max_time')
            ->groupBy('reservoir_id');

        // 通过 joinSub 按 (reservoir_id, metric_time) 精确匹配
        $metrics = ModelMetric::query()
            ->joinSub($latestPerReservoir, 'latest', function ($join) {
                $join->on('model_metrics.reservoir_id', '=', 'latest.reservoir_id')
                     ->on('model_metrics.metric_time', '=', 'latest.max_time');
            })
            ->select('model_metrics.*')
            ->get();

        if ($metrics->isEmpty()) {
            return [
                'total_reservoirs'   => 0,
                'grade_distribution' => [],
                'reservoirs'         => [],
            ];
        }

        $gradeCount = ['S' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
        $summary    = [];

        foreach ($metrics as $metric) {
            $grade = $metric->health_grade;
            $gradeCount[$grade] = ($gradeCount[$grade] ?? 0) + 1;

            $summary[] = [
                'reservoir_id'  => $metric->reservoir_id,
                'health_grade'  => $grade,
                'overall_score' => (float) $metric->overall_score,
                'metric_time'   => $metric->metric_time->toDateTimeString(),
            ];
        }

        return [
            'total_reservoirs'   => count($summary),
            'grade_distribution' => array_filter($gradeCount, fn ($c) => $c > 0),
            'reservoirs'         => $summary,
        ];
    }

    /**
     * 接收边缘端上报的指标数据，写入 model_metrics
     */
    public function receiveMetrics(array $data): array
    {
        $metric = ModelMetric::updateOrCreate(
            [
                'reservoir_id' => $data['reservoir_id'],
                'metric_time'  => $data['metric_time'],
            ],
            [
                'edge_node_id'            => $data['edge_node_id'],
                'water_level_mae_24h'     => $data['water_level_mae_24h'] ?? 0,
                'flow_mae_24h'            => $data['flow_mae_24h'] ?? 0,
                'physics_correction_rate' => $data['physics_correction_rate'] ?? 0,
                'trend_accuracy'          => $data['trend_accuracy'] ?? 0,
                'prediction_score'        => $data['prediction_score'] ?? 0,
                'safety_override_rate'    => $data['safety_override_rate'] ?? 0,
                'decision_level_dist'     => $this->normalizeJsonField($data['decision_level_dist'] ?? null),
                'shadow_risk_pass_rate'   => $data['shadow_risk_pass_rate'] ?? 0,
                'smooth_filter_rate'      => $data['smooth_filter_rate'] ?? 0,
                'decision_score'          => $data['decision_score'] ?? 0,
                'avg_physics_violation'   => $data['avg_physics_violation'] ?? 0,
                'gate_limit_touch_rate'   => $data['gate_limit_touch_rate'] ?? 0,
                'rate_limit_exceed_rate'  => $data['rate_limit_exceed_rate'] ?? 0,
                'compliance_score'        => $data['compliance_score'] ?? 0,
                'overall_score'           => $data['overall_score'] ?? 0,
                'health_grade'            => $data['health_grade'] ?? 'D',
                'created_at'              => now(),
            ]
        );

        LogHelper::business('边缘端上报模型指标', [
            'metric_id'    => $metric->id,
            'edge_node_id' => $data['edge_node_id'],
            'reservoir_id' => $data['reservoir_id'],
            'overall_score' => $data['overall_score'] ?? 0,
            'health_grade'  => $data['health_grade'] ?? 'D',
        ], 'info', 'MODEL_METRICS');

        return $this->formatMetric($metric);
    }

    /**
     * 格式化为数组 — 确保 JSON 字段输出干净
     */
    private function formatMetric(ModelMetric $metric): array
    {
        return [
            'id'                      => $metric->id,
            'edge_node_id'            => $metric->edge_node_id,
            'reservoir_id'            => $metric->reservoir_id,
            'metric_time'             => $metric->metric_time->toDateTimeString(),

            // 维度一
            'prediction_score'        => (float) $metric->prediction_score,
            'water_level_mae_24h'     => (float) $metric->water_level_mae_24h,
            'flow_mae_24h'            => (float) $metric->flow_mae_24h,
            'physics_correction_rate' => (float) $metric->physics_correction_rate,
            'trend_accuracy'          => (float) $metric->trend_accuracy,

            // 维度二
            'decision_score'          => (float) $metric->decision_score,
            'safety_override_rate'    => (float) $metric->safety_override_rate,
            'decision_level_dist'     => $metric->decision_level_dist,
            'shadow_risk_pass_rate'   => (float) $metric->shadow_risk_pass_rate,
            'smooth_filter_rate'      => (float) $metric->smooth_filter_rate,

            // 维度三
            'compliance_score'        => (float) $metric->compliance_score,
            'avg_physics_violation'   => (float) $metric->avg_physics_violation,
            'gate_limit_touch_rate'   => (float) $metric->gate_limit_touch_rate,
            'rate_limit_exceed_rate'  => (float) $metric->rate_limit_exceed_rate,

            // 综合
            'overall_score'           => (float) $metric->overall_score,
            'health_grade'            => $metric->health_grade,
        ];
    }

    /**
     * 指标明细分页列表
     */
    public function getList(array $filters): array
    {
        $query = ModelMetric::query()
            ->when(!empty($filters['reservoir_id']), fn ($q) => $q->where('reservoir_id', $filters['reservoir_id']))
            ->when(!empty($filters['health_grade']), fn ($q) => $q->where('health_grade', $filters['health_grade']))
            ->when(!empty($filters['start_time']), fn ($q) => $q->where('metric_time', '>=', $filters['start_time']))
            ->when(!empty($filters['end_time']), fn ($q) => $q->where('metric_time', '<=', $filters['end_time']))
            ->orderByDesc('metric_time');

        $perPage = min((int) ($filters['page_size'] ?? 20), 100);
        $data = $query->paginate($perPage, ['*'], 'page', (int) ($filters['page'] ?? 1));

        return [
            'total' => $data->total(),
            'page'  => $data->currentPage(),
            'list'  => $data->map(fn (ModelMetric $m) => $this->formatMetric($m))->toArray(),
        ];
    }

    /**
     * 模型版本对比
     * 对比两个模型版本的训练指标 + 部署期间的运行时指标
     */
    public function compare(array $data): array
    {
        $reservoirId = $data['reservoir_id'];
        $modelA = \App\Models\SettingsModel::findOrFail($data['model_a_id']);
        $modelB = \App\Models\SettingsModel::findOrFail($data['model_b_id']);

        // 确定每个模型版本的运行时间窗口
        $windowA = $this->getModelActiveWindow($modelA);
        $windowB = $this->getModelActiveWindow($modelB);

        // 拉取各自窗口内的运行时指标
        $metricsA = !empty($windowA)
            ? ModelMetric::where('reservoir_id', $reservoirId)
                ->whereBetween('metric_time', [$windowA['start'], $windowA['end']])
                ->get()
            : collect();

        $metricsB = !empty($windowB)
            ? ModelMetric::where('reservoir_id', $reservoirId)
                ->whereBetween('metric_time', [$windowB['start'], $windowB['end']])
                ->get()
            : collect();

        return [
            'model_a' => [
                'id'            => $modelA->id,
                'name'          => $modelA->name,
                'version'       => $modelA->version,
                'type'          => $modelA->type,
                'framework'     => $modelA->framework,
                'training'      => [
                    'accuracy'        => (float) $modelA->accuracy,
                    'f1_score'        => (float) $modelA->f1_score,
                    'training_date'   => $modelA->training_date?->toDateString(),
                    'size'            => $modelA->size,
                ],
                'active_window' => $windowA,
                'runtime_avg'   => $this->averageMetrics($metricsA),
                'sample_count'  => $metricsA->count(),
            ],
            'model_b' => [
                'id'            => $modelB->id,
                'name'          => $modelB->name,
                'version'       => $modelB->version,
                'type'          => $modelB->type,
                'framework'     => $modelB->framework,
                'training'      => [
                    'accuracy'        => (float) $modelB->accuracy,
                    'f1_score'        => (float) $modelB->f1_score,
                    'training_date'   => $modelB->training_date?->toDateString(),
                    'size'            => $modelB->size,
                ],
                'active_window' => $windowB,
                'runtime_avg'   => $this->averageMetrics($metricsB),
                'sample_count'  => $metricsB->count(),
            ],
            'comparison' => $this->diffMetrics(
                $this->averageMetrics($metricsA),
                $this->averageMetrics($metricsB)
            ),
        ];
    }

    /**
     * 推断模型的活跃时间窗口
     */
    private function getModelActiveWindow(\App\Models\SettingsModel $model): array
    {
        $start = $model->deployed_at ?? $model->created_at;

        // 找到下一个同类型模型的部署时间作为本模型的结束时间
        $nextModel = \App\Models\SettingsModel::where('type', $model->type)
            ->where('id', '!=', $model->id)
            ->where('deployed_at', '>', $start)
            ->orderBy('deployed_at')
            ->first();

        $end = $nextModel?->deployed_at ?? now();

        return [
            'start' => $start->toDateTimeString(),
            'end'   => $end->toDateTimeString(),
        ];
    }

    private function averageMetrics($collection): array
    {
        if ($collection->isEmpty()) {
            return [];
        }

        return [
            'prediction_score'        => round($collection->avg('prediction_score'), 4),
            'decision_score'          => round($collection->avg('decision_score'), 4),
            'compliance_score'        => round($collection->avg('compliance_score'), 4),
            'overall_score'           => round($collection->avg('overall_score'), 4),
            'water_level_mae_24h'     => round($collection->avg('water_level_mae_24h'), 4),
            'flow_mae_24h'            => round($collection->avg('flow_mae_24h'), 2),
            'physics_correction_rate' => round($collection->avg('physics_correction_rate'), 4),
            'trend_accuracy'          => round($collection->avg('trend_accuracy'), 4),
            'safety_override_rate'    => round($collection->avg('safety_override_rate'), 4),
            'shadow_risk_pass_rate'   => round($collection->avg('shadow_risk_pass_rate'), 4),
            'smooth_filter_rate'      => round($collection->avg('smooth_filter_rate'), 4),
            'avg_physics_violation'   => round($collection->avg('avg_physics_violation'), 4),
            'gate_limit_touch_rate'   => round($collection->avg('gate_limit_touch_rate'), 4),
            'rate_limit_exceed_rate'  => round($collection->avg('rate_limit_exceed_rate'), 4),
        ];
    }

    private function diffMetrics(array $a, array $b): array
    {
        if (empty($a) || empty($b)) {
            return [];
        }

        $diff = [];
        foreach ($a as $key => $valA) {
            $valB = $b[$key] ?? 0;
            $diff[$key] = [
                'model_a' => $valA,
                'model_b' => $valB,
                'change'  => round($valB - $valA, 4),
            ];
        }
        return $diff;
    }

    /**
     * 标准化 JSON 字段：字符串 → 数组，防止 JSON Cast 双重编码
     */
    private function normalizeJsonField(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : null;
    }
}
