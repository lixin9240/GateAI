"""
物理约束与安全防护层 (Physics-Informed Guard)
==============================================
在 LSTM 预测 + DQN 决策之上，增加四层物理判断:

  Layer 1: 物理校验器  →  验证 LSTM 预测是否符合水量平衡方程
  Layer 2: 安全约束器  →  用确定性规则兜底，一票否决危险动作
  Layer 3: 影子水位模型 →  物理快模拟，事前量化风险概率
  Layer 4: 指令平滑器  →  死区过滤 + 变化率限制，保护机械设备

核心公式:
  水量平衡: V_{t+1} = V_t + (Q_in - Q_out) * dt
  出库流量: Q_out = sum(gate_max_discharge[i] * gate_opening[i])
  影子水位: shadow_level = level + (inflow - outflow) * dt / area
  风险评估: risk = f(shadow_level, danger_threshold)

完全不修改 LSTM/DQN 模型，纯外部防护。
"""

import numpy as np
from typing import List, Tuple, Dict, Optional
from dataclasses import dataclass
from enum import Enum


# ==================== 数据模型 ====================

class RiskLevel(Enum):
    SAFE = "safe"
    WARNING = "warning"
    DANGER = "danger"
    CRITICAL = "critical"


class DecisionLevel(Enum):
    AUTO_L3 = "L3_AUTO"         # 高自信 + 低风险 → 自动执行
    SUGGEST_L2 = "L2_SUGGEST"    # 中自信 → 建议执行
    MANUAL_L1 = "L1_MANUAL"      # 低自信/高风险 → 人工介入
    OVERRIDE = "OVERRIDE"        # 安全规则覆盖


@dataclass
class PhysicsCheckResult:
    """物理校验结果"""
    passed: bool
    original_prediction: np.ndarray   # LSTM 原始预测
    corrected_prediction: np.ndarray  # 物理修正后的预测
    violation_magnitude: float        # 违反物理定律的程度
    reason: str


@dataclass
class RiskAssessment:
    """风险评估结果"""
    risk_level: RiskLevel
    risk_probability: float           # 0.0 ~ 1.0
    shadow_levels: List[float]        # 影子水位序列
    max_shadow_level: float
    reason: str


@dataclass
class SafetyResult:
    """安全检查结果"""
    passed: bool
    original_action: List[float]
    constrained_action: List[float]
    override_reason: str
    decision_level: DecisionLevel


@dataclass
class SmoothedCommand:
    """平滑后的指令"""
    openings: List[float]
    was_filtered: bool
    filter_reason: str


# ==================== Layer 1: 物理校验器 ====================

