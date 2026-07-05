"""
数据漂移检测器 (Drift Detector)
===============================
检测输入数据分布漂移, 判断模型是否需要重训练或降级。

触发规则:
  - drift_score > 0.3 → warning: 建议重训练
  - drift_score > 0.6 → critical: 自动降级至 L1_MANUAL
  - 每 24h 检测一次

Usage:
    detector = DriftDetector(reservoir_id=1, config_path='deploy_config.json', db=db)
    result = detector.detect(current_features)  # 计算漂移分数
    if result['drift_level'] == 'critical': ... # 触发告警
"""

import json
import os
import math
from collections import deque
from datetime import datetime
from typing import Dict, List, Optional, Tuple


class DriftDetector:
    """
    Wasserstein 距离漂移检测器 (简化实现: 使用统计矩差异替代)
    """

    def __init__(self, reservoir_id: int, config_path: str = None, db=None, window_size: int = 1440):
        """
        Parameters
        ----------
        reservoir_id : int
        config_path  : str - deploy_config.json 路径 (从中加载 baseline)
        db           : HydropowerDB instance
        window_size  : int - 滑动窗口大小 (默认1440 = 24h每分钟一个采样点)
        """
        self.reservoir_id = reservoir_id
        self.db = db
        self.window_size = window_size

        # 特征基线 (训练集统计)
        self._baseline: dict = self._load_baseline(config_path)

        # 运行时滑动窗口
        self._feature_windows: Dict[str, deque] = {
            key: deque(maxlen=window_size)
            for key in self._baseline.get('features', ['upstream_level', 'downstream_level', 'inflow', 'rainfall', 'temperature'])
        }

        # 检测结果
        self._last_result: Optional[dict] = None
        self._last_detect_time: Optional[datetime] = None

        # 连续 critical 计数
        self._consecutive_critical: int = 0

    def _load_baseline(self, config_path: str = None) -> dict:
        """从 deploy_config 或模型元数据中加载训练集特征分布基线"""
        if config_path is None:
            config_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'deploy_config.json')

        baseline = {
            'features': ['upstream_level', 'downstream_level', 'inflow', 'rainfall', 'temperature'],
            'stats': {},
        }

        if os.path.exists(config_path):
            try:
                with open(config_path) as f:
                    cfg = json.load(f)

                # 从 reservoir 配置构造合理基线
                rc = cfg.get('reservoir', {})
                baseline['stats'] = {
                    'upstream_level': {
                        'mean': (rc.get('max_upstream', 195) + rc.get('min_upstream', 165)) / 2,
                        'std':  (rc.get('max_upstream', 195) - rc.get('min_upstream', 165)) / 4,
                    },
                    'downstream_level': {
                        'mean': (rc.get('max_downstream', 130) + rc.get('min_downstream', 115)) / 2,
                        'std':  (rc.get('max_downstream', 130) - rc.get('min_downstream', 115)) / 4,
                    },
                    'inflow': {
                        'mean': 300,
                        'std':  150,
                    },
                    'rainfall': {
                        'mean': 5,
                        'std':  5,
                    },
                    'temperature': {
                        'mean': 20,
                        'std':  5,
                    },
                }
            except Exception as e:
                print(f"[DriftDetector] Failed to load baseline from config: {e}")

        return baseline

    def feed(self, features: dict) -> None:
        """录入当前特征值到滑动窗口"""
        for key in self._feature_windows:
            if key in features:
                self._feature_windows[key].append(float(features[key]))

    def detect(self, deduplicate: bool = True) -> dict:
        """
        每隔 24h 检测一次 (deduplicate=True 时跳过未到时间的调用)

        Returns:
            {
                'drift_score': float,        # 0~1, 越大漂移越严重
                'drift_level': str,          # 'normal' / 'warning' / 'critical'
                'affected_features': list,   # 哪些特征漂移了
                'recommendation': str,
            }
        """
        now = datetime.now()

        # 去重: 每 24h 只检测一次
        if deduplicate and self._last_detect_time:
            hours_since = (now - self._last_detect_time).total_seconds() / 3600
            if hours_since < 23:
                return self._last_result or {'drift_score': 0, 'drift_level': 'normal', 'affected_features': []}

        self._last_detect_time = now

        # 逐特征计算漂移分数
        feature_drifts = {}
        for key in self._feature_windows:
            if not self._feature_windows[key] or len(self._feature_windows[key]) < 10:
                feature_drifts[key] = 0.0
                continue

            baseline = self._baseline.get('stats', {}).get(key, {'mean': 0, 'std': 1})
            if baseline.get('std', 1) == 0:
                baseline['std'] = 1

            # 简化 Wasserstein: 用标准化后的均值差代替
            current_mean = sum(self._feature_windows[key]) / len(self._feature_windows[key])
            z_score = abs(current_mean - baseline['mean']) / baseline['std']
            feature_drifts[key] = min(1.0, z_score / 3.0)  # z=3 → drift=1.0

        # 综合漂移分数 (取加权平均)
        weights = {
            'upstream_level':   0.30,
            'downstream_level': 0.20,
            'inflow':           0.30,
            'rainfall':         0.10,
            'temperature':      0.10,
        }
        drift_score = sum(feature_drifts.get(k, 0) * weights.get(k, 0.1) for k in feature_drifts)
        drift_score = round(min(1.0, drift_score), 4)

        # 判断等级
        if drift_score > 0.6:
            drift_level = 'critical'
            self._consecutive_critical += 1
        elif drift_score > 0.3:
            drift_level = 'warning'
            self._consecutive_critical = 0
        else:
            drift_level = 'normal'
            self._consecutive_critical = 0

        recommendation = {
            'normal': '',
            'warning': f'数据分布偏移 (score={drift_score}), 建议关注模型预测准确性',
            'critical': f'严重数据漂移 (score={drift_score}), 建议重训练模型 (连续{self._consecutive_critical}次)',
        }[drift_level]

        # 找出受影响特征
        affected = [k for k, v in feature_drifts.items() if v > 0.3]

        self._last_result = {
            'drift_score':       drift_score,
            'drift_level':       drift_level,
            'affected_features': affected,
            'recommendation':    recommendation,
            'consecutive_critical': self._consecutive_critical,
            'detected_at':       now.strftime('%Y-%m-%d %H:%M:%S'),
            'feature_details':   feature_drifts,
        }

        # 持久化
        if self.db and drift_level != 'normal':
            try:
                self.db.insert_drift_log(
                    reservoir_id=self.reservoir_id,
                    drift_score=drift_score,
                    drift_level=drift_level,
                    affected_features=json.dumps(affected),
                    detected_at=now.strftime('%Y-%m-%d %H:%M:%S'),
                )
            except Exception as e:
                print(f"[DriftDetector] persist failed: {e}")

        return self._last_result

    def should_degrade(self) -> Tuple[bool, str]:
        """判断是否应该自动降级模型"""
        if self._last_result and self._last_result['drift_level'] == 'critical':
            if self._consecutive_critical >= 3:
                return True, f"连续 {self._consecutive_critical} 次严重漂移, 强制切换至 L1_MANUAL"
        return False, ""

    def get_baseline(self) -> dict:
        """返回当前基线"""
        return self._baseline
