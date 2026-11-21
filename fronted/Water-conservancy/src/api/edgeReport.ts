// ============================================================
// 边缘端数据上报 — API 接口层
// 依据：《总接口文档》§11 边缘端数据上报接口（4 接口）
// 认证方式：EdgeToken HMAC 认证（边缘端 JWT Token）
// ============================================================
import http from './request'
import type { ApiResponse } from '@/shared/types'
import type {
  EdgeMonitoringReportParams,
  EdgeDispatchDecisionParams,
  EdgeCommandFeedbackParams,
  EdgeAlarmReportParams,
  EdgeInterlockLogParams,
} from '@/types/edgeNode'

// Edge 路由基础路径（边缘端专用，认证走 EdgeToken）
const EDGE_BASE = '/edge'

// ── §11.1 上报监测数据 ──
// 边缘端批量写入 monitoring_data 表，单次最多 1000 条
export function reportMonitoringData(data: EdgeMonitoringReportParams) {
  return http.post<ApiResponse<{ received: number }>>(`${EDGE_BASE}/monitoring-data`, data)
}

// ── §11.2 上报调度决策结果 ──
// 边缘端写入 dispatch_decisions 表
export function reportDispatchDecision(data: EdgeDispatchDecisionParams) {
  return http.post<ApiResponse<{ decision_id: number }>>(`${EDGE_BASE}/dispatch-decisions`, data)
}

// ── §11.3 上报执行回执 ──
// 更新 control_commands 执行状态，写入 gate_actions 表
export function reportCommandFeedback(commandId: string, data: EdgeCommandFeedbackParams) {
  return http.put<ApiResponse<null>>(
    `${EDGE_BASE}/control-commands/${encodeURIComponent(commandId)}/feedback`,
    data,
  )
}

// ── §11.4 上报告警 ──
// 边缘端写入 alarms 表
export function reportAlarm(data: EdgeAlarmReportParams) {
  return http.post<ApiResponse<{ alarm_id: number }>>(`${EDGE_BASE}/alarms`, data)
}

// ── §13.6 边缘端上报互锁触发事件 ──
// 认证方式：EdgeToken HMAC-SHA256
export function reportInterlockLog(data: EdgeInterlockLogParams) {
  return http.post<ApiResponse<null>>(
    `${import.meta.env.VITE_API_V1_PREFIX ?? '/v1'}/edge/gate-interlock-logs`,
    data,
  )
}
