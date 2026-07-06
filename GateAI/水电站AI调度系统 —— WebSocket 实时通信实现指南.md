# 水电站AI调度系统 —— WebSocket 实时通信实现指南

> 版本: 1.0 | 日期: 2026-07-06
>
> 本文档为《水电站AI调度系统_完整部署手册》**第 6 章（系统架构）**、**第 11 章（云端↔边缘通信）** 及 **第 14 章（断网自治）** 的配套实现指南。
> 覆盖 **前端（Vue 3） ↔ 云端（Laravel）** 与 **云端（Laravel） ↔ 边缘（Jetson Python）** 的全链路 WebSocket 代码落地。

## 目录

1. 通信架构回顾
2. 环境依赖安装
3. 云端实现 —— Laravel Reverb 服务端
4. 边缘端实现 —— Jetson Python 客户端
5. 前端实现 —— Vue 3 订阅端
6. 消息协议规范
7. 断网重连与降级机制
8. 配置清单与启动验证

## 一、通信架构回顾

根据手册 **6.1 节** 与 **11.1 节**，WebSocket 承担两条实时链路：

| 链路            | 协议                   | 方向 | 频率               | 承载内容                                    |
| :-------------- | :--------------------- | :--- | :----------------- | :------------------------------------------ |
| **前端 ↔ 云端** | WSS (Laravel Reverb)   | 双向 | 事件驱动           | 水位/流量实时展示、AI决策推送、人工指令下发 |
| **云端 ↔ 边缘** | WSS (Python WebSocket) | 双向 | 5s 上报 / 事件指令 | 传感器数据上报、配置热更新、远程控制        |

> **核心原则**（手册 6.2 节）：云端不参与实时推理控制，但承担指令中转与数据分发。

## 二、环境依赖安装

### 2.1 云端 Laravel 端

bash

```
composer require laravel/reverb
php artisan reverb:install
php artisan vendor:publish --tag=reverb-config
```



### 2.2 边缘 Jetson Python 端

bash

```
pip3 install websockets
# 已有依赖：flask, flask-cors, torch, numpy, minimalmodbus
```



### 2.3 前端 Vue 3 端

bash

```
npm install laravel-echo pusher-js
```



## 三、云端实现 —— Laravel Reverb 服务端

> 对应手册 **11.2 节（消息类型）** 与 **11.3 节（云端配置）**。

### 3.1 环境变量配置 (`.env`)

ini

```
REVERB_APP_ID=hydropower-app
REVERB_APP_KEY=your-app-key-here
REVERB_APP_SECRET=your-app-secret-here
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=https

# 边缘设备通信共享密钥（用于 HMAC 签名，对应手册 12.2 节）
EDGE_SHARED_SECRET=GYZ_EDGE_SECURE_2026
```



### 3.2 创建数据推送事件 (EdgeDataUpdated)

新建 `app/Events/EdgeDataUpdated.php`：

php

```
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * 边缘数据推送事件 —— 对应手册 11.2 节消息类型
 * 使用 ShouldBroadcastNow 确保实时性，不走队列
 */
class EdgeDataUpdated implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public string $edgeId;
    public string $type;      // water_level | flow_rate | gate_status | alarm | decision
    public array $payload;
    public string $timestamp;

    public function __construct(string $edgeId, string $type, array $payload)
    {
        $this->edgeId = $edgeId;
        $this->type = $type;
        $this->payload = $payload;
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * 定义广播频道：每个边缘设备独立频道
     * 前端订阅 edge.{edgeId} 即可接收
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('edge.' . $this->edgeId)
        ];
    }

    /**
     * 定义广播数据结构（严格贴合手册 11.2 节）
     */
    public function broadcastWith(): array
    {
        return [
            'type'      => $this->type,
            'edge_id'   => $this->edgeId,
            'payload'   => $this->payload,
            'timestamp' => $this->timestamp,
        ];
    }

    public function broadcastAs(): string
    {
        return 'edge.data';
    }
}
```