class PhysicsValidator:
    """
    物理校验器 — 验证 LSTM 预测值是否符合水量平衡方程

    核心公式:
      delta_V = (Q_in - Q_out) * dt * 3600   (m3)
      delta_h = delta_V / area                 (m)
      h_physical[t+1] = h[t] + delta_h
    """

    def __init__(
        self,
        reservoir_area: float = 15_000_000,  # m2 (水面面积)
        max_level_change_per_hour: float = 2.0,  # m/h (物理极限)
        tolerance: float = 0.5,  # 容差 (m)
    ):
        self.area = reservoir_area
        self.max_delta_h = max_level_change_per_hour
        self.tolerance = tolerance

    def validate_lstm_prediction(
        self,
        predicted_levels: np.ndarray,   # (6,) 预测 6h 水位
        predicted_inflows: np.ndarray,  # (6,) 预测 6h 流量
        current_level: float,
        current_inflow: float,
        gate_openings: List[float],
        gate_max_discharge: Tuple[float, ...] = (300, 200, 250),
    ) -> PhysicsCheckResult:
        """
        校验 LSTM 多步预测是否违反物理定律

        对每一步计算物理上合理的水位范围，如果 LSTM 预测超出范围则修正。
        """
        total_outflow = sum(g * d for g, d in zip(gate_openings, gate_max_discharge))
        dt = 3600  # 1 小时 = 3600 秒

        corrected_levels = predicted_levels.copy()
        violations = []

        prev_level = current_level
        prev_inflow = current_inflow

        for h in range(len(predicted_levels)):
            pred_level = predicted_levels[h]
            pred_inflow = predicted_inflows[h] if h < len(predicted_inflows) else current_inflow

            # 水量平衡: delta_h = (Q_in - Q_out) * dt / area
            net_flow = pred_inflow - total_outflow
            delta_volume = net_flow * dt
            physical_delta_h = delta_volume / self.area

            # 物理上合理的水位
            physical_level = prev_level + physical_delta_h

            # 检查 LSTM 预测是否偏离物理值太多
            deviation = abs(pred_level - physical_level)

            if deviation > self.tolerance:
                # 修正：用物理值 + 保留 LSTM 趋势方向
                lstm_trend = 1 if pred_level > prev_level else -1
                blend = 0.3  # 30% LSTM + 70% 物理
                corrected_levels[h] = blend * pred_level + (1 - blend) * physical_level
                violations.append(deviation)

            # 绝对物理极限检查
            if abs(pred_level - prev_level) > self.max_delta_h:
                corrected_levels[h] = prev_level + self.max_delta_h * (1 if pred_level > prev_level else -1)
                violations.append(abs(pred_level - prev_level))

            prev_level = corrected_levels[h]
            prev_inflow = pred_inflow

        max_violation = max(violations) if violations else 0.0
        passed = max_violation <= self.tolerance

        return PhysicsCheckResult(
            passed=passed,
            original_prediction=predicted_levels.copy(),
            corrected_prediction=corrected_levels,
            violation_magnitude=max_violation,
            reason=f"物理校验: {'通过' if passed else f'修正 {len(violations)} 步预测, 最大偏差 {max_violation:.2f}m'}",
        )

    def validate_single_step(
        self,
        predicted_level: float,
        current_level: float,
        inflow: float,
        total_outflow: float,
    ) -> float:
        """单步验证，返回物理上合理的水位"""
        net_flow = inflow - total_outflow
        delta_h = net_flow * 3600 / self.area
        physical = current_level + delta_h

        if abs(predicted_level - physical) > self.tolerance:
            return 0.3 * predicted_level + 0.7 * physical
        return predicted_level


# ==================== Layer 2: 安全约束器 ====================

