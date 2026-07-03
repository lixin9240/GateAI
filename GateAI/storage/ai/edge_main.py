"""
边缘端自主控制主循环 (Production)
==================================
架构: 传感器采集 → AI推理 → PLC执行 → 云端上报 → 循环

启动:
  python edge_main.py                        # 模拟模式（无PLC，无云端）
  python edge_main.py --plc COM3             # 连接真实PLC
  python edge_main.py --cloud http://192.168.1.100:8000 --token xxx  # 上报云端
  python edge_main.py --interval 60          # 60秒一个周期（默认3600秒）

对齐接口文档:
  11.1 POST /api/edge/monitoring-data
  11.2 POST /api/edge/dispatch-decisions
  11.4 POST /api/edge/alarms
  12.1 GET  /api/edge/physics-config/{id}
"""

import sys, os, json, time, uuid, logging
from datetime import datetime
from typing import Optional

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import numpy as np
from inference_server import GateController, SensorData
from api_server import CloudAPI, init_cloud

# ---- 结果格式化（本地用） ----

def _build_result(cmd, sensor=None):
    """构建与接口文档一致的决策结果字典"""
    import numpy as np
    result = {
        "trace_id": str(uuid.uuid4())[:8],
        "decision_time": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "decision_mode": getattr(cmd, "decision_level", "L3_AUTO"),
        "risk_rank": 1 if cmd.safety_flag == "safe" else (2 if cmd.safety_flag == "warning" else 3),
        "recommended_opening": [round(g * 100, 1) for g in cmd.gate_openings],
        "confidence": round(cmd.confidence * 100, 1),
        "safety_flag": cmd.safety_flag,
        "predicted_inflows": [round(v, 1) for v in cmd.predicted_inflows],
        "predicted_levels": [round(v, 2) for v in cmd.predicted_levels],
        "predicted_peak_level": round(max(cmd.predicted_levels), 2) if len(cmd.predicted_levels) > 0 else None,
        "lstm_predictions": {
            "1h": {"level": round(cmd.predicted_levels[0], 2) if len(cmd.predicted_levels) > 0 else None,
                   "flow": round(cmd.predicted_inflows[0], 1) if len(cmd.predicted_inflows) > 0 else None},
            "3h": {"level": round(cmd.predicted_levels[2], 2) if len(cmd.predicted_levels) > 2 else None,
                   "flow": round(cmd.predicted_inflows[2], 1) if len(cmd.predicted_inflows) > 2 else None},
            "6h": {"level": round(cmd.predicted_levels[-1], 2) if len(cmd.predicted_levels) else None,
                   "flow": round(cmd.predicted_inflows[-1], 1) if len(cmd.predicted_inflows) else None},
        },
        "physics_validation": {
            "passed": getattr(cmd, "physics_violation", 0.0) < 0.01,
            "physics_violation_m": round(getattr(cmd, "physics_violation", 0.0), 4),
            "risk_level": getattr(cmd, "risk_level", "safe"),
            "risk_probability": round(getattr(cmd, "risk_probability", 0.0), 4),
            "shadow_levels": getattr(cmd, "shadow_levels", []),
            "command_smoothed": getattr(cmd, "command_smoothed", False),
        },
        "inference_time_ms": 0,
    }
    if sensor:
        result["upstream_level"] = sensor.upstream_level
        result["downstream_level"] = sensor.downstream_level
        result["inflow_rate"] = sensor.inflow
        result["current_opening"] = float(np.mean(sensor.gate_openings)) * 100
    return result

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
log = logging.getLogger("edge")


# 云端上报由 api_server.CloudAPI 提供（对齐接口文档 11.x / 12.x）


# ==================== 传感器模拟器 ====================