### 3.3 创建下发指令事件 (CommandIssued)

新建 `app/Events/CommandIssued.php`（云端 → 边缘 指令下发）：

php

```
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class CommandIssued implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public string $commandId;
    public array $payload;      // 例如 ['gate_openings' => [100, 75, 50]]
    public string $sign;
    public int $expireAt;
    public string $nonce;

    public function __construct(string $edgeId, array $payload)
    {
        $this->commandId = 'cmd-' . now()->format('Ymd-His') . '-' . substr(md5(uniqid()), 0, 6);
        $this->payload = $payload;
        $this->expireAt = now()->addSeconds(30)->timestamp;
        $this->nonce = bin2hex(random_bytes(10));

        // HMAC-SHA256 签名（对应手册 12.2 节指令包格式）
        $signatureRaw = $this->commandId . json_encode($payload) . $this->expireAt . $this->nonce;
        $this->sign = hash_hmac('sha256', $signatureRaw, config('services.edge.secret'));
    }

    public function broadcastOn(): array
    {
        return [new Channel('edge.' . $this->edgeId)];
    }

    public function broadcastWith(): array
    {
        return [
            'type'       => 'command',
            'command_id' => $this->commandId,
            'payload'    => $this->payload,
            'sign'       => $this->sign,
            'expire_at'  => $this->expireAt,
            'nonce'      => $this->nonce,
        ];
    }
}
```



### 3.4 在 Controller 中触发推送

php

```
<?php

namespace App\Http\Controllers;

use App\Events\EdgeDataUpdated;
use App\Events\CommandIssued;
use Illuminate\Http\Request;

class EdgeStreamController extends Controller
{
    // 边缘数据上报入口（边缘端 HTTP 或内部事件触发）
    public function publishEdgeData(Request $request)
    {
        $edgeId = $request->input('edge_id', 'jetson-hydropower-01');
        $type = $request->input('type', 'water_level');
        $payload = $request->input('payload', []);

        broadcast(new EdgeDataUpdated($edgeId, $type, $payload));

        return response()->json(['sent' => true]);
    }

    // 人工下发闸门指令（触发 CommandIssued）
    public function sendGateCommand(Request $request)
    {
        $edgeId = $request->input('edge_id', 'jetson-hydropower-01');
        $openings = $request->input('gate_openings', [100, 100, 50]);

        broadcast(new CommandIssued($edgeId, ['gate_openings' => $openings]));

        return response()->json([
            'success' => true,
            'command_id' => $command->commandId // 实际需重构返回
        ]);
    }
}
```



### 3.5 启动 Reverb 服务

bash

```
# 前台调试
php artisan reverb:start --host=0.0.0.0 --port=8080

# 生产环境建议通过 Supervisor 守护（配置参考手册第 9.3 节 systemd 风格）
```



## 四、边缘端实现 —— Jetson Python 客户端

> 对应手册 **11.3 节（云端↔边缘通信）** 与 **14 节（断网自治）**。
> 边缘端作为 WebSocket **客户端**，主动连接云端 Reverb 服务。

### 4.1 部署配置文件 (`deploy_config.json`) 追加 `cloud` 字段

json

```
{
  "device": "cuda",
  "inference": { "interval_seconds": 5, "history_buffer_size": 24 },
  "plc": { "port": "/dev/ttyUSB0", "slave_id": 1, "baudrate": 9600, "timeout": 1.0 },
  "cloud": {
    "ws_url": "wss://your-domain.com:8080/app/edge",
    "api_url": "https://your-domain.com/api/edge",
    "edge_id": "jetson-hydropower-01",
    "shared_secret": "GYZ_EDGE_SECURE_2026",
    "report_interval_seconds": 5,
    "heartbeat_interval_seconds": 30
  }
}
```



### 4.2 边缘 WebSocket 客户端代码

新建 `/opt/hydropower/edge_ws_client.py`：

python

