# 水电站闸门智能调度系统 — 部署手册

> 版本: 4.0-final | 日期: 2026-07-01 | 适用设备: NVIDIA Jetson Orin Nano / x86_64 Linux / Windows

---

## 目录

1. [系统架构](#1-系统架构)
2. [部署包内容](#2-部署包内容)
3. [环境准备](#3-环境准备)
4. [快速部署](#4-快速部署)
5. [接入 PLC 和传感器](#5-接入-plc-和传感器)
6. [MySQL 数据库配置](#6-mysql-数据库配置)
7. [生产运行](#7-生产运行)
8. [模型重训练](#8-模型重训练)
9. [故障排查](#9-故障排查)

---

## 1. 系统架构

### 1.1 三层部署架构

```
┌──────────────────────────────────────────────────────────────────┐
│                      第一层: 云平台 (训练)                         │
│                                                                   │
│  ┌─────────────────────┐    ┌─────────────────────┐              │
│  │  LSTM 来水预测模型    │    │  DQN 闸门调度模型     │              │
│  │  数据: 历史水位/流量   │    │  环境: 数字孪生仿真   │              │
│  │  输出: 未来6h趋势     │    │  输出: 最优闸门策略    │              │
│  └──────────┬──────────┘    └──────────┬──────────┘              │
│             │                          │                          │
│             └──────────┬───────────────┘                          │
│                        │ 模型导出 (TorchScript + state_dict)       │
└────────────────────────┼──────────────────────────────────────────┘
                         │
                         │  部署包下发 (scp / U盘 / OTA)
                         │
┌────────────────────────┼──────────────────────────────────────────┐
│                      第二层: 边缘计算 (推理)                        │
│                 NVIDIA Jetson Orin Nano (67 TOPS)                  │
│                                                                   │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │                    inference_server.py                       │ │
│  │                                                              │ │
│  │  ┌───────────┐   ┌───────────┐   ┌───────────┐              │ │
│  │  │ 数据预处理  │──▶│ LSTM 预测  │──▶│ DQN 决策   │              │ │
│  │  │ (24h历史)  │   │ (6h来水)   │   │ (125动作)  │              │ │
│  │  └───────────┘   └───────────┘   └─────┬─────┘              │ │
│  │                                        │                     │ │
│  │                         ┌──────────────┴──────────────┐      │ │
│  │                         │  安全兜底 + 人工接管开关      │      │ │
│  │                         └──────────────────────────────┘      │ │
│  └─────────────────────────────────────────────────────────────┘ │
│                                                                   │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │                     MySQL 数据库                              │ │
│  │  sensor_readings | decision_logs | alerts | co2_statistics   │ │
│  └─────────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────────┘
                         │
                         │  Modbus RTU/TCP
                         │
┌────────────────────────┼──────────────────────────────────────────┐
│                    第三层: 现场设备 (执行)                          │
│                                                                   │
│  ┌──────────────────┐                                            │
│  │ 西门子 S7-200     │                                            │
│  │ SMART PLC         │                                            │
│  └───┬─────────┬────┘                                            │
│      │         │                                                  │
│  ┌───▼──┐  ┌──▼──────────────────────────────┐                  │
│  │ 输入  │  │            输  出                │                  │
│  │      │  │                                  │                  │
│  │ 超声波 │  │  ┌─────┐  ┌─────┐  ┌─────┐     │                  │
│  │ 液位计 │  │  │闸门1 │  │闸门2 │  │闸门3 │     │                  │
│  │ (水位) │  │  │溢洪道│  │泄洪洞│  │发电  │     │                  │
│  │      │  │  │电动  │  │电动  │  │电动  │     │                  │
│  │ 流量计 │  │  │推杆  │  │推杆  │  │推杆  │     │                  │
│  │      │  │  │0-100%│  │0-100%│  │0-100%│     │                  │
│  │ 雨量计 │  │  └─────┘  └─────┘  └─────┘     │                  │
│  │      │  │                                  │                  │
│  │ 温度计 │  │  ┌─────┐                        │                  │
│  └──────┘  │  │继电器│ (紧急制动)              │                  │
│            │  └─────┘                        │                  │
│            └─────────────────────────────────┘                  │
└──────────────────────────────────────────────────────────────────┘
```

### 1.2 数据流 (7 步闭环)

```
   ① 全域感知          ② 数据传输          ③ 边缘AI推理
  ┌──────────┐      ┌──────────┐      ┌──────────────┐
  │ 超声波液位计│      │          │      │ LSTM 来水预测  │
  │ 流量计     │─────▶│ PLC 采集  │─────▶│ DQN 闸门决策   │
  │ 雨量计     │      │ Modbus   │      │ 安全风险评估    │
  │ 温度计     │      │          │      └──────┬───────┘
  └──────────┘      └──────────┘             │
        ▲                                     ▼
        │                            ┌──────────────┐
        │             ④ 指令下发      │              │
        │           ┌──────────┐     │  MySQL 记录   │
        │           │ PLC 接收  │     │  传感器+决策   │
        │           │ 驱动闸门   │     │  告警+指标    │
        │           └────┬─────┘     └──────┬───────┘
        │                │                  │
        │                ▼                  ▼
        │           ┌──────────┐     ┌──────────────┐
        │           │ 电动推杆   │     │ ⑤ 云边同步    │
        └───────────│ 调节开度   │     │ 模型更新下发   │
          ⑥ 反馈    └──────────┘     └──────────────┘
       (传感器持续
        监测水位变化)                ┌──────────────┐
                                    │ ⑦ 监控告警    │
                                    │ 水位超限/系统  │
                                    │ 异常实时通知   │
                                    └──────────────┘
```

### 1.3 边缘计算层 (实时决策核心 — 系统的"反应中枢")

> 这是整个智能调度系统的核心——"边"。负责毫秒级快速响应，不依赖云端网络。

**为什么需要边缘计算？**

传统方案将数据上传到云端做 AI 推理再等结果返回，一来一回延迟至少 200-500ms。当洪水来临时，水位每秒都在上涨，几百毫秒的延迟可能决定大坝安全。边缘计算把 AI 模型直接部署在水电站机房的本地设备上，**数据不出机房，毫秒级响应**。

**怎么搭？**

```
  传感器 → PLC → Jetson Orin Nano (本地AI推理) → PLC → 闸门执行
                     ▲
                     │ 模型从云端训练好后下发
                     │ 日常推理不需要网络
```

**硬件选择：**

| 项目 | 规格 |
|------|------|
| 设备 | NVIDIA Jetson Orin Nano 开发者套件 |
| 算力 | **67 TOPS** (Int8) |
| GPU | 1024-core NVIDIA Ampere + 32 Tensor Cores |
| CPU | 6-core ARM Cortex-A78AE |
| 内存 | 8 GB LPDDR5 |
| 存储 | SSD (建议 128GB+) |
| 功耗 | 7W-15W (被动散热即可) |
| 接口 | USB 3.2, Gigabit Ethernet, M.2, GPIO |
| 系统 | Ubuntu 22.04 + JetPack 6.0 |

**软件部署：**

| 组件 | 内容 | 文件 |
|------|------|------|
| LSTM 来水预测模型 | 基于过去24小时水位/流量数据，预测未来6小时来水趋势 | `models/lstm_state_dict.pt` |
| DQN 闸门决策模型 | 综合当前状态+LSTM预测结果，计算3个闸门的最优开度 | `models/dqn_scripted.pt` |
| 推理引擎 | 传感器→LSTM预测→DQN决策→PLC指令，全链路毫秒级完成 | `inference_server.py` |
| 归一化器 | 数据预处理，输入缩放至模型训练时的分布 | `models/scaler_X.pkl` |
| 安全兜底 | 水位超限自动全开闸门，AI推理失败时切换人工模式 | 内置于推理引擎 |

**推理流程 (单次耗时 < 5ms)：**

```
  ① 传感器数据更新历史缓冲区
         │
         ▼
  ② LSTM 前向推理 (24h序列 → 6h预测)
         │
         ▼
  ③ 构建12维增强状态向量
         │
         ▼
  ④ DQN 前向推理 (12维状态 → 125个动作Q值)
         │
         ▼
  ⑤ argmax 选最优动作 → 解码为3个闸门开度
         │
         ▼
  ⑥ 安全判定 (预测峰值水位 vs 危险/警告阈值)
         │
         ▼
  ⑦ 指令输出 (Modbus写PLC寄存器) + MySQL记录
```

**"不依赖网络"意味着什么？**

- 日常运行：Jetson 在本地完成 AI 推理 → PLC 执行 → 传感器反馈，**完全离线闭环**
- 网络恢复时：自动将离线期间积累的推理日志同步到 MySQL
- 模型更新时：云端训练好新模型 → 运维人员 U盘拷贝或 OTA 下发 → Jetson 热加载新模型

**洪水场景下的响应速度：**

```
  水位超限触发 → 5ms AI推理 → 1ms Modbus写PLC → 10ms 闸门动作
  ─────────────────────────────────────────────────────
  端到端: ~20ms 完成紧急响应
  对比云端方案: 200-500ms (10-25倍差距)
```

### 1.4 硬件清单

| 设备 | 型号 | 数量 | 用途 |
|------|------|------|------|
| 边缘计算网关 | NVIDIA Jetson Orin Nano (67TOPS) | 1 | AI 推理 |
| PLC | 西门子 S7-200 SMART | 1 | 数据采集/指令执行 |
| 超声波液位计 | 4-20mA / RS485 | 2 | 上/下游水位 |
| 流量计 | 电磁式 / 超声波 | 1 | 入库流量 |
| 雨量计 | 翻斗式 | 1 | 降雨量 |
| 温度计 | PT100 / 数字式 | 1 | 环境温度 |
| 电动推杆 | 伺服驱动 | 3 | 闸门启闭 |
| 继电器 | 电磁式 | 3 | 紧急制动 |
| 交换机 | 工业以太网 | 1 | 网络通信 |
| UPS | 不间断电源 | 1 | 断电保护 |

---

## 2. 部署包内容

```
hydropower_deploy/
├── inference_server.py    # 推理服务主程序 (自包含)
├── deploy_config.json     # 配置文件 (模型参数/水库参数/安全阈值)
├── DEPLOY_GUIDE.md        # 本部署手册
└── models/
    ├── dqn_scripted.pt    # DQN 模型 (TorchScript, 1152 KB)
    ├── lstm_state_dict.pt # LSTM 模型 (权重文件, 3257 KB)
    └── scaler_X.pkl       # 数据归一化器 (879 B)
```

---

## 3. 环境准备

### 3.1 Jetson Orin Nano (推荐)

```bash
# JetPack 默认包含 PyTorch, 只需补充:
sudo apt update
pip3 install scikit-learn joblib mysqlclient

# 验证 CUDA 可用
python3 -c "import torch; print(f'PyTorch {torch.__version__}, CUDA: {torch.cuda.is_available()}')"
```

### 3.2 x86_64 Linux / Windows

```bash
pip install torch numpy scikit-learn joblib mysqlclient
```

### 3.3 硬件接线

| 传感器/执行器 | 接口 | 连接目标 |
|--------------|------|---------|
| 超声波液位计 (上游) | 4-20mA / RS485 | PLC AI 模块 |
| 超声波液位计 (下游) | 4-20mA / RS485 | PLC AI 模块 |
| 流量计 | 4-20mA / 脉冲 | PLC AI 模块 |
| 雨量计 | 脉冲 / RS485 | PLC AI 模块 |
| 温度计 | 4-20mA | PLC AI 模块 |
| 电动推杆 ×3 | 继电器输出 | PLC DO 模块 |
| PLC ↔ 边缘网关 | Modbus RTU (RS485) 或 Modbus TCP | COM 口或网口 |

---

## 4. 快速部署

### 4.1 复制部署包

```bash
# 方式1: U盘
cp -r /media/usb/hydropower_deploy/ /opt/hydropower/

# 方式2: SCP
scp -r hydropower_deploy/ user@192.168.1.100:/opt/hydropower/

# 方式3: 直接运行 (Windows)
# 将 hydropower_deploy/ 文件夹复制到目标目录即可
```

### 4.2 测试推理

```bash
cd /opt/hydropower
python inference_server.py
```

预期输出:
```
Device: cuda
DQN: TorchScript loaded
LSTM: state_dict loaded
Ready: LSTM(24h->6h) + DQN(125 actions)

=======================================================
  Test Inference
=======================================================
  Gates:  ['100%', '100%', '50%']
  Inflow: ['222', '226', '227', '224', '228', '237']
  Levels: ['182.0', '182.0', '182.0', ...]
  Peak:   182.0m | Safety: safe
  Conf:   1.00 | Time: 1.2ms
```

---

## 5. 接入 PLC 和传感器

编辑 `inference_server.py`, 修改 `PLCInterface` 类:

```python
import minimalmodbus  # pip install minimalmodbus

class PLCInterface:
    """西门子 S7-200 SMART Modbus RTU 通信"""

    def __init__(self, port='COM3', slave_id=1):
        self.instrument = minimalmodbus.Instrument(port, slave_id)
        self.instrument.serial.baudrate = 9600
        self.instrument.serial.timeout = 1.0

    def read_sensors(self) -> SensorData:
        """
        读取 PLC 寄存器
        寄存器映射:
          40001: 上游水位 (m)     → 实际值 = 寄存器值 / 100
          40002: 下游水位 (m)
          40003: 入库流量 (m3/s)
          40004: 降雨量 (mm/h)
          40005: 温度 (C)
          40006-40008: 当前闸门开度反馈 (%)
        """
        values = self.instrument.read_registers(0, 8, functioncode=3)

        return SensorData(
            upstream_level=values[0] / 100.0,
            downstream_level=values[1] / 100.0,
            inflow=values[2] / 10.0,
            rainfall=values[3] / 10.0,
            temperature=values[4] / 10.0,
            gate_openings=[values[5]/100.0, values[6]/100.0, values[7]/100.0],
        )

    def write_gates(self, openings: List[float]) -> bool:
        """
        写入闸门开度到 PLC
        寄存器映射:
          40010-40012: 目标闸门开度 (0-100%)
        """
        values = [int(o * 100) for o in openings]
        self.instrument.write_registers(9, values)
        return True
```

然后在 `main()` 的 daemon 循环中启用:

```python
if args.daemon:
    plc = PLCInterface(port='COM3')  # 取消注释并配置端口
    ctrl = GateController()
    while True:
        sensor = plc.read_sensors()
        cmd = ctrl.step(sensor)
        plc.write_gates(cmd.gate_openings)

        # 记录到 MySQL
        if cmd.safety_flag != "safe":
            print(f"[ALERT] {cmd.safety_flag}: peak={max(cmd.predicted_levels):.1f}m")

        time.sleep(args.interval)
```

---

## 6. MySQL 数据库配置

### 6.1 安装 MySQL

```bash
# Jetson / Ubuntu
sudo apt install mysql-server
sudo mysql_secure_installation

# 创建数据库和用户
sudo mysql -e "
  CREATE DATABASE IF NOT EXISTS hydropower_smart CHARACTER SET utf8mb4;
  CREATE USER IF NOT EXISTS 'hydropower'@'localhost' IDENTIFIED BY 'GYZ032411';
  GRANT ALL PRIVILEGES ON hydropower_smart.* TO 'hydropower'@'localhost';
  FLUSH PRIVILEGES;
"
```

### 6.2 连接数据库

```python
from database import HydropowerDB

db = HydropowerDB(
    host='localhost',
    port=3306,
    user='hydropower',
    password='GYZ032411',
    db_name='hydropower_smart',
)

# 在推理循环中记录
db.insert_sensor(SensorRecord(...))
db.insert_decision(DecisionRecord(...))
```

### 6.3 数据库表结构

| 表名 | 用途 | 关键字段 |
|------|------|---------|
| `sensor_readings` | 传感器原始数据 | upstream_level, inflow, rainfall, temperature |
| `decision_logs` | AI 决策记录 | gate1/2/3_opening, confidence, safety_flag |
| `alerts` | 告警事件 | alert_level, alert_type, message |
| `model_metrics` | 模型性能统计 | avg_inference_time, safety_flag_pct |
| `co2_statistics` | 碳排放节约 | total_power_kwh, co2_saved_kg |

---

## 7. 生产运行

### 7.1 systemd 服务 (Linux/Jetson 推荐)

创建 `/etc/systemd/system/hydropower.service`:

```ini
[Unit]
Description=Hydropower AI Inference Service
After=network.target mysql.service

[Service]
Type=simple
User=hydropower
WorkingDirectory=/opt/hydropower
ExecStart=/usr/bin/python3 /opt/hydropower/inference_server.py --daemon --interval 3600
Restart=always
RestartSec=10
StandardOutput=append:/var/log/hydropower.log
StandardError=append:/var/log/hydropower.log

[Install]
WantedBy=multi-user.target
```

启动服务:

```bash
sudo systemctl daemon-reload
sudo systemctl enable hydropower
sudo systemctl start hydropower
sudo systemctl status hydropower   # 查看状态
journalctl -u hydropower -f        # 查看日志
```

### 7.2 Windows 服务

```powershell
# 使用 nssm (Non-Sucking Service Manager)
nssm install HydropowerAI "python" "D:\hydropower\inference_server.py --daemon"
nssm set HydropowerAI AppDirectory "D:\hydropower"
nssm start HydropowerAI
```

### 7.3 配置参数

编辑 `deploy_config.json` 调整运行参数:

```json
{
  "inference": {
    "interval_seconds": 3600,    // 调度间隔 (默认1小时)
    "history_buffer_size": 24    // LSTM 历史窗口
  },
  "safety": {
    "threshold_danger": 190.0,   // 危险水位 (m)
    "threshold_warning": 188.0   // 警告水位 (m)
  }
}
```

---

## 8. 模型重训练

当积累足够的真实运行数据后，可以用新数据重新训练模型:

```bash
# 在训练机上运行
cd D:\hydropower_scheduling

# 1. 用真实数据替换模拟数据
#    编辑 train_lstm_final.py 的 generate_data() 函数
#    导入 MySQL 中积累的真实传感器数据

# 2. 重新训练
python train_dqn_final.py    # DQN (~11分钟)
python train_lstm_final.py   # LSTM (~3分钟)

# 3. 导出部署包
python deploy.py

# 4. 更新边缘设备
scp -r hydropower_deploy/ user@jetson:/opt/hydropower/
ssh user@jetson "sudo systemctl restart hydropower"
```

---

## 9. 故障排查

### 推理报错

| 症状 | 原因 | 解决 |
|------|------|------|
| `FileNotFoundError: deploy_config.json` | 未在部署目录执行 | `cd /opt/hydropower` 再运行 |
| `CUDA out of memory` | Jetson 显存不足 | 设置 `device='cpu'` |
| `KeyError: 'model_state_dict'` | 模型文件损坏 | 重新运行 `deploy.py` 导出 |
| 推理结果恒定为同一动作 | 模型未收敛 | 增加训练回合数重新训练 |

### PLC 通信故障

| 症状 | 原因 | 解决 |
|------|------|------|
| `Timeout` | RS485 接线松动 | 检查 A/B/GND 接线 |
| `Checksum error` | 波特率不匹配 | 确认 PLC 和程序波特率一致 (9600) |
| `Illegal data address` | 寄存器地址错误 | 核对 PLC 寄存器映射表 |

### MySQL 连接失败

| 症状 | 原因 | 解决 |
|------|------|------|
| `Can't connect to MySQL` | MySQL 未启动 | `sudo systemctl start mysql` |
| `Access denied` | 密码/用户错误 | 检查 `deploy_config.json` 中的数据库配置 |
| `Unknown database` | 数据库未创建 | 执行 6.1 节的建库命令 |

---

## 附录 A: 模型技术规格

| 参数 | DQN | LSTM |
|------|-----|------|
| 架构 | Dueling Double DQN | 2层 BiLSTM + MultiheadAttention |
| 输入 | 12维状态向量 | 24h×5特征序列 |
| 输出 | 125个离散动作 (3闸门×5档) | 6h×2 (水位+流量) |
| 参数量 | 284,798 | 831,628 |
| 推理速度 | <1ms (GPU) / ~2ms (CPU) | <2ms (GPU) / ~5ms (CPU) |
| 模型大小 | 1.1 MB | 3.2 MB |

## 附录 B: 授权与维护

- **技术支持**: 参考训练代码 `train_dqn_final.py` 和 `train_lstm_final.py` 中的注释
- **模型更新周期**: 建议每季度用新积累的运行数据重训练一次
- **监控指标**: 关注 `model_metrics` 表中的 confidence 趋势和 safety_flag 比例