class SensorSimulator:
    """
    PC 测试用传感器模拟器（与训练环境物理模型一致）
    有真实 PLC 后替换为 plc_driver
    """

    def __init__(self):
        self.level = 182.0
        self.downstream = 120.5
        self.inflow = 250.0
        self.rainfall = 0.0
        self.temperature = 22.0
        self.gates = [0.3, 0.2, 0.4]
        self.gate_max = [300, 200, 250]

    def read(self) -> SensorData:
        return SensorData(
            upstream_level=round(self.level, 2),
            downstream_level=round(self.downstream, 2),
            inflow=round(self.inflow, 1),
            rainfall=round(self.rainfall, 1),
            temperature=round(self.temperature, 1),
            gate_openings=[round(g, 3) for g in self.gates],
        )

    def update(self, gate_openings: list):
        """根据闸门指令更新物理状态（与训练 env.HydropowerEnv 一致）"""
        self.gates = gate_openings
        total_discharge = sum(g * d for g, d in zip(gate_openings, self.gate_max))

        # 降雨
        if np.random.random() < 0.15:
            self.rainfall = min(50, max(0, self.rainfall + np.random.exponential(3)))
        self.rainfall *= 0.9

        # 入库流量
        self.inflow += np.random.normal(0, 15)
        self.inflow += self.rainfall * 2
        self.inflow = np.clip(self.inflow, 20, 600)

        # 上游水位
        net_inflow = self.inflow - total_discharge
        level_change = net_inflow / 1_000_000 * 10.0
        self.level += level_change
        self.level = np.clip(self.level, 165, 195)

        # 下游水位
        ds_change = 0.005 * (total_discharge - 150) + np.random.normal(0, 0.01)
        self.downstream += ds_change
        self.downstream = np.clip(self.downstream, 115, 130)

        # 温度
        self.temperature += np.random.normal(0, 0.5)
        self.temperature = np.clip(self.temperature, 10, 40)


# ==================== 主控制循环 ====================

def format_cmd_output(sensor: SensorData, result: dict, elapsed_ms: float):
    """格式化单步输出"""
    gates_str = " | ".join(
        f"{'溢洪道' if i==0 else '泄洪洞' if i==1 else '发电'}:{result['recommended_opening'][i]:.0f}%"
        for i in range(3)
    )
    phys = result["physics_validation"]
    return (
        f"水位:{sensor.upstream_level:.1f}m 下游:{sensor.downstream_level:.1f}m "
        f"流量:{sensor.inflow:.0f}m3/s 雨:{sensor.rainfall:.1f}mm/h\n"
        f"  LSTM: 6h峰值={result['predicted_peak_level']:.1f}m "
        f"| DQN: [{gates_str}]\n"
        f"  决策级:{result['decision_mode']} 风险:{phys['risk_level']}(p={phys['risk_probability']:.2f}) "
        f"物理:{'PASS' if phys['passed'] else 'CORRECTED'} "
        f"| {elapsed_ms:.1f}ms"
    )


