"""
闸门互锁守卫 (Gate Interlock Guard)
==================================
Layer 2.5: 在 SafetyGuard 之后，对 DQN 输出的闸门开度做组合约束。
防止危险的操作组合（如溢洪道全开时发电闸也全开）。

4 条默认规则:
  1. 泄洪-发电互斥: 溢洪道 > 80% → 发电闸 ≤ 50%
  2. 下游冲击保护: 两闸门同时增开 > 30% → 第三个禁同向
  3. 对称性约束: 溢洪道与泄洪洞开度差 > 40% → 强制对齐
  4. 最小下泄保障: 三闸门总开度 < 5% → 禁止全关

Usage:
    guard = GateInterlockGuard(edge_node_id=1, cloud_api=cloud_api)
    result = guard.check(gate_openings, current_state)
    if result.triggered:
        gate_openings = result.constrained_openings
"""

import json
from dataclasses import dataclass, field
from typing import List, Optional


@dataclass
class InterlockResult:
    triggered: bool = False
    original_openings: List[float] = field(default_factory=lambda: [0.3, 0.2, 0.4])
    constrained_openings: List[float] = field(default_factory=lambda: [0.3, 0.2, 0.4])
    triggered_rules: List[str] = field(default_factory=list)
    override_reason: str = ""


