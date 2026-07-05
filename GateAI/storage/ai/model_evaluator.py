"""
模型评判系统 (Model Evaluation System)
=====================================
三维评判体系：预测准确性 × 决策可靠性 × 物理合规性

综合评分 = 0.4 × Prediction_Score + 0.35 × Decision_Score + 0.25 × Compliance_Score

等级:
  S级(≥0.85): L3_AUTO    A级(≥0.70): L3_AUTO
  B级(≥0.55): L2_SUGGEST C级(≥0.40): L1_MANUAL
  D级(<0.40): 自动冻结, 回退至上一个 S/A 级版本

Usage:
    evaluator = ModelEvaluator(edge_node_id=1, reservoir_id=1, db=db)
    evaluator.record_inference(cmd, sensor)          # 每次推理后调用
    evaluator.record_actual(actual_level, actual_flow)  # 下周期回填
    score = evaluator.compute_overall()              # 综合评分
    if evaluator.should_alert(): ...                 # 是否触发告警
    evaluator.persist()                              # 写入数据库
"""

import json
import math
from collections import deque
from dataclasses import dataclass, field
from datetime import datetime
from typing import List, Optional, Tuple


# ─── 权重配置 ──────────────────────────────────────
WEIGHT_PREDICTION  = 0.40
WEIGHT_DECISION    = 0.35
WEIGHT_COMPLIANCE  = 0.25

# 评分阈值
GRADE_THRESHOLDS = {
    'S': 0.85,
    'A': 0.70,
    'B': 0.55,
    'C': 0.40,
    'D': 0.00,
}

# 连续 D 级才触发自动回退
CONSECUTIVE_D_THRESHOLD = 3


@dataclass
class MetricsBuffer:
    """内存滑动窗口缓冲区"""

    # ── 维度一：预测准确性 ──
    prediction_errors: deque = field(default_factory=lambda: deque(maxlen=24))
    flow_errors: deque = field(default_factory=lambda: deque(maxlen=24))
    physics_corrections: deque = field(default_factory=lambda: deque(maxlen=100))
    trend_matches: deque = field(default_factory=lambda: deque(maxlen=24))

    # ── 维度二：决策可靠性 ──
    safety_overrides: deque = field(default_factory=lambda: deque(maxlen=100))
    decision_levels: deque = field(default_factory=lambda: deque(maxlen=100))
    risk_levels: deque = field(default_factory=lambda: deque(maxlen=100))
    smooth_filters: deque = field(default_factory=lambda: deque(maxlen=100))

    # ── 维度三：物理合规性 ──
    physics_violations: deque = field(default_factory=lambda: deque(maxlen=100))
    gate_limit_touches: deque = field(default_factory=lambda: deque(maxlen=100))
    rate_limit_exceeds: deque = field(default_factory=lambda: deque(maxlen=100))


