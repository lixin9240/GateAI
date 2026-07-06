"""
配置同步客户端 (Config Sync Client)
==================================
从云端拉取最新物理防护配置，替换硬编码阈值。支持定时刷新、本地缓存、断网降级。

Usage:
    sync = ConfigSyncClient(edge_node_id=1, cloud_base_url="http://47.108.169.152:8089", cloud_token="xxx")
    config = sync.get_config()          # 获取当前配置
    sync.sync_from_cloud()              # 从云端拉取
    if sync.has_update('/api/edge/physics-config/1'):
        config = sync.get_config()
"""

import json
import os
import hashlib
import time
from datetime import datetime
from typing import Optional


class ConfigSyncClient:
    """
    从云端 GET /api/edge/physics-config/{edge_node_id} 拉取配置
    本地缓存到 config_cache.json，断网时降级使用
    """

    def __init__(self, edge_node_id: int, cloud_base_url: str = None,
                 cloud_token: str = None, config_version: str = None):
        self.edge_node_id = edge_node_id

        base = os.path.dirname(os.path.abspath(__file__))
        self.cache_path = os.path.join(base, 'config_cache.json')

        # 从 deploy_config.json 读取云端地址（如果没传）
        if not cloud_base_url:
            deploy_config = os.path.join(base, 'deploy_config.json')
            if os.path.exists(deploy_config):
                with open(deploy_config) as f:
                    cfg = json.load(f)
                cloud = cfg.get('cloud', {})
                cloud_base_url = cloud.get('api_url', '')
                cloud_token = cloud_token or ''

        self.cloud_url = (cloud_base_url or '').rstrip('/')
        self.cloud_token = cloud_token or ''
        self._version_hash: Optional[str] = None
        self._config: dict = {}
        self._last_sync: Optional[datetime] = None
        self._sync_interval_hours: float = 6.0
        self._config_version: str = config_version or '1.0.0'

        # 启动时加载本地缓存
        self._load_cache()

    # ─── 公开方法 ───────────────────────────────

    def get_config(self) -> dict:
        """返回当前配置（优先缓存）"""
        if not self._config:
            self._load_cache()
        return self._config or self._default_config()

    def sync_from_cloud(self) -> bool:
        """从云端拉取配置，返回是否有更新"""
        if not self.cloud_url:
            return False

        url = f"{self.cloud_url}/api/v1/edge/physics-config/{self.edge_node_id}"
        headers = {}
        if self.cloud_token:
            headers['Authorization'] = f'Bearer {self.cloud_token}'
        if self._version_hash:
            headers['If-None-Match'] = self._version_hash

        try:
            import urllib.request
            req = urllib.request.Request(url, headers=headers)
            with urllib.request.urlopen(req, timeout=10) as resp:
                if resp.status == 304:
                    print(f"[ConfigSync] 配置未变更 (hash={self._version_hash[:8]})")
                    self._last_sync = datetime.now()
                    return False

                data = json.loads(resp.read().decode('utf-8'))

                # 提取配置内容（接口返回格式可能是 {data: {...}} 或直接 {...}）
                if isinstance(data, dict) and 'data' in data:
                    config = data['data']
                else:
                    config = data

                new_hash = hashlib.md5(json.dumps(config, sort_keys=True).encode()).hexdigest()
                if new_hash == self._version_hash:
                    self._last_sync = datetime.now()
                    return False

                self._config = config
                self._version_hash = new_hash
                self._config_version = str(config.get('version', self._config_version))
                self._last_sync = datetime.now()
                self._save_cache()
                print(f"[ConfigSync] 配置已更新 -> v{self._config_version} (hash={new_hash[:8]})")
                return True

        except Exception as e:
            print(f"[ConfigSync] 拉取失败, 降级使用本地缓存: {e}")
            self._load_cache()  # 重新加载缓存
            return False

    def is_stale(self) -> bool:
        """判断缓存是否过期（超过 6 小时）"""
        if self._last_sync is None:
            return True
        hours_since = (datetime.now() - self._last_sync).total_seconds() / 3600
        return hours_since > self._sync_interval_hours

    def has_update(self, url: str) -> bool:
        """快捷方法：检查是否有新配置"""
        return self.sync_from_cloud()

    def get_thresholds(self) -> dict:
        """从配置中提取阈值（兼容旧代码从 deploy_config.json 读的格式）"""
        cfg = self.get_config()
        return {
            'upstream_danger':    float(cfg.get('upstream_danger',    190.0)),
            'upstream_emergency': float(cfg.get('upstream_emergency', 193.0)),
            'upstream_warning':   float(cfg.get('upstream_warning',   188.0)),
            'upstream_min':       float(cfg.get('upstream_min',       167.0)),
            'downstream_danger':  float(cfg.get('downstream_danger',  128.0)),
            'downstream_max':     float(cfg.get('downstream_max',     130.0)),
            'downstream_min':     float(cfg.get('downstream_min',     115.0)),
            'eco_flow_min':       float(cfg.get('eco_flow_min',        20.0)),
            'reservoir_area':     float(cfg.get('reservoir_area', 15000000.0)),
            'max_level_change_per_hour': float(cfg.get('max_level_change_per_hour', 2.0)),
            'ideal_min':          float(cfg.get('ideal_min', 178.0)),
            'ideal_max':          float(cfg.get('ideal_max', 188.0)),
            # 影子水位
            'shadow_lookahead_steps': int(cfg.get('shadow_lookahead_steps', 3)),
            'shadow_danger_offset':   float(cfg.get('shadow_danger_offset', 3.0)),
            # 指令平滑
            'deadband_percent':  float(cfg.get('deadband_percent',  0.02)),
            'max_rate_per_hour': float(cfg.get('max_rate_per_hour', 0.10)),
            # 熔断
            'fusion_l3_confidence': float(cfg.get('fusion_l3_confidence', 0.70)),
            'fusion_l3_risk':       float(cfg.get('fusion_l3_risk',       0.30)),
            'fusion_l2_confidence': float(cfg.get('fusion_l2_confidence', 0.50)),
            'fusion_l2_risk':       float(cfg.get('fusion_l2_risk',       0.10)),
            # 闸门
            'gate_max_discharge': cfg.get('gate_max_discharge', [300, 200, 250]),
        }

    def get_config_version(self) -> str:
        return self._config_version

    # ─── 内部方法 ───────────────────────────────

    def _load_cache(self):
        if not os.path.exists(self.cache_path):
            return
        try:
            with open(self.cache_path) as f:
                cache = json.load(f)
            self._config = cache.get('config', {})
            self._version_hash = cache.get('version_hash', None)
            last_sync = cache.get('last_sync')
            self._last_sync = datetime.fromisoformat(last_sync) if last_sync else None
        except Exception:
            pass

    def _save_cache(self):
        cache = {
            'version_hash': self._version_hash,
            'last_sync':    (self._last_sync or datetime.now()).isoformat(),
            'config':       self._config,
        }
        with open(self.cache_path, 'w') as f:
            json.dump(cache, f, indent=2)

    @staticmethod
    def _default_config() -> dict:
        """默认配置（fallback）"""
        return {
            'upstream_danger':    190.0,
            'upstream_emergency': 193.0,
            'upstream_warning':   188.0,
            'upstream_min':       167.0,
            'downstream_danger':  128.0,
            'downstream_max':     130.0,
            'downstream_min':     115.0,
            'eco_flow_min':        20.0,
            'reservoir_area': 15_000_000.0,
            'max_level_change_per_hour': 2.0,
            'ideal_min': 178.0,
            'ideal_max': 188.0,
            'shadow_lookahead_steps': 3,
            'shadow_danger_offset':   3.0,
            'deadband_percent':  0.02,
            'max_rate_per_hour': 0.10,
            'fusion_l3_confidence': 0.70,
            'fusion_l3_risk':       0.30,
            'fusion_l2_confidence': 0.50,
            'fusion_l2_risk':       0.10,
            'gate_max_discharge': [300, 200, 250],
        }
