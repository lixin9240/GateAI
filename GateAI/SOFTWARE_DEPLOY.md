# 水电站闸门智能调度系统 — 软件部署手册

> 版本: 5.1 | 日期: 2026-07-02
>
> **当前阶段: PC 开发联调（不需要额外硬件）**
> 生产硬件: NVIDIA Jetson Orin Nano — 项目规定，后期迁移

---

# 第一部分：现在就能做的（无硬件依赖 ⭐）

---

## 一、PC 端训练 + API 部署（现在就要做）

### 1.1 环境

```powershell
python --version            # 3.10.11
pip list | findstr torch    # PyTorch 2.12.1
nvidia-smi                  # RTX 5060
```

### 1.2 训练模型（~14 分钟）

```powershell
cd D:\hydropower_scheduling
python train_dqn_final.py    # DQN 决策模型, ~11 min
python train_lstm_final.py   # LSTM 预测模型, ~3 min
```

产出:
- `models/dqn_model.pth` (284,798 参数)
- `models/lstm_model.pth` (470,124 参数)
- `models/scaler_X.pkl`

### 1.3 导出部署包

```powershell
python deploy.py
```

产出 `D:\hydropower_deploy\`:

```
hydropower_deploy/
├── api_server.py           ← HTTP API（组长调这个）
├── inference_server.py     ← 推理引擎
├── database.py             ← MySQL 读写
├── deploy_config.json      ← 配置
├── requirements.txt        ← 依赖
├── models/
│   ├── dqn_scripted.pt     ← DQN TorchScript 模型
│   ├── lstm_state_dict.pt  ← LSTM 权重
│   └── scaler_X.pkl        ← 归一化器
├── scripts/
├── data/  logs/
```

### 1.4 启动 API 服务

```powershell
cd D:\hydropower_deploy
pip install -r requirements.txt
python api_server.py --port 5000
```

看到以下输出即成功:
```
Device: cpu
DQN: TorchScript loaded
LSTM: state_dict loaded
Ready: LSTM(24h→6h) + DQN(125 actions)
Listening on http://0.0.0.0:5000
```

---

## 二、AI 模型规格

| | DQN（决策） | LSTM（预测） |
|------|:---:|:---:|
| 算法 | Dueling Double DQN | 2层 BiLSTM + MultiheadAttention |
| 输入 | 12维状态向量 | 24h × 5特征序列 |
| 输出 | 125个离散动作 → 3个闸门开度 | 6h × 2 (水位+流量) |
| 参数量 | 284,798 | 470,124 |
| 推理速度 (GPU) | < 1ms | < 2ms |
| 推理速度 (CPU) | ~2ms | ~5ms |

**工作流程:** 传感器数据 → LSTM 预测未来6小时来水 → DQN 综合当前状态 + LSTM预测 → 最优闸门开度 + 置信度 + 安全判定。

---

## 三、交给组长的接口

> 详细文档见 `API_接口文档_给组长.md`

```
POST /api/infer            推理一次（核心接口）
POST /api/infer/batch      批量推理
GET  /api/health           健康检查
GET  /api/models/info      模型信息
POST /api/history/reset    重置 LSTM 历史缓冲区
```

**核心接口 `POST /api/infer`：**

请求:
```json
{
  "upstream_level": 182.0,     "downstream_level": 120.5,
  "inflow": 250.0,             "rainfall": 3.0,
  "temperature": 22.0,
  "gate1_opening": 0.3,        "gate2_opening": 0.2,        "gate3_opening": 0.4
}
```

响应:
```json
{
  "success": true,
  "data": {
    "gate_openings": [100, 100, 50],
    "predicted_inflows": [221.9, 225.5, 227.0, 224.4, 227.5, 236.9],
    "predicted_levels": [181.97, 181.96, ...],
    "predicted_peak_level": 181.97,
    "confidence": 1.0,
    "safety_flag": "safe",
    "inference_time_ms": 1.23
  }
}
```

**Laravel 调用示例：**

```php
$response = Http::post('http://localhost:5000/api/infer', [
    'upstream_level'   => 182.0,
    'downstream_level' => 120.5,
    'inflow'           => 250.0,
    'rainfall'         => 3.0,
    'temperature'      => 22.0,
    'gate1_opening'    => 0.3,
    'gate2_opening'    => 0.2,
    'gate3_opening'    => 0.4,
]);
$result = $response->json();
// $result['data']['gate_openings'] → [100, 100, 50]
```

---

---

# 第二部分：有硬件之后才做的

> ⚠️ 以下内容依赖 Jetson / PLC / 传感器 / 闸门等物理硬件。现阶段不需要操作，仅供参考。

---

## 四、系统架构全景

### 4.1 三层架构

```
┌──────────────────────────────────────────────────────────────────┐
│                         用 户 层                                   │
│  值班运维 │ 调度决策工程师 │ 站长/管理 │ 系统管理员 │ 算法工程师    │
└──────────────────────────┬───────────────────────────────────────┘
                           │
┌──────────────────────────▼───────────────────────────────────────┐
│                    云    端  (非实时)                                │
│                                                                   │
│  ┌─────────────────────┐    ┌─────────────────────┐              │
│  │ Vue 3 前端           │    │ Laravel 10+ API     │              │
│  │ 监控大屏│告警│调度   │    │ RESTful│WS│权限│日志 │              │
│  │ 数字孪生│历史│设备    │    └──────────┬──────────┘              │
│  │ 系统设置             │               │                          │
│  └─────────────────────┘               │                          │
│                                         │                          │
│  ┌──────────────────────────────────────┴───────────────────────┐ │
│  │  MySQL 热库(3月) │ MySQL 冷库(3月~1年) │ OSS 超长期归档(1年+) │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                                                                   │
│  ★ 云端职责: 数据存储/展示、用户管理、模型离线训练、人工指令下发     │
│  ★ 云端不参与: 实时 AI 推理、PLC 控制指令下发                       │
└──────────────────────────┬───────────────────────────────────────┘
                           │  HTTP/HTTPS + WebSocket (wss)
                           │
