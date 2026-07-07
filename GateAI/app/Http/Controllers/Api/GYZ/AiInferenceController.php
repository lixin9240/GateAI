<?php

namespace App\Http\Controllers\Api\GYZ;

use App\Http\Controllers\Controller;
use App\Models\SettingsModel;
use App\Services\HydropowerService;
use App\Support\LogHelper;
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
            'reservoir_id'     => 'nullable|integer|min:1',
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

        // 自动执行判断：L3_AUTO + 高置信度 + 低风险 → 可自动调度
        $result['auto_dispatch'] = ($result['decision_mode'] ?? '') === 'L3_AUTO'
            && ($result['confidence'] ?? 0) >= 0.85
            && ($result['safety_flag'] ?? '') === 'safe';

        // 附上水库信息
        $reservoirId = $request->input('reservoir_id');
        if ($reservoirId) {
            $reservoir = \App\Models\Reservoir::find($reservoirId);
            $result['reservoir'] = $reservoir ? ['id' => $reservoir->id, 'name' => $reservoir->name] : null;
        }

        LogHelper::business('AI推理调用', [
            'sensor'        => array_intersect_key($sensor, array_flip(['upstream_level', 'downstream_level', 'inflow'])),
            'decision_mode' => $result['decision_mode'] ?? 'unknown',
            'confidence'    => $result['confidence'] ?? null,
            'risk_level'    => $result['risk_level'] ?? null,
            'user_id'       => auth()->id(),
        ], 'info', 'AI_INFERENCE');

        return Result::success('推理完成', $result);
    }

    /**
     * 执行回填 —— 调度执行后回填实际水位和开度，供模型评判系统计算预测误差
     * POST /api/v1/monitor/hydro-feedback
     */
    public function feedback(Request $request): JsonResponse
    {
        $request->validate([
            'decision_id'       => 'required|integer',
            'actual_level'      => 'required|numeric',
            'actual_flow'       => 'required|numeric',
            'executed_opening'  => 'required|numeric',
        ]);

        // 更新调度决策的实际执行结果
        \App\Models\DispatchDecision::where('id', $request->decision_id)->update([
            'executed_opening'   => $request->executed_opening,
            'actual_level_after' => $request->actual_level,
            'execution_status'   => 'executed',
            'executed_at'        => now(),
        ]);

        LogHelper::business('推理执行回填', [
            'decision_id'      => $request->decision_id,
            'actual_level'     => $request->actual_level,
            'actual_flow'      => $request->actual_flow,
            'executed_opening' => $request->executed_opening,
        ], 'info', 'FEEDBACK');

        return Result::success('回填成功');
    }
}
