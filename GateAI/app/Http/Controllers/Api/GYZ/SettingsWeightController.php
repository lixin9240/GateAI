<?php

namespace App\Http\Controllers\Api\Gyz;

use App\Http\Controllers\Controller;
use App\Http\Requests\GYZ\WeightUpdateRequest;
use App\Services\GYZ\SettingsWeightService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

class SettingsWeightController extends Controller
{
    public function __construct(
        protected SettingsWeightService $service
    ) {}

    /**
     * 8.2.1 获取当前权重
     */
    public function show(): JsonResponse
    {
        $weight = $this->service->current();

        return Result::success('获取成功', $weight);
    }

    /**
     * 8.2.2 更新权重配置
     */
    public function update(WeightUpdateRequest $request): JsonResponse
    {
        $weight = $this->service->update(
            $request->validated(),
            (int) auth('api')->id()
        );

        return Result::success('权重配置更新成功', $weight);
    }
}