┌──────────────────────────▼───────────────────────────────────────┐
│                   边  缘  端  (实时核心)                              │
│              NVIDIA Jetson Orin Nano (67 TOPS, ARM64)              │
│                                                                   │
│  ┌──────────┐ ┌──────────┐ ┌────────────┐ ┌──────────────────┐  │
│  │ LSTM预测  │ │ DQN 决策  │ │ 指令安全网关 │ │ 数据采集/预处理   │  │
│  │ 未来6h    │ │ 125动作   │ │ 签名+防重放 │ │ 传感器→清洗→缓存 │  │
│  │ 水位+流量 │ │ 3闸门开度 │ │ 6道校验     │ │ 24h滑动窗口      │  │
│  └──────────┘ └──────────┘ └────────────┘ └──────────────────┘  │
│                                                                   │
│  ★ 推理速度: 单次 < 5ms (GPU)  │  端到端 ~20ms                     │
│  ★ 断网自治: ≥ 72 小时本地缓存  │  联网后自动同步                   │
└──────────────────────────┬───────────────────────────────────────┘
                           │  Modbus RTU (RS485, 9600bps, 8N1)
                           │
┌──────────────────────────▼───────────────────────────────────────┐
│                    端    侧  (物理执行)                               │
│                                                                   │
│  ┌──────────┐ ┌─────────┐ ┌────────┐ ┌───────────┐ ┌─────────┐  │
│  │超声波液位计│ │超声波流量│ │电动推杆 │ │西门子 S7-200│ │急停继电器│  │
│  │上下游×2   │ │计 RS485 │ │闸门驱动 │ │SMART SR20  │ │×3       │  │
│  │4-20mA     │ │DN15     │ │100mm行程│ │+ EM AE04   │ │         │  │
│  └──────────┘ └─────────┘ └────────┘ └───────────┘ └─────────┘  │
│                                                                   │
│  ★ 端侧职责: 物理数据采集 + 指令执行, 不做任何软件决策               │
└──────────────────────────────────────────────────────────────────┘
```

### 4.2 数据闭环（7 步）

```
① 全域感知 ──▶ ② 数据传输 ──▶ ③ 边缘AI推理 ──▶ ④ 指令下发 ──▶ ⑤ 执行反馈
  传感器          PLC采集        LSTM+ DQN        PLC接收         电动推杆
  (5s间隔)        Modbus         安全判定         Modbus写寄存器     闸门动作
                                    │                                │
                                    ▼                                │
                              ⑥ MySQL记录                            │
                              传感器+决策+告警                        │
                                    │                                │
                                    ▼                                │
                              ⑦ 上报云端 ◀───────────────────────────┘
                              MQTT/HTTP + WS推送前端
