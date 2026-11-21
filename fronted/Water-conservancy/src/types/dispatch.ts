// ============================================================
// 调度决策 — TypeScript 类型定义
// 字段严格对齐《总接口文档》§4.1-4.9
// ============================================================

// ---------- 决策模式 / 风险等级 ----------
export type DecisionMode = 'L1' | 'L2' | 'L3'
export type RiskRank = 1 | 2 | 3 // 1低 2中 3高
export type ExecutionStatus = 'pending' | 'executed' | 'rejected' | 'failed'
export type ActionType = 'open' | 'close' | 'maintain' | 'emergency'
export type ActionSource = 'dqn_auto' | 'manual' | 'emergency_override' | 'physics_corrected'

// ---------- 影响因素 ----------
export interface DecisionFactor {
  name: string
  value: number | string
  direction: 'up' | 'down' | 'neutral'
  weight: number
}

// ---------- 方案对比（alternatives 内部结构）----------
export interface DispatchPlan {
  id: string
  opening: number
  expectedLevel: number
  power: number
  safetyScore: number
  totalScore: number
  recommended: boolean
  /** 该方案对应的置信度 */
  confidence: number
  /** 该方案对关键因素的影响（切换方案时动画更新） */
  factorImpacts?: Record<
    string,
    { direction: 'up' | 'down' | 'neutral'; value: string; weight?: number }
  >
}

// ---------- 权重配置 ----------
export interface WeightsUsed {
  power_weight: number
  safety_weight: number
  ecology_weight: number
}

// ---------- 本次推理指标（physics_validation）----------
export interface PhysicsValidationContribution {
  prediction: number
  decision: number
  compliance: number
  overall: number
}

export interface PhysicsValidationInterlock {
  triggered: boolean
  rules: string[]
  reason: string
}

export type TrendDirection = 'pending' | 'match' | 'mismatch'
export type ShadowRiskLevel = 'safe' | 'warning' | 'danger' | 'critical'
export type InferenceDecisionLevel = 'L3_AUTO' | 'L2_SUGGEST' | 'L1_MANUAL' | 'OVERRIDE'

export interface PhysicsValidation {
  passed: boolean
  physics_violation_m: number
  physics_correction_steps?: number
  trend_direction?: TrendDirection
  risk_level: ShadowRiskLevel
  risk_probability: number
  shadow_levels?: number[]
  command_smoothed: boolean
  smooth_reason: string
  safety_overridden: boolean
  safety_override_reason: string
  decision_level: InferenceDecisionLevel
  gate_limit_touched: boolean
  rate_exceeded: boolean
  interlock?: PhysicsValidationInterlock
  contribution: PhysicsValidationContribution
}

// ---------- AI 决策详情（§4.2）----------
export interface DecisionDetail {
  id: number
  trace_id: string
  reservoir_id: number
  decision_time: string
  decision_mode: DecisionMode
  risk_rank: RiskRank
  upstream_level: number
  downstream_level: number
  inflow_rate: number
  current_opening: number
  lstm_predictions: Record<string, { level: number; flow: number }>
  recommended_opening: number
  confidence: number
  factors: DecisionFactor[]
  alternatives: DispatchPlan[] // 文档中为 alternatives
  weights_used: WeightsUsed
  reward_score: number
  physics_validation: PhysicsValidation | null
  execution_status: ExecutionStatus
  executed_opening: number | null
  actual_level_after: number | null
  actual_power_after: number | null
  created_at: string
}

// ---------- LSTM 预测数据（§4.1）----------
export interface PredictionPoint {
  time: string
  value: number
}

export interface PredictionData {
  id: number
  base_time: string
  predict_term: 1 | 2 | 3 // 1=1h 2=3h 3=6h
  water_seq: PredictionPoint[] // 水位时序
  flow_seq: PredictionPoint[] // 流量时序
  predict_accuracy: number
  created_at: string
}