class ModelEvaluator:
    """
    模型健康度评分引擎
    每次推理后累积数据，每小时聚合计算一次评分
    """

    def __init__(self, edge_node_id: int, reservoir_id: int, db=None):
        self.edge_node_id = edge_node_id
        self.reservoir_id = reservoir_id
        self.db = db  # HydropowerDB instance (optional, for persist)
        self.buffer = MetricsBuffer()

        # 上一周期预测 (用于下一周期回填真实值)
        self._last_predicted_level: Optional[float] = None
        self._last_predicted_flow: Optional[float] = None
        self._last_trend_predicted: Optional[str] = None  # 'up' or 'down'

        # 评分历史（追踪连续 D 级次数）
        self._score_history: deque = deque(maxlen=24)
        self._consecutive_d_count: int = 0

        # 当前评分缓存
        self._current_scores: dict = {}

    # ─── 数据记录 ───────────────────────────────────

    def record_inference(self, cmd, sensor) -> None:
        """
        每次推理后调用，从 ControlCommand 中提取评判数据。
        cmd: ControlCommand 对象 (含 physics_validation 等字段)
        sensor: SensorData 对象
        """
        pv = getattr(cmd, 'physics_validation', {}) or {}

        # ── 维度一数据：物理校验 ──
        physics_passed = pv.get('passed', True)
        violation_magnitude = pv.get('violation_magnitude', 0.0)
        self.buffer.physics_corrections.append(not physics_passed)
        self.buffer.physics_violations.append(float(violation_magnitude))

        # ── 维度二数据：决策可靠性 ──
        safety_overridden = pv.get('safety_overridden', False)
        decision_level = str(pv.get('decision_level', getattr(cmd, 'decision_level', 'L3_AUTO')))
        risk_level = str(getattr(cmd, 'risk_level', pv.get('risk_level', 'safe')))
        smoothed = pv.get('command_smoothed', False)

        self.buffer.safety_overrides.append(safety_overridden)
        self.buffer.decision_levels.append(decision_level)
        self.buffer.risk_levels.append(risk_level)
        self.buffer.smooth_filters.append(smoothed)

        # ── 维度三数据：物理合规性 ──
        gate_limit_touched = pv.get('gate_limit_touched', False)
        rate_exceeded = pv.get('rate_exceeded', False)
        self.buffer.gate_limit_touches.append(gate_limit_touched)
        self.buffer.rate_limit_exceeds.append(rate_exceeded)

        # 保存当前预测，供下一周期 record_actual() 用
        self._last_predicted_level = getattr(cmd, 'predicted_levels', [None])[0] if hasattr(cmd, 'predicted_levels') else None
        self._last_predicted_flow = getattr(cmd, 'predicted_inflows', [None])[0] if hasattr(cmd, 'predicted_inflows') else None

        # 保存趋势方向
        if hasattr(cmd, 'predicted_levels') and cmd.predicted_levels and len(cmd.predicted_levels) >= 2:
            self._last_trend_predicted = 'up' if cmd.predicted_levels[-1] > cmd.predicted_levels[0] else 'down'

    def record_actual(self, actual_level: float, actual_flow: float) -> None:
        """
        下一周期用真实传感器值回填，计算预测误差和趋势准确率。
        """
        if self._last_predicted_level is not None:
            error = abs(self._last_predicted_level - actual_level)
            self.buffer.prediction_errors.append((self._last_predicted_level, actual_level))

        if self._last_predicted_flow is not None:
            ferror = abs(self._last_predicted_flow - actual_flow)
            self.buffer.flow_errors.append((self._last_predicted_flow, actual_flow))

        # 趋势方向匹配（简化：比较预测方向和实际变化方向）
        # 实际方向通过对比最近两次实际值判断
        if self._last_trend_predicted is not None and hasattr(self, '_prev_actual_level'):
            diff = actual_level - self._prev_actual_level
            actual_trend = 'up' if diff > 0 else 'down'
            self.buffer.trend_matches.append(self._last_trend_predicted == actual_trend)

        self._prev_actual_level = actual_level
        self._last_predicted_level = None
        self._last_predicted_flow = None
        self._last_trend_predicted = None

    # ─── 维度计算 ───────────────────────────────────

    def compute_prediction_score(self) -> float:
        """维度一：预测准确性 (0~1)"""
        weights = {'mae_level': 0.35, 'mae_flow': 0.25, 'correction_rate': 0.20, 'trend': 0.20}
        b = self.buffer

        # 水位 MAE 评分
        if b.prediction_errors:
            mae_level = sum(abs(p - a) for p, a in b.prediction_errors) / len(b.prediction_errors)
            mae_level_score = max(0.0, 1.0 - mae_level / 1.0)  # 1m 误差=0分
        else:
            mae_level_score = 1.0

        # 流量 MAE 评分
        if b.flow_errors:
            mae_flow = sum(abs(p - a) for p, a in b.flow_errors) / max(len(b.flow_errors), 1)
            mae_flow_score = max(0.0, 1.0 - mae_flow / 200.0)  # 200 m³/s 误差=0分
        else:
            mae_flow_score = 1.0

        # 物理修正率评分（修正越少越好）
        if b.physics_corrections:
            correction_rate = sum(1 for c in b.physics_corrections if c) / len(b.physics_corrections)
            correction_score = max(0.0, 1.0 - correction_rate * 5.0)  # 20%修正=0分
        else:
            correction_score = 1.0

        # 趋势准确率
        if b.trend_matches:
            trend_acc = sum(1 for t in b.trend_matches if t) / len(b.trend_matches)
        else:
            trend_acc = 1.0

        score = (
            weights['mae_level'] * mae_level_score +
            weights['mae_flow'] * mae_flow_score +
            weights['correction_rate'] * correction_score +
            weights['trend'] * trend_acc
        )
        return max(0.0, min(1.0, score))

    def compute_decision_score(self) -> float:
        """维度二：决策可靠性 (0~1)"""
        weights = {'safety': 0.30, 'autonomy': 0.30, 'risk_pass': 0.20, 'smooth': 0.20}
        b = self.buffer

        # 安全覆盖率（被覆盖越少越好）
        if b.safety_overrides:
            override_rate = sum(1 for o in b.safety_overrides if o) / len(b.safety_overrides)
            safety_score = max(0.0, 1.0 - override_rate * 3.0)
        else:
            safety_score = 1.0

        # 决策自主率（L3_AUTO 占比）
        if b.decision_levels:
            l3_rate = sum(1 for d in b.decision_levels if d == 'L3_AUTO') / len(b.decision_levels)
        else:
            l3_rate = 1.0

        # 风险通过率（safe 占比）
        if b.risk_levels:
            safe_rate = sum(1 for r in b.risk_levels if r == 'safe') / len(b.risk_levels)
        else:
            safe_rate = 1.0

        # 平滑过滤率（不被过滤越少越好）
        if b.smooth_filters:
            smooth_rate = sum(1 for s in b.smooth_filters if s) / len(b.smooth_filters)
            smooth_score = max(0.0, 1.0 - smooth_rate * 3.0)
        else:
            smooth_score = 1.0

        score = (
            weights['safety'] * safety_score +
            weights['autonomy'] * l3_rate +
            weights['risk_pass'] * safe_rate +
            weights['smooth'] * smooth_score
        )
        return max(0.0, min(1.0, score))

    def compute_compliance_score(self) -> float:
        """维度三：物理合规性 (0~1)"""
        weights = {'violation': 0.40, 'gate_limit': 0.30, 'rate_limit': 0.30}
        b = self.buffer

        # 平均物理偏差评分
        if b.physics_violations:
            avg_violation = sum(b.physics_violations) / len(b.physics_violations)
            violation_score = max(0.0, 1.0 - avg_violation / 2.0)  # 2m偏差=0分
        else:
            violation_score = 1.0

        # 限位触碰率
        if b.gate_limit_touches:
            touch_rate = sum(1 for t in b.gate_limit_touches if t) / len(b.gate_limit_touches)
            touch_score = max(0.0, 1.0 - touch_rate * 5.0)
        else:
            touch_score = 1.0

        # 变化率超限率
        if b.rate_limit_exceeds:
            exceed_rate = sum(1 for e in b.rate_limit_exceeds if e) / len(b.rate_limit_exceeds)
            exceed_score = max(0.0, 1.0 - exceed_rate * 5.0)
        else:
            exceed_score = 1.0

        score = (
            weights['violation'] * violation_score +
            weights['gate_limit'] * touch_score +
            weights['rate_limit'] * exceed_score
        )
        return max(0.0, min(1.0, score))

    def compute_overall(self) -> dict:
        """加权计算综合评分 + 映射等级"""
        p_score = self.compute_prediction_score()
        d_score = self.compute_decision_score()
        c_score = self.compute_compliance_score()

        overall = (
            WEIGHT_PREDICTION  * p_score +
            WEIGHT_DECISION    * d_score +
            WEIGHT_COMPLIANCE  * c_score
        )

        # 映射等级
        grade = 'D'
        for g, threshold in GRADE_THRESHOLDS.items():
            if overall >= threshold:
                grade = g
                break

        # 追踪连续 D 级
        if grade == 'D':
            self._consecutive_d_count += 1
        else:
            self._consecutive_d_count = 0

        self._score_history.append(overall)

        self._current_scores = {
            'prediction_score': round(p_score, 4),
            'decision_score':     round(d_score, 4),
            'compliance_score':   round(c_score, 4),
            'overall_score':      round(overall, 4),
            'health_grade':       grade,
            'consecutive_d_count': self._consecutive_d_count,
            'computed_at':        datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
        }
        return self._current_scores

    def should_alert(self) -> Tuple[bool, str]:
        """判断是否触发降级告警"""
        scores = self._current_scores or self.compute_overall()
        grade = scores['health_grade']

        if self._consecutive_d_count >= CONSECUTIVE_D_THRESHOLD:
            return True, f"连续 {self._consecutive_d_count} 次 D 级评分, 建议自动回退模型"

        if grade == 'C':
            return True, "模型健康度降至 C 级, 强制切换至 L1_MANUAL"

        if grade == 'D':
            return True, "模型健康度降至 D 级"

        return False, ""

    def to_dict(self) -> dict:
        """序列化为字典供 API 返回"""
        scores = self._current_scores or self.compute_overall()
        b = self.buffer

        return {
            'edge_node_id':      self.edge_node_id,
            'reservoir_id':      self.reservoir_id,
            'overall_score':     scores['overall_score'],
            'health_grade':      scores['health_grade'],
            'prediction_score': {
                'score':         scores['prediction_score'],
                'mae_level_m':   round(sum(abs(p - a) for p, a in b.prediction_errors) / max(len(b.prediction_errors), 1), 4) if b.prediction_errors else 0,
                'mae_flow_m3s':  round(sum(abs(p - a) for p, a in b.flow_errors) / max(len(b.flow_errors), 1), 2) if b.flow_errors else 0,
                'correction_rate': round(sum(1 for c in b.physics_corrections if c) / max(len(b.physics_corrections), 1), 4) if b.physics_corrections else 0,
                'trend_accuracy': round(sum(1 for t in b.trend_matches if t) / max(len(b.trend_matches), 1), 4) if b.trend_matches else 1.0,
            },
            'decision_score': {
                'score':            scores['decision_score'],
                'safety_override_rate': round(sum(1 for o in b.safety_overrides if o) / max(len(b.safety_overrides), 1), 4) if b.safety_overrides else 0,
                'l3_auto_rate':     round(sum(1 for d in b.decision_levels if d == 'L3_AUTO') / max(len(b.decision_levels), 1), 4) if b.decision_levels else 1.0,
                'risk_pass_rate':   round(sum(1 for r in b.risk_levels if r == 'safe') / max(len(b.risk_levels), 1), 4) if b.risk_levels else 1.0,
                'smooth_filter_rate': round(sum(1 for s in b.smooth_filters if s) / max(len(b.smooth_filters), 1), 4) if b.smooth_filters else 0,
            },
            'compliance_score': {
                'score':               scores['compliance_score'],
                'avg_violation_m':      round(sum(b.physics_violations) / max(len(b.physics_violations), 1), 4) if b.physics_violations else 0,
                'gate_limit_touch_rate': round(sum(1 for t in b.gate_limit_touches if t) / max(len(b.gate_limit_touches), 1), 4) if b.gate_limit_touches else 0,
                'rate_limit_exceed_rate': round(sum(1 for e in b.rate_limit_exceeds if e) / max(len(b.rate_limit_exceeds), 1), 4) if b.rate_limit_exceeds else 0,
            },
            'consecutive_d_count': scores.get('consecutive_d_count', 0),
        }

    def persist(self) -> bool:
        """通过 database.py 写入 model_metrics 表"""
        if not self.db:
            return False

        scores = self.compute_overall()
        b = self.buffer

        try:
            water_level_mae = round(sum(abs(p - a) for p, a in b.prediction_errors) / max(len(b.prediction_errors), 1), 4) if b.prediction_errors else 0
            flow_mae = round(sum(abs(p - a) for p, a in b.flow_errors) / max(len(b.flow_errors), 1), 2) if b.flow_errors else 0
            correction_rate = round(sum(1 for c in b.physics_corrections if c) / max(len(b.physics_corrections), 1), 4) if b.physics_corrections else 0
            trend_acc = round(sum(1 for t in b.trend_matches if t) / max(len(b.trend_matches), 1), 4) if b.trend_matches else 1.0

            override_rate = round(sum(1 for o in b.safety_overrides if o) / max(len(b.safety_overrides), 1), 4) if b.safety_overrides else 0
            l3_rate = round(sum(1 for d in b.decision_levels if d == 'L3_AUTO') / max(len(b.decision_levels), 1), 4) if b.decision_levels else 1.0
            safe_rate = round(sum(1 for r in b.risk_levels if r == 'safe') / max(len(b.risk_levels), 1), 4) if b.risk_levels else 1.0
            smooth_rate = round(sum(1 for s in b.smooth_filters if s) / max(len(b.smooth_filters), 1), 4) if b.smooth_filters else 0

            avg_violation = round(sum(b.physics_violations) / max(len(b.physics_violations), 1), 4) if b.physics_violations else 0
            touch_rate = round(sum(1 for t in b.gate_limit_touches if t) / max(len(b.gate_limit_touches), 1), 4) if b.gate_limit_touches else 0
            rate_exceed = round(sum(1 for e in b.rate_limit_exceeds if e) / max(len(b.rate_limit_exceeds), 1), 4) if b.rate_limit_exceeds else 0

            decision_dist = {'L3_AUTO': 0, 'L2_SUGGEST': 0, 'L1_MANUAL': 0, 'OVERRIDE': 0}
            if b.decision_levels:
                for d in b.decision_levels:
                    decision_dist[d] = decision_dist.get(d, 0) + 1
                total = len(b.decision_levels)
                decision_dist = {k: round(v / total * 100, 1) for k, v in decision_dist.items()}

            self.db.insert_model_metrics_detail(
                edge_node_id=self.edge_node_id,
                reservoir_id=self.reservoir_id,
                metric_time=datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                water_level_mae_24h=water_level_mae,
                flow_mae_24h=flow_mae,
                physics_correction_rate=correction_rate,
                trend_accuracy=trend_acc,
                prediction_score=scores['prediction_score'],
                safety_override_rate=override_rate,
                decision_level_dist=json.dumps(decision_dist),
                shadow_risk_pass_rate=safe_rate,
                smooth_filter_rate=smooth_rate,
                decision_score=scores['decision_score'],
                avg_physics_violation=avg_violation,
                gate_limit_touch_rate=touch_rate,
                rate_limit_exceed_rate=rate_exceed,
                compliance_score=scores['compliance_score'],
                overall_score=scores['overall_score'],
                health_grade=scores['health_grade'],
            )
            return True
        except Exception as e:
            print(f"[ModelEvaluator] persist failed: {e}")
            return False
