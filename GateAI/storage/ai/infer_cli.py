"""
AI 推理命令行接口 — 供 Laravel 直接调用
========================================
Laravel 通过 proc_open 调用此脚本，JSON 进 JSON 出，无需 HTTP 服务。

用法:
  echo '{"upstream_level":182,...}' | python infer_cli.py
  python infer_cli.py < sensor_data.json
  python infer_cli.py --reset   # 重置 LSTM 历史缓冲区

输入 (stdin JSON):   {"upstream_level":182, "downstream_level":120.5, ...}
输出 (stdout JSON):  {"success":true, "data":{...}}  或  {"success":false, "error":"..."}

Laravel 调用示例见 infer_cli_example.php
"""

import sys, os, json, time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from inference_server import GateController, SensorData

# 全局单例，首次调用时加载模型
_controller = None


def get_controller():
    global _controller
    if _controller is None:
        # 抑制模型加载时的 print 输出（只输出 JSON 到 stdout）
        import io
        old_stdout = sys.stdout
        sys.stdout = io.StringIO()
        try:
            _controller = GateController()
        finally:
            sys.stdout = old_stdout
    return _controller


def parse_input(data: dict) -> SensorData:
    """从 Laravel 传来的 JSON 解析传感器数据"""
    return SensorData(
        upstream_level=float(data.get("upstream_level", 180.0)),
        downstream_level=float(data.get("downstream_level", 120.0)),
        inflow=float(data.get("inflow", 200.0)),
        rainfall=float(data.get("rainfall", 0.0)),
        temperature=float(data.get("temperature", 20.0)),
        gate_openings=[
            float(data.get("gate1_opening", data.get("gate_openings", [0.3, 0.2, 0.4])[0])),
            float(data.get("gate2_opening", data.get("gate_openings", [0.3, 0.2, 0.4])[1])),
            float(data.get("gate3_opening", data.get("gate_openings", [0.3, 0.2, 0.4])[2])),
        ],
    )


def build_output(cmd, sensor: SensorData = None, elapsed_ms: float = 0) -> dict:
    """构建对齐接口文档 11.2 的输出"""
    result = {
        "gate_openings": [round(g * 100, 1) for g in cmd.gate_openings],
        "predicted_inflows": [round(v, 1) for v in cmd.predicted_inflows],
        "predicted_levels": [round(v, 2) for v in cmd.predicted_levels],
        "predicted_peak_level": round(max(cmd.predicted_levels), 2) if len(cmd.predicted_levels) > 0 else None,
        "confidence": round(cmd.confidence, 4),
        "safety_flag": cmd.safety_flag,
        "decision_mode": getattr(cmd, "decision_level", "L3_AUTO"),
        "risk_level": getattr(cmd, "risk_level", "safe"),
        "risk_probability": round(getattr(cmd, "risk_probability", 0.0), 4),
        "physics_passed": getattr(cmd, "physics_violation", 0.0) < 0.01,
        "inference_time_ms": round(elapsed_ms, 2),
    }
    if sensor:
        result["context"] = {
            "upstream_level": sensor.upstream_level,
            "downstream_level": sensor.downstream_level,
            "inflow_rate": sensor.inflow,
            "current_opening": [round(g * 100, 1) for g in sensor.gate_openings],
        }
    return result


def main():
    if len(sys.argv) > 1 and sys.argv[1] == "--reset":
        ctrl = get_controller()
        ctrl.history = []
        print(json.dumps({"success": True, "message": "History reset"}))
        return

    try:
        raw = sys.stdin.read()
        if not raw.strip():
            print(json.dumps({"success": False, "error": "No input"}))
            sys.exit(1)

        data = json.loads(raw)
        sensor = parse_input(data)
        ctrl = get_controller()

        t0 = time.time()
        cmd = ctrl.step(sensor)
        elapsed = (time.time() - t0) * 1000

        output = build_output(cmd, sensor, elapsed)
        print(json.dumps({"success": True, "data": output}, ensure_ascii=False))

    except json.JSONDecodeError as e:
        print(json.dumps({"success": False, "error": f"JSON parse error: {e}"}))
        sys.exit(1)
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}))
        sys.exit(1)


if __name__ == "__main__":
    main()