```
#!/usr/bin/env python3
import asyncio
import json
import time
import hmac
import hashlib
import logging
from typing import Dict, Any
import websockets
import json

# 假设已有配置加载模块
from deploy_config import config  # 或直接 json.load

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

class EdgeWSClient:
    def __init__(self):
        self.edge_id = config['cloud']['edge_id']
        self.ws_url = config['cloud']['ws_url']
        self.secret = config['cloud']['shared_secret']
        self.report_interval = config['cloud'].get('report_interval_seconds', 5)
        self.websocket = None
        self.running = True
        self.cached_data = []  # 断网时本地缓存（对应手册14节）

    async def connect_and_loop(self):
        """主循环：带指数退避的重连机制"""
        retry_count = 0
        while self.running:
            try:
                # 指数退避重连（手册14节：30s超时判定后重试）
                if retry_count > 0:
                    wait = min(60, 2 ** retry_count)
                    logger.info(f"断网重连，等待 {wait}s...")
                    await asyncio.sleep(wait)

                async with websockets.connect(
                    self.ws_url,
                    extra_headers={"Edge-ID": self.edge_id},
                    ping_interval=30,      # 心跳保活（手册11.2）
                    ping_timeout=10,
                    close_timeout=5
                ) as ws:
                    self.websocket = ws
                    retry_count = 0
                    logger.info(f"✅ WebSocket 已连接云端 ({self.ws_url})")

                    # 网络恢复：上传缓存数据（手册14节）
                    if self.cached_data:
                        await self._upload_cached(ws)

                    # 并发执行：发送数据 + 接收指令
                    await asyncio.gather(
                        self._send_loop(ws),
                        self._receive_loop(ws)
                    )

            except websockets.ConnectionClosed:
                logger.warning("⚠️ 连接已关闭（服务端断开）")
                retry_count += 1
            except Exception as e:
                logger.error(f"❌ 连接异常: {e}")
                retry_count += 1

    async def _send_loop(self, ws):
        """定时上报传感器 & AI 决策数据（手册11.1：5s频率）"""
        while self.running:
            try:
                # 1. 从 PLC / 本地缓存读取最新数据（此处模拟，实际调 minimalmodbus）
                sensor_data = self._read_plc_sensors()
                
                # 2. 组装标准消息（手册11.2节）
                message = {
                    "type": "water_level",  # 或 "flow_rate" / "decision"
                    "edge_id": self.edge_id,
                    "payload": sensor_data,
                    "timestamp": time.time()
                }
                await ws.send(json.dumps(message))
                logger.debug(f"📤 上报数据: {sensor_data}")

                # 3. 如果是决策结果，额外推送 decision 类型
                # （实际由 inference_server 触发，这里仅为示例）
                if self._has_new_decision():
                    decision_msg = {
                        "type": "decision",
                        "edge_id": self.edge_id,
                        "payload": self._get_latest_decision(),
                        "timestamp": time.time()
                    }
                    await ws.send(json.dumps(decision_msg))

                await asyncio.sleep(self.report_interval)

            except websockets.ConnectionClosed:
                logger.warning("发送循环：连接断开，退出循环")
                break
            except Exception as e:
                logger.error(f"发送数据异常: {e}")
                # 断网时缓存数据（手册14节）
                self.cached_data.append({
                    "type": "water_level",
                    "payload": sensor_data,
                    "timestamp": time.time()
                })
                await asyncio.sleep(2)

    async def _receive_loop(self, ws):
        """接收云端下发的指令（手册12.2节：指令包校验）"""
        async for raw in ws:
            try:
                data = json.loads(raw)
                # 只处理 command 类型
                if data.get('type') != 'command':
                    continue

                # 1. 防重放 & 时效性校验 (30s)
                if time.time() > data['expire_at']:
                    logger.warning("⏰ 指令已过期，拒绝执行")
                    continue

                # 2. HMAC 签名校验（手册12.2）
                raw_sign = data['command_id'] + json.dumps(data['payload']) + str(data['expire_at']) + data['nonce']
                expected_sign = hmac.new(
                    self.secret.encode(),
                    raw_sign.encode(),
                    hashlib.sha256
                ).hexdigest()
                
                if not hmac.compare_digest(expected_sign, data['sign']):
                    logger.error("🔒 HMAC 签名校验失败！拒绝执行")
                    continue

                # 3. 执行指令（写入 PLC）
                logger.info(f"✅ 收到合法指令: {data['command_id']} -> {data['payload']}")
                self._execute_command(data['payload'])
                
                # 4. 反馈执行结果（可选）
                await ws.send(json.dumps({
                    "type": "command_ack",
                    "command_id": data['command_id'],
                    "status": "executed"
                }))

            except json.JSONDecodeError:
                logger.error("无效 JSON 数据")
            except Exception as e:
                logger.error(f"处理指令异常: {e}")

    # ========== 占位方法（实际需对接 PLC 和推理模块） ==========
    def _read_plc_sensors(self):
        # 实际使用 minimalmodbus 读取 40001-40005
        # 此处返回模拟数据
        return {
            "upstream_level": 182.05,
            "downstream_level": 120.32,
            "inflow": 245.7,
            "rainfall": 2.3,
            "temperature": 22.5,
            "gate1_opening": 30.0,
            "gate2_opening": 20.0,
            "gate3_opening": 40.0
        }

    def _has_new_decision(self):
        # 实际从 inference_server 获取标志位
        return False

    def _get_latest_decision(self):
        return {"gate_openings": [100, 100, 50], "confidence": 0.92}

    def _execute_command(self, payload):
        # 写入 PLC 保持寄存器 40010-40012（目标开度）
        # inst.write_register(9, payload['gate_openings'][0])
        logger.info(f"🔧 执行指令: 闸门开度 {payload.get('gate_openings')}%")

    async def _upload_cached(self, ws):
        """断网恢复后批量上传缓存（手册14节）"""
        logger.info(f"📦 上传 {len(self.cached_data)} 条缓存数据...")
        for item in self.cached_data:
            try:
                await ws.send(json.dumps(item))
                await asyncio.sleep(0.1)
            except:
                break
        self.cached_data.clear()

if __name__ == "__main__":
    client = EdgeWSClient()
    try:
        asyncio.run(client.connect_and_loop())
    except KeyboardInterrupt:
        logger.info("🛑 客户端主动退出")
```



