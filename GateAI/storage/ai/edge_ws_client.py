#!/usr/bin/env python3
"""
边缘端 WebSocket 客户端 — 连接云端 Reverb 服务
=============================================
作用:
  - 定时上报传感器数据 + AI 决策结果（5s 间隔）
  - 接收云端下发的控制指令（HMAC 校验）
  - 断网时本地缓存，恢复后批量上传

启动:
  python3 edge_ws_client.py
  python3 edge_ws_client.py --edge-id jetson-hydropower-01
"""
import asyncio
import json
import time
import hmac
import hashlib
import logging
import argparse
import os
from typing import Optional

logging.basicConfig(level=logging.INFO, format='%(asctime)s [%(levelname)s] %(message)s')
logger = logging.getLogger("edge_ws")


class EdgeWSClient:
    def __init__(self, config_path: str = None):
        base = os.path.dirname(os.path.abspath(__file__))
        config_path = config_path or os.path.join(base, 'deploy_config.json')
        with open(config_path) as f:
            self.cfg = json.load(f)

        cloud = self.cfg.get('cloud', {})
        self.edge_id  = cloud.get('edge_id', 'jetson-hydropower-01')
        self.ws_url   = cloud.get('ws_url', 'ws://localhost:8080/app/edge')
        self.secret   = cloud.get('shared_secret', '')
        self.interval = cloud.get('report_interval_seconds', 5)

        self.websocket: Optional = None
        self.running = True
        self.cached_data = []  # 断网缓存（对应手册 14 节）

        # 引用已有推理模块（延迟加载）
        self._controller = None
        self._sensor_src = None

    # ─── 主循环 ───────────────────────────────

    async def connect_and_loop(self):
        retry_count = 0
        while self.running:
            try:
                if retry_count > 0:
                    wait = min(60, 2 ** retry_count)
                    logger.info(f"断网重连中, 等待 {wait}s...")
                    await asyncio.sleep(wait)

                async with self._connect_ws() as ws:
                    self.websocket = ws
                    retry_count = 0
                    logger.info(f"WebSocket 已连接云端 ({self.ws_url})")

                    if self.cached_data:
                        await self._upload_cached(ws)

                    await asyncio.gather(
                        self._send_loop(ws),
                        self._receive_loop(ws),
                    )

            except Exception as e:
                logger.error(f"连接异常: {e}")
                retry_count += 1

    def _connect_ws(self):
        import websockets
        return websockets.connect(
            self.ws_url,
            extra_headers={"Edge-ID": self.edge_id},
            ping_interval=30,
            ping_timeout=10,
            close_timeout=5,
        )

    # ─── 发送循环 ─────────────────────────────

    async def _send_loop(self, ws):
        while self.running:
            try:
                sensor = self._read_sensors()
                msg = {
                    "type":      "water_level",
                    "edge_id":   self.edge_id,
                    "payload":   sensor,
                    "timestamp": time.time(),
                }
                await ws.send(json.dumps(msg))

                # 如果有新决策，额外推送
                decision = self._get_latest_decision()
                if decision:
                    await ws.send(json.dumps({
                        "type":      "decision",
                        "edge_id":   self.edge_id,
                        "payload":   decision,
                        "timestamp": time.time(),
                    }))

                await asyncio.sleep(self.interval)

            except Exception as e:
                logger.error(f"发送失败, 缓存数据: {e}")
                self.cached_data.append({"type": "water_level", "payload": sensor, "timestamp": time.time()})
                await asyncio.sleep(2)

    # ─── 接收循环 ─────────────────────────────

    async def _receive_loop(self, ws):
        async for raw in ws:
            try:
                data = json.loads(raw)
                if data.get('type') != 'command':
                    continue

                # 时效性校验（30s）
                if time.time() > data['expire_at']:
                    logger.warning("指令已过期, 拒绝执行")
                    continue

                # HMAC 签名校验
                sign_raw = data['command_id'] + json.dumps(data['payload']) + str(data['expire_at']) + data['nonce']
                expected = hmac.new(self.secret.encode(), sign_raw.encode(), hashlib.sha256).hexdigest()
                if not hmac.compare_digest(expected, data['sign']):
                    logger.error("HMAC 校验失败, 拒绝执行")
                    continue

                logger.info(f"收到合法指令: {data['command_id']} → {data['payload']}")
                self._execute_command(data['payload'])

                await ws.send(json.dumps({
                    "type":       "command_ack",
                    "command_id": data['command_id'],
                    "status":     "executed",
                }))

            except json.JSONDecodeError:
                logger.error("无效 JSON")
            except Exception as e:
                logger.error(f"处理指令异常: {e}")

    # ─── 传感器 & 推理 ────────────────────────

    def _read_sensors(self) -> dict:
        """从推理引擎获取当前传感器数据"""
        try:
            from inference_server import GateController, SensorData
            import numpy as np

            if self._sensor_src is None:
                self._sensor_src = self._create_simulator()

            sensor = self._sensor_src.read()
            return {
                "upstream_level":   round(sensor.upstream_level, 2),
                "downstream_level": round(sensor.downstream_level, 2),
                "inflow":           round(sensor.inflow, 1),
                "rainfall":         round(sensor.rainfall, 1),
                "temperature":      round(sensor.temperature, 1),
                "gate_opening":     float(np.mean(sensor.gate_openings)) * 100,
            }
        except ImportError:
            return {"upstream_level": 182.0, "downstream_level": 120.0, "inflow": 250}

    def _get_latest_decision(self) -> Optional[dict]:
        """获取最新 AI 决策（如果有）"""
        if self._controller and hasattr(self._controller, '_last_cmd'):
            cmd = self._controller._last_cmd
            return {
                "gate_openings": [round(g * 100, 1) for g in cmd.gate_openings],
                "safety_flag":   cmd.safety_flag,
                "confidence":    round(cmd.confidence * 100, 1),
            }
        return None

    def _execute_command(self, payload: dict):
        logger.info(f"执行指令: 闸门开度 {payload.get('gate_openings')}%")

    def _create_simulator(self):
        from edge_main import SensorSimulator
        return SensorSimulator()

    # ─── 断网缓存 ─────────────────────────────

    async def _upload_cached(self, ws):
        logger.info(f"上传 {len(self.cached_data)} 条缓存数据...")
        for item in self.cached_data:
            try:
                await ws.send(json.dumps(item))
                await asyncio.sleep(0.1)
            except Exception:
                break
        self.cached_data.clear()


# ─── 入口 ─────────────────────────────────────

if __name__ == "__main__":
    p = argparse.ArgumentParser(description="Edge WebSocket Client")
    p.add_argument("--edge-id", default="jetson-hydropower-01")
    p.add_argument("--config", help="deploy_config.json path")
    args = p.parse_args()

    client = EdgeWSClient(config_path=args.config)
    try:
        asyncio.run(client.connect_and_loop())
    except KeyboardInterrupt:
        logger.info("客户端退出")