```

### 4.3 核心设计原则

| 原则 | 含义 | 系统体现 |
|------|------|---------|
| **云端不控制** | 云端不参与实时 AI 推理和 PLC 指令下发 | 控制指令由边缘端直接发给 PLC |
| **边缘可自治** | 断网时系统不瘫痪 | 本地推理+本地控制+本地缓存, 联网后同步 |
| **安全优先** | 安全 > 效率 > 体验 | 急停最高优先级, 六道安全校验 |
| **可解释 AI** | 不搞"黑箱"决策 | 每个决策输出: 影响因素 + 方案对比 + 置信度 |
| **全链路可追溯** | 任何操作都有据可查 | 统一 trace_id + 不可删除日志 |
| **渐进式信任** | 人机协作逐步放权 | L1仅建议 → L2半自动 → L3全自动 |
| **优雅降级** | 不依赖单一通道 | WebSocket 优先 + HTTP 轮询降级 |

---

## 五、硬件清单与详细参数

> 来源: PPT 设计文档第6页, 总预算 **¥8,510**

### 5.1 硬件总表

| 序号 | 硬件 | 型号/品牌 | 数量 | 单价 | 关键参数 |
|:--:|------|------|:--:|------|------|
| 1 | 超声波液位计 | 上戈智能 0-5m | 2 | ¥460 | 4-20mA, DC24V, 防爆防腐 |
| 2 | 超声波流量计 | 谊程 DN15 | 1 | ¥300 | RS485, 循环水/污水 |
| 3 | 电动推杆 | LUILEC 升级款 | 1 | ¥190 | 行程100mm, 推力100kg |
| 4 | PLC 控制器 | 西门子 S7-200 SMART SR20 | 1 | ¥1,000 | 继电器输出, 以太网+RS485 |
| 5 | 模拟量输入模块 | 艾莫迅 EM AE04 | 1 | ¥360 | 4路 4-20mA 输入 |
| 6 | **边缘计算网关** | **NVIDIA Jetson Orin Nano 8GB** | 1 | **¥4,700** | **67 TOPS, 13.3寸触摸屏套件** |
| 7 | 开关电源 | 明纬 NDR-240-24 | 1 | ¥290 | AC220V→DC24V, 10A, 240W |
| 8 | 循环水泵 | TURBOVOLT DP2401 | 1 | ¥80 | 12V, 扬程70m, 隔膜泵 |
| 9 | USB转RS485 | 绿联 UGREEN 55839 | 1 | ¥60 | 工业级, Modbus 调试 |
| 10 | 折叠蓄水池 | 京喜自营 | 1 | ¥290 | 1.5×1m, 0.5m高, 帆布 |
| 11 | 降压模块 | 安力巨 24V→12V | 1 | ¥70 | 10A, 120W |
| 12 | 快速接头 | DN15=4分 | 1 | ¥30 | 不锈钢, A+C型 |
| 13 | 硅胶软管 | 内径16mm | 1 | ¥100 | 耐弯折, 配宝塔接头 |
| 14 | 亚克力闸门板 | 5mm透明 | 2 | ¥120 | 30×30cm + 40×40cm |
| | **合计** | | | **¥8,510** | |

### 5.2 Jetson Orin Nano 详细规格

| 项目 | 规格 |
|------|------|
| AI 算力 | **67 TOPS** (Int8) |
| GPU | 1024-core NVIDIA Ampere + 32 Tensor Cores |
| CPU | 6-core ARM Cortex-A78AE @ 1.5GHz |
| 内存 | 8 GB LPDDR5 (128-bit) |
| 存储 | 支持 NVMe SSD (建议 128GB+) + microSD |
| 功耗 | 7W-15W (被动散热即可, 无需风扇) |
| 接口 | USB 3.2 ×2, Gigabit Ethernet, M.2 Key M, 40-pin GPIO, HDMI |
| 显示 | 13.3寸触摸屏 (套件含) |
| 系统 | Ubuntu 22.04 + JetPack 6.0 |
| 尺寸 | 103×79×35mm |
| 工作温度 | -25°C ~ 80°C |

### 5.3 西门子 S7-200 SMART SR20 详细规格

| 项目 | 规格 |
|------|------|
| CPU 类型 | 继电器输出 |
| 数字量 I/O | 12 输入 / 8 输出 |
| 通信口 | 1×以太网 + 1×RS485 |
| 扩展模块 | 最多 6 个 (本配置用 1 个 EM AE04) |
| 供电 | DC24V |
| 编程软件 | STEP 7-Micro/WIN SMART |
| Modbus 支持 | RTU (从站) + TCP |

### 5.4 各硬件作用与连接关系

```
                      ┌─────────────────────────┐
                      │   明纬 NDR-240-24        │
                      │   AC220V → DC24V, 10A   │
                      └────┬────┬────┬────┬─────┘
                           │    │    │    │
              ┌────────────┼────┼────┼────┼──────────────┐
              │            │    │    │    │              │
         ┌────▼──┐   ┌────▼──┐ │  ┌─▼──────────┐  ┌────▼──────┐
         │液位计1 │   │液位计2 │ │  │ S7-200 SR20│  │ Jetson    │
         │4-20mA  │   │4-20mA  │ │  │ + EM AE04  │  │ Orin Nano │
         │上游水位│   │下游水位│ │  │            │  │ DC 19V    │
         └───┬────┘   └───┬────┘ │  └┬─────┬─────┘  │ (自带电源) │
             │            │      │   │     │        └───────────┘
             │       ┌────▼──────┴───┼─────┼──────────────┐
             │       │   EM AE04    │     │              │
             │       │ 4路模拟量输入 │     │              │
             │       └──────────────┘     │              │
             │                            │              │
         ┌───▼────────────────────────────▼───────────┐  │
         │         S7-200 SMART SR20                  │  │
         │  ┌─────────────────────────────────────┐   │  │
         │  │ DI (输入)      │ DO (继电器输出)      │   │  │
         │  │ 液位/流量/温度  │ 推杆/急停继电器      │   │  │
         │  └─────────────────────────────────────┘   │  │
         │  ┌──────────────────────────────────────┐  │  │
         │  │ RS485 口 ────────────── Modbus RTU ──┼──┘  │
         │  └──────────────────────────────────────┘     │
         └────────────────┬──────────────────────────────┘
                          │
              ┌───────────┼───────────┐
              │           │           │
         ┌────▼──┐   ┌───▼───┐  ┌───▼─────┐
         │电动推杆│   │继电器1 │  │继电器2   │
         │ 闸门1  │   │ 急停   │  │ 急停     │
         └───────┘   └───────┘  └─────────┘
```

---

## 六、Jetson Orin Nano 部署（详细步骤）

### 6.1 开箱初始化

**第一步: 烧录系统**

1. 下载 NVIDIA SDK Manager (需要 Ubuntu 22.04 宿主机 或 VMware 虚拟机)
2. 用 USB-C 数据线连接 Jetson 到宿主机
3. SDK Manager 中选择:
   - **Hardware:** Jetson Orin Nano 8GB
   - **JetPack:** 6.0 (包含 Ubuntu 22.04 + CUDA 12.2 + PyTorch 2.2)
4. 烧录完成后 Jetson 自动重启进入 Ubuntu 桌面

**第二步: 首次配置**

```bash
# 设置用户名密码
# 用户名: hydropower
# 密码: 自定

# 连接网络
# 有线: 插网线即可
# WiFi: 设置 → Network → Wi-Fi

# 更新系统
sudo apt update && sudo apt upgrade -y

# 检查关键组件
python3 --version         # Python 3.10.x
nvcc --version            # CUDA 12.2
python3 -c "import torch; print(torch.cuda.is_available())"  # True
python3 -c "import torch; print(torch.__version__)"           # 2.2.x
```

**第三步: 安装推理依赖**

```bash
# 系统依赖
sudo apt install -y python3-pip libmysqlclient-dev nginx curl git

# Python 依赖
pip3 install --upgrade pip
pip3 install torch numpy scikit-learn joblib mysqlclient
pip3 install minimalmodbus flask flask-cors gunicorn