class GateInterlockGuard:
    """
    闸门互锁守卫 (Layer 2.5)
    仅在非 OVERRIDE 情况下生效（防洪优先于一切互锁）
    """

    def __init__(self, edge_node_id: int = 0, cloud_api=None):
        self.edge_node_id = edge_node_id
        self.cloud_api = cloud_api

        # 默认规则（全局适用，reservoir_id=NULL）
        self._rules = self._default_rules()

    # ─── 默认规则 ───────────────────────────────

    @staticmethod
    def _default_rules() -> list:
        return [
            {
                'rule_code': 'spillway_intake_mutex',
                'rule_name': '泄洪-发电互斥',
                'enabled': True,
                'priority': 1,
                'trigger': {'spillway_opening_gt': 0.80},
                'constraint': {'intake_max': 0.50, 'action': 'clamp'},
            },
            {
                'rule_code': 'downstream_impact_protect',
                'rule_name': '下游冲击保护',
                'enabled': True,
                'priority': 2,
                'trigger': {'any_two_delta_gt': 0.30},
                'constraint': {'third_freeze': True, 'action': 'freeze'},
            },
            {
                'rule_code': 'symmetry_constraint',
                'rule_name': '对称性约束',
                'enabled': True,
                'priority': 3,
                'trigger': {'spillway_tunnel_diff_gt': 0.40},
                'constraint': {'max_diff': 0.40, 'action': 'align'},
            },
            {
                'rule_code': 'min_discharge_guarantee',
                'rule_name': '最小下泄保障',
                'enabled': True,
                'priority': 4,
                'trigger': {'total_opening_lt': 0.05},
                'constraint': {'min_total': 0.05, 'action': 'floor'},
            },
        ]

    # ─── 主入口 ─────────────────────────────────

    def check(self, gate_openings: List[float], current_state: dict = None,
              prev_openings: List[float] = None) -> InterlockResult:
        """
        逐条检查启用规则（按 priority 排序）

        Parameters:
            gate_openings:  DQN 原始输出 [g1, g2, g3]
            current_state:  {'upstream_level': ..., 'downstream_level': ...}
            prev_openings:  上一周期的闸门开度（用于计算变化量）

        Returns:
            InterlockResult
        """
        openings = list(gate_openings)
        prev = list(prev_openings) if prev_openings else [0.3, 0.2, 0.4]
        triggered = []

        # 按 priority 排序
        rules = sorted(self._rules, key=lambda r: r['priority'])

        for rule in rules:
            if not rule.get('enabled', True):
                continue

            method_name = f"_rule_{rule['rule_code']}"
            handler = getattr(self, method_name, None)
            if handler is None:
                continue

            result = handler(openings, prev, rule['trigger'], rule['constraint'])
            if result is not None:
                openings = list(result)  # 约束后的开度
                triggered.append(rule['rule_code'])

        return InterlockResult(
            triggered=len(triggered) > 0,
            original_openings=list(gate_openings),
            constrained_openings=openings,
            triggered_rules=triggered,
            override_reason=f"互锁触发: {', '.join(triggered)}" if triggered else "",
        )

    # ─── 规则 1: 泄洪-发电互斥 ──────────────────

    def _rule_spillway_intake_mutex(self, openings, prev, trigger, constraint) -> Optional[List[float]]:
        """溢洪道(gate1) > 80% → 发电闸(gate3) ≤ 50%"""
        threshold = trigger.get('spillway_opening_gt', 0.80)
        intake_max = constraint.get('intake_max', 0.50)

        if openings[0] > threshold and openings[2] > intake_max:
            new_opens = list(openings)
            new_opens[2] = intake_max
            self._log('spillway_intake_mutex', f'溢洪道{openings[0]*100:.0f}%>{threshold*100:.0f}%, 发电闸{openings[2]*100:.0f}→{intake_max*100:.0f}%')
            return new_opens
        return None

    # ─── 规则 2: 下游冲击保护 ──────────────────

    def _rule_downstream_impact_protect(self, openings, prev, trigger, constraint) -> Optional[List[float]]:
        """任两闸门同时增开 > 30% → 第三个禁止同向"""
        delta_threshold = trigger.get('any_two_delta_gt', 0.30)

        deltas = [abs(openings[i] - prev[i]) for i in range(3)]
        # 找出增开超过阈值的闸门
        increasing = [i for i in range(3) if openings[i] - prev[i] > delta_threshold]
        if len(increasing) >= 2:
            # 冻结第三个闸门
            third = [i for i in range(3) if i not in increasing][0]
            new_opens = list(openings)
            new_opens[third] = prev[third]
            self._log('downstream_impact_protect', f'闸{increasing}同时增开>{delta_threshold*100:.0f}%, 冻结闸{third}')
            return new_opens
        return None

    # ─── 规则 3: 对称性约束 ────────────────────

    def _rule_symmetry_constraint(self, openings, prev, trigger, constraint) -> Optional[List[float]]:
        """溢洪道(0)与泄洪洞(1)开度差 > 40% → 强制对齐至差值 ≤ 40%"""
        max_diff = constraint.get('max_diff', 0.40)

        diff = abs(openings[0] - openings[1])
        if diff > max_diff:
            new_opens = list(openings)
            mid = (openings[0] + openings[1]) / 2
            if openings[0] > openings[1]:
                new_opens[0] = min(1.0, mid + max_diff / 2)
                new_opens[1] = max(0.0, mid - max_diff / 2)
            else:
                new_opens[1] = min(1.0, mid + max_diff / 2)
                new_opens[0] = max(0.0, mid - max_diff / 2)
            self._log('symmetry_constraint', f'差{round(diff*100)}% > {round(max_diff*100)}%, 对齐至差{round(abs(new_opens[0]-new_opens[1])*100)}%')
            return new_opens
        return None

    # ─── 规则 4: 最小下泄保障 ──────────────────

    def _rule_min_discharge(self, openings, prev, trigger, constraint) -> Optional[List[float]]:
        """三闸门总开度 < 5% → 禁止全关，强制至少 5%"""
        min_total = constraint.get('min_total', 0.05)

        if sum(openings) < min_total:
            # 均分最小开度
            each = min_total / 3.0
            self._log('min_discharge_guarantee', f'总开度{round(sum(openings)*100,1)}%<{min_total*100}%, 强制≥{min_total*100}%')
            return [each, each, each]
        return None

    # ─── 辅助 ───────────────────────────────────

    def _log(self, rule_code: str, message: str):
        """记录触发事件（本地 + 云端）"""
        print(f"[Interlock] {rule_code}: {message}")

    def reload_rules(self, remote_rules: list = None):
        """热加载规则（从云端或本地覆盖默认规则）
        remote_rules: [{rule_code, enabled, priority, trigger, constraint}, ...]
        """
        if remote_rules:
            # 合并远程规则（远程优先覆盖同名默认规则）
            rule_map = {r['rule_code']: r for r in self._rules}
            for rule in remote_rules:
                rule_map[rule['rule_code']] = rule
            self._rules = sorted(rule_map.values(), key=lambda r: r.get('priority', 99))
            print(f"[Interlock] 规则已更新: {len(self._rules)} 条")

    def get_rules(self) -> list:
        """返回当前规则列表"""
        return self._rules
