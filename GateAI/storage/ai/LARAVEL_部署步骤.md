# AI 模型部署到 Laravel 项目 — 完整步骤

> 前提：你有一个 Laravel 项目，实现了总接口文档的 70 个路由。
> 目标：让 Laravel 能调用我们的 Python AI 模型（LSTM预测 + DQN决策 + 物理校验）。

---

## 第 1 步：把 AI 模块复制到 Laravel 项目里

把整个 `D:\hydropower_deploy` 目录复制到 Laravel 项目的 `storage/ai/` 下：

```powershell
# 假设你的 Laravel 项目在 D:\laravel-hydro
xcopy D:\hydropower_deploy\* D:\laravel-hydro\storage\ai\ /E /I
```

复制后 Laravel 项目结构应该是：

```
D:\laravel-hydro\
├── app/
│   └── Services/
│       └── HydropowerService.php    ← 第 2 步创建
├── storage/
│   └── ai/                           ← 整个 AI 模块在这里
│       ├── infer_cli.py              ← Laravel 直接调这个文件
│       ├── inference_server.py       ← 推理引擎
│       ├── physics_guard.py          ← 四层物理防护
│       ├── models/
│       │   ├── lstm_state_dict.pt   ← LSTM 模型
│       │   ├── dqn_model.pth        ← DQN 模型
│       │   └── scaler_X.pkl         ← 归一化器
│       ├── deploy_config.json
│       └── requirements.txt
├── .env                              ← 第 3 步修改
└── routes/
    └── api.php                       ← 第 4 步修改
```

## 第 2 步：创建 Laravel Service 类

在 `app/Services/HydropowerService.php` 创建文件：

```php
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
```

## 第 3 步：配置 .env

在 Laravel 项目的 `.env` 文件末尾加上：

```env
# AI 推理引擎配置
AI_PYTHON_BIN=python
AI_INFERENCE_PATH=storage/ai/infer_cli.py
```

确保 Laravel 服务器上已安装 PyTorch 依赖：

```powershell
cd storage/ai
pip install -r requirements.txt
```

## 第 4 步：在 Controller 中调用

### 场景 A：组长调 `POST /api/infer`（开发测试）

在 `routes/api.php` 加一个测试路由：

```php
Route::post('/api/infer', function (Request $request) {
    $sensor = [
        'upstream_level'   => $request->input('upstream_level', 180),
        'downstream_level' => $request->input('downstream_level', 120),
        'inflow'           => $request->input('inflow', 200),
        'rainfall'         => $request->input('rainfall', 0),
        'temperature'      => $request->input('temperature', 20),
        'gate1_opening'    => $request->input('gate1_opening', 0.3),
        'gate2_opening'    => $request->input('gate2_opening', 0.2),
        'gate3_opening'    => $request->input('gate3_opening', 0.4),
    ];

    $result = \App\Services\HydropowerService::infer($sensor);

    if (!$result) {
        return response()->json(['code' => 90003, 'msg' => 'AI推理失败', 'success' => false]);
    }

    return response()->json([
        'code'    => 0,
        'msg'     => '操作成功',
        'success' => true,
        'data'    => $result,
    ]);
});
```

### 场景 B：对接 `POST /api/dispatch/execute`（4.4 人工下发指令）

