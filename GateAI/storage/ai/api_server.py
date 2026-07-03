"""
Hydropower AI Inference — Cloud API Client
===========================================
边缘端主动调用云端 Laravel 接口（对齐总接口文档 11.x / 12.x）

这不是 HTTP 服务器。边缘端不需要开端口等人调。
边缘端自己采集、自己推理、自己上报云端。
"""

import os, json, time, uuid
import urllib.request
from datetime import datetime
from typing import Optional


class CloudAPI:
    """
    云端 API 客户端 — 边缘端调用云端接口

    对齐总接口文档:
      11.1 POST /api/edge/monitoring-data    上报监测数据
      11.2 POST /api/edge/dispatch-decisions 上报调度决策
      11.3 PUT  /api/edge/control-commands/{id}/feedback  上报执行回执
      11.4 POST /api/edge/alarms              上报告警
      12.1 GET  /api/edge/physics-config/{id} 拉取物理参数
    """

    def __init__(self, base_url: str = None, token: str = None):
        self.base_url = (base_url or os.environ.get("CLOUD_API_URL", "")).rstrip("/")
        self.token = token or os.environ.get("CLOUD_API_TOKEN", "")
        self.pending: list = []
        self.enabled = bool(self.base_url)

    def _request(self, method: str, endpoint: str, body: dict = None) -> Optional[dict]:
        if not self.enabled:
            return None
        url = f"{self.base_url}{endpoint}"
        data = json.dumps(body, ensure_ascii=False).encode() if body else None
        headers = {"Authorization": f"Bearer {self.token}"}
        if data:
            headers["Content-Type"] = "application/json"
        try:
            req = urllib.request.Request(url, data=data, headers=headers, method=method)
            resp = urllib.request.urlopen(req, timeout=5)
            return json.loads(resp.read())
        except Exception as e:
            self.pending.append({"method": method, "endpoint": endpoint, "body": body})
            return None

    def flush(self):
        while self.pending:
            item = self.pending[0]
            if self._request(item["method"], item["endpoint"], item["body"]):
                self.pending.pop(0)
            else:
                break

    # ---- 11.1 上报监测数据 ----
    def report_monitoring(self, reservoir_id: int, edge_node_id: int, records: list):
        return self._request("POST", "/api/edge/monitoring-data", {
            "reservoir_id": reservoir_id,
            "edge_node_id": edge_node_id,
            "data": records,
        })

    # ---- 11.2 上报调度决策 ----
    def report_decision(self, payload: dict):
        return self._request("POST", "/api/edge/dispatch-decisions", payload)

    # ---- 11.3 上报执行回执 ----
    def report_feedback(self, command_id: str, status: str, actual_opening: float,
                        duration_ms: int = 0, is_smoothed: bool = False):
        return self._request("PUT", f"/api/edge/control-commands/{command_id}/feedback", {
            "status": status,
            "executed_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            "actual_opening": actual_opening,
            "duration_ms": duration_ms,
            "is_smoothed": is_smoothed,
        })

    # ---- 11.4 上报告警 ----
    def report_alarm(self, reservoir_id: int, edge_node_id: int, equipment_id: int,
                     alarm_type: str, level: str, message: str,
                     metric_value: float, threshold_value: float, duration: int = 0):
        return self._request("POST", "/api/edge/alarms", {
            "reservoir_id": reservoir_id,
            "edge_node_id": edge_node_id,
            "equipment_id": equipment_id,
            "type": alarm_type,
            "level": level,
            "message": message,
            "metric_value": metric_value,
            "threshold_value": threshold_value,
            "duration": duration,
            "exceed_start": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        })

    # ---- 12.1 拉取物理参数 ----
    def fetch_physics_config(self, reservoir_id: int) -> Optional[dict]:
        return self._request("GET", f"/api/edge/physics-config/{reservoir_id}")


# 全局单例
cloud: Optional[CloudAPI] = None


def init_cloud(base_url: str = None, token: str = None):
    global cloud
    cloud = CloudAPI(base_url, token)
    return cloud
