# 水电站闸门智能调度 — AI 推理接口文档

> 给组长：模型训练和部署已就绪，以下是调用方式。

---

## 零、这个服务谁来启动？

部署包就绪，启动方式三选一：

### 方式 A：组长自己启动（推荐 ⭐）

把 `D:\hydropower_deploy\` 整个文件夹拷到组长电脑上：

```powershell
cd D:\hydropower_deploy
pip install -r requirements.txt
python api_server.py --port 5000
```

然后 Laravel 配 `AI_INFERENCE_URL=http://localhost:5000`，本地闭环。

> 推理只依赖 CPU，不需要 GPU，任意 Windows/Linux/Mac 都行。

### 方式 B：你启动，组长远程调

你的 PC 跑 `api_server.py`，组长 `.env` 配你的 IP：

```
AI_INFERENCE_URL=http://你的IP:5000
```

> 缺点：你的电脑得一直开着，且要在同一网络。

### 方式 C：部署到共用开发服务器

如果有 Linux 开发服务器，scp 上去跑，全组都能调：

```bash
scp -r D:\hydropower_deploy user@dev-server:/opt/
ssh user@dev-server
cd /opt/hydropower_deploy
pip install -r requirements.txt
nohup python api_server.py --port 5000 &
```

---

## 一、部署包位置

```
D:\hydropower_deploy\
├── api_server.py          ← HTTP API 服务入口
├── inference_server.py    ← 推理引擎
├── database.py            ← MySQL 读写
├── deploy_config.json     ← 配置文件
├── requirements.txt       ← Python 依赖
├── models/
│   ├── dqn_scripted.pt    ← DQN 决策模型 (1.1MB)
│   ├── lstm_state_dict.pt ← LSTM 预测模型 (3.2MB)
│   └── scaler_X.pkl       ← 数据归一化器
```

---

## 二、启动服务

```powershell
# 1. 安装依赖（首次）
cd D:\hydropower_deploy
pip install -r requirements.txt

# 2. 启动 API 服务
python api_server.py --port 5000

# 看到以下输出即成功：
#   [Init] Models loaded successfully
#   [Init] Listening on http://0.0.0.0:5000
```

**参数说明：**

| 参数 | 默认值 | 说明 |
|------|--------|------|
| `--port` | 5000 | 监听端口 |
| `--host` | 0.0.0.0 | 监听地址 |
| `--no-db` | 关闭 | 不加此参数会自动连接 MySQL 记录推理日志 |
| `--debug` | 关闭 | Flask 调试模式（开发用） |

---

## 三、接口列表

### 1. 核心推理 `POST /api/infer`

**请求：**

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

**响应：**

```json
{
  "success": true,
  "data": {
    "gate_openings": [100.0, 100.0, 50.0],
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
| `gate_openings` | [float×3] | **建议闸门开度 (%)** — 这是核心输出 |
| `predicted_inflows` | [float×6] | LSTM 预测未来 6 小时入库流量 |
| `predicted_levels` | [float×6] | LSTM 预测未来 6 小时上游水位 |
| `predicted_peak_level` | float | 预测峰值水位 (m) |
| `confidence` | 0~1 | DQN 置信度 |
| `safety_flag` | string | `safe` 安全 / `warning` 警告 / `danger` 危险 |
| `inference_time_ms` | float | 推理耗时 (毫秒) |

---

### 2. 健康检查 `GET /api/health`

```
GET http://localhost:5000/api/health
```

```json
{
  "status": "ok",
  "service": "hydropower-inference",
  "version": "5.0",
  "device": "cpu",
  "uptime_seconds": 3600.5,
  "inference_count": 42,
  "history_buffer_size": 24
}
```

---

### 3. 模型信息 `GET /api/models/info`

返回已加载模型的参数、安全阈值、水库配置等。

---

### 4. 重置历史 `POST /api/history/reset`

清空 LSTM 历史缓冲区（切换仿真工况时使用，无请求体）。

---

### 5. 批量推理 `POST /api/infer/batch`

```json
{
  "samples": [
    {"upstream_level": 182.0, "inflow": 250.0, ...},
    {"upstream_level": 182.1, "inflow": 255.0, ...}
  ]
}
```

按顺序推理，上一组结果会更新 LSTM 上下文，影响后续预测。

---

## 四、Laravel 后端调用示例

```php
use Illuminate\Support\Facades\Http;

// 单次推理
$response = Http::post('http://localhost:5000/api/infer', [
    'upstream_level'   => $sensor->upstream_level,
    'downstream_level' => $sensor->downstream_level,
    'inflow'           => $sensor->inflow,
    'rainfall'         => $sensor->rainfall,
    'temperature'      => $sensor->temperature,
    'gate1_opening'    => $sensor->gate1_opening,
    'gate2_opening'    => $sensor->gate2_opening,
    'gate3_opening'    => $sensor->gate3_opening,
]);

$result = $response->json();

if ($result['success']) {
    $gates     = $result['data']['gate_openings'];      // [100, 100, 50]
    $safety    = $result['data']['safety_flag'];         // "safe"
    $confidence = $result['data']['confidence'];         // 0.95
    $peak      = $result['data']['predicted_peak_level']; // 181.97
}
```

---

## 五、AI 模型说明

| | DQN（决策） | LSTM（预测） |
|------|:---:|:---:|
| 算法 | Dueling Double DQN | 2层 BiLSTM + Attention |
| 输入 | 12维状态（当前水位/流量/开度 + LSTM预测结果） | 过去24小时 × 5特征序列 |
| 输出 | 3个闸门最优开度 (125种组合) | 未来6小时水位 + 流量 |
| 参数量 | 284,798 | 831,628 |
| 推理速度 | < 1ms (GPU) | < 2ms (GPU) |
| 模型文件 | dqn_scripted.pt (1.1MB) | lstm_state_dict.pt (3.2MB) |

**工作流程：** 传感器数据 → LSTM 预测未来6小时来水 → DQN 综合当前状态+LSTM预测 → 输出最优闸门开度 + 置信度 + 安全判定。

---

## 六、注意事项

1. **LSTM 需要历史数据预热** — 服务刚启动时历史缓冲区为空，前 24 次推理使用均值填充。连续推理 24 次后预测越来越准。
2. **切换仿真工况时** — 调 `POST /api/history/reset` 清空缓冲区。
3. **MySQL 连接** — 服务启动时会自动尝试连接（配置在 `deploy_config.json` 中），连接失败不影响推理，仅跳过日志写入。
4. **生产环境** — 建议用 `waitress` 或 `gunicorn` 替代 Flask 内置服务器。

---

> **模型训练人：[你的名字]**
>
> **日期：2026-07-02**