### 4.3 集成到系统服务（与 inference_server 并存）

在 `/opt/hydropower/` 下增加启动脚本 `start_edge.sh`：

bash

```
#!/bin/bash
nohup python3 /opt/hydropower/edge_ws_client.py >> /var/log/edge_ws.log 2>&1 &
```



**推荐做法**：将 `edge_ws_client.py` 作为 systemd 服务与 inference 并行运行（参考手册 9.3 节修改 `hydropower-inference.service` 或新建 `hydropower-edge.service`）。

## 五、前端实现 —— Vue 3 订阅端

> 对应手册 **6.1 节（三层架构）** 与 **11.2 节（前端展示）**。

### 5.1 配置 `laravel-echo` (Vue 插件)

新建 `src/plugins/echo.js`：

javascript

```
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT || 8080,
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],   // 仅使用加密通道（手册6.2节）
    disableStats: true,
    // 断网自动重连（手册14节：指数退避由 Echo 内部处理）
    reconnectionAttempts: 'Infinity',
    reconnectionDelay: 3000,
    reconnectionDelayMax: 30000,
});

export default echo;
```



### 5.2 Vue 组合式 API 订阅示例

新建 `src/composables/useEdgeRealtime.js`：

javascript

```
import { ref, onMounted, onUnmounted } from 'vue';
import echo from '@/plugins/echo';

export function useEdgeRealtime(edgeId = 'jetson-hydropower-01') {
    const upstreamLevel = ref(null);
    const downstreamLevel = ref(null);
    const gateOpenings = ref([0, 0, 0]);
    const latestDecision = ref(null);
    const isConnected = ref(false);
    let channel = null;
    let httpFallbackTimer = null;

    const subscribe = () => {
        if (!echo.connector || !echo.connector.pusher) {
            console.warn('WebSocket 未就绪，启用 HTTP 降级轮询');
            enableHttpFallback();
            return;
        }

        isConnected.value = true;
        channel = echo.channel(`edge.${edgeId}`);

        // 监听边缘数据（对应手册11.2消息类型）
        channel.listen('.edge.data', (event) => {
            console.log('📩 收到边缘事件:', event);
            switch (event.type) {
                case 'water_level':
                    upstreamLevel.value = event.payload.upstream_level;
                    downstreamLevel.value = event.payload.downstream_level;
                    break;
                case 'decision':
                    latestDecision.value = event.payload;
                    gateOpenings.value = event.payload.gate_openings || [0, 0, 0];
                    break;
                case 'gate_status':
                    gateOpenings.value = event.payload.openings || [0, 0, 0];
                    break;
                case 'alarm':
                    console.warn('🚨 告警:', event.payload);
                    // 触发全局告警弹窗
                    break;
                default:
                    break;
            }
        });

        // 监听连接状态（断网降级触发）
        echo.connector.pusher.connection.bind('disconnected', () => {
            console.warn('WebSocket 已断开，降级 HTTP 轮询');
            enableHttpFallback();
        });
        echo.connector.pusher.connection.bind('connected', () => {
            console.log('WebSocket 已恢复');
            if (httpFallbackTimer) {
                clearInterval(httpFallbackTimer);
                httpFallbackTimer = null;
            }
        });
    };

    // HTTP 降级轮询（手册6.2节：优雅降级）
    const enableHttpFallback = () => {
        if (httpFallbackTimer) return;
        httpFallbackTimer = setInterval(async () => {
            try {
                const res = await fetch(`/api/edge/status?edge_id=${edgeId}`);
                const data = await res.json();
                if (data.upstream_level !== undefined) {
                    upstreamLevel.value = data.upstream_level;
                }
                if (data.decision) {
                    latestDecision.value = data.decision;
                }
                // 5秒轮询（手册11.1）
            } catch (e) {
                console.error('HTTP降级轮询失败', e);
            }
        }, 5000);
    };

    // 下发控制指令（前端调用示例）
    const sendCommand = async (gateOpenings) => {
        try {
            await fetch('/api/edge/command', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ edge_id: edgeId, gate_openings: gateOpenings })
            });
        } catch (e) {
            console.error('指令下发失败', e);
        }
    };

    onUnmounted(() => {
        if (channel) {
            echo.leaveChannel(`edge.${edgeId}`);
        }
        if (httpFallbackTimer) {
            clearInterval(httpFallbackTimer);
        }
    });

    return {
        upstreamLevel,
        downstreamLevel,
        gateOpenings,
        latestDecision,
        isConnected,
        subscribe,
        sendCommand,
    };
}
```



