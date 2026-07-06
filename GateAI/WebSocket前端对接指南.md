# WebSocket 前端对接指南

> 后端 WebSocket 服务基于 Laravel Reverb，已全部就绪。前端只需 3 步接入。

---

## 一、安装依赖

```bash
npm install laravel-echo pusher-js
```

---

## 二、配置 Echo

新建 `src/plugins/echo.js`：

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const echo = new Echo({
    broadcaster: 'reverb',
    key: 'hydropower-gateai-key',
    wsHost: '47.108.169.152',
    wsPort: 8080,
    wssPort: 8080,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
    // 断网自动重连
    reconnectionAttempts: Infinity,
    reconnectionDelay: 3000,
    reconnectionDelayMax: 30000,
});

export default echo;
```

> 本地开发把 `wsHost` 改成 `localhost`。

---

## 三、订阅频道

### 3.1 监控大屏 — 实时水位/流量

```js
import echo from '@/plugins/echo';

const channel = echo.channel('edge.jetson-hydropower-01');

channel.listen('.edge.data', (event) => {
    switch (event.type) {
        case 'water_level':
            // event.payload = { upstream_level, downstream_level, inflow, ... }
            updateWaterLevel(event.payload);
            break;
        case 'decision':
            // event.payload = { gate_openings: [40,30,50], safety_flag, confidence }
            updateGateDisplay(event.payload);
            break;
        case 'alarm':
            // event.payload = { code, msg }
            showAlarm(event.payload);
            break;
    }
});
```

### 3.2 模型管理页 — 健康度告警

```js
echo.channel('settings.models.health')
    .listen('.model.health', (event) => {
        // event = {
        //   model_type: 'lstm_prediction',
        //   model_version: '5.1.0',
        //   health_grade: 'D',        // S/A/B/C/D
        //   overall_score: 0.38,
        //   message: '连续3次D级评分'
        // }
        if (event.health_grade === 'D') {
            alert(`模型 ${event.model_type} 降至 D 级，建议回退`);
        }
    });
```

### 3.3 系统设置页 — 配置变更通知

```js
echo.channel('settings.config')
    .listen('.config.update', (event) => {
        // event = { module: 'thresholds|weights|physics_guard', version: 1234567890 }
        toast(`配置已更新: ${event.module}，请刷新页面`);
    });
```

---

## 四、频道总览

| 频道名 | 监听事件 | 触发时机 | 前端页面 |
|------|------|------|------|
| `edge.jetson-hydropower-01` | `.edge.data` | 边缘端每 5s 上报水位/流量/闸门 | **监控大屏** |
| `edge.jetson-hydropower-01` | `.edge.data` (type=decision) | AI 推理出新决策 | **监控大屏** |
| `edge.jetson-hydropower-01` | `.edge.data` (type=alarm) | 水位超限/设备故障 | **告警列表** |
| `settings.models.health` | `.model.health` | 模型评分降至 C/D 级 | **模型管理页** |
| `settings.config` | `.config.update` | 阈值/权重/物理防护配置被修改 | **系统设置页** |

> 频道只有登录用户才能订阅（后端 `channels.php` 已做认证校验）。

---

## 五、消息协议

所有消息统一结构：

```json
{
    "type": "water_level",
    "edge_id": "jetson-hydropower-01",
    "payload": {
        "upstream_level": 182.05,
        "downstream_level": 120.32,
        "inflow": 245.7,
        "rainfall": 2.3,
        "temperature": 22.5,
        "gate_opening": 30.0
    },
    "timestamp": "2026-07-06T15:30:00+08:00"
}
```

| `type` | payload 内容 | 说明 |
|------|------|------|
| `water_level` | `{upstream_level, downstream_level, inflow, ...}` | 每 5s 推送 |
| `decision` | `{gate_openings, safety_flag, confidence, decision_mode}` | AI 新决策时推送 |
| `alarm` | `{code, msg, level}` | 告警触发时推送 |
| `model.health` | `{model_type, health_grade, overall_score, message}` | 模型评分变化时推送 |
| `config.update` | `{module, version}` | 配置被修改时推送 |

---

## 六、HTTP 降级方案

当 WebSocket 连不上时，自动切 HTTP 轮询兜底：

```js
let wsConnected = false;
let fallbackTimer = null;

// Echo 状态监听
echo.connector.pusher.connection.bind('connected', () => {
    wsConnected = true;
    if (fallbackTimer) { clearInterval(fallbackTimer); fallbackTimer = null; }
});

echo.connector.pusher.connection.bind('disconnected', () => {
    wsConnected = false;
    if (!fallbackTimer) {
        fallbackTimer = setInterval(async () => {
            const res = await fetch('/api/v1/monitoring/realtime?reservoir_id=1');
            const data = await res.json();
            if (data.success) updateWaterLevel(data.data);
        }, 5000);
    }
});
```

---

## 七、快速验证

1. 后端启动 Reverb 后，打开浏览器控制台
2. 粘贴测试代码：

```js
import echo from '@/plugins/echo';
echo.channel('edge.jetson-hydropower-01')
    .listen('.edge.data', (e) => console.log('收到边缘数据:', e));
```

3. 控制台每 5 秒打印一次数据 → 通了
