// ============================================================
// 边缘节点管理 — TypeScript 类型定义
// 依据：《总接口文档》§6 边缘节点管理模块 + §11 边缘端上报 + §12 物理配置
// ============================================================

// ---------- 节点状态 ----------
export type EdgeNodeStatus = 'online' | 'offline' | 'fault'

// ---------- 边缘节点条目（§6.1 列表）----------
export interface EdgeNodeItem {
  id: number
  name: string
  code: string
  reservoir_id: number
  reservoir_name: string
  status: EdgeNodeStatus
  ip: string
  last_heartbeat: string
  cpu_usage: number
  memory_usage: number
  model_version: string
  autonomy_mode: number // 0/1 是否断网自治
}

// ---------- 边缘节点详情（§6.2）----------
export interface EdgeNodeDetail extends EdgeNodeItem {
  location?: string
  disk_usage?: number
  plc_status?: string
  cache_size?: number
  threshold_version?: string
  weight_version?: string
  physics_config_version?: string
  // 关联设备列表
  equipment_list?: EdgeNodeEquipment[]
}

// ---------- 节点关联设备 ----------
export interface EdgeNodeEquipment {
  id: number
  name: string
  code: string
  type: string
  status: string
}

// ---------- 注册边缘节点请求（§6.3）----------
export interface EdgeNodeCreateParams {
  name: string
  code: string
  reservoir_id: number
  location?: string
  ip?: string
}

// ---------- 心跳上报请求（§6.4）----------
export interface EdgeNodeHeartbeatParams {
  status: 'online' | 'fault'
  cpu_usage?: number
  memory_usage?: number
  disk_usage?: number
  plc_status?: string
  autonomy_mode?: number
  cache_size?: number
  model_version?: string
  threshold_version?: string
  weight_version?: string
  physics_config_version?: string
}

// ---------- 边缘端监测数据上报（§11.1）----------
export interface EdgeMonitoringDataPoint {
  timestamp: string
  upstream_level: number
  downstream_level: number
  water_head: number
  inflow_rate: number
  outflow_rate: number
  gate_opening: number
  power_output: number
  cumulative_energy?: number
}

export interface EdgeMonitoringReportParams {
  reservoir_id: number
  edge_node_id: number
  data: EdgeMonitoringDataPoint[]
}

// ---------- 边缘端调度决策上报（§11.2）----------
export interface EdgeDispatchDecisionParams {
  trace_id: string
  reservoir_id: number
  edge_node_id: number
  prediction_id: number
  decision_time: string
  decision_mode: 'L1' | 'L2' | 'L3'
  risk_rank: 1 | 2 | 3
  upstream_level: number
  downstream_level: number
  inflow_rate: number
  current_opening: number
  lstm_predictions: Record<string, { level: number; flow: number }>
  recommended_opening: number
  confidence: number
  factors: Array<{ name: string; value: number | string; direction: string; weight: number }>
  alternatives: Array<Record<string, unknown>>
  weights_used: { power: number; safety: number; ecology: number }
  reward_score?: number
  physics_validation?: Record<string, unknown>
}

// ---------- 边缘端执行回执上报（§11.3）----------
export interface EdgeCommandFeedbackParams {
  status: 'executed' | 'failed'
  executed_at: string
  actual_opening?: number
  duration_ms?: number
  actuator_current?: number
  is_smoothed?: boolean
  execution_result?: Record<string, unknown>
}

// ---------- 边缘端告警上报（§11.4）----------
export interface EdgeAlarmReportParams {
  reservoir_id: number
  edge_node_id: number
  equipment_id: number
  type: string // water_level / flow / gate / power / equipment / physics_violation / comm_loss
  level: 'urgent' | 'important' | 'normal'
  message: string
  threshold_id?: number
  metric_value: number
  threshold_value: number
  duration: number
  exceed_start: string
  trace_id?: string
}

// ---------- 边缘端互锁事件上报（§13.6）----------
export interface EdgeInterlockLogParams {
  reservoir_id: number
  rule_id: number
  decision_id?: number
  trigger_time?: string
  gate1_opening_before: number
  gate2_opening_before: number
  gate3_opening_before: number
  upstream_level: number
  downstream_level: number
  inflow_rate: number
  gate1_opening_after: number
  gate2_opening_after: number
  gate3_opening_after: number
  action_detail?: Record<string, unknown>
}

// ---------- 物理参数（§12.1 边缘端拉取）----------
export interface LevelAreaMapItem {
  water_level: number
  surface_area: number
  max_discharge: number
}

export interface PhysicsValidationConfig {
  enabled: boolean
  max_deviation_m: number
  confidence_penalty: number
}

export interface EdgePhysicsConfig {
  level_area_map: LevelAreaMapItem[]
  validation: PhysicsValidationConfig
  physics_guard?: {
    version_hash: string
    config: Record<string, unknown>
  }
  version: number
  fetched_at: string
}

// ---------- 配置同步状态 ----------
export type ConfigSyncStatus = 'synced' | 'stale' | 'offline'

export interface EdgeSyncStatus {
  edge_node_id: number
  edge_node_name: string
  model_version: string
  model_sync: ConfigSyncStatus
  threshold_version: string
  threshold_sync: ConfigSyncStatus
  weight_version: string
  weight_sync: ConfigSyncStatus
  physics_version: string
  physics_sync: ConfigSyncStatus
  last_sync_at: string | null
}

// ---------- 模型下发参数（§8.3.6）----------
export interface ModelDeployParams {
  edge_node_ids: number[]
  strategy?: 'immediate' | 'gradual' | 'scheduled'
}

export interface ModelDeployResult {
  deployment_id: number
  model_id: number
  model_version: string
  target_nodes: number
  status: string
  created_at: string
}