```php
// 在 DispatchController 的 execute 方法中
public function execute(Request $request)
{
    $request->validate([
        'reservoir_id'    => 'required|integer',
        'target_opening'  => 'required|numeric',
    ]);

    // 从数据库获取当前传感器数据
    $latestSensor = MonitoringData::where('reservoir_id', $request->reservoir_id)
        ->latest('timestamp')
        ->first();

    // 调 AI 推理
    $aiResult = HydropowerService::infer([
        'upstream_level'   => $latestSensor->upstream_level,
        'downstream_level' => $latestSensor->downstream_level,
        'inflow'           => $latestSensor->inflow_rate,
        'rainfall'         => $latestSensor->rainfall ?? 0,
        'temperature'      => $latestSensor->temperature ?? 20,
        'gate1_opening'    => $request->target_opening / 100,
        'gate2_opening'    => $request->target_opening / 100,
        'gate3_opening'    => $request->target_opening / 100,
    ]);

    // 写入决策记录表
    $decision = DispatchDecision::create([
        'reservoir_id'        => $request->reservoir_id,
        'decision_time'       => now(),
        'decision_mode'       => $aiResult['decision_mode'],
        'risk_rank'           => $aiResult['safety_flag'] === 'safe' ? 1 : ($aiResult['safety_flag'] === 'warning' ? 2 : 3),
        'upstream_level'      => $latestSensor->upstream_level,
        'downstream_level'    => $latestSensor->downstream_level,
        'inflow_rate'         => $latestSensor->inflow_rate,
        'current_opening'     => $request->target_opening,
        'recommended_opening' => $aiResult['gate_openings'][0],
        'confidence'          => $aiResult['confidence'] * 100,
        'lstm_predictions'    => json_encode($aiResult['predicted_inflows']),
        'physics_validation'  => json_encode([
            'passed'        => $aiResult['physics_passed'],
            'risk_level'    => $aiResult['risk_level'],
            'risk_probability' => $aiResult['risk_probability'],
        ]),
        'execution_status'    => 'pending',
    ]);

    $commandId = 'CMD-' . strtoupper(Str::random(8));

    return response()->json([
        'code'    => 0,
        'msg'     => '操作成功',
        'success' => true,
        'data'    => ['command_id' => $commandId],
    ]);
}
```

### 场景 C：边缘端自主上报（11.2）

如果你的 edge_main.py 在 Jetson 上跑，它自己调云端上报。PC 测试时也可以手动调用：

```php
// 在 EdgeDataController 中接收边缘端上报
public function dispatchDecisions(Request $request)
{
    $data = $request->all();

    DispatchDecision::create([
        'trace_id'            => $data['trace_id'],
        'reservoir_id'        => $data['reservoir_id'],
        'edge_node_id'        => $data['edge_node_id'],
        'decision_time'       => $data['decision_time'],
        'decision_mode'       => $data['decision_mode'],
        'risk_rank'           => $data['risk_rank'],
        'upstream_level'      => $data['upstream_level'],
        'downstream_level'    => $data['downstream_level'],
        'inflow_rate'         => $data['inflow_rate'],
        'recommended_opening' => $data['recommended_opening'],
        'confidence'          => $data['confidence'],
        'lstm_predictions'    => json_encode($data['lstm_predictions']),
        'physics_validation'  => json_encode($data['physics_validation']),
        'execution_status'    => 'executed',
    ]);

    return response()->json(['code' => 0, 'msg' => '操作成功', 'success' => true]);
}
```

## 第 5 步：安装 Python 依赖 + 验证

```powershell
# 进入 AI 模块目录
cd D:\laravel-hydro\storage\ai

# 安装 Python 依赖
pip install -r requirements.txt

# 验证模型能正常加载
echo '{"upstream_level":182,"downstream_level":120.5,"inflow":250,"gate1_opening":0.3,"gate2_opening":0.2,"gate3_opening":0.4}' | python infer_cli.py
```

如果输出了一行 JSON（`{"success":true,"data":{...}}`），说明部署成功。

---

## 总结

| Laravel 需要做的事情 | 对应的文件 |
|---|---|
| 调 AI 推理 | `HydropowerService::infer($sensor)` → 调 `infer_cli.py` |
| 推理结果写入数据库 | Controller 里调 Service，拿到结果写 MySQL |
| 70 个接口返回数据 | 从 MySQL 读 `dispatch_decisions` 表返回给前端 |
| PC 端定期自动推理 | `php artisan schedule:run` + 定时任务 |
| 边缘端自主运行 | `python edge_main.py`（不需要 Laravel） |

**AI 模型本身不需要改任何东西。** Laravel 调 `infer_cli.py`，JSON 进 JSON 出。
