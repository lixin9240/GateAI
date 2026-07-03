"""
数据持久化层 (MySQL)
提供数据库连接 + 结构化日志，支撑模型迭代与系统监控

Usage:
    from database import HydropowerDB, SensorRecord, DecisionRecord, AlertRecord
    db = HydropowerDB(host='localhost', user='hydropower', password='GYZ032411', db_name='hydropower_smart')
    db.insert_sensor(SensorRecord(...))
"""

import MySQLdb
import json
import os
from typing import List, Dict
from datetime import datetime, timedelta
from dataclasses import dataclass
from contextlib import contextmanager


# ==================== 数据模型 ====================

@dataclass
class SensorRecord:
    timestamp: str
    upstream_level: float
    downstream_level: float
    inflow: float
    rainfall: float
    temperature: float


@dataclass
class DecisionRecord:
    timestamp: str
    gate1_opening: float
    gate2_opening: float
    gate3_opening: float
    predicted_inflow_avg: float
    predicted_level_max: float
    confidence: float
    safety_flag: str
    inference_time_ms: float


@dataclass
class AlertRecord:
    timestamp: str
    alert_level: str
    alert_type: str
    message: str
    upstream_level: float
    downstream_level: float


@dataclass
class ModelMetrics:
    timestamp: str
    avg_inference_time_ms: float
    total_decisions: int
    safety_flag_danger_pct: float
    safety_flag_warning_pct: float
    avg_confidence: float


# ==================== 数据库管理 ====================

