<?php

namespace App\Http\Controllers\Api\Fmy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fmy\EdgeModelMetricsRequest;
use App\Http\Requests\Fmy\ModelMetricsCompareRequest;
use App\Http\Requests\Fmy\ModelMetricsHistoryRequest;
use App\Http\Requests\Fmy\ModelMetricsRequest;
use App\Services\Fmy\ModelMetricService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

/**
 * 模型三维评判体系 —— 预测准确性 × 决策可靠性 × 物理合规性
 *
 * 四层架构：Request → Controller → Service → Model
 * Controller 仅收请求、调 Service、返回 Result
 */
class ModelMetricController extends Controller
{
    public function __construct(
        protected ModelMetricService $modelMetricService,
    ) {}

    /**
     * 最新模型指标
     * GET /api/settings/ai/metrics?reservoir_id=1
     */
    public function latest(ModelMetricsRequest $request): JsonResponse
    {
        $data = $this->modelMetricService->getLatest((int) $request->input('reservoir_id'));
        return Result::success('操作成功', $data);
    }

    /**
     * 历史趋势
     * GET /api/settings/ai/metrics/history?reservoir_id=1&days=7
     */
    public function history(ModelMetricsHistoryRequest $request): JsonResponse
    {
        $data = $this->modelMetricService->getHistory(
            (int) $request->input('reservoir_id'),
            (int) ($request->input('days') ?? 7)
        );
        return Result::success('操作成功', $data);
    }

    /**
     * 指标明细分页列表
     * GET /api/settings/ai/metrics/list
     */
    public function listMetrics(ModelMetricsRequest $request): JsonResponse
    {
        $data = $this->modelMetricService->getList($request->validated());
        return Result::success('操作成功', $data);
    }

    /**
     * 模型版本/时段对比
     * POST /api/settings/ai/metrics/compare
     */
    public function compare(ModelMetricsCompareRequest $request): JsonResponse
    {
        $data = $this->modelMetricService->compare($request->validated());
        return Result::success('对比完成', $data);
    }

    /**
     * 全局健康概览
     * GET /api/settings/ai/health
     */
    public function health(): JsonResponse
    {
        $data = $this->modelMetricService->getHealthSummary();
        return Result::success('操作成功', $data);
    }

    /**
     * 边缘端上报指标
     * POST /api/edge/model-metrics
     */
    public function receive(EdgeModelMetricsRequest $request): JsonResponse
    {
        $data = $this->modelMetricService->receiveMetrics($request->validated());
        return Result::success('上报成功', $data);
    }
}
