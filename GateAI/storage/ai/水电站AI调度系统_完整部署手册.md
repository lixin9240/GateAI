# 水电站闸门智能调度系统 — 完整部署手册

> 版本: 6.0 | 日期: 2026-07-02
>
> 本文档合并了部署包内所有说明文档，覆盖从当前 PC 开发联调到未来 Jetson 硬件部署的全流程。

---

## 目录

**第一部分：当前阶段 — PC 开发联调（无硬件依赖）**

1. [快速开始](#一快速开始)
2. [模型训练与导出](#二模型训练与导出)
3. [API 接口文档](#三api-接口文档)
4. [Laravel 集成](#四laravel-集成)
5. [AI 模型规格](#五ai-模型规格)

**第二部分：未来阶段 — 硬件现场部署（需 Jetson / PLC）**

6. [系统架构全景](#六系统架构全景)
7. [硬件清单与接线](#七硬件清单与接线)
8. [Jetson 烧录指南 — VMware 方式](#八jetson-烧录指南--vmware-方式)
9. [Jetson 推理服务部署](#九jetson-推理服务部署)
10. [PLC + 传感器 + 物理模型搭建](#十plc--传感器--物理模型搭建)
11. [云端 ↔ 边缘通信配置](#十一云端--边缘通信配置)
12. [安全体系](#十二安全体系)
13. [三级自动执行模式](#十三三级自动执行模式)
14. [断网自治机制](#十四断网自治机制)
15. [模型重训练](#十五模型重训练)
16. [故障排查](#十六故障排查)

**附录**

17. [部署前检查清单](#十七部署前检查清单)
18. [常用命令速查](#十八常用命令速查)
19. [部署包文件清单](#十九部署包文件清单)

---

# 第一部分：当前阶段 — PC 开发联调

---

## 一、快速开始

> 给组长：部署包到手，3 步即可跑起来。

### 这个服务谁来启动？

| 方式 | 操作 | 适用场景 |
|:--:|------|------|
| **A（推荐）** | 组长自己启动：拷贝部署包 → `pip install -r requirements.txt` → `python api_server.py --port 5000` | 组长电脑有 Python |
| B | 你启动，组长远程调 `.env` 配 `AI_INFERENCE_URL=http://你的IP:5000` | 同网络，你电脑需一直开着 |
| C | 部署到共用 Linux 服务器，scp 上去跑 | 有开发服务器 |

> 推理只依赖 CPU，不需要 GPU，任意 Windows/Linux/Mac 都行。

### 3 步跑起来

```powershell
# 第 1 步：安装依赖（就这一次）
cd D:\hydropower_deploy
pip install -r requirements.txt

# 第 2 步：启动服务
python api_server.py --port 5000
# 看到 "Listening on http://0.0.0.0:5000" 即成功
# 不要关窗口。

# 第 3 步：验证（新开终端）
curl http://localhost:5000/api/health
# → {"status":"ok","service":"hydropower-inference",...}
```

### 部署包结构

```
D:\hydropower_deploy\
├── api_server.py              ← HTTP API 入口（组长调这个）
├── inference_server.py        ← 推理引擎
├── database.py                ← MySQL 读写
├── deploy_config.json         ← 配置文件
├── requirements.txt           ← Python 依赖列表
├── README.md                  ← 快速使用说明
├── models/
│   ├── dqn_scripted.pt        ← DQN 决策模型 (1.1MB)
│   ├── lstm_state_dict.pt     ← LSTM 预测模型 (3.2MB)
│   └── scaler_X.pkl           ← 数据归一化器
├── scripts/
│   ├── health_check.sh
│   ├── backup_db.sh
│   └── hydropower.service
├── data/  logs/
```

### 常见问题

| 问题 | 解决 |
|------|------|
| 报错 `No module named 'flask'` | `pip install flask flask-cors` |
| 端口 5000 被占用 | `python api_server.py --port 5001` |
| 没有 GPU 能跑吗 | 能，CPU 推理只要几毫秒 |
| 关闭终端服务就停了 | `start /B python api_server.py --port 5000` |

---

## 二、模型训练与导出

### 2.1 训练模型

```powershell
cd D:\hydropower_scheduling

# 训练 DQN 决策模型（~11 分钟，RTX 5060）
python train_dqn_final.py
# → 产出 models/dqn_model.pth（284,798 参数）

# 训练 LSTM 预测模型（~3 分钟）
python train_lstm_final.py
# → 产出 models/lstm_model.pth（470,124 参数）+ models/scaler_X.pkl
```

### 2.2 导出部署包

```powershell
cd D:\hydropower_scheduling
python deploy.py
# → 产出 D:\hydropower_deploy\（包含 TorchScript 模型 + API 服务）
```

---

## 三、API 接口文档

### 3.1 参数说明

| 参数 | 默认值 | 说明 |
|------|--------|------|
| `--port` | 5000 | 监听端口 |
| `--host` | 0.0.0.0 | 监听地址 |
| `--no-db` | 关闭 | 不加此参数会自动连接 MySQL 记录推理日志 |
| `--debug` | 关闭 | Flask 调试模式（开发用） |

### 3.2 接口列表

| 接口 | 方法 | 说明 |
|------|:--:|------|
| `/api/infer` | POST | **核心推理接口** |
| `/api/infer/batch` | POST | 批量推理 |
| `/api/health` | GET | 健康检查 |
| `/api/models/info` | GET | 模型参数信息 |
| `/api/history/reset` | POST | 重置 LSTM 历史缓冲区 |
| `/api/history/status` | GET | 查看历史缓冲区状态 |

### 3.3 核心接口：POST /api/infer

**请求体：**

```json
{
  "upstream_level": 182.0,
  "downstream_level": 120.5,
  "inflow": 250.0,
  "rainfall": 3.0,
  "temperature": 22.0,
  "gate1_opening": 0.3,
  "gate2_opening": 0.2,
  "gate3_opening": 0.4
}
```

| 字段 | 类型 | 单位 | 说明 |
|------|------|------|------|
| `upstream_level` | float | 米 | 上游水位 |
| `downstream_level` | float | 米 | 下游水位 |
| `inflow` | float | m³/s | 入库流量 |
| `rainfall` | float | mm/h | 降雨量 |
| `temperature` | float | °C | 温度 |
| `gate1_opening` | float | 0~1 | 闸门1当前开度（0=全关, 1=全开） |
| `gate2_opening` | float | 0~1 | 闸门2当前开度 |
| `gate3_opening` | float | 0~1 | 闸门3当前开度 |

**响应体：**

```json
{
  "success": true,
  "data": {
    "gate_openings": [100.0, 100.0, 50.0],
    "gate_openings_raw": [1.0, 1.0, 0.5],
    "predicted_inflows": [221.9, 225.5, 227.0, 224.4, 227.5, 236.9],
    "predicted_levels": [181.97, 181.96, 181.96, 181.96, 181.96, 181.96],
    "predicted_peak_level": 181.97,
    "confidence": 1.0,
    "safety_flag": "safe",
    "inference_time_ms": 1.23
  }
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| `gate_openings` | [float×3] | **建议闸门开度 (%)** — 核心输出 |
| `predicted_inflows` | [float×6] | LSTM 预测未来6小时入库流量 |
| `predicted_levels` | [float×6] | LSTM 预测未来6小时上游水位 |
| `predicted_peak_level` | float | 预测峰值水位 (m) |
| `confidence` | 0~1 | DQN 置信度 |
| `safety_flag` | string | `safe` / `warning` / `danger` |
| `inference_time_ms` | float | 推理耗时 (毫秒) |

### 3.4 健康检查：GET /api/health

```json
{
  "status": "ok",
  "service": "hydropower-inference",
  "version": "5.0",
  "device": "cpu",
  "uptime_seconds": 3600.5,
  "inference_count": 42,
  "history_buffer_size": 24,
  "timestamp": "2026-07-02T09:38:42"
}
```

---

## 四、Laravel 集成

### 4.1 配置

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

### 4.2 创建 Service 类

新建 `app/Services/AiInferenceService.php`：

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiInferenceService
{
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.ai_inference.url');
        $this->timeout = config('services.ai_inference.timeout', 10);
    }

    /** 核心方法：传入传感器数据，返回闸门决策 */
    public function infer(array $sensor): array
    {
        $response = Http::timeout($this->timeout)
            ->post("{$this->baseUrl}/api/infer", [
                'upstream_level'   => $sensor['upstream_level']   ?? 180.0,
                'downstream_level' => $sensor['downstream_level'] ?? 120.0,
                'inflow'           => $sensor['inflow']           ?? 200.0,
                'rainfall'         => $sensor['rainfall']         ?? 0.0,
                'temperature'      => $sensor['temperature']      ?? 20.0,
                'gate1_opening'    => $sensor['gate1_opening']    ?? 0.0,
                'gate2_opening'    => $sensor['gate2_opening']    ?? 0.0,
                'gate3_opening'    => $sensor['gate3_opening']    ?? 0.0,
            ]);

        if ($response->failed()) {
            throw new \Exception("AI inference failed: HTTP {$response->status()}");
        }

        $body = $response->json();
        if (empty($body['success'])) {
            throw new \Exception("AI inference failed: " . ($body['error'] ?? 'unknown'));
        }

        return $body['data'];
    }

    /** 健康检查 */
    public function health(): array
    {
        return Http::timeout(5)->get("{$this->baseUrl}/api/health")->json();
    }

    /** 获取模型信息 */
    public function modelsInfo(): array
    {
        return Http::timeout(5)->get("{$this->baseUrl}/api/models/info")->json();
    }

    /** 批量推理 */
    public function inferBatch(array $samples): array
    {
        return Http::timeout($this->timeout * count($samples))
            ->post("{$this->baseUrl}/api/infer/batch", ['samples' => $samples])
            ->json();
    }

    /** 重置 LSTM 历史缓冲区 */
    public function resetHistory(): array
    {
        return Http::timeout(5)->post("{$this->baseUrl}/api/history/reset")->json();
    }
}
```

### 4.3 Controller 调用示例

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

    /** GET /api/dispatch/ai-suggestion */
    public function getSuggestion(): JsonResponse
    {
        $sensor = \App\Models\SensorReading::latest()->first();

        try {
            $result = $this->ai->infer($sensor->toArray());

            return response()->json([
                'success'    => true,
                'suggestion' => [
                    'gate_openings'     => $result['gate_openings'],
                    'predicted_peak'    => $result['predicted_peak_level'],
                    'predicted_levels'  => $result['predicted_levels'],
                    'predicted_inflows' => $result['predicted_inflows'],
                    'confidence'        => $result['confidence'],
                    'safety_flag'       => $result['safety_flag'],
                    'inference_time_ms' => $result['inference_time_ms'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
```

### 4.4 调用流程

```
前端 Vue 3 → GET /api/realtime/snapshot → Laravel Controller
  → $this->ai->infer($sensorData)
  → HTTP::post("http://localhost:5000/api/infer")
  → Python Flask → GateController.step() → DQN+LSTM 推理
  → JSON 返回 → Laravel 透传 → 前端渲染
```

### 4.5 异常降级

```php
try {
    $aiResult = $this->ai->infer($sensorData);
} catch (\Exception $e) {
    \Log::warning('AI inference failed: ' . $e->getMessage());
    $aiResult = [
        'gate_openings'  => null,
        'confidence'     => null,
        'safety_flag'    => 'unknown',
    ];
    // AI 挂了前端照常显示传感器数据，不阻塞
}
```

---

## 五、AI 模型规格

### 5.1 模型对比

| | DQN（决策） | LSTM（预测） |
|------|:---:|:---:|
| 算法 | Dueling Double DQN | 2层 BiLSTM + MultiheadAttention |
| 输入 | 12维状态向量 | 24h × 5特征序列 |
| 输出 | 125个离散动作 → 3闸门开度 | 6h × 2 (水位+流量) |
| 参数量 | 284,798 | 831,628 |
| 推理速度 GPU | < 1ms | < 2ms |
| 推理速度 CPU | ~2ms | ~5ms |
| 模型大小 | 1.1 MB | 3.2 MB |

### 5.2 推理流程

```
传感器数据 → 更新24h历史缓冲区
  → LSTM 预测未来6h水位+流量
  → 构建12维增强状态向量
  → DQN 前向推理 (125个动作Q值)
  → argmax 选最优动作 → 解码为3闸门开度
  → 安全判定 (safe/warning/danger)
  → 输出指令 + 置信度 + 安全标志
```

### 5.3 DQN 三目标奖励函数

Score = A×发电收益 + B×安全系数 + C×生态流量（A+B+C=1.0）

| 场景 | 发电A | 安全B | 生态C |
|------|:--:|:--:|:--:|
| 日常运行 | 0.40 | 0.35 | 0.25 |
| 枯水期 | 0.60 | 0.25 | 0.15 |
| 汛期 | 0.15 | 0.70 | 0.15 |
| 生态调度 | 0.20 | 0.25 | 0.55 |

---

# 第二部分：未来阶段 — 硬件现场部署

> ⚠️ 以下内容依赖 Jetson / PLC / 传感器 / 闸门等物理硬件。现阶段不需要操作。

---

## 六、系统架构全景

### 6.1 三层部署架构

```
┌──────────────────────────────────────────────┐
│                 云    端  (非实时)              │
│  Vue 3 前端 + Laravel API + MySQL + OSS       │
│  ★ 数据存储/展示、用户管理、模型离线训练         │
│  ★ 不参与实时 AI 推理和 PLC 控制指令下发        │
└──────────────────┬───────────────────────────┘
                   │  HTTP/HTTPS + WebSocket
┌──────────────────▼───────────────────────────┐
│              边  缘  端  (实时核心)             │
│         NVIDIA Jetson Orin Nano (67 TOPS)     │
│  LSTM 预测 + DQN 决策 + 指令安全网关 + 数据采集  │
│  ★ 单次推理 < 5ms，端到端 ~20ms               │
│  ★ 断网可自治 ≥ 72 小时                        │
└──────────────────┬───────────────────────────┘
                   │  Modbus RS485 (9600bps)
┌──────────────────▼───────────────────────────┐
│               端    侧  (物理执行)              │
│  超声波液位计×2 + 流量计 + PLC S7-200 + 电动推杆│
│  ★ 物理数据采集 + 指令执行，不做软件决策         │
└──────────────────────────────────────────────┘
```

### 6.2 核心设计原则

| 原则 | 说明 |
|------|------|
| **云端不控制** | 云端不参与实时推理和控制指令下发 |
| **边缘可自治** | 断网时边缘端独立运行，联网后自动同步 |
| **安全优先** | 急停全局最高优先级，六道安全校验 |
| **可解释 AI** | 每个决策输出：影响因素 + 方案对比 + 置信度 |
| **全链路可追溯** | 统一 trace_id 串联全链路，日志不可删除 |
| **渐进式信任** | L1仅建议 → L2半自动 → L3全自动 |
| **优雅降级** | WebSocket 优先 + HTTP 轮询降级 |

### 6.3 数据闭环（7 步）

```
① 全域感知 → ② PLC 采集 → ③ 边缘 AI 推理 → ④ 指令下发
   (5s)        Modbus       LSTM+DQN+安全判定    PLC 接收
                              │
    ⑦ 上报云端 ← ⑥ MySQL 记录 ← ⑤ 执行反馈
    MQTT/HTTP    传感器+决策      电动推杆+闸门
```

### 6.4 技术栈

| 层级 | 技术 |
|------|------|
| 前端 | Vue 3 + TypeScript + Pinia + Element Plus + ECharts + DataV + Three.js |
| 后端 | Laravel 10+ + PHP 8.x + WebSocket (Reverb) |
| 数据库 | MySQL 8.0 + Redis |
| AI 框架 | PyTorch 2.x + scikit-learn |
| 边缘推理 | NVIDIA Jetson Orin Nano + JetPack 6.0 |
| 工业通信 | Modbus RTU (minimalmodbus) |
| PLC | 西门子 S7-200 SMART SR20 + EM AE04 |

---

## 七、硬件清单与接线

### 7.1 硬件清单

> 来源: PPT 设计文档，总预算 **¥8,510**

| 序号 | 硬件 | 型号/品牌 | 数量 | 单价 | 关键参数 |
|:--:|------|------|:--:|------|------|
| 1 | 超声波液位计 | 上戈智能 0-5m | 2 | ¥460 | 4-20mA, DC24V |
| 2 | 超声波流量计 | 谊程 DN15 | 1 | ¥300 | RS485 |
| 3 | 电动推杆 | LUILEC | 1 | ¥190 | 行程100mm, 推力100kg |
| 4 | PLC 控制器 | 西门子 S7-200 SMART SR20 | 1 | ¥1,000 | 继电器输出 |
| 5 | 模拟量模块 | EM AE04 | 1 | ¥360 | 4路 4-20mA |
| 6 | **边缘计算网关** | **Jetson Orin Nano 8GB** | 1 | **¥4,700** | 67 TOPS |
| 7 | 开关电源 | 明纬 NDR-240-24 | 1 | ¥290 | AC220V→DC24V, 10A |
| 8 | 循环水泵 | DP2401 | 1 | ¥80 | 12V, 扬程70m |
| 9 | USB转RS485 | 绿联 UGREEN 55839 | 1 | ¥60 | 工业级 |
| 10 | 折叠蓄水池 | 1.5×1m | 1 | ¥290 | 模拟上下游 |
| 11 | 降压模块 | 24V→12V 10A | 1 | ¥70 | 水泵供电 |
| 12 | 快速接头 | DN15 | 1 | ¥30 | 水路连接 |
| 13 | 硅胶软管 | 内径16mm | 1 | ¥100 | 循环水路 |
| 14 | 亚克力闸门板 | 5mm | 2 | ¥120 | 30×30 + 40×40cm |
| | **合计** | | | **¥8,510** | |

### 7.2 Jetson Orin Nano 规格

| 项目 | 规格 |
|------|------|
| AI 算力 | 67 TOPS (Int8) |
| GPU | 1024-core NVIDIA Ampere + 32 Tensor Cores |
| CPU | 6-core ARM Cortex-A78AE |
| 内存 | 8 GB LPDDR5 |
| 存储 | NVMe SSD (建议128GB+) + microSD |
| 功耗 | 7W-15W (被动散热) |
| 系统 | Ubuntu 22.04 + JetPack 6.0 |

### 7.3 硬件连接关系图

```
AC220V → 明纬 NDR-240-24 → DC24V ─┬── PLC S7-200 SR20
                                   ├── EM AE04
                                   ├── 液位计1 (上游, 4-20mA → CH0)
                                   ├── 液位计2 (下游, 4-20mA → CH1)
                                   └── 24V→12V降压 → 水泵 (12V)

Jetson (自带19V电源) ──USB── 绿联 RS485 ── A/B/GND ── PLC RS485 口

SR20 DO 输出:
  Q0.0 → 电动推杆 正转 (闸门上升/开)
  Q0.1 → 电动推杆 反转 (闸门下降/关)
  Q0.2 → 急停继电器1 (切断推杆)
  Q0.3 → 急停继电器2 (切断水泵)
```

### 7.4 Modbus 寄存器映射

| 地址 | 含义 | 方向 | 换算 |
|------|------|:--:|------|
| 40001 | 上游水位 (m) | 读 | val/100 |
| 40002 | 下游水位 (m) | 读 | val/100 |
| 40003 | 入库流量 (m³/s) | 读 | val/10 |
| 40004 | 降雨量 (mm/h) | 读 | val/10 |
| 40005 | 温度 (°C) | 读 | val/10 |
| 40006-40008 | 闸门1/2/3 当前开度 (%) | 读 | val/100 |
| 40010-40012 | 闸门1/2/3 目标开度 (%) | 写 | val×100 |
| 40020 | 1=AI自动 / 0=手动 | 写 | — |
| 40021 | 1=急停 | 写 | — |

---

## 八、Jetson 烧录指南 — VMware 方式

### 8.1 为什么需要虚拟机？

Jetson 出厂没系统。烧录工具 SDK Manager 只能在 Ubuntu 上跑。你电脑是 Windows，所以用 VMware 在 Windows 里套一个 Ubuntu 虚拟机来跑 SDK Manager。

> 虚拟机 = 一个大文件。不影响 Windows，不用分区，删掉文件夹就干干净净。

### 8.2 准备清单

| 需要的 | 哪里来 |
|--------|--------|
| VMware Workstation Pro 17 | 官网下载，个人免费 |
| Ubuntu 22.04 ISO (~4.7GB) | `https://releases.ubuntu.com/jammy/` |
| NVIDIA 开发者账号 | 免费注册 |
| USB-C 数据线（能传数据的） | 你现有那根就行 |
| 跳线帽 | Jetson 套件自带 |
| 硬盘空闲 100GB+ | 你电脑 D 盘 |

### 8.3 第 1 步：安装 VMware

1. 访问 `https://www.vmware.com/products/workstation-pro.html`
2. 下载 VMware Workstation Pro 17 for Windows（~600MB）
3. 双击 `.exe` → 一路下一步 → 完成

### 8.4 第 2 步：创建 Ubuntu 虚拟机

1. 打开 VMware → 点「创建新的虚拟机」
2. 选「典型(推荐)」→ 下一步
3. 选「安装程序光盘映像文件(iso)」→ 浏览 → 选 `ubuntu-22.04.4-desktop-amd64.iso` → 下一步
4. 客户机操作系统选 **「Linux」**，版本选 **「Ubuntu 64-bit」** → 下一步
5. 名称 `Ubuntu-Jetson`，位置 D 盘 → 下一步
6. 磁盘大小 **80GB**，选「将虚拟磁盘存储为单个文件」→ 下一步
7. 点「自定义硬件」→ 内存拉 **8GB** → CPU 核心数 **4** → 关闭 → 完成

> ⚠️ 必须是 Ubuntu 22.04，不能是 24.04。

### 8.5 第 3 步：安装 Ubuntu

虚拟机自动启动，进入 Ubuntu 安装界面：

1. 选语言 → Install Ubuntu
2. 键盘默认 → Continue
3. Normal installation，两个勾都勾 → Continue
4. Erase disk and install Ubuntu → Install Now → Continue
5. 时区选 Shanghai → Continue
6. 创建用户:
   - Your name: `hydropower`
   - Password: 设简单密码如 `123456`
   - 选「Log in automatically」
7. Continue → 等 10-15 分钟 → Restart Now

### 8.6 第 4 步：装 SDK Manager

Ubuntu 桌面里 `Ctrl+Alt+T` 打开终端：

```bash
# 更新系统
sudo apt update && sudo apt upgrade -y

# 安装依赖
sudo apt install -y python3-pip ssh curl

# 下载 SDK Manager
# 浏览器访问 https://developer.nvidia.com/sdk-manager
# 登录 NVIDIA 账号 → 下载 .deb 文件

# 安装
cd ~/Downloads
sudo dpkg -i sdkmanager*.deb
sudo apt install -f -y
sudo dpkg -i sdkmanager*.deb

# 启动
sdkmanager
```

### 8.7 第 5 步：Jetson 进入恢复模式

**操作顺序严格按这个来：**

```
① Jetson 断电
② 跳线帽套在 FC REC 和 GND 两个针脚上
③ USB-C 数据线一头连 Jetson，另一头连你 PC
④ 插 Jetson 电源
```

VMware 菜单: **虚拟机 → 可移动设备 → NVIDIA Corp. APX → 连接**

验证：
```bash
lsusb | grep NVIDIA
# 有输出 → 连上了
```

### 8.8 第 6 步：SDK Manager 烧录

1. 登录 NVIDIA 账号
2. STEP 1: 勾选 `Jetson Orin Nano 8GB`，**取消 Host Machine**
3. STEP 2: 选 JetPack 6.0，勾选 CUDA + cuDNN + TensorRT + OpenCV + **PyTorch**
4. STEP 3: 设 Jetson 用户名 `hydropower`，密码自定（**记住！**）
5. 点 Flash → 等 30-60 分钟 → 显示 `Installation completed`

### 8.9 第 7 步：Jetson 首次开机

1. 拔 Jetson 电源
2. 拔 USB-C 数据线
3. **拔掉 FC REC 跳线帽（必须！）**
4. 插显示器/触摸屏 + 键盘鼠标
5. 插电源 → 开机 → Ubuntu 桌面出现
6. 用第 6 步设的密码登录

验证:
```bash
python3 -c "import torch; print(torch.cuda.is_available())"
# → True
```

---

## 九、Jetson 推理服务部署

### 9.1 安装推理依赖

```bash
sudo apt install -y python3-pip libmysqlclient-dev nginx
pip3 install torch numpy scikit-learn joblib mysqlclient minimalmodbus flask flask-cors gunicorn
```

### 9.2 拷贝部署包

```bash
# U盘方式
sudo mkdir -p /opt/hydropower
sudo cp -r /media/hydropower/USB/hydropower_deploy/* /opt/hydropower/
sudo chown -R hydropower:hydropower /opt/hydropower

# 测试
cd /opt/hydropower
python3 api_server.py --port 5000
# 看到 "Listening on http://0.0.0.0:5000" 即成功
```

### 9.3 配置生产环境

**systemd 服务:**

```bash
sudo tee /etc/systemd/system/hydropower-inference.service << 'EOF'
[Unit]
Description=Hydropower AI Inference Service
After=network.target

[Service]
Type=simple
User=hydropower
WorkingDirectory=/opt/hydropower
ExecStart=/home/hydropower/.local/bin/gunicorn -w 4 -b 0.0.0.0:5000 --timeout 30 wsgi:app
Restart=always
RestartSec=10
StandardOutput=append:/var/log/hydropower.log
StandardError=append:/var/log/hydropower.log

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable hydropower-inference
sudo systemctl start hydropower-inference
```

**防火墙:**

```bash
sudo ufw allow from 192.168.0.0/16 to any port 5000
sudo ufw allow 22/tcp
sudo ufw enable
```

### 9.4 部署配置 (deploy_config.json)

```json
{
  "device": "cuda",
  "inference": { "interval_seconds": 5, "history_buffer_size": 24 },
  "safety": { "threshold_danger": 190.0, "threshold_warning": 188.0 },
  "execution_mode": { "level": "L2", "auto_confidence_threshold": 0.80, "auto_opening_change_max": 10.0 },
  "plc": { "port": "/dev/ttyUSB0", "slave_id": 1, "baudrate": 9600, "timeout": 1.0 },
  "mysql": { "host": "localhost", "port": 3306, "user": "hydropower", "password": "GYZ032411", "database": "hydropower_smart" }
}
```

---

## 十、PLC + 传感器 + 物理模型搭建

### 10.1 物理模型搭建顺序

**1. 搭建水路系统:**

```
┌─────────────────────────────────────┐
│         折叠蓄水池 (1.5×1m)           │
│  ┌───────────────┬─────────────────┐│
│  │ 上游 (高水位)  │ 下游 (低水位)    ││
│  │ [液位计1]     │ [液位计2]       ││
│  └───────┬───────┴────────┬────────┘│
│          │ ← 亚克力隔板(40×40cm) →   │
│          │   (中间开闸门口)          │
└──────────┼────────────────┼─────────┘
           │                │
   ┌───────▼────┐  ┌────────▼─────────┐
   │  循环水泵   │  │ 超声波流量计 DN15 │
   │  12V 隔膜泵 │  │ RS485            │
   └───────┬────┘  └────────┬─────────┘
           └────────┬───────┘
                    │
              硅胶软管回路
```

**2. 安装传感器:**

| 传感器 | 位置 | 接线 |
|--------|------|------|
| 液位计1 | 上游区，距池底30cm垂直 | 2芯 4-20mA → EM AE04 CH0 |
| 液位计2 | 下游区，距池底30cm垂直 | 2芯 4-20mA → EM AE04 CH1 |
| 流量计 | 下游出水管路中间 | 4芯 RS485(A/B/GND/VCC) |

**3. 安装闸门:**

```
电动推杆 固定在上方支架 → 伸缩带动 亚克力闸门板(30×30cm)
  → 卡在隔板槽内上下滑动 → 控制上下游水流
```

### 10.2 电源接线

```
AC220V → 明纬 NDR-240-24
  → DC24V → PLC SR20 (L+/M)
  → DC24V → EM AE04 (L+/M)
  → DC24V → 液位计1, 液位计2
  → DC24V → 24V→12V降压模块 → 水泵

Jetson: 自带19V电源适配器，单独供电
```

### 10.3 EM AE04 模拟量接线

| EM AE04 端子 | 连接 | 信号 |
|-------------|------|------|
| CH0+ / CH0- | 液位计1 | 4-20mA |
| CH1+ / CH1- | 液位计2 | 4-20mA |
| CH2+ / CH2- | 预留 | 4-20mA |
| CH3+ / CH3- | 预留 | 4-20mA |

### 10.4 SR20 DO 控制接线

| DO 端子 | 连接 | 功能 |
|---------|------|------|
| Q0.0 | 电动推杆正转 | 闸门上升 (开) |
| Q0.1 | 电动推杆反转 | 闸门下降 (关) |
| Q0.2 | 急停继电器1 | 切断推杆供电 |
| Q0.3 | 急停继电器2 | 切断水泵供电 |

### 10.5 RS485 通信接线

```
Jetson USB → 绿联 USB-RS485 → A(+) → PLC RS485 A(+)
                              → B(-) → PLC RS485 B(-)
                              → GND  → PLC RS485 GND
```

参数: 9600bps, 8N1, Modbus RTU, 从站地址=1

### 10.6 PLC 编程要点

在 STEP 7-Micro/WIN SMART 中配置：

| 配置项 | 值 |
|--------|-----|
| 通信口 | RS485 (Port 0) |
| 协议 | Modbus RTU 从站 |
| 地址 | 1 |
| 波特率 | 9600, 8N1 |

V 存储区 → Modbus 保持寄存器映射：
- VW0-VW8 → 40001-40005 (传感器读数)
- VW10-VW14 → 40006-40008 (闸门反馈)
- VW18-VW22 → 40010-40012 (目标开度)
- VW38 → 40020 (AI自动/手动)
- VW40 → 40021 (急停)

### 10.7 调试验证

```bash
# 1. 测试读传感器
python3 -c "
import minimalmodbus
inst = minimalmodbus.Instrument('/dev/ttyUSB0', 1)
inst.serial.baudrate = 9600
val = inst.read_register(0, functioncode=3)
print(f'上游水位: {val/100:.2f} m')
"

# 2. 测试写闸门
python3 -c "
import minimalmodbus
inst = minimalmodbus.Instrument('/dev/ttyUSB0', 1)
inst.serial.baudrate = 9600
inst.write_register(9, 100)  # 闸门1开度 100%
print('已写入')
"

# 3. 完整联调（传感器→AI推理→闸门动作）
cd /opt/hydropower
python3 inference_server.py --daemon --interval 5
```

---

## 十一、云端 ↔ 边缘通信配置

### 11.1 通信架构

| 通道 | 协议 | 用途 | 频率 |
|------|------|------|:--:|
| 边缘→云端 | MQTT/HTTP | 监测数据、AI 决策上报 | 5s |
| 云端→边缘 | WebSocket | 人工指令、配置热更新 | 事件 |
| 前端↔云端 | WebSocket(主) + HTTP(降级) | 实时展示 | 5s |
| 边缘→PLC | Modbus RTU | 传感器读、闸门写 | 5s |

### 11.2 WebSocket 消息类型

| 消息类型 | 方向 | 内容 |
|---------|------|------|
| `water_level` | 边缘→云→前端 | 上下游水位、水头 |
| `flow_rate` | 边缘→云→前端 | 入库/出库流量 |
| `gate_status` | 边缘→云→前端 | 闸门开度、状态 |
| `alarm` | 边缘→云→前端 | 告警通知（事件触发） |
| `decision` | 边缘→云→前端 | AI 决策三要素（事件触发） |
| `command` | 云端→边缘 | 控制/配置指令 |
| `heartbeat` | 双向 | 心跳保活 (30s) |

### 11.3 云端配置

`deploy_config.json`:
```json
{
  "cloud": {
    "api_url": "https://server/api/edge",
    "ws_url": "wss://server/app/edge",
    "report_interval_seconds": 5,
    "heartbeat_interval_seconds": 30
  }
}
```

---

## 十二、安全体系

### 12.1 五层防护

```
第一层 — 传输安全: HTTPS + WSS + Token 拦截器
第二层 — 认证安全: bcrypt + 5次锁定30min + 首次强制改密 + IP 限流
第三层 — 授权安全: 路由守卫 + API 中间件 + v-permission 按钮指令
第四层 — 指令安全: 设备身份 → HMAC签名 → 时间戳(±30s) → 防重放 → 权限 → 模式
第五层 — 操作安全: 二次确认 + 急停最高优先级 + 不可删除日志
```

### 12.2 指令包格式

```json
{
  "edge_id": "jetson-hydropower-01",
  "command_id": "cmd-20260702-143025-abc123",
  "payload": { "gate_openings": [100, 75, 50] },
  "sign": "HMAC-SHA256(command_id+payload+timestamp, secret)",
  "expire_at": 1759932055,
  "nonce": "a1b2c3d4e5f6g7h8i9j0"
}
```

校验: 设备身份 → 签名 → 时间戳 → 防重放 → 权限 → 模式 → 执行

### 12.3 急停特殊处理

- 不经过 AI 推理模块，直接写 PLC 40021 = 1
- 不校验运行模式，任何模式均可触发
- 不校验权限，所有登录用户均可触发
- 响应 < 100ms
- 前端全局固定定位红色按钮，所有页面可见

---

## 十三、三级自动执行模式

| 等级 | 模式 | 行为 | 典型场景 |
|:--:|------|------|------|
| **L1** | 仅建议 | AI 只出方案，须人工确认 | 系统刚上线、汛期高危 |
| **L2** | 半自动 | 置信度≥80%且变化≤10%时自动，否则降级 L1 | 日常运行 |
| **L3** | 全自动 | AI 决策自动下发，异常告警等人工 | 枯水期稳定工况 |

---

## 十四、断网自治机制

```
① 心跳超时 60s → 判定断网
② 进入自治:
   ├── LSTM/DQN 继续本地推理
   ├── 告警用本地缓存阈值
   ├── 数据写入本地 SQLite/文件缓存
   └── L2/L3 自动调度照常执行
③ 网络恢复:
   ├── WebSocket 自动重连 (指数退避)
   ├── 批量上传缓存数据 (按时间顺序)
   └── 同步最新配置 → 恢复正常
```

自治能力: **≥ 72 小时**本地缓存。

---

## 十五、模型重训练

### 15.1 用真实数据重训练

```powershell
# 1. 导出 MySQL 数据
mysql -u root -p -e "
  SELECT * FROM hydropower_smart.sensor_readings
  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
  ORDER BY created_at
" > real_data.csv

# 2. 替换模拟数据，重训练
python train_dqn_final.py      # ~11 min
python train_lstm_final.py     # ~3 min

# 3. 导出 + 下发 Jetson
python deploy.py
scp -r hydropower_deploy/ hydropower@jetson:/tmp/
ssh hydropower@jetson "sudo rsync -a /tmp/hydropower_deploy/ /opt/hydropower/ && sudo systemctl restart hydropower-inference"
```

> 建议周期: 每季度重训练一次。

### 15.2 模型版本管理

上传 → 格式校验 → 沙箱测试 → 可用/不可用 → 激活/回滚

---

## 十六、故障排查

### Jetson

| 症状 | 排查/解决 |
|------|------|
| 无法启动 | `dmesg \| grep error` |
| CUDA 不可用 | `python3 -c "import torch; print(torch.cuda.is_available())"` |
| 推理全是同一结果 | `md5sum /opt/hydropower/models/*.pt` 对比 checksums.md5 |
| `CUDA out of memory` | 改 `deploy_config.json` 中 `"device":"cpu"` |

### PLC 通信

| 症状 | 排查/解决 |
|------|------|
| `Timeout` | 检查 A/B/GND 接线，`ls /dev/ttyUSB0`，测 A-B 电阻(~120Ω) |
| `Checksum error` | 确认 PLC 和程序波特率均为 9600, 8N1 |
| `Illegal data address` | 核对 VW→4xxxx 映射 |
| 值不变 | 确认 PLC 在 RUN 模式（绿灯常亮） |

### 传感器

| 症状 | 排查/解决 |
|------|------|
| 水位恒为 0 | 测 4-20mA 回路电流，0mA=断路 |
| 水位乱跳 | 水面波动/电磁干扰，加滑动平均滤波 |

### MySQL / WebSocket

| 症状 | 解决 |
|------|------|
| `Can't connect` | `sudo systemctl status mysql` |
| `Access denied` | 检查 `deploy_config.json` 中 user/password |
| WS 断开 | 前端内置自动重连 + HTTP 降级 |
| 边缘无法上报 | `curl http://云端IP/api/health` |

---

## 十七、部署前检查清单

- [ ] Jetson 已烧录 JetPack 6.0，CUDA 可用
- [ ] Python 依赖全部安装 (`pip3 list | grep torch flask`)
- [ ] 部署包已拷贝到 `/opt/hydropower/`
- [ ] `deploy_config.json` 配置已更新
- [ ] API 测试: `curl http://localhost:5000/api/health` → ok
- [ ] PLC RS485 通信正常 (读 40001 有值)
- [ ] 液位计通电，4-20mA 回路正常
- [ ] 推杆受控伸缩
- [ ] 急停能切断推杆/水泵供电
- [ ] systemd 服务已注册，重启自动启动
- [ ] 防火墙已配置
- [ ] 云端 API + WebSocket 可连通

---

## 十八、常用命令速查

```bash
# === Jetson ===
sudo systemctl start|stop|restart hydropower-inference
journalctl -u hydropower-inference -f --since "10 min ago"
python3 /opt/hydropower/inference_server.py              # 手动测试
curl http://localhost:5000/api/health                     # 健康检查

# === PLC 调试 ===
python3 -c "import minimalmodbus; m=minimalmodbus.Instrument('/dev/ttyUSB0',1); m.serial.baudrate=9600; print(m.read_register(0))"

# === PC 训练 ===
cd D:\hydropower_scheduling
python train_dqn_final.py && python train_lstm_final.py
python deploy.py

# === PC API ===
cd D:\hydropower_deploy
pip install -r requirements.txt
python api_server.py --port 5000
```

---

## 十九、部署包文件清单

| 文件 | 作用 | 阶段 |
|------|------|:--:|
| `api_server.py` | HTTP API 服务 (Flask) | ⭐ 现在 |
| `inference_server.py` | 推理引擎 (GateController) | ⭐ 现在 |
| `database.py` | MySQL 读写 | ⭐ 现在 |
| `deploy_config.json` | 统一配置 | ⭐ 现在 |
| `requirements.txt` | Python 依赖列表 | ⭐ 现在 |
| `models/dqn_scripted.pt` | DQN 模型 (1.1MB) | ⭐ 现在 |
| `models/lstm_state_dict.pt` | LSTM 模型 (3.2MB) | ⭐ 现在 |
| `models/scaler_X.pkl` | 归一化器 | ⭐ 现在 |
| `models/checksums.md5` | 模型完整性校验 | ⭐ 现在 |
| `README.md` | 快速开始说明 | ⭐ 现在 |
| `水电站AI调度系统_完整部署手册.md` | 本文档（完整手册） | ⭐ 现在 |
| `scripts/hydropower.service` | systemd 服务文件 | 后期 |
| `scripts/health_check.sh` | 定时健康检查脚本 | 后期 |
| `scripts/backup_db.sh` | 数据库备份脚本 | 后期 |
| `data/` | 本地缓存目录 | 后期 |
| `logs/` | 日志目录 | 后期 |
