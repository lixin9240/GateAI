<?php

namespace App\Http\Controllers\Api\Gyz;

use App\Http\Controllers\Controller;
use App\Http\Requests\GYZ\ThresholdListRequest;
use App\Http\Requests\GYZ\ThresholdUpdateRequest;
use App\Services\GYZ\SettingsThresholdService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

class SettingsThresholdController extends Controller
{
    public function __construct(
        protected SettingsThresholdService $service
    ) {}

    /**
     * 8.1.1 获取阈值列表
     */
    public function index(ThresholdListRequest $request): JsonResponse
    {
        $list = $this->service->list(
            $request->validated('reservoir_id'),
            $request->validated('metric')
        );

        return Result::success('获取成功', $list);
    }

    /**
     * 8.1.2 更新阈值配置
     */
    public function update(int $id, ThresholdUpdateRequest $request): JsonResponse
    {
        $threshold = $this->service->update(
            $id,
            $request->validated(),
            (int) auth('api')->id()
        );

        return Result::success('阈值配置更新成功', $threshold);
    }
}