class SafetyGuard:
    """
    安全动作约束 — 用确定性的物理/安全规则兜底

    规则优先级 (从高到低):
      1. 防洪: 水位 > 紧急线 → 强制全开
      2. 生态: 下游流量不足 → 禁止关闸
      3. 物理: 闸门已达限位 → 禁止无效操作
      4. 死水位: 水位 < 死水位 → 强制保水
    """

    def __init__(
        self,
        upstream_danger: float = 190.0,
        upstream_emergency: float = 193.0,
        upstream_min: float = 165.0,
        downstream_danger: float = 128.0,
        eco_flow_min: float = 20.0,
    ):
        self.upstream_danger = upstream_danger
        self.upstream_emergency = upstream_emergency
        self.upstream_min = upstream_min
        self.downstream_danger = downstream_danger
        self.eco_flow_min = eco_flow_min

    def check(
        self,
        state: Dict,
        dqn_action: List[float],  # [gate1, gate2, gate3] 0~1
    ) -> SafetyResult:
        """
        对 DQN 动作进行安全校验

        Parameters
        ----------
        state : dict 包含:
            upstream_level, downstream_level, inflow,
            downstream_flow (下游实际流量), gate_positions (当前开度)
        dqn_action : DQN 建议的闸门开度 [0~1, ...]
        """
        upstream = state.get("upstream_level", 180.0)
        downstream = state.get("downstream_level", 120.0)
        downstream_flow = state.get("downstream_flow", downstream)
        current_gates = state.get("gate_positions", [0.3, 0.2, 0.4])

        # ---- 规则 1: 防洪最高优先级 ----
        if upstream > self.upstream_emergency:
            return SafetyResult(
                passed=False,
                original_action=dqn_action,
                constrained_action=[1.0, 1.0, 1.0],
                override_reason=f"防洪预案: 水位 {upstream:.1f}m > 紧急线 {self.upstream_emergency}m, 强制全开",
                decision_level=DecisionLevel.OVERRIDE,
            )

        # ---- 规则 2: 死水位保护 ----
        if upstream < self.upstream_min + 2:
            # 接近死水位, 最小化出库
            min_gates = [max(0.0, g - 0.5) for g in dqn_action]
            return SafetyResult(
                passed=False,
                original_action=dqn_action,
                constrained_action=[max(0.0, g) for g in min_gates],
                override_reason=f"死水位保护: {upstream:.1f}m < {self.upstream_min + 2}m, 禁止增泄",
                decision_level=DecisionLevel.OVERRIDE,
            )

        # ---- 规则 3: 下游防洪 ----
        if downstream > self.downstream_danger:
            # 下游水位超限, 禁止增泄
            constrained = [min(g, c) for g, c in zip(dqn_action, current_gates)]
            return SafetyResult(
                passed=False,
                original_action=dqn_action,
                constrained_action=constrained,
                override_reason=f"下游防洪: 下游 {downstream:.1f}m > {self.downstream_danger}m, 禁止增泄",
                decision_level=DecisionLevel.OVERRIDE,
            )

        # ---- 规则 4: 生态流量保障 ----
        if downstream_flow < self.eco_flow_min:
            # 下游流量不足, 检查 DQN 是否要关闸
            total_change = sum(dqn_action) - sum(current_gates)
            if total_change < -0.05:  # 总体趋势是关闸
                return SafetyResult(
                    passed=False,
                    original_action=dqn_action,
                    constrained_action=current_gates,  # 维持现状
                    override_reason=f"生态流量: 下游 {downstream_flow:.1f} < {self.eco_flow_min} m3/s, 禁止关闸",
                    decision_level=DecisionLevel.OVERRIDE,
                )

        # ---- 规则 5: 闸门物理限位 ----
        constrained = list(dqn_action)
        modified = False
        for i, (action, current) in enumerate(zip(dqn_action, current_gates)):
            if action > 0.99 and current > 0.99:
                constrained[i] = 1.0
            elif action < 0.01 and current < 0.01:
                constrained[i] = 0.0

        # ---- 全部通过 ----
        return SafetyResult(
            passed=True,
            original_action=dqn_action,
            constrained_action=constrained,
            override_reason="安全校验通过",
            decision_level=DecisionLevel.AUTO_L3,
        )


# ==================== Layer 3: 影子水位模型 ====================