### 5.3 在 Vue 页面中使用

vue

```
<template>
  <div>
    <p>上游水位: {{ upstreamLevel ?? '--' }} m</p>
    <p>下游水位: {{ downstreamLevel ?? '--' }} m</p>
    <p>AI建议开度: {{ gateOpenings.join('%, ') }}%</p>
    <button @click="sendCommand([100, 80, 60])">下发全开指令</button>
  </div>
</template>

<script setup>
import { useEdgeRealtime } from '@/composables/useEdgeRealtime';
import { onMounted } from 'vue';

const { upstreamLevel, downstreamLevel, gateOpenings, subscribe, sendCommand } = useEdgeRealtime();

onMounted(() => {
  subscribe();
});
</script>
```



## 六、消息协议规范

严格依据手册 **11.2 节**，统一定义如下消息格式：

| 类型 (`type`) | 方向         | Payload 示例                                           | 说明                     |
| :------------ | :----------- | :----------------------------------------------------- | :----------------------- |
| `water_level` | 边缘→云→前端 | `{"upstream_level":182.05, "downstream_level":120.32}` | 水位数据（5s 推送）      |
| `flow_rate`   | 边缘→云→前端 | `{"inflow":245.7, "outflow":220.3}`                    | 流量数据                 |
| `gate_status` | 边缘→云→前端 | `{"openings":[30,20,40], "mode":"auto"}`               | 闸门实时状态             |
| `alarm`       | 边缘→云→前端 | `{"code":"OVF_001", "msg":"水位超限"}`                 | 事件触发告警             |
| `decision`    | 边缘→云→前端 | `{"gate_openings":[100,100,50], "confidence":0.92}`    | AI 新决策（事件触发）    |
| `command`     | 云端→边缘    | `{"gate_openings":[100,75,50], "sign":"xxx"}`          | 控制/配置指令（含 HMAC） |
| `heartbeat`   | 双向         | `{"pong": "2026-07-06T..."}`                           | 心跳保活（30s）          |