def main():
    import argparse
    p = argparse.ArgumentParser(description="Edge Autonomous Control Loop")
    p.add_argument("--plc", help="PLC串口 (如 COM3)，不传使用模拟器")
    p.add_argument("--cloud", help="云端 API 地址 (如 http://192.168.1.100:8000)")
    p.add_argument("--token", help="云端认证 Token")
    p.add_argument("--interval", type=int, default=3600, help="控制周期(秒)，默认3600，测试可用60")
    p.add_argument("--once", action="store_true", help="只跑一次，用于测试")
    args = p.parse_args()

    # ---- 初始化 ----
    log.info("=" * 55)
    log.info("  Edge Autonomous Control Loop v5.0 (Physics-Informed)")
    log.info("=" * 55)

    log.info("Loading AI models...")
    controller = GateController()
    log.info(f"Device: {controller.device} | Interval: {args.interval}s")

    # 传感器
    if args.plc:
        from plc_driver import create_plc_driver
        plc = create_plc_driver("modbus_rtu", port=args.plc)
        plc.connect()
        log.info(f"PLC: {args.plc}")
        sensor_src = plc  # PLC 驱动
    else:
        sensor_src = SensorSimulator()
        log.info("Sensor: Simulator (no PLC)")

    # 云端
    cloud_api = init_cloud(base_url=args.cloud, token=args.token)

    log.info("Entering main loop...")
    log.info("-" * 55)

    # ---- 主循环 ----
    cycle = 0
    try:
        while True:
            cycle += 1
            t0 = time.time()
            trace_id = str(uuid.uuid4())[:8]

            # 1. 读取传感器
            if isinstance(sensor_src, SensorSimulator):
                sensor = sensor_src.read()
            else:
                reading = sensor_src.read_sensors()
                if reading is None:
                    log.error("PLC read failed, skipping cycle")
                    time.sleep(10)
                    continue
                sensor = SensorData(
                    upstream_level=reading.upstream_level,
                    downstream_level=reading.downstream_level,
                    inflow=reading.inflow,
                    rainfall=reading.rainfall,
                    temperature=reading.temperature,
                    gate_openings=reading.gate_feedback,
                )

            # 2. AI 推理
            cmd = controller.step(sensor)
            elapsed = (time.time() - t0) * 1000

            # 2. 构建结果
            result = _build_result(cmd, sensor)
            result["trace_id"] = trace_id
            result["inference_time_ms"] = round(elapsed, 1)

            # 3. 输出
            print(f"\n[{datetime.now().strftime('%H:%M:%S')}] Cycle #{cycle} [{trace_id}]")
            print(format_cmd_output(sensor, result, elapsed))

            # 4. 执行闸门（PLC 或模拟器）
            if isinstance(sensor_src, SensorSimulator):
                sensor_src.update(cmd.gate_openings)
            else:
                from plc_driver import GateCommand, ControlMode
                sensor_src.write_gates(GateCommand(openings=cmd.gate_openings))
            log.debug("Gate executed")

            # 5. 云端上报（对齐接口文档 11.1 / 11.2）
            if cloud_api and cloud_api.enabled:
                # 11.1 上报监测数据
                cloud_api.report_monitoring(1, 1, [{
                    "timestamp": datetime.now().strftime("%Y-%m-%dT%H:%M:%S"),
                    "upstream_level": sensor.upstream_level,
                    "downstream_level": sensor.downstream_level,
                    "water_head": sensor.upstream_level - sensor.downstream_level,
                    "inflow_rate": sensor.inflow,
                    "outflow_rate": sum(g * d for g, d in zip(cmd.gate_openings, [300, 200, 250])),
                    "gate_opening": float(np.mean(cmd.gate_openings)) * 100,
                    "power_output": 0.85 * sum(g * d for g, d in zip(cmd.gate_openings, [300, 200, 250])) * max(sensor.upstream_level - sensor.downstream_level, 10) * 9.81 / 1000,
                }])
                # 11.2 上报调度决策
                cloud_api.report_decision({
                    "trace_id": trace_id, "reservoir_id": 1, "edge_node_id": 1,
                    "prediction_id": 0,
                    "decision_time": result["decision_time"],
                    "decision_mode": result["decision_mode"],
                    "risk_rank": result["risk_rank"],
                    "upstream_level": sensor.upstream_level,
                    "downstream_level": sensor.downstream_level,
                    "inflow_rate": sensor.inflow,
                    "current_opening": result.get("current_opening", 0),
                    "lstm_predictions": result["lstm_predictions"],
                    "recommended_opening": result["recommended_opening"][0],
                    "confidence": result["confidence"],
                    "factors": [],
                    "alternatives": [],
                    "weights_used": {"power": 0.4, "safety": 0.4, "ecology": 0.2},
                    "physics_validation": result["physics_validation"],
                })
                cloud_api.flush()

                # 11.4 告警上报
                if result["safety_flag"] == "danger" or result["physics_validation"]["risk_level"] in ("critical", "danger"):
                    cloud_api.report_alarm(
                        1, 1, 1, "physics_violation",
                        "urgent" if result["safety_flag"] == "danger" else "important",
                        f"风险:{result['physics_validation']['risk_level']} 峰值:{result['predicted_peak_level']}m",
                        result["predicted_peak_level"], 190.0,
                    )

            if args.once:
                break

            # 7. 等待下一周期
            log.info(f"Sleeping {args.interval}s...")
            time.sleep(args.interval)

    except KeyboardInterrupt:
        log.info("Shutdown requested")
    finally:
        if not isinstance(sensor_src, SensorSimulator):
            sensor_src.disconnect()
        log.info("Edge loop stopped")


if __name__ == "__main__":
    main()