class ShadowWaterModel:
    """
    影子水位风险评估 — 用简化物理模型快速模拟决策后果

    公式:
      Q_out = sum(gate_max_discharge[i] * opening[i])
      delta_volume = (Q_in - Q_out) * dt
      shadow_level = current_level + delta_volume / area

    对多步前瞻计算影子水位序列, 评估越限风险。
    """

    def __init__(
        self,
        reservoir_area: float = 15_000_000,
        upstream_danger: float = 190.0,
        upstream_emergency: float = 193.0,
        downstream_danger: float = 128.0,
        lookahead_steps: int = 3,  # 前瞻步数
    ):
        self.area = reservoir_area
        self.upstream_danger = upstream_danger
        self.upstream_emergency = upstream_emergency
        self.downstream_danger = downstream_danger
        self.lookahead_steps = lookahead_steps

    def assess(
        self,
        current_level: float,
        current_downstream: float,
        inflow: float,
        gate_openings: List[float],
        gate_max_discharge: Tuple[float, ...] = (300, 200, 250),
        predicted_inflows: Optional[np.ndarray] = None,  # LSTM 预测 (可选)
        dt: float = 3600.0,  # 秒
    ) -> RiskAssessment:
        """
        计算执行 gate_openings 后的影子水位和风险

        Parameters
        ----------
        predicted_inflows : LSTM 预测的未来 n 步入库流量 (可选, 提高精度)
        """
        total_outflow = sum(g * d for g, d in zip(gate_openings, gate_max_discharge))
        shadow_levels = [current_level]
        shadow_downstream = current_downstream

        level = current_level
        ds = current_downstream

        for step in range(self.lookahead_steps):
            # 使用 LSTM 预测流量 (如果有) 否则假设恒定
            if predicted_inflows is not None and step < len(predicted_inflows):
                q_in = predicted_inflows[step]
            else:
                q_in = inflow

            # 上游水位更新
            net_flow = q_in - total_outflow
            delta_volume = net_flow * dt
            delta_h = delta_volume / self.area
            level += delta_h

            # 下游水位简化更新
            ds += 0.005 * (total_outflow - 150) + np.random.normal(0, 0.01)
            ds = np.clip(ds, 114, 131)

            shadow_levels.append(level)

        max_shadow = max(shadow_levels)

        # ---- 风险评估 ----
        if max_shadow >= self.upstream_emergency:
            risk_level = RiskLevel.CRITICAL
            risk_prob = 0.95 + min(0.05, (max_shadow - self.upstream_emergency) * 0.05)
            reason = f"影子水位 {max_shadow:.1f}m 超过紧急线 {self.upstream_emergency}m"
        elif max_shadow >= self.upstream_danger:
            risk_level = RiskLevel.DANGER
            margin = max_shadow - self.upstream_danger
            risk_prob = 0.5 + margin / (self.upstream_emergency - self.upstream_danger) * 0.45
            reason = f"影子水位 {max_shadow:.1f}m 超过危险线 {self.upstream_danger}m"
        elif max_shadow >= self.upstream_danger - 3:
            risk_level = RiskLevel.WARNING
            risk_prob = 0.1 + 0.4 * (max_shadow - (self.upstream_danger - 3)) / 3
            reason = f"影子水位 {max_shadow:.1f}m 接近警戒线"
        else:
            risk_level = RiskLevel.SAFE
            risk_prob = max(0.0, min(0.1, (max_shadow - 170) / (self.upstream_danger - 170) * 0.1))
            reason = f"影子水位 {max_shadow:.1f}m, 安全"

        return RiskAssessment(
            risk_level=risk_level,
            risk_probability=round(risk_prob, 4),
            shadow_levels=[round(s, 2) for s in shadow_levels],
            max_shadow_level=round(max_shadow, 2),
            reason=reason,
        )


# ==================== Layer 4: 指令平滑器 ====================

class CommandSmoother:
    """
    指令平滑化 — 死区过滤 + 变化率限制

    保护闸门电机，避免频繁微小调节和剧烈动作。
    """

    def __init__(
        self,
        deadband: float = 0.02,          # 死区 (开度百分比变化量 < 此值 → 忽略)
        max_rate_of_change: float = 0.10, # 最大变化率 (百分比/分钟)
        time_step_minutes: float = 60.0,  # 控制周期 (分钟)
    ):
        self.deadband = deadband
        self.max_rate = max_rate_of_change
        self.dt = time_step_minutes

    def smooth(
        self,
        target_openings: List[float],    # 目标开度 [0~1]
        current_openings: List[float],    # 当前开度 [0~1]
    ) -> SmoothedCommand:
        """应用死区和变化率限制"""
        result = []
        filtered = False
        reasons = []

        for i, (target, current) in enumerate(zip(target_openings, current_openings)):
            delta = target - current

            # 死区检查
            if abs(delta) < self.deadband:
                result.append(current)
                if abs(delta) > 0.001:
                    filtered = True
                    reasons.append(f"闸门{i+1}: 变化 {delta*100:.1f}% < 死区 {self.deadband*100:.1f}%, 忽略")
                continue

            # 变化率限制
            max_delta = self.max_rate * (self.dt / 60.0)  # 转换为小时步长
            if abs(delta) > max_delta:
                clipped = current + (max_delta if delta > 0 else -max_delta)
                result.append(clipped)
                filtered = True
                reasons.append(f"闸门{i+1}: 限制变化率 {delta*100:.1f}% → {max_delta*100:.1f}%")
            else:
                result.append(target)

        return SmoothedCommand(
            openings=[round(r, 4) for r in result],
            was_filtered=filtered,
            filter_reason="; ".join(reasons) if reasons else "无过滤",
        )


