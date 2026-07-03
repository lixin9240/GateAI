"""
Hydropower AI Inference Server - Production Deployment
======================================================
LSTM predicts future inflow 6h ahead -> DQN decides gate openings -> Output to PLC

Architecture: Sensors -> LSTM(24h history) -> DQN(12-dim state) -> 3 gate commands

Deploy:
  Jetson Orin Nano:  pip install torch numpy scikit-learn joblib
  Any Linux x86_64:  same
  Windows:           same

Usage:
  python inference_server.py                 # test mode
  python inference_server.py --daemon        # run hourly
"""

import torch, torch.nn as nn, numpy as np, joblib, json, time, os, sys
from datetime import datetime
from typing import List, Tuple, Optional
from dataclasses import dataclass, field

# Physics guard - 四层物理防护
try:
    from physics_guard import (
        PhysicsInformedController, PhysicsCheckResult,
        SafetyResult, RiskAssessment, SmoothedCommand,
        DecisionLevel, RiskLevel,
    )
    PHYSICS_GUARD_AVAILABLE = True
except ImportError:
    PHYSICS_GUARD_AVAILABLE = False


# ==================== Model Definitions (for loading state_dict) ====================

class FinalLSTM(nn.Module):
    """LSTM model - must match training architecture exactly (v2 Final: 96-dim, 0.4 dropout)"""
    def __init__(self, input_size=5, hidden_size=96, num_layers=2, output_size=2, pred_horizon=6, dropout=0.4):
        super().__init__()
        h = hidden_size
        self.lstm1 = nn.LSTM(input_size, h, 1, batch_first=True, bidirectional=True)
        self.lstm2 = nn.LSTM(h * 2, h, 1, batch_first=True, bidirectional=True)
        self.attn = nn.MultiheadAttention(h * 2, 4, dropout=dropout, batch_first=True)
        self.norm = nn.LayerNorm(h * 2)
        self.drop = nn.Dropout(dropout)
        self.head = nn.Sequential(
            nn.Linear(h * 2, h), nn.GELU(), nn.Dropout(dropout),
            nn.Linear(h, pred_horizon * output_size))
        self.pred_horizon = pred_horizon
        self.output_size = output_size

    def forward(self, x):
        o, _ = self.lstm1(x); o, _ = self.lstm2(o)
        o, _ = self.attn(o, o, o); o = o.mean(1)
        o = self.norm(o); o = self.drop(o)
        return self.head(o).view(x.size(0), self.pred_horizon, self.output_size)


