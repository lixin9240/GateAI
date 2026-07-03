<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class HydropowerService
{
    /**
     * 调用 AI 模型做一次推理
     *
     * 工作原理：Laravel 用 proc_open 启动 Python 进程，
     * 把传感器数据通过 stdin 写进去，从 stdout 读取 JSON 结果。
     *
     * @param array $sensor 传感器数据，7 个字段必填：
     *   upstream_level   - 上游水位 (米)，例如 182.0
     *   downstream_level - 下游水位 (米)，例如 120.5
     *   inflow           - 入库流量 (m³/s)，例如 250.0
     *   rainfall         - 降雨量 (mm/h)，例如 3.0（可选，默认 0）
     *   temperature      - 温度 (°C)，例如 22.0（可选，默认 20）
     *   gate1_opening    - 闸门1当前开度 (0~1)，例如 0.3
     *   gate2_opening    - 闸门2当前开度 (0~1)，例如 0.2
     *   gate3_opening    - 闸门3当前开度 (0~1)，例如 0.4
     *
     * @return array|null 推理结果，包含：
     *   gate_openings      - AI建议的闸门开度 (%)，例如 [40.0, 25.0, 50.0]
     *   predicted_inflows  - LSTM预测未来6h入库流量 [282, 281, ...]
     *   predicted_levels   - LSTM预测未来6h上游水位 [182.0, 182.1, ...]
     *   predicted_peak_level - 预测6h内峰值水位 (m)
     *   confidence         - 置信度 (0~1)
     *   safety_flag        - safe / warning / danger
     *   decision_mode      - L3_AUTO / L2_SUGGEST / L1_MANUAL / OVERRIDE
     *   risk_level         - safe / warning / danger / critical
     *   risk_probability   - 风险概率 (0~1)
     *   physics_passed     - 物理校验是否通过 (true/false)
     *   inference_time_ms  - 推理耗时 (毫秒)
     */
    public static function infer(array $sensor): ?array
    {
        // Python 脚本路径
        $scriptPath = env('AI_INFERENCE_PATH', storage_path('ai/infer_cli.py'));
        // Python 可执行文件
        $pythonBin  = env('AI_PYTHON_BIN', 'python');
        // 工作目录设为模型文件所在目录
        $workDir    = storage_path('ai');

        // 给传感器数据补上默认值
        $input = array_merge([
            'rainfall'    => 0.0,
            'temperature' => 20.0,
        ], $sensor);

        $inputJson = json_encode($input, JSON_UNESCAPED_UNICODE);

        // 打开 Python 进程
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin  — 我们把传感器 JSON 写进去
            1 => ['pipe', 'w'],  // stdout — 从这里读 AI 推理结果
            2 => ['pipe', 'w'],  // stderr — 错误信息
        ];

        $process = proc_open(
            "{$pythonBin} {$scriptPath}",
            $descriptors,
            $pipes,
            $workDir
        );

        if (!is_resource($process)) {
            Log::error('AI推理: 无法启动Python进程');
            return null;
        }

        // 把传感器数据写入 stdin
        fwrite($pipes[0], $inputJson);
        fclose($pipes[0]);

        // 从 stdout 读取 JSON 结果
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            Log::error("AI推理: Python exit code={$exitCode}", ['stderr' => $stderr]);
            return null;
        }

        $response = json_decode($stdout, true);

        if (!$response || !($response['success'] ?? false)) {
            Log::error('AI推理: 返回失败', ['response' => $response]);
            return null;
        }

        return $response['data'];
    }

    /**
     * 重置 LSTM 历史缓冲区
     * 切换水库或服务重启后调用，清空之前的 24 小时历史数据
     */
    public static function resetHistory(): bool
    {
        $scriptPath = env('AI_INFERENCE_PATH', storage_path('ai/infer_cli.py'));
        $pythonBin  = env('AI_PYTHON_BIN', 'python');
        $workDir    = storage_path('ai');

        $cmd = "{$pythonBin} {$scriptPath} --reset";
        $output = shell_exec("cd {$workDir} && {$cmd} 2>&1");

        return str_contains($output ?? '', '"success": true');
    }
}
