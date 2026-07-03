# Laravel 项目集成 AI 推理服务指南

> 给组长：以下是在 Laravel 项目中调用 AI 推理的完整代码，复制粘贴即可。

---

## 一、前提

AI 推理服务已启动：

```powershell
cd D:\hydropower_deploy
python api_server.py --port 5000
```

确认服务正常：

```powershell
curl http://localhost:5000/api/health
# → {"status":"ok","service":"hydropower-inference",...}
```

---

## 二、Laravel 集成（3 步）

### 第 1 步：加配置

`.env` 加一行：

```env
AI_INFERENCE_URL=http://localhost:5000
```

`config/services.php` 加：

```php
'ai_inference' => [
    'url' => env('AI_INFERENCE_URL', 'http://localhost:5000'),
    'timeout' => 10,
],
```

### 第 2 步：创建 Service 类

新建 `app/Services/AiInferenceService.php`：

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class AiInferenceService
{
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.ai_inference.url');
        $this->timeout = config('services.ai_inference.timeout', 10);
    }

    /**
     * 核心方法：传入传感器数据，返回闸门决策
     *
     * @param array $sensor  传感器数据
     * @return array         推理结果
     * @throws \Exception
     */
    public function infer(array $sensor): array
    {
        $payload = [
            'upstream_level'   => $sensor['upstream_level']   ?? 180.0,
            'downstream_level' => $sensor['downstream_level'] ?? 120.0,
            'inflow'           => $sensor['inflow']           ?? 200.0,
            'rainfall'         => $sensor['rainfall']         ?? 0.0,
            'temperature'      => $sensor['temperature']      ?? 20.0,
            'gate1_opening'    => $sensor['gate1_opening']    ?? 0.0,
            'gate2_opening'    => $sensor['gate2_opening']    ?? 0.0,
            'gate3_opening'    => $sensor['gate3_opening']    ?? 0.0,
        ];

        $response = Http::timeout($this->timeout)
            ->post("{$this->baseUrl}/api/infer", $payload);

        if ($response->failed()) {
            throw new \Exception("AI inference failed: HTTP {$response->status()}");
        }

        $body = $response->json();

        if (empty($body['success'])) {
            throw new \Exception("AI inference failed: " . ($body['error'] ?? 'unknown'));
        }

        return $body['data'];
    }

    /**
     * 健康检查
     */
    public function health(): array
    {
        return Http::timeout(5)
            ->get("{$this->baseUrl}/api/health")
            ->json();
    }

    /**
     * 获取模型信息
     */
    public function modelsInfo(): array
    {
        return Http::timeout(5)
            ->get("{$this->baseUrl}/api/models/info")
            ->json();
    }

    /**
     * 批量推理
     *
     * @param array $samples  多组传感器数据
     * @return array
     */
    public function inferBatch(array $samples): array
    {
        $response = Http::timeout($this->timeout * count($samples))
            ->post("{$this->baseUrl}/api/infer/batch", [
                'samples' => $samples,
            ]);

        return $response->json();
    }

    /**
     * 重置 LSTM 历史缓冲区
     */
    public function resetHistory(): array
    {
        return Http::timeout(5)
            ->post("{$this->baseUrl}/api/history/reset")
            ->json();
    }
}
```

### 第 3 步：在 Controller 里调用

```php
<?php

namespace App\Http\Controllers;

use App\Services\AiInferenceService;
use Illuminate\Http\JsonResponse;

class DispatchController extends Controller
{
    protected AiInferenceService $ai;

    public function __construct(AiInferenceService $ai)
    {
        $this->ai = $ai;
    }