// ---------- 调度记录（决策列表 §4.3）----------
export interface DispatchRecordSnapshot {
  factors: DecisionFactor[]
  confidence: number
  recommended_opening: number
  plans: { id: string; opening: number; totalScore: number; recommended: boolean }[]
}

export interface DispatchRecord {
  id: number
  decision_time: string
  decision_mode: DecisionMode
  recommended_opening: number
  confidence: number
  risk_rank: RiskRank
  execution_status: ExecutionStatus
  physics_validation: PhysicsValidation | null
  /** 动作描述 */
  action?: string
  /** 操作人 */
  operator_name?: string
  /** 后端原始决策模式标签，如 L3_AUTO */
  decision_mode_label?: string
  /** 决策快照（展开查看） */
  snapshot?: DispatchRecordSnapshot
}

// ---------- 阀门动作历史（§4.6）----------
export interface GateAction {
  id: number
  equipment_id: number
  previous_opening: number
  target_opening: number
  actual_opening: number | null
  action_type: ActionType
  action_source: ActionSource
  duration_ms: number
  is_smoothed: number
  acted_at: string
  interlock_rule_id?: number | null
  interlock_rule_name?: string | null
}

// ---------- 指令全链路追踪（§4.5）----------
export type CommandStatus =
  | 'pending'
  | 'sent'
  | 'acknowledged'
  | 'verified'
  | 'executed'
  | 'failed'

export interface CommandTrace {
  id: number
  command_id: string
  trace_id: string
  decision_id: number | null
  gate_action_id: number | null
  edge_node_id: number
  operator_id: number | null
  command_type: string
  payload: Record<string, unknown>
  target_equipment: number | null
  target_opening: number
  status: CommandStatus
  sent_at: string | null
  acknowledged_at: string | null
  verified_at: string | null
  executed_at: string | null
  feedback_at: string | null
  full_delay_ms: number | null
  execution_result: Record<string, unknown> | null
  reject_reason: string | null
  is_emergency: boolean
  created_at: string
  updated_at: string
}

// ---------- 急停日志（§4.9）----------
export interface EmergencyStopLog {
  id: number
  trigger_user_id: number
  trigger_time: string
  edge_ack_time: string | null
  plc_shut_time: string | null
  recover_time: string | null
  stop_reason: string
  /** 关联指令 ID（后端可能为 number 或 string） */
  command_id?: string | number | null
  recover_user_id?: number | null
  decision_id?: number | null
}

// ---------- 当前运行状态（页面需要，综合多个接口）----------
export interface DispatchStatus {
  mode: 'auto' | 'manual'
  autoLevel: 1 | 2 | 3 // 前端本地维护的自动执行级别
  upstreamLevel: number
  downstreamLevel: number
  flowRate: number // 入库流量
  gateOpening: number
  lastDispatchAt: string | null
  isExecuting: boolean
  executingTarget: number | null
}

// ---------- 请求体类型 ----------

/** 人工下发指令（§4.4）*/
export interface ExecuteParams {
  reservoir_id: number
  decision_id?: number
  target_opening: number
  operate_note?: string
}

/** 急停（§4.7）*/
export interface EmergencyStopParams {
  reservoir_id: number
  stop_reason: string
}

// ---------- 物理防护配置（§2.5 — 调度/孪生/告警页展示）----------
export type PhysicsGuardSyncStatus = 'synced' | 'stale' | 'offline'

export interface PhysicsGuardSummary {
  reservoir_id: number
  reservoir_name: string
  config_version: string
  is_active: boolean
  upstream_emergency: number
  upstream_danger: number
  upstream_warning: number
  fusion_l3_confidence: number
  fusion_l3_risk: number
  last_sync_at: string | null
  sync_status: PhysicsGuardSyncStatus
}

export interface PhysicsGuardHistoryChange {
  field: string
  label: string
  before: string | number
  after: string | number
}

export interface PhysicsGuardHistoryItem {
  id: number
  config_version: string
  changed_at: string
  changed_by_name: string
  description: string
  is_active: boolean
  changes: PhysicsGuardHistoryChange[]
}