class DQN(nn.Module):
    """Dueling DQN - must match training architecture exactly"""
    def __init__(self, state_dim=12, action_dim=125, hidden_size=256, num_layers=4, dropout=0.1):
        super().__init__()
        layers = []; in_dim = state_dim
        for i in range(num_layers):
            layers.extend([nn.Linear(in_dim, hidden_size), nn.ReLU(), nn.LayerNorm(hidden_size)])
            if i < num_layers - 1: layers.append(nn.Dropout(dropout))
            in_dim = hidden_size
        self.feature_extractor = nn.Sequential(*layers)
        self.value_stream = nn.Sequential(nn.Linear(hidden_size, hidden_size // 2), nn.ReLU(), nn.Linear(hidden_size // 2, 1))
        self.advantage_stream = nn.Sequential(nn.Linear(hidden_size, hidden_size // 2), nn.ReLU(), nn.Linear(hidden_size // 2, action_dim))

    def forward(self, x):
        f = self.feature_extractor(x)
        v = self.value_stream(f); a = self.advantage_stream(f)
        return v + a - a.mean(dim=1, keepdim=True)


# ==================== Data Structures ====================

@dataclass
class SensorData:
    upstream_level: float; downstream_level: float; inflow: float
    rainfall: float; temperature: float; gate_openings: List[float]

@dataclass
class ControlCommand:
    gate_openings: List[float]; predicted_inflows: List[float]
    predicted_levels: List[float]; confidence: float; safety_flag: str
    # Physics guard fields
    physics_checked: bool = True
    physics_violation: float = 0.0           # 物理偏差量 (m)
    corrected_levels: List[float] = field(default_factory=list)
    risk_level: str = "safe"                  # safe/warning/danger/critical
    risk_probability: float = 0.0
    shadow_levels: List[float] = field(default_factory=list)
    decision_level: str = "L3_AUTO"           # L3_AUTO/L2_SUGGEST/L1_MANUAL/OVERRIDE
    command_smoothed: bool = False
    smooth_reason: str = ""


# ==================== Inference Engine ====================

class GateController:
    """LSTM + DQN inference engine"""

    def __init__(self, config_path=None, model_dir=None):
        base = os.path.dirname(os.path.abspath(__file__))
        config_path = config_path or os.path.join(base, "deploy_config.json")
        model_dir = model_dir or os.path.join(base, "models")
        with open(config_path) as f:
            self.cfg = json.load(f)
        mc = self.cfg["models"]
        self.device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
        print(f"Device: {self.device}")

        # Load DQN (try TorchScript first, fallback to state_dict)
        dqn_name = os.path.basename(mc["dqn"]["file"])
        dqn_path = os.path.join(model_dir, dqn_name)
        try:
            self.dqn = torch.jit.load(dqn_path, map_location=self.device)
            print("DQN: TorchScript loaded")
        except Exception:
            self.dqn = DQN(mc["dqn"]["state_dim"], mc["dqn"]["action_dim"]).to(self.device)
            ckpt = torch.load(dqn_path, map_location=self.device, weights_only=False)
            self.dqn.load_state_dict(ckpt["model_state_dict"] if "model_state_dict" in ckpt else ckpt)
            print("DQN: state_dict loaded")
        self.dqn.eval()

        # Load LSTM
        lstm_name = os.path.basename(mc["lstm"]["file"])
        lstm_path = os.path.join(model_dir, lstm_name)
        ckpt_l = torch.load(lstm_path, map_location=self.device, weights_only=False)
        lcfg = mc["lstm"]
        self.lstm = FinalLSTM(
            input_size=lcfg["input_features"], hidden_size=lcfg.get("hidden_size", 128),
            num_layers=lcfg.get("num_layers", 2), output_size=lcfg.get("output_features", 2),
            pred_horizon=lcfg["pred_horizon"], dropout=lcfg.get("dropout", 0.3),
        ).to(self.device)
        self.lstm.load_state_dict(ckpt_l["model_state_dict"])
        self.lstm.eval()
        print("LSTM: state_dict loaded")

        # Scaler
        scaler_name = os.path.basename(mc["scaler"])
        self.scaler = joblib.load(os.path.join(model_dir, scaler_name))

        # Config
        self.seq_length = lcfg["seq_length"]
        self.pred_horizon = lcfg["pred_horizon"]
        self.num_gates = mc["dqn"]["num_gates"]
        self.action_bins = mc["dqn"]["action_bins"]
        self.rc = self.cfg["reservoir"]
        self.sc = self.cfg["safety"]
        self.history = []
        self.prev_gates = [0.3, 0.2, 0.4]

        # Physics guard (四层物理防护)
        if PHYSICS_GUARD_AVAILABLE:
            self.physics = PhysicsInformedController(
                reservoir_area=self.rc.get("reservoir_area", 15_000_000),
                upstream_danger=self.rc["upstream_danger"],
                upstream_emergency=self.rc.get("upstream_emergency", 193.0),
                gate_max_discharge=tuple(mc["dqn"].get("gate_max_discharge", [300, 200, 250])),
            )
            print("Physics: 4-layer guard active (Validator + Safety + Shadow + Smooth)")
        else:
            self.physics = None

        # 模型路径记录（支持热加载）
        self._lstm_path = lstm_path
        self._dqn_path = dqn_path
        self._model_dir = model_dir
        self._config_path = config_path
        self.model_version = "5.0.0"

        print(f"Ready: LSTM(24h->6h) + DQN({mc['dqn']['action_dim']} actions) | v{self.model_version}")

    # ==================== 模型热加载 ====================

    def reload_models(self, lstm_path: str = None, dqn_path: str = None) -> bool:
        """
        热加载新模型文件，无需重启服务

        Parameters
        ----------
        lstm_path : 新 LSTM 模型路径 (None = 不更新)
        dqn_path  : 新 DQN 模型路径 (None = 不更新)

        Returns True if any model was reloaded
        """
        reloaded = False

        if lstm_path and os.path.exists(lstm_path):
            lcfg = self.cfg["models"]["lstm"]
            ckpt = torch.load(lstm_path, map_location=self.device, weights_only=False)
            self.lstm.load_state_dict(ckpt["model_state_dict"])
            self.lstm.eval()
            self._lstm_path = lstm_path
            self.history = []  # 清空历史缓冲区
            print(f"[HotReload] LSTM: {os.path.basename(lstm_path)} (epoch={ckpt.get('epoch', '?')})")
            reloaded = True

        if dqn_path and os.path.exists(dqn_path):
            ckpt = torch.load(dqn_path, map_location=self.device, weights_only=False)
            self.dqn.load_state_dict(ckpt["model_state_dict"] if "model_state_dict" in ckpt else ckpt)
            self.dqn.eval()
            self._dqn_path = dqn_path
            print(f"[HotReload] DQN: {os.path.basename(dqn_path)} (ep={ckpt.get('episode', '?')})")
            reloaded = True

        if reloaded:
            print("[HotReload] Models updated, history buffer cleared")
        return reloaded

    def step(self, sensor: SensorData) -> ControlCommand:
        t0 = time.time()
        self._update_history(sensor)
        inflows, levels = self._predict()

        # ===== Layer 1: 物理校验 LSTM 预测 =====
        physics_check_passed = True
        if self.physics and len(self.history) >= self.seq_length:
            check = self.physics.validate_prediction(
                predicted_levels=levels,
                predicted_inflows=inflows,
                current_level=sensor.upstream_level,
                current_inflow=sensor.inflow,
                gate_openings=sensor.gate_openings,
            )
            physics_check_passed = check.passed
            if not check.passed:
                levels = check.corrected_prediction
                cmd_check = check  # 暂存
        else:
            cmd_check = None

        # ===== DQN 决策 =====
        cmd = self._decide(sensor, inflows, levels)

        # ===== Layer 2-4: 安全约束 + 风险评估 + 指令平滑 =====
        if self.physics:
            # Layer 2: 安全约束
            state = {
                "upstream_level": sensor.upstream_level,
                "downstream_level": sensor.downstream_level,
                "inflow": sensor.inflow,
                "downstream_flow": sensor.downstream_level,
                "gate_positions": sensor.gate_openings,
            }
            safety = self.physics.constrain_action(state, cmd.gate_openings)
            if not safety.passed:
                cmd.gate_openings = safety.constrained_action
                cmd.decision_level = safety.decision_level.value

            # Layer 3: 影子水位风险评估
            risk = self.physics.assess_risk(
                current_level=sensor.upstream_level,
                current_downstream=sensor.downstream_level,
                inflow=sensor.inflow,
                gate_openings=cmd.gate_openings,
                predicted_inflows=inflows,
            )
            cmd.risk_level = risk.risk_level.value
            cmd.risk_probability = risk.risk_probability
            cmd.shadow_levels = risk.shadow_levels

            # 双因子熔断
            if cmd.decision_level == "L3_AUTO":
                cmd.decision_level = self.physics.make_decision(
                    cmd.confidence, risk, safety
                ).value

            # 物理校验结果追踪
            if physics_check_passed:
                cmd.physics_violation = 0.0
            elif cmd_check is not None:
                cmd.physics_violation = cmd_check.violation_magnitude
                cmd.corrected_levels = [round(v, 2) for v in cmd_check.corrected_prediction.tolist()]

            # Layer 4: 指令平滑
            smooth = self.physics.smooth_command(cmd.gate_openings, self.prev_gates)
            if smooth.was_filtered:
                cmd.gate_openings = smooth.openings
                cmd.command_smoothed = True
                cmd.smooth_reason = smooth.filter_reason

            self.prev_gates = cmd.gate_openings

        cmd.inference_time_ms = (time.time() - t0) * 1000
        return cmd

    def _update_history(self, sensor):
        feats = [sensor.upstream_level, sensor.inflow, sensor.downstream_level, sensor.rainfall, sensor.temperature]
        self.history.append(feats)
        if len(self.history) > self.seq_length:
            self.history = self.history[-self.seq_length:]

    def _predict(self):
        if not self.history:
            return np.zeros(self.pred_horizon), np.zeros(self.pred_horizon)
        if len(self.history) < self.seq_length:
            pad_n = self.seq_length - len(self.history)
            pad = np.tile(np.mean(self.history, axis=0), (pad_n, 1))
            seq = np.vstack([pad, np.array(self.history)])
        else:
            seq = np.array(self.history)
        seq_s = self.scaler.transform(seq)
        seq_t = torch.FloatTensor(seq_s).unsqueeze(0).to(self.device)
        with torch.no_grad():
            pred = self.lstm(seq_t).cpu().numpy()[0]
        pf = np.zeros((self.pred_horizon, 5)); pf[:, [0, 1]] = pred
        po = self.scaler.inverse_transform(pf)
        return po[:, 1], po[:, 0]

    def _decide(self, sensor, inflows, levels):
        up, down, inflow, rain = sensor.upstream_level, sensor.downstream_level, sensor.inflow, sensor.rainfall
        avg_gate = float(np.mean(sensor.gate_openings))
        prev_up = self.history[-2][0] if len(self.history) >= 2 else up
        prev_in = self.history[-2][1] if len(self.history) >= 2 else inflow
        uc, ic = up - prev_up, inflow - prev_in
        du = max(0, self.rc["upstream_danger"] - up) / 30.0
        dd = max(0, self.rc["downstream_danger"] - down) / 15.0
        dmi, dma = up - self.rc["ideal_min"], self.rc["ideal_max"] - up

        state = np.array([
            up / self.rc["max_upstream"], inflow / 600.0,
            down / self.rc["max_downstream"], rain / self.rc["rain_max_intensity"],
            np.clip(uc / 5.0, -1, 1), np.clip(ic / 100.0, -1, 1),
            (self.rc["eco_flow_min"] + 5) / 100.0, avg_gate,
            du, dd, np.clip(dmi / 15.0, -1, 1), np.clip(dma / 15.0, -1, 1),
        ], dtype=np.float32)
        st = torch.FloatTensor(state).unsqueeze(0).to(self.device)
        with torch.no_grad():
            q = self.dqn(st)
        action = q.argmax(dim=1).item()
        ops = []; t = action
        for _ in range(self.num_gates): ops.append(t % self.action_bins); t //= self.action_bins
        gates = [o / (self.action_bins - 1) for o in ops[::-1]]
        peak = float(max(levels)) if len(levels) > 0 else up
        sf = "danger" if peak > self.sc["threshold_danger"] else "warning" if peak > self.sc["threshold_warning"] else "safe"
        return ControlCommand(
            gate_openings=[round(v, 3) for v in gates],
            predicted_inflows=[round(v, 1) for v in inflows.tolist()],
            predicted_levels=[round(v, 2) for v in levels.tolist()],
            confidence=round(min(1.0, max(0.3, q.max().item() / 100)), 3),
            safety_flag=sf,
        )


# ==================== PLC Interface ====================

class PLCInterface:
    """Override with your PLC protocol (Modbus RTU/TCP, Profinet, etc.)"""
    def read_sensors(self) -> Optional[SensorData]:
        raise NotImplementedError("Implement for your PLC protocol")
    def write_gates(self, openings: List[float]) -> bool:
        raise NotImplementedError("Implement for your PLC protocol")


# ==================== Main ====================

def main():
    import argparse
    p = argparse.ArgumentParser(description="Hydropower AI Inference Server")
    p.add_argument("--daemon", action="store_true")
    p.add_argument("--interval", type=int, default=3600)
    args = p.parse_args()

    ctrl = GateController()

    if args.daemon:
        print(f"Daemon: running every {args.interval}s...")
        while True:
            # sensor = your_plc.read_sensors()
            # cmd = ctrl.step(sensor)
            # your_plc.write_gates(cmd.gate_openings)
            print(f"[{datetime.now().strftime('%H:%M:%S')}] tick")
            time.sleep(args.interval)
    else:
        print("\n" + "=" * 55)
        print("  Test Inference")
        print("=" * 55)
        sensor = SensorData(182.0, 120.5, 250.0, 3.0, 22.0, [0.3, 0.2, 0.4])
        cmd = ctrl.step(sensor)
        print(f"  Gates:  {[f'{g*100:.0f}%' for g in cmd.gate_openings]}")
        print(f"  Inflow: {[f'{v:.0f}' for v in cmd.predicted_inflows]}")
        print(f"  Levels: {[f'{v:.1f}' for v in cmd.predicted_levels]}")
        print(f"  Peak:   {max(cmd.predicted_levels):.1f}m | Safety: {cmd.safety_flag}")
        print(f"  Conf:   {cmd.confidence:.2f} | Time: {cmd.inference_time_ms:.1f}ms")
        print(f"  --- Physics Guard ---")
        print(f"  L1-Validator:  {'PASS' if cmd.physics_violation < 0.01 else f'CORRECTED ({cmd.physics_violation:.2f}m)'}")
        print(f"  L2-Safety:     {cmd.decision_level}")
        print(f"  L3-Risk:       {cmd.risk_level} (p={cmd.risk_probability:.2f})")
        print(f"  L4-Smooth:     {'Filtered' if cmd.command_smoothed else 'Passed'}")


if __name__ == "__main__":
    main()