    /**
     * 获取 AI 调度建议
     * GET /api/dispatch/ai-suggestion
     */
    public function getSuggestion(): JsonResponse
    {
        // 从数据库或缓存取最新传感器数据
        $latestSensor = \App\Models\SensorReading::latest()->first();

        try {
            $result = $this->ai->infer([
                'upstream_level'   => $latestSensor->upstream_level,
                'downstream_level' => $latestSensor->downstream_level,
                'inflow'           => $latestSensor->inflow,
                'rainfall'         => $latestSensor->rainfall,
                'temperature'      => $latestSensor->temperature,
                'gate1_opening'    => $latestSensor->gate1_opening,
                'gate2_opening'    => $latestSensor->gate2_opening,
                'gate3_opening'    => $latestSensor->gate3_opening,
            ]);

            return response()->json([
                'success'    => true,
                'suggestion' => [
                    'gate_openings'       => $result['gate_openings'],        // [100, 100, 50]
                    'predicted_peak'      => $result['predicted_peak_level'], // 181.97
                    'predicted_levels'    => $result['predicted_levels'],     // 未来6h水位
                    'predicted_inflows'   => $result['predicted_inflows'],    // 未来6h流量
                    'confidence'          => $result['confidence'],           // 0.95
                    'safety_flag'         => $result['safety_flag'],          // "safe"
                    'inference_time_ms'   => $result['inference_time_ms'],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 前端定时轮询 —— 拿到数据后直接给前端渲染
     * GET /api/realtime/snapshot
     */
    public function realtimeSnapshot(): JsonResponse
    {
        $latestSensor = \App\Models\SensorReading::latest()->first();

        // 默认值（AI 服务挂了也能返回基础数据）
        $response = [
            'sensor' => $latestSensor,
            'ai'     => null,
        ];

        try {
            $result = $this->ai->infer([
                'upstream_level'   => $latestSensor->upstream_level,
                'downstream_level' => $latestSensor->downstream_level,
                'inflow'           => $latestSensor->inflow,
                'rainfall'         => $latestSensor->rainfall,
                'temperature'      => $latestSensor->temperature,
                'gate1_opening'    => $latestSensor->gate1_opening,
                'gate2_opening'    => $latestSensor->gate2_opening,
                'gate3_opening'    => $latestSensor->gate3_opening,
            ]);
            $response['ai'] = $result;

        } catch (\Exception $e) {
            \Log::warning("AI inference unavailable: " . $e->getMessage());
            // 不报错，前端照常显示传感器数据
        }

        return response()->json($response);
    }
}
```

---

## 三、调用流程图

```
前端 Vue 3
   │  GET /api/realtime/snapshot
   ▼
Laravel Controller (DispatchController)
   │  $this->ai->infer([sensor data])
   ▼
AiInferenceService
   │  HTTP::post("http://localhost:5000/api/infer", $payload)
   ▼
Python Flask (api_server.py)
   │  GateController.step(sensor)
   ▼
DQN + LSTM 推理
   │  → gate_openings + predicted_levels + confidence + safety_flag
   ▼
返回 JSON → Laravel 透传 → 前端渲染
```

---

## 四、异常处理

AI 服务不可用时的降级策略：

```php
try {
    $aiResult = $this->ai->infer($sensorData);
} catch (\Exception $e) {
    // 降级方案：返回传感器原始数据，不阻塞前端
    \Log::warning('AI inference failed: ' . $e->getMessage());
    $aiResult = [
        'gate_openings'     => null,
        'confidence'        => null,
        'safety_flag'       => 'unknown',
        'inference_time_ms' => 0,
    ];
}
```

---

## 五、定时任务（可选）

`app/Console/Commands/AutoDispatchCommand.php`：

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AiInferenceService;

class AutoDispatchCommand extends Command
{
    protected $signature = 'dispatch:auto';
    protected $description = 'Run AI auto dispatch';

    public function handle()
    {
        $sensor = \App\Models\SensorReading::latest()->first();
        $ai = app(AiInferenceService::class);

        try {
            $result = $ai->infer($sensor->toArray());

            // L3全自动 / L2半自动满足条件 → 下发指令
            if ($result['confidence'] >= 0.80) {
                // 这里写 PLC 下发逻辑（或调边缘端 WebSocket）
                $this->info("Auto dispatched: gates=" . implode(',', $result['gate_openings']));
            } else {
                $this->warn("Confidence too low ({$result['confidence']}), skipped");
            }

        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
```

注册到 `app/Console/Kernel.php`：

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('dispatch:auto')->everyFiveSeconds();
}
```

---

## 六、总结

| 文件 | 作用 |
|------|------|
| `.env` | 配置 `AI_INFERENCE_URL` |
| `config/services.php` | 读取 env 配置 |
| `app/Services/AiInferenceService.php` | 封装 HTTP 调用（复制即用） |
| Controller 里 `$this->ai->infer($data)` | 一行拿到推理结果 |

> AI 推理服务已就绪，Laravel 端只需新建一个 Service 类 + 配置一个 env 变量即可对接。
