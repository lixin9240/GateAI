<?php

namespace App\Http\Controllers\Api\GYZ;

use App\Http\Controllers\Controller;
use App\Models\SettingsModel;
use App\Services\HydropowerService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiInferenceController extends Controller
{
    /**
     * AI 推理 —— 前端传入传感器数据，返回 LSTM 预测 + DQN 决策
     *
     * POST /api/v1/settings/ai/infer
     */
    public function infer(Request $request): JsonResponse
    {
        $request->validate([
            'upstream_level'   => 'required|numeric',
            'downstream_level' => 'required|numeric',
            'inflow'           => 'required|numeric',
            'rainfall'         => 'nullable|numeric',
            'temperature'      => 'nullable|numeric',
            'gate1_opening'    => 'nullable|numeric',
            'gate2_opening'    => 'nullable|numeric',
            'gate3_opening'    => 'nullable|numeric',
        ]);

        $sensor = $request->only([
            'upstream_level', 'downstream_level', 'inflow',
            'rainfall', 'temperature',
            'gate1_opening', 'gate2_opening', 'gate3_opening',
        ]);

        $result = HydropowerService::infer($sensor);

        if (! $result) {
            return Result::error(
                \App\Enums\ResponseCode::PROGRAM_ERROR,
                'AI推理失败，请检查模型是否已激活'
            );
        }

        // 附上当前使用的模型信息
        $activeModels = SettingsModel::query()
            ->where('is_active', 1)
            ->select('id', 'name', 'version', 'type', 'accuracy', 'file_path')
            ->get()
            ->keyBy('type')
            ->toArray();

        $result['models_used'] = $activeModels;

        return Result::success('推理完成', $result);
    }
}