class HydropowerDB:
    """MySQL 数据库管理器"""

    def __init__(self, host="localhost", port=3306, user="root",
                 password="", db_name="hydropower_smart"):
        self.host = host
        self.port = port
        self.user = user
        self.password = password
        self.db_name = db_name
        self._ensure_database()
        self._init_tables()

    def _raw_connect(self):
        return MySQLdb.connect(
            host=self.host, port=self.port,
            user=self.user, passwd=self.password,
            charset='utf8mb4',
        )

    def _connect(self):
        return MySQLdb.connect(
            host=self.host, port=self.port,
            user=self.user, passwd=self.password,
            db=self.db_name, charset='utf8mb4',
        )

    @contextmanager
    def _get_conn(self):
        conn = self._connect()
        try:
            yield conn
            conn.commit()
        except Exception:
            conn.rollback()
            raise
        finally:
            conn.close()

    def _ensure_database(self):
        conn = self._raw_connect()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    f"CREATE DATABASE IF NOT EXISTS `{self.db_name}` "
                    "DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
                )
            conn.commit()
        finally:
            conn.close()

    def _init_tables(self):
        with self._get_conn() as conn:
            with conn.cursor() as cur:
                cur.execute("""
                    CREATE TABLE IF NOT EXISTS sensor_readings (
                        id BIGINT AUTO_INCREMENT PRIMARY KEY,
                        timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        upstream_level DOUBLE NOT NULL,
                        downstream_level DOUBLE NOT NULL,
                        inflow DOUBLE NOT NULL,
                        rainfall DOUBLE NOT NULL,
                        temperature DOUBLE NOT NULL,
                        INDEX idx_sensor_ts (timestamp)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                """)
                cur.execute("""
                    CREATE TABLE IF NOT EXISTS decision_logs (
                        id BIGINT AUTO_INCREMENT PRIMARY KEY,
                        timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        gate1_opening DOUBLE NOT NULL,
                        gate2_opening DOUBLE NOT NULL,
                        gate3_opening DOUBLE NOT NULL,
                        predicted_inflow_avg DOUBLE,
                        predicted_level_max DOUBLE,
                        confidence DOUBLE,
                        safety_flag VARCHAR(20) NOT NULL,
                        inference_time_ms DOUBLE NOT NULL,
                        INDEX idx_decision_ts (timestamp)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                """)
                cur.execute("""
                    CREATE TABLE IF NOT EXISTS alerts (
                        id BIGINT AUTO_INCREMENT PRIMARY KEY,
                        timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        alert_level VARCHAR(20) NOT NULL,
                        alert_type VARCHAR(50) NOT NULL,
                        message VARCHAR(500) NOT NULL,
                        upstream_level DOUBLE,
                        downstream_level DOUBLE,
                        acknowledged TINYINT DEFAULT 0,
                        INDEX idx_alerts_ts (timestamp)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                """)
                cur.execute("""
                    CREATE TABLE IF NOT EXISTS model_metrics (
                        id BIGINT AUTO_INCREMENT PRIMARY KEY,
                        timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        avg_inference_time_ms DOUBLE,
                        total_decisions BIGINT,
                        safety_flag_danger_pct DOUBLE,
                        safety_flag_warning_pct DOUBLE,
                        avg_confidence DOUBLE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                """)
                cur.execute("""
                    CREATE TABLE IF NOT EXISTS co2_statistics (
                        id BIGINT AUTO_INCREMENT PRIMARY KEY,
                        date DATE NOT NULL UNIQUE,
                        total_power_kwh DOUBLE NOT NULL,
                        co2_saved_kg DOUBLE NOT NULL,
                        cumulative_co2_kg DOUBLE NOT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                """)

    # ==================== 写入 ====================

    def insert_sensor(self, record: SensorRecord) -> int:
        with self._get_conn() as conn:
            with conn.cursor() as cur:
                cur.execute(
                    """INSERT INTO sensor_readings
                       (timestamp, upstream_level, downstream_level, inflow, rainfall, temperature)
                       VALUES (%s, %s, %s, %s, %s, %s)""",
                    (record.timestamp, record.upstream_level, record.downstream_level,
                     record.inflow, record.rainfall, record.temperature),
                )
            return cur.lastrowid

    def insert_decision(self, record: DecisionRecord) -> int:
        with self._get_conn() as conn:
            with conn.cursor() as cur:
                cur.execute(
                    """INSERT INTO decision_logs
                       (timestamp, gate1_opening, gate2_opening, gate3_opening,
                        predicted_inflow_avg, predicted_level_max, confidence,
                        safety_flag, inference_time_ms)
                       VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)""",
                    (record.timestamp, record.gate1_opening, record.gate2_opening,
                     record.gate3_opening, record.predicted_inflow_avg,
                     record.predicted_level_max, record.confidence,
                     record.safety_flag, record.inference_time_ms),
                )
            return cur.lastrowid

    def insert_alert(self, record: AlertRecord) -> int:
        with self._get_conn() as conn:
            with conn.cursor() as cur:
                cur.execute(
                    """INSERT INTO alerts
                       (timestamp, alert_level, alert_type, message, upstream_level, downstream_level)
                       VALUES (%s, %s, %s, %s, %s, %s)""",
                    (record.timestamp, record.alert_level, record.alert_type,
                     record.message, record.upstream_level, record.downstream_level),
                )
            return cur.lastrowid

    def insert_model_metrics(self, metrics: ModelMetrics):
        with self._get_conn() as conn:
            with conn.cursor() as cur:
                cur.execute(
                    """INSERT INTO model_metrics
                       (timestamp, avg_inference_time_ms, total_decisions,
                        safety_flag_danger_pct, safety_flag_warning_pct, avg_confidence)
                       VALUES (%s, %s, %s, %s, %s, %s)""",
                    (metrics.timestamp, metrics.avg_inference_time_ms,
                     metrics.total_decisions, metrics.safety_flag_danger_pct,
                     metrics.safety_flag_warning_pct, metrics.avg_confidence),
                )

    def upsert_co2(self, date: str, power_kwh: float):
        co2_kg = power_kwh * 0.8995
        with self._get_conn() as conn:
            with conn.cursor() as cur:
                cur.execute("SELECT COALESCE(MAX(cumulative_co2_kg), 0) FROM co2_statistics")
                cumulative = cur.fetchone()[0] + co2_kg
                cur.execute(
                    """INSERT INTO co2_statistics (date, total_power_kwh, co2_saved_kg, cumulative_co2_kg)
                       VALUES (%s, %s, %s, %s)
                       ON DUPLICATE KEY UPDATE total_power_kwh=VALUES(total_power_kwh),
                                               co2_saved_kg=VALUES(co2_saved_kg),
                                               cumulative_co2_kg=VALUES(cumulative_co2_kg)""",
                    (date, power_kwh, co2_kg, cumulative),
                )

    # ==================== 查询 ====================

    def _rows_to_dicts(self, cursor) -> List[Dict]:
        cols = [desc[0] for desc in cursor.description]
        return [dict(zip(cols, row)) for row in cursor.fetchall()]

    def get_recent_sensors(self, hours=24) -> List[Dict]:
        cutoff = (datetime.now() - timedelta(hours=hours)).strftime("%Y-%m-%d %H:%M:%S")
        with self._get_conn() as conn:
            with conn.cursor() as cur:
                cur.execute("SELECT * FROM sensor_readings WHERE timestamp >= %s ORDER BY timestamp DESC", (cutoff,))
                return self._rows_to_dicts(cur)

    def get_recent_decisions(self, hours=24) -> List[Dict]:
        cutoff = (datetime.now() - timedelta(hours=hours)).strftime("%Y-%m-%d %H:%M:%S")
        with self._get_conn() as conn:
            with conn.cursor() as cur:
                cur.execute("SELECT * FROM decision_logs WHERE timestamp >= %s ORDER BY timestamp DESC", (cutoff,))
                return self._rows_to_dicts(cur)

    def get_alert_history(self, hours=72) -> List[Dict]:
        cutoff = (datetime.now() - timedelta(hours=hours)).strftime("%Y-%m-%d %H:%M:%S")
        with self._get_conn() as conn:
            with conn.cursor() as cur:
                cur.execute("SELECT * FROM alerts WHERE timestamp >= %s ORDER BY timestamp DESC", (cutoff,))
                return self._rows_to_dicts(cur)

    def get_unacknowledged_alerts(self) -> List[Dict]:
        with self._get_conn() as conn:
            with conn.cursor() as cur:
                cur.execute("SELECT * FROM alerts WHERE acknowledged = 0 ORDER BY timestamp DESC")
                return self._rows_to_dicts(cur)

    def acknowledge_alert(self, alert_id: int):
        with self._get_conn() as conn:
            with conn.cursor() as cur:
                cur.execute("UPDATE alerts SET acknowledged = 1 WHERE id = %s", (alert_id,))

    def get_co2_summary(self) -> Dict:
        today = datetime.now().strftime("%Y-%m-%d")
        with self._get_conn() as conn:
            with conn.cursor() as cur:
                cur.execute("SELECT * FROM co2_statistics WHERE date = %s", (today,))
                today_row = cur.fetchone()
                cur.execute("SELECT SUM(co2_saved_kg), SUM(total_power_kwh) FROM co2_statistics")
                total = cur.fetchone()
        today_dict = None
        if today_row:
            cols = ['id', 'date', 'total_power_kwh', 'co2_saved_kg', 'cumulative_co2_kg']
            today_dict = dict(zip(cols, today_row))
        return {"today": today_dict, "total_co2_kg": total[0] or 0, "total_power_kwh": total[1] or 0}

    def compute_model_metrics(self, hours=24) -> ModelMetrics:
        cutoff = (datetime.now() - timedelta(hours=hours)).strftime("%Y-%m-%d %H:%M:%S")
        with self._get_conn() as conn:
            with conn.cursor() as cur:
                cur.execute(
                    """SELECT AVG(inference_time_ms), COUNT(*),
                              SUM(CASE WHEN safety_flag='danger' THEN 1 ELSE 0 END)*100.0/GREATEST(COUNT(*),1),
                              SUM(CASE WHEN safety_flag='warning' THEN 1 ELSE 0 END)*100.0/GREATEST(COUNT(*),1),
                              AVG(confidence)
                       FROM decision_logs WHERE timestamp >= %s""",
                    (cutoff,),
                )
                row = cur.fetchone()
        return ModelMetrics(
            timestamp=datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            avg_inference_time_ms=float(row[0] or 0),
            total_decisions=int(row[1] or 0),
            safety_flag_danger_pct=float(row[2] or 0),
            safety_flag_warning_pct=float(row[3] or 0),
            avg_confidence=float(row[4] or 0),
        )

    def cleanup_old_data(self, retention_days=365):
        cutoff = (datetime.now() - timedelta(days=retention_days)).strftime("%Y-%m-%d %H:%M:%S")
        with self._get_conn() as conn:
            with conn.cursor() as cur:
                for table in ["sensor_readings", "decision_logs", "alerts"]:
                    cur.execute(f"DELETE FROM `{table}` WHERE timestamp < %s", (cutoff,))
        print(f"Cleaned data before {cutoff}")