所有消息统一包含 `edge_id` 和 `timestamp` 字段。

## 七、断网重连与降级机制

> 完全参照手册 **第 14 节（断网自治机制）**。

| 场景             | 处理方式                                              | 代码实现位置                                       |
| :--------------- | :---------------------------------------------------- | :------------------------------------------------- |
| **边缘端断网**   | 本地 SQLite 缓存 + 指数退避重连（60s超时判定）        | `EdgeWSClient.connect_and_loop` 中的 `retry_count` |
| **边缘网络恢复** | 自动重连成功 → 批量上传 `cached_data`                 | `_upload_cached` 方法                              |
| **前端断网**     | Echo 内部自动重连（`reconnectionAttempts: Infinity`） | `echo.js` 配置                                     |
| **前端降级**     | WS 彻底不可用时切换 HTTP 轮询（5s/次）                | `useEdgeRealtime` 中 `enableHttpFallback`          |
| **云端指令过期** | 边缘校验 `expire_at` ±30s，超时丢弃                   | `_receive_loop` 中的时间戳校验                     |

## 八、配置清单与启动验证

### 8.1 部署前检查清单

- **云端**：`.env` 已配置 `REVERB_*` 及 `EDGE_SHARED_SECRET`
- **云端**：`php artisan reverb:start` 正常运行，端口 8080 对外开放（WSS）
- **边缘**：`deploy_config.json` 中 `cloud.ws_url` 指向正确的云端地址
- **边缘**：`pip3 list | grep websockets` 已安装
- **前端**：`.env` 中 `VITE_REVERB_*` 变量正确填充

### 8.2 启动命令速查

bash

```
# === 云端启动 Reverb ===
php artisan reverb:start --host=0.0.0.0 --port=8080

# === 边缘端启动（前台调试） ===
cd /opt/hydropower
python3 edge_ws_client.py

# === 边缘端后端运行 ===
nohup python3 /opt/hydropower/edge_ws_client.py >> /var/log/edge_ws.log 2>&1 &

# === 前端启动 ===
npm run dev
```



### 8.3 连通性验证（3 步搞定）

**1. 验证边缘→云端上报**：
查看 `/var/log/edge_ws.log`，应出现 `✅ WebSocket 已连接云端`，且每 5s 打印 `📤 上报数据`。

**2. 验证前端订阅**：
打开浏览器控制台，应打印 `📩 收到边缘事件`，水位数据实时更新。

**3. 验证云端→边缘指令**：
调用 Laravel 接口（例如 Postman 请求 `POST /api/edge/command`），边缘日志出现 `✅ 收到合法指令` 即成功。

------

> **文档结束** | 如有部署问题，请参照《完整部署手册》第 16 章（故障排查）或查看 `/var/log/edge_ws.log` 与 `storage/logs/laravel.log`