# ==================== 统一控制器: 物理 + AI 融合 ====================

class PhysicsInformedController:
    """
    物理信息增强控制器 — 在 AI 推理管道中加入四层物理防护

    Pipeline:
      Sensor → [LSTM 预测] → PhysicsValidator (Layer 1)
            → [DQN 决策]   → SafetyGuard     (Layer 2)
                           → ShadowWaterModel (Layer 3)
                           → CommandSmoother  (Layer 4)
                           → 最终指令输出
    """

    def __init__(
        self,
        reservoir_area: float = 15_000_000,
        upstream_danger: float = 190.0,
        upstream_emergency: float = 193.0,
        gate_max_discharge: Tuple[float, ...] = (300, 200, 250),
    ):
        self.validator = PhysicsValidator(
            reservoir_area=reservoir_area,
        )
        self.safety = SafetyGuard(
            upstream_danger=upstream_danger,
            upstream_emergency=upstream_emergency,
        )
        self.shadow = ShadowWaterModel(
            reservoir_area=reservoir_area,
            upstream_danger=upstream_danger,
            upstream_emergency=upstream_emergency,
        )
        self.smoother = CommandSmoother()
        self.gate_max_discharge = gate_max_discharge

    def validate_prediction(
        self,
        predicted_levels: np.ndarray,
        predicted_inflows: np.ndarray,
        current_level: float,
        current_inflow: float,
        gate_openings: List[float],
    ) -> PhysicsCheckResult:
        """Layer 1: 物理校验 LSTM 预测"""
        return self.validator.validate_lstm_prediction(
            predicted_levels=predicted_levels,
            predicted_inflows=predicted_inflows,
            current_level=current_level,
            current_inflow=current_inflow,
            gate_openings=gate_openings,
            gate_max_discharge=self.gate_max_discharge,
        )

    def constrain_action(
        self,
        state: Dict,
        dqn_action: List[float],
    ) -> SafetyResult:
        """Layer 2: 安全约束 DQN 动作"""
        return self.safety.check(state, dqn_action)

    def assess_risk(
        self,
        current_level: float,
        current_downstream: float,
        inflow: float,
        gate_openings: List[float],
        predicted_inflows: Optional[np.ndarray] = None,
    ) -> RiskAssessment:
        """Layer 3: 影子水位风险评估"""
        return self.shadow.assess(
            current_level=current_level,
            current_downstream=current_downstream,
            inflow=inflow,
            gate_openings=gate_openings,
            gate_max_discharge=self.gate_max_discharge,
            predicted_inflows=predicted_inflows,
        )

    def smooth_command(
        self,
        target_openings: List[float],
        current_openings: List[float],
    ) -> SmoothedCommand:
        """Layer 4: 指令平滑"""
        return self.smoother.smooth(target_openings, current_openings)

    def make_decision(
        self,
        dqn_confidence: float,
        risk: RiskAssessment,
        safety: SafetyResult,
    ) -> DecisionLevel:
        """
        双因子熔断决策

        L3 (自动): confidence >= 0.7 AND risk < 0.30
        L2 (建议): confidence >= 0.5 AND risk < 0.10
        L1 (人工): 其他情况
        """
        if safety.decision_level == DecisionLevel.OVERRIDE:
            return DecisionLevel.OVERRIDE

        if dqn_confidence >= 0.70 and risk.risk_probability < 0.30:
            return DecisionLevel.AUTO_L3
        elif dqn_confidence >= 0.50 and risk.risk_probability < 0.10:
            return DecisionLevel.SUGGEST_L2
        else:
            return DecisionLevel.MANUAL_L1
