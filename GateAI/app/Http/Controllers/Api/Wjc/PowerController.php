<?php

namespace App\Http\Controllers\Api\Wjc;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wjc\PowerRequest;
use App\Services\Wjc\PowerService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

class PowerController extends Controller
{
    public function __construct(
        protected PowerService $powerService
    ) {}

    /**
     * 发电机组列表 + 实时出力
     * GET /api/v1/power/units?reservoir_id=1
     */
    public function units(PowerRequest $request): JsonResponse
    {
        $data = $this->powerService->getUnits(
            $request->input('reservoir_id')
        );
        return Result::success('成功', $data);
    }

    /**
     * 发电出力趋势
     * GET /api/v1/power/trend?reservoir_id=1&start_time=&end_time=&granularity=hour
     */
    public function trend(PowerRequest $request): JsonResponse
    {
        $data = $this->powerService->getTrend($request->all());
        return Result::success('成功', $data);
    }
}