# 验证
python3 -c "import torch, numpy, sklearn, joblib, MySQLdb, flask; print('All OK')"
```

### 6.2 部署包拷贝

**方式 A: U盘拷贝**

```bash
# 插入 U盘
lsblk                          # 确认 U盘挂载点, 如 /media/hydropower/USB
sudo mkdir -p /opt/hydropower
sudo cp -r /media/hydropower/USB/hydropower_deploy/* /opt/hydropower/
sudo chown -R hydropower:hydropower /opt/hydropower
```

**方式 B: SCP 网络传输**

```bash
# 在 PC 上执行 (确保 PC 能 ping 通 Jetson)
scp -r D:\hydropower_deploy hydropower@192.168.1.100:/tmp/
# 然后在 Jetson 上:
sudo mv /tmp/hydropower_deploy /opt/hydropower
```

**方式 C: OTA 云端下发 (后期)**

```bash
# 云端通过 WebSocket 推送部署包
# 边缘端收到后自动解压替换
```

### 6.3 首次测试

```bash
cd /opt/hydropower

# 1. 不连 PLC 的纯推理测试
python3 inference_server.py

# 预期输出:
#   Device: cuda
#   DQN: TorchScript loaded (284,798 params)
#   LSTM: state_dict loaded (470,124 params)
#   Ready: LSTM(24h->6h) + DQN(125 actions)
#   =======================================================
#     Test Inference
#   =======================================================
#     Gates:  ['100%', '100%', '50%']

# 2. 启动 API 服务测试
python3 api_server.py --port 5000

# 3. 另开终端验证
curl http://localhost:5000/api/health
# → {"status":"ok","service":"hydropower-inference","device":"cuda",...}

# 4. 测推理接口
curl -X POST http://localhost:5000/api/infer \
  -H "Content-Type: application/json" \
  -d '{"upstream_level":182.0, "downstream_level":120.5, "inflow":250.0,
       "rainfall":3.0, "temperature":22.0,
       "gate1_opening":0.3, "gate2_opening":0.2, "gate3_opening":0.4}'
```

### 6.4 生产环境配置

**使用 Gunicorn 替代 Flask 开发服务器:**

```bash
pip3 install gunicorn

# 创建 WSGI 入口
cat > /opt/hydropower/wsgi.py << 'EOF'
from api_server import app
if __name__ == "__main__":
    app.run()
EOF

# 启动 (4 worker, 绑定所有网卡)
gunicorn -w 4 -b 0.0.0.0:5000 --timeout 30 wsgi:app
```

**创建 systemd 服务:**

```bash
sudo tee /etc/systemd/system/hydropower-inference.service << 'EOF'
[Unit]
Description=Hydropower AI Inference Service
After=network.target
Wants=network.target

[Service]
Type=simple
User=hydropower
WorkingDirectory=/opt/hydropower
ExecStart=/home/hydropower/.local/bin/gunicorn -w 4 -b 0.0.0.0:5000 --timeout 30 wsgi:app
Restart=always
RestartSec=10
StandardOutput=append:/var/log/hydropower.log
StandardError=append:/var/log/hydropower.log

# 安全加固
NoNewPrivileges=yes
PrivateTmp=yes
ProtectSystem=strict
ProtectHome=yes
ReadWritePaths=/opt/hydropower/data /opt/hydropower/logs

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable hydropower-inference
sudo systemctl start hydropower-inference
sudo systemctl status hydropower-inference
```

**创建定时健康检查脚本:**

```bash
# 已有 scripts/health_check.sh
sudo cp /opt/hydropower/scripts/health_check.sh /etc/cron.hourly/hydropower-health
sudo chmod +x /etc/cron.hourly/hydropower-health
```

### 6.5 防火墙配置

```bash
# 只允许局域网访问推理端口
sudo ufw allow from 192.168.0.0/16 to any port 5000
sudo ufw allow 22/tcp          # SSH
sudo ufw enable
sudo ufw status
```

### 6.6 触摸屏配置

Jetson 套件含 13.3 寸触摸屏。部署后可直接在屏幕上查看:

```bash
# 安装轻量浏览器用于本机查看监控页面
sudo apt install -y chromium-browser

# 设开机自动打开监控大屏 (前端部署后)
# 如果云端前端部署在 http://192.168.1.200
mkdir -p ~/.config/autostart
cat > ~/.config/autostart/dashboard.desktop << 'EOF'
[Desktop Entry]
Type=Application
Name=Hydropower Dashboard
Exec=chromium-browser --kiosk --disable-infobars http://192.168.1.200/dashboard
X-GNOME-Autostart-enabled=true
EOF
```

---

## 七、PLC + 传感器 + 物理模型搭建

### 7.1 物理模型搭建 (水路系统)

这是整个项目最耗时的部分。按照以下顺序组装:

**第一步: 搭建循环水路**

```
   ┌─────────────────────────────────────────────┐
   │              折叠蓄水池 (1.5×1m)              │
   │  ┌──────────────────────┬─────────────────┐ │
   │  │   上游 (高水位区)     │ 下游 (低水位区)    │ │
   │  │   ≈ 0.5m 水深        │  ≈ 0.3m 水深     │ │
   │  │                      │                  │ │
   │  │  [液位计1]           │  [液位计2]        │ │
   │  │                      │                  │ │
   │  └──────────┬───────────┴────────┬─────────┘ │
   │             │  ← 亚克力隔板(40×40cm) →        │
   │             │     (中间开闸门口)              │
   └─────────────┼────────────────────┼───────────┘
                 │                    │
    ┌────────────▼────┐    ┌─────────▼──────────┐
    │   循环水泵       │    │  超声波流量计 DN15  │
    │  12V 隔膜泵     │    │  RS485             │
    │  扬程 70m       │    │  采集模拟流量数据   │
    └────────┬────────┘    └─────────┬──────────┘
             │                       │
             └───────────┬───────────┘
                         │
                    硅胶软管回路
                  (DN15, 内径16mm)
```

**第二步: 安装传感器**

| 传感器 | 安装位置 | 接线方式 | 注意事项 |
|------|---------|---------|------|
| 液位计1 | 上游区, 距池底 30cm 垂直安装 | 2芯 4-20mA → EM AE04 CH0 | 探头不能贴底, 防止淤泥干扰 |
| 液位计2 | 下游区, 距池底 30cm 垂直安装 | 2芯 4-20mA → EM AE04 CH1 | 同上 |
| 流量计 | 下游出水管路中间 | 4芯 RS485(A/B/GND/VCC) → USB-RS485 | 方向不能反, 箭头指向水流 |

**第三步: 安装闸门机构**

```
电动推杆 固定在上方支架
    │
    ├── 推杆伸出/缩回 (行程 100mm)
    │
    ▼
亚克力闸门板 (30×30cm, 5mm厚)
    │
    ├── 卡在隔板的闸门槽内上下滑动
    │
    ▼
全关: 推杆伸出100mm → 闸门到底 → 水不流通
全开: 推杆缩回0mm   → 闸门提起 → 水自由流过
半开: 推杆伸出50mm  → 闸门一半
```

### 7.2 PLC 接线详细

**电源接线:**

```
AC 220V 市电
    │
    ▼
明纬 NDR-240-24 (DIN导轨安装)
    │
    ├── DC 24V ──┬── PLC S7-200 SR20 (L+/M)
    │            ├── EM AE04 (L+/M)
    │            ├── 液位计1 (24V+)
    │            ├── 液位计2 (24V+)
    │            └── 24V→12V降压模块 → 水泵 (12V)
    │
    └── Jetson 用自带 19V 电源适配器 (单独供电)
```

**信号接线 (EM AE04 模块):**

| EM AE04 端子 | 连接对象 | 信号类型 |
|-------------|---------|------|
| CH0+ / CH0- | 液位计1 (上游) | 4-20mA |
| CH1+ / CH1- | 液位计2 (下游) | 4-20mA |
| CH2+ / CH2- | 预留 (可接温度变送器) | 4-20mA |
| CH3+ / CH3- | 预留 | 4-20mA |

**控制的接线 (SR20 DO 继电器输出):**

| SR20 DO 端子 | 连接对象 | 功能 |
|-------------|---------|------|
| Q0.0 | 电动推杆 (正转) | 闸门上升 (开) |
| Q0.1 | 电动推杆 (反转) | 闸门下降 (关) |
| Q0.2 | 急停继电器1 | 切断推杆供电 |
| Q0.3 | 急停继电器2 | 切断水泵供电 |

**RS485 通信接线:**

```
Jetson USB 口 → 绿联 USB-RS485 → A(+) → PLC RS485 A(+)
                                 → B(-) → PLC RS485 B(-)
                                 → GND  → PLC RS485 GND
```

### 7.3 PLC 编程配置

在 STEP 7-Micro/WIN SMART 中做以下配置:

**1. 设置 Modbus 从站参数:**

```
通信口: RS485 (Port 0)
地址: 1
波特率: 9600
校验: 无 (8N1)
协议: Modbus RTU
```

**2. 寄存器映射 (在 PLC 程序中定义):**

在 PLC 的 V 存储区定义以下 Modbus 保持寄存器:

| 保持寄存器 | Modbus 地址 | 变量名 | 功能 | 换算公式 |
|-----------|------------|-------|------|---------|
| VW0 | 40001 | 上游水位 | AIW0 采集值 (0-27648) → 水位 m | 水位 = AIW0/27648 × 500 (cm) |
| VW2 | 40002 | 下游水位 | AIW2 采集值 → 水位 m | 同上 |
| VW4 | 40003 | 入库流量 | 流量计 RS485 读取值 | 流量 = 值/10 (m³/s) |
| VW6 | 40004 | 降雨量 | 模拟或预设值 | 降雨量 = 值/10 (mm/h) |
| VW8 | 40005 | 温度 | 模拟或预设值 | 温度 = 值/10 (°C) |
| VW10-14 | 40006-40008 | 闸门反馈 | 推杆位置反馈 (如有时) | 开度 = 值/100 (%) |
| VW18-22 | 40010-40012 | 目标开度 | Jetson 写入的闸门指令 | PLC → DO 输出控制推杆 |
| VW38 | 40020 | 模式控制 | 1=AI自动, 0=手动 | 控制 DO 输出使能 |
| VW40 | 40021 | 急停控制 | 1=触发急停 | Q0.2/Q0.3 置位 |

**3. 闸门控制的 PLC 逻辑 (梯形图):**

```
// 闸门1 目标开度 → 推杆动作
// VW18 = 目标开度 (如 50 = 50%)
// 当前推杆伸出量 通过限位开关或定时估算
//
// 逻辑:
//   如果 AI自动模式(VW38=1) 且 非急停(VW40=0):
//     如果 目标开度 > 当前开度+死区(5%):
//       正转 Q0.0 (开闸门)
//     如果 当前开度 > 目标开度+死区(5%):
//       反转 Q0.1 (关闸门)
//     否则:
//       停止
```

### 7.4 调试验证

**第一步: 验证 4-20mA 信号**

```bash
# 在 Jetson 上用 Python 直读串口测试
python3 -c "
import minimalmodbus
inst = minimalmodbus.Instrument('/dev/ttyUSB0', 1)
inst.serial.baudrate = 9600
# 读上游水位寄存器
val = inst.read_register(0, functioncode=3)
print(f'上游水位原始值: {val}')
print(f'上游水位: {val/100:.2f} m')
"
```

**第二步: 验证闸门控制**

```bash
# 写入 100% 开度, 观察推杆动作
python3 -c "
import minimalmodbus
inst = minimalmodbus.Instrument('/dev/ttyUSB0', 1)
inst.serial.baudrate = 9600
# 写闸门1目标开度 = 100%
inst.write_register(9, 100)
print('闸门1目标开度已设为 100%')
"
```

**第三步: 联调——从传感器读到 AI 推理到闸门动作**

```bash
cd /opt/hydropower
# 编辑 deploy_config.json, 确保 plc.port = "/dev/ttyUSB0"

# 启动完整推理循环
python3 -c "
from inference_server import GateController, SensorData
from api_server import get_db
import minimalmodbus, time

ctrl = GateController()
plc = minimalmodbus.Instrument('/dev/ttyUSB0', 1)
plc.serial.baudrate = 9600

while True:
    # 1. 读传感器
    vals = plc.read_registers(0, 8, functioncode=3)
    sensor = SensorData(
        upstream_level=vals[0]/100, downstream_level=vals[1]/100,
        inflow=vals[2]/10, rainfall=vals[3]/10, temperature=vals[4]/10,
        gate_openings=[vals[5]/100, vals[6]/100, vals[7]/100]
    )
    
    # 2. AI 推理
    cmd = ctrl.step(sensor)
    
    # 3. 安全检查
    if cmd.safety_flag == 'danger':
        plc.write_register(20, 1)  # 急停
        print('EMERGENCY STOP!')
        break
    
    # 4. 写闸门指令
    gates_int = [int(g * 100) for g in cmd.gate_openings]
    plc.write_registers(9, gates_int)
    
    print(f'Gates: {[f\"{g}%\" for g in gates_int]} | '
          f'Conf: {cmd.confidence:.2f} | Safety: {cmd.safety_flag}')
    time.sleep(5)
"
```

---

## 八、云端 ↔ 边缘端通信部署

### 8.1 通信架构

```
Jetson (水电站机房)                      云端服务器
═══════════════════                    ══════════════

┌──────────────────┐    上报数据       ┌──────────────────┐
│  api_server.py   │─── MQTT/HTTP ──▶│  Laravel API      │
│  (推理服务:5000)  │                  │  POST /api/edge/* │
└──────────────────┘                  └────────┬─────────┘
        │                                      │
        │    接收指令                            │
        │◀──────── WebSocket ───────────────────┘
        │         (Swoole/Reverb)
        │
┌───────▼──────────┐
│  Jetson 本地 DB   │  断网时缓存, 联网后批量上传
│  SQLite/文件缓存  │
└──────────────────┘
```

### 8.2 边缘端上报配置

`deploy_config.json` 中配置云端地址:

```json
{
  "cloud": {
    "api_url": "https://your-server.com/api/edge",
    "ws_url": "wss://your-server.com/app/edge",
    "mqtt_broker": "mqtt://your-server.com:1883",
    "report_interval_seconds": 5,
    "heartbeat_interval_seconds": 30
  }
}
```

### 8.3 前端 ↔ 云端实时通信

| 场景 | 协议 | 机制 |
|------|------|------|
| 正常运行 | WebSocket | 5s 推送水位/流量/开度/告警 |
| WS 断开 > 3s | 自动降级 HTTP 轮询 | 每 5s GET /api/realtime/snapshot |
| WS 重连成功 | 停止轮询, 恢复 WS | Toast通知 + 状态灯恢复绿色 |

### 8.4 WebSocket 消息类型

| 消息类型 | 方向 | 内容 | 频率 |
|---------|------|------|:--:|
| `water_level` | 边缘→云→前端 | 上下游水位、水头 | 5s |
| `flow_rate` | 边缘→云→前端 | 入库/出库流量 | 5s |
| `gate_status` | 边缘→云→前端 | 闸门开度、状态 | 5s |
| `power` | 边缘→云→前端 | 发电功率、累计电量 | 5s |
| `device_health` | 边缘→云→前端 | 传感器在线率、PLC/网关状态 | 30s |
| `alarm` | 边缘→云→前端 | 正式告警通知 | 事件触发 |
| `decision` | 边缘→云→前端 | AI 决策结果 (可解释三要素) | 事件触发 |
| `command` | 云端→边缘 | 控制/配置指令 | 事件触发 |
| `heartbeat` | 双向 | 心跳保活 | 30s |

---

## 九、安全体系部署

### 9.1 五层安全防护

```
第一层: 传输安全
  ├── 全站 HTTPS (Let's Encrypt 免费证书)
  ├── WebSocket over TLS (wss://)
  ├── Token 注入 Axios 拦截器
  └── API 接口 IP 白名单 (仅允许局域网/内网)

第二层: 认证安全
  ├── bcrypt 密码哈希 (cost=12)
  ├── 连续 5 次失败锁定 30 分钟
  ├── 首次登录强制改密 (不可关闭/跳过弹窗)
  ├── 密码复杂度 5 项 (长度≥8/大写/小写/数字/特殊字符)
  └── Token 过期 2h + 静默刷新 + 刷新失败自动跳转登录页

第三层: 授权安全
  ├── 页面级: Vue Router 守卫 + 动态菜单根据 role 过滤
  ├── API 级: Laravel 中间件校验 Token + 权限码
  └── 按钮级: v-permission 指令 + usePermission()

第四层: 指令安全 (边缘端安全网关, 六道校验)
  ├── ① 设备身份: 指令 target edge_id == 本设备 ID
  ├── ② HMAC-SHA256 签名: 防 payload 被篡改
  ├── ③ 时间戳: |now - issued_at| ≤ 30s, 防过期指令
  ├── ④ 防重放 Nonce: 已用 nonce 的指令拒绝, 缓存 5min
  ├── ⑤ 权限: 操作人 role 是否有下发权限
  └── ⑥ 模式: 当前 L1/L2/L3 是否允许自动执行

第五层: 操作安全
  ├── 所有控制操作必须二次确认 (弹窗)
  ├── 急停全局最高优先级 (绕过所有校验)
  ├── 操作日志不可删除 (全链路 trace_id 串联)
  └── 操作日志保留 ≥ 3 年
```

### 9.2 指令签名流程

```
云端 操作人发起指令
  │
  ▼
后端生成指令包:
  {
    "edge_id": "jetson-hydropower-01",
    "command_id": "cmd-20260702-143025-abc123",
    "payload": { "gate_openings": [100, 75, 50] },
    "sign": "HMAC-SHA256(command_id+payload+timestamp, secret_key)",
    "expire_at": 1759932055,
    "nonce": "a1b2c3d4e5f6g7h8i9j0"
  }
  │
  ▼  WebSocket 推送至边缘端
  │
  ▼
边缘端安全网关逐项校验:
  设备身份 → 签名 → 时间戳 → 防重放 → 权限 → 模式
  │
  ├── 全部通过 → 执行 → 回执 {status: "executed"}
  └── 任一失败 → 拒绝 → 记录异常日志 → 回执 {status: "rejected", reason: "..."}
```

### 9.3 急停的特殊处理

- **不经过 AI 推理模块**: 直接写 PLC 寄存器 40021 = 1 → Q0.2/Q0.3 置位
- **不校验运行模式**: 任何模式 (L1/L2/L3/手动) 均可触发
- **不校验权限**: 所有登录用户均有急停按钮
- **响应延迟 < 100ms**: 后端直接转发, 不做额外处理
- **前端**: 全局固定定位红色按钮, 所有页面可见, 点击二次确认
- **恢复**: 需管理员在系统设置页手动解除, 恢复过程记录日志

---

## 十、三级自动执行模式配置

| 等级 | 模式 | 行为 | 适用场景 |
|:--:|------|------|------|
| **L1** | 仅建议 | AI 只出方案, 不自动执行, 须人工确认 | 系统刚上线、汛期高危、模型刚更新 |
| **L2** | 半自动 | 置信度 ≥ 80% 且 开度变化 ≤ 10% → 自动执行; 否则降级 L1 | 日常运行、水位平稳 |
| **L3** | 全自动 | AI 决策自动下发, 异常时告警等人工 | 枯水期、已验证成熟 |

`deploy_config.json` 配置:

```json
{
  "execution_mode": {
    "level": "L2",
    "auto_confidence_threshold": 0.80,
    "auto_opening_change_max": 10.0
  }
}
```

---

## 十一、断网自治机制

### 11.1 断网触发与处理

```
① 心跳超时检测 (60s 无云端 heartbeat → 判定断网)
  │
  ▼
② 进入自治模式:
  ├── LSTM 继续本地推理 (使用最后同步的权重配置)
  ├── DQN 继续本地决策
  ├── 告警使用本地缓存的阈值
  ├── 监测数据 + 调度日志 → 写入本地 SQLite / JSON 文件
  ├── L2/L3 自动调度照常执行
  └── 人工指令通道断开 (云端此时无法下发新指令)
  │
  ▼
③ 网络恢复检测 (每 10s 尝试重连 WebSocket)
  │
  ▼
④ 重连成功 → 恢复流程:
  ├── 批量上传缓存数据 (按时间顺序, HTTP POST)
  ├── 同步最新配置 (阈值/权重/模型版本)
  ├── 清除本地缓存
  └── 恢复正常上报模式
```

### 11.2 本地缓存实现

```python
# 断网时自动切换本地缓存
# 已在 inference_server.py 中预留接口

import sqlite3, json, os

class LocalCache:
    def __init__(self, cache_dir="/opt/hydropower/data"):
        os.makedirs(cache_dir, exist_ok=True)
        self.db = sqlite3.connect(os.path.join(cache_dir, "offline_cache.db"))
        self.db.execute("""CREATE TABLE IF NOT EXISTS cache_queue
            (id INTEGER PRIMARY KEY AUTOINCREMENT,
             data_type TEXT, payload TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)""")

    def store(self, data_type: str, payload: dict):
        self.db.execute("INSERT INTO cache_queue (data_type, payload) VALUES (?, ?)",
                        (data_type, json.dumps(payload)))
        self.db.commit()

    def drain(self) -> list:
        """联网后批量取出并清空"""
        rows = self.db.execute("SELECT id, data_type, payload FROM cache_queue ORDER BY id").fetchall()
        self.db.execute("DELETE FROM cache_queue")
        self.db.commit()
        return [{"data_type": r[1], "payload": json.loads(r[2])} for r in rows]
```

---

## 十二、模型重训练

### 12.1 用真实数据重训练

```powershell
# 1. 从 MySQL 导出累积的真实传感器数据
mysql -u root -p -e "
  SELECT upstream_level, downstream_level, inflow, rainfall, temperature,
         gate1_opening, gate2_opening, gate3_opening, created_at
  FROM hydropower_smart.sensor_readings
  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
  ORDER BY created_at
" > real_data.csv

# 2. 替换模拟数据
# 编辑 train_dqn_final.py / train_lstm_final.py:
#   将 generate_data() → load_real_data('D:\hydropower_scheduling\real_data.csv')

# 3. 重新训练 (RTX 5060)
python train_dqn_final.py      # ~11 min
python train_lstm_final.py     # ~3 min

# 4. 导出部署包
python deploy.py

# 5. 模型验证后下发 Jetson
scp -r hydropower_deploy/ hydropower@192.168.1.100:/tmp/
ssh hydropower@192.168.1.100 "sudo rsync -a /tmp/hydropower_deploy/ /opt/hydropower/ && sudo systemctl restart hydropower-inference"
```

### 12.2 模型版本管理

| 操作 | 说明 |
|------|------|
| 上传 | 云端系统设置页 → 拖拽 .pt 文件 → 后端校验格式 |
| 状态 | 待验证 → 沙箱测试(空跑) → 可用/不可用 |
| 激活 | 当前模型指针指向新版本, 边缘端下一秒推理自动使用 |
| 回滚 | 保留上一版本, 点击回滚, 秒级切回 |
| 下发 | 云端 → WebSocket → 边缘端下载 + MD5 校验 → 热加载 |

> 建议重训练周期: 每季度

---

## 十三、故障排查

### 13.1 Jetson 端

| 症状 | 可能原因 | 排查命令 |
|------|---------|------|
| 无法启动 | SD卡/NVMe 损坏 | `dmesg \| grep error` |
| CUDA 不可用 | JetPack 未正确安装 | `python3 -c "import torch; print(torch.cuda.is_available())"` |
| 推理全是同一结果 | 模型未收敛或损坏 | `md5sum /opt/hydropower/models/*.pt`, 对比 checksums.md5 |
| `CUDA out of memory` | 显存不足 | `deploy_config.json` 改 `"device":"cpu"` |
| 服务频繁重启 | Gunicorn worker 超时 | `journalctl -u hydropower-inference -f` |

### 13.2 PLC 通信故障

| 症状 | 可能原因 | 排查步骤 |
|------|---------|------|
| `Timeout` | RS485 接线松动/反接 | 1. 检查 A/B/GND 接线 2. `ls /dev/ttyUSB0` 确认串口存在 3. 用万用表测 A-B 间电阻 (~120Ω) |
| `Checksum error` | 波特率不匹配 | 确认 PLC 和程序均为 9600, 8N1 |
| `Illegal data address` | 寄存器地址不对 | 核对 PLC 中 VW 到 4xxxx 的映射 |
| 读出来的值一直不变 | PLC 未在 RUN 模式 | 检查 PLC 面板状态灯: RUN=绿色常亮 |

### 13.3 传感器故障

| 症状 | 可能原因 | 排查 |
|------|---------|------|
| 水位值恒为 0 | 液位计断电或损坏 | 用万用表测 4-20mA 回路电流, 0mA=断路 |
| 水位值乱跳 | 水面波动、电磁干扰 | 软件加滑动平均滤波 |
| 流量值恒为 0 | 水泵未工作或管路堵塞 | 检查水泵供电、管路是否弯折 |

### 13.4 MySQL 故障

| 症状 | 解决 |
|------|------|
| `Can't connect` | `sudo systemctl status mysql`, 确认服务运行 |
| `Access denied` | 检查 `deploy_config.json` 中 user/password |
| `Too many connections` | 检查推理循环中是否正确关闭连接 |
| 数据写入慢 | 检查索引, 考虑批量写入 |

### 13.5 WebSocket 故障

| 症状 | 解决 |
|------|------|
| 前端无法连接 WS | `php artisan reverb:status` 确认 Reverb 在运行 |
| WS 频繁断开 | 检查防火墙/代理设置, Nginx proxy_read_timeout 调大 |
| 边缘端无法上报 | `curl http://云端IP/api/health` 确认网络通 |

---

## 十四、附录

### 附录 A: 部署前检查清单

- [ ] Jetson 已烧录 JetPack 6.0, CUDA 可用
- [ ] Python 依赖全部安装 (`pip3 list | grep torch flask`)
- [ ] 部署包已拷贝到 `/opt/hydropower/`
- [ ] `deploy_config.json` 配置已更新 (串口/数据库/安全阈值)
- [ ] API 测试: `curl http://localhost:5000/api/health` 返回 ok
- [ ] PLC RS485 通信测试通过 (读 40001 寄存器有值)
- [ ] 液位计通电, 4-20mA 回路正常
- [ ] 推杆能受控伸缩
- [ ] 急停按钮能切断推杆/水泵供电
- [ ] systemd 服务已注册, 重启后自动启动
- [ ] 防火墙已配置 (只开放必要端口)
- [ ] 云端 API 地址配置正确, WebSocket 可连通

### 附录 B: 常用命令速查

```bash
# === Jetson ===
sudo systemctl start|stop|restart hydropower-inference
sudo systemctl status hydropower-inference
journalctl -u hydropower-inference -f --since "10 min ago"
python3 /opt/hydropower/inference_server.py                 # 手动测试推理
curl http://localhost:5000/api/health                       # 健康检查

# === PLC 调试 ===
python3 -c "import minimalmodbus; m=minimalmodbus.Instrument('/dev/ttyUSB0',1); m.serial.baudrate=9600; print(m.read_register(0))"

# === PC 训练 ===
cd D:\hydropower_scheduling
python train_dqn_final.py && python train_lstm_final.py
python deploy.py

# === 日志 ===
tail -f /var/log/hydropower.log                             # Jetson 服务日志
sudo journalctl -u hydropower-inference --since "1 hour ago"
```

### 附录 C: 部署包文件清单

| 文件 | 作用 | 阶段 |
|------|------|:--:|
| `api_server.py` | HTTP API 服务 (Flask) | ⭐ 现在 |
| `inference_server.py` | 推理引擎 (GateController) | ⭐ 现在 |
| `database.py` | MySQL 读写 | ⭐ 现在 |
| `deploy_config.json` | 统一配置 | ⭐ 现在 |
| `requirements.txt` | Python 依赖 | ⭐ 现在 |
| `models/dqn_scripted.pt` | DQN TorchScript 模型 (1.1MB) | ⭐ 现在 |
| `models/lstm_state_dict.pt` | LSTM 权重 (3.2MB) | ⭐ 现在 |
| `models/scaler_X.pkl` | 归一化器 | ⭐ 现在 |
| `models/checksums.md5` | 模型完整性校验 | ⭐ 现在 |
| `scripts/hydropower.service` | systemd 服务文件 | 后期 |
| `scripts/health_check.sh` | 定时健康检查 | 后期 |
| `scripts/backup_db.sh` | 数据库备份 | 后期 |
| `data/` | 本地缓存 + 离线数据 | 后期 |
| `logs/` | Gunicorn 日志 | 后期 |
| `README.md` | 快速开始 | ⭐ 现在 |
| `API_接口文档_给组长.md` | 接口文档 | ⭐ 现在 |
| `Laravel集成指南_给组长.md` | Laravel 集成代码 | ⭐ 现在 |
| `SOFTWARE_DEPLOY.md` | 本手册 | ⭐ 现在 |
| `DEPLOY_GUIDE.md` | 综合部署指南 | 后期 |

