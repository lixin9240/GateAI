<?php
/**
 * 水电站AI推理服务 — Laravel 集成
 * ==================================
 * 通过 proc_open 调用 Python infer_cli.py，JSON 进 JSON 出
 *
 * 部署步骤:
 *   1. 把整个 hydropower_deploy 目录复制到 Laravel 项目的 storage/ai/ 下
 *   2. 把这个 HydropowerService.php 放到 app/Services/ 下
 *   3. 在 .env 中设置: AI_INFERENCE_PATH=storage/ai/infer_cli.py
 *   4. Controller 中调用: $result = HydropowerService::infer($sensorData);
 */

namespace App\Services;

use Illuminate\Support\Facades\Log;

class HydropowerService
{
    /**
     * 执行一次 AI 推理
     *
     * @param array $sensor 传感器数据
     *   [
     *     'upstream_level'   => 182.0,  // 上游水位 (m)
     *     'downstream_level' => 120.5,  // 下游水位 (m)
     *     'inflow'           => 250.0,  // 入库流量 (m³/s)
     *     'rainfall'         => 3.0,    // 降雨量 (mm/h)
     *     'temperature'      => 22.0,   // 温度 (°C)
     *     'gate1_opening'    => 0.3,    // 闸门1开度 (0~1)
     *     'gate2_opening'    => 0.2,
     *     'gate3_opening'    => 0.4,
     *   ]
     * @return array|null 推理结果，失败返回 null
     */
    public static function infer(array $sensor): ?array
    {
        $scriptPath = env('AI_INFERENCE_PATH', storage_path('ai/infer_cli.py'));
        $pythonBin  = env('AI_PYTHON_BIN', 'python');

        $input = json_encode($sensor, JSON_UNESCAPED_UNICODE);

        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open(
            "{$pythonBin} {$scriptPath}",
            $descriptors,
            $pipes,
            storage_path('ai')  // 工作目录 = 模型文件所在目录
        );

        if (!is_resource($process)) {
            Log::error('HydropowerService: 无法启动 Python 进程');
            return null;
        }

        // 写入输入
        fwrite($pipes[0], $input);
        fclose($pipes[0]);

        // 读取输出
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            Log::error("HydropowerService: Python 退出码 {$exitCode}", ['stderr' => $stderr]);
            return null;
        }

        $result = json_decode($stdout, true);
        if (!$result || !($result['success'] ?? false)) {
            Log::error('HydropowerService: 推理失败', ['result' => $result]);
            return null;
        }

        return $result['data'];
    }

    /**
     * 批量推理 — 多次调用 infer，自动维护 LSTM 历史
     */
    public static function inferBatch(array $samples): array
    {
        $results = [];
        foreach ($samples as $sensor) {
            $results[] = self::infer($sensor);
        }
        return $results;
    }

    /**
     * 重置 LSTM 历史缓冲区（切换水库/模型重启后调用）
     */
    public static function resetHistory(): bool
    {
        $scriptPath = env('AI_INFERENCE_PATH', storage_path('ai/infer_cli.py'));
        $pythonBin  = env('AI_PYTHON_BIN', 'python');
        $output = shell_exec("{$pythonBin} {$scriptPath} --reset 2>&1");
        return str_contains($output ?? '', '"success": true');
    }
}
