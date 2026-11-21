// ============================================================
// 数字孪生 — TypeScript 类型定义
// 依据：《水电站阀门智能调度系统-详细需求报告》第四章
// ============================================================

// ---------- 仿真场景 ----------
export type SimulationScene = 'normal' | 'flood' | 'dry' | 'rainstorm' | 'custom'

// ---------- 仿真状态 ----------
export type SimulationStatus = 'idle' | 'running' | 'paused' | 'finished'

// ---------- 仿真倍速 ----------
export type SimulationSpeed = 1 | 2 | 5 | 10

// ---------- 模型类型 ----------
export type ModelType = 'LSTM' | 'DQN'

// ---------- 模型状态 ----------
export type ModelStatus = 'inactive' | 'active' | 'validating'

// ---------- 故障复盘状态 ----------
export type ReviewStatus = 'pending' | 'reviewed'

// ---------- 仿真参数 ----------
export interface SimulationParams {
  scene: SimulationScene
  initialLevel: number // 初始水位 m
  inflowRate: number // 入库流量 m³/s
  durationMin: number // 仿真时长 min
}

/** 启动仿真时的完整载荷（含倍速、阀门开度） */
export interface SimulationStartPayload extends SimulationParams {
  speed?: SimulationSpeed
  gateOpening?: number
  scenarioId?: number
  modelId?: number
  reservoirId?: number
}

/** 9.2 启动仿真返回 */
export interface SimulationStartResult {
  simulation_id: string
  status: string
  start_time?: string
  estimated_end_time?: string
  ws_endpoint?: string
}

/** 后端场景条目 */
export interface SimulationScenarioItem {
  id: number
  name: string
  type: string
  description?: string | null
  status: string
  model_id: number | null
  duration?: number
  speed?: number
  scenario_config?: Record<string, unknown> | null
  created_at?: string
  updated_at?: string
  /** 已被仿真使用的次数，>0 时后端通常不允许删除 */
  usage_count?: number
}

/** 创建/更新仿真场景请求体 */
export interface SimulationScenarioPayload {
  name: string
  type: string
  description?: string | null
  duration?: number
  speed?: number
  model_id?: number | null
  scenario_config?: Record<string, unknown> | null
  status?: string
}

/** WebSocket / Reverb 进度推送 */
export interface SimulationProgressMetrics {
  upstream_level?: number
  downstream_level?: number
  inflow_rate?: number
  outflow_rate?: number
  gate_opening?: number
  power_output?: number
}

export interface SimulationProgressPayload {
  simulationId?: string
  simulation_id?: string
  progress?: number
  status?: string
  metrics?: SimulationProgressMetrics
  anomalies?: unknown[]
  timestamp?: string
}

/** 9.4 仿真结果 */
export interface SimulationResultPoint {
  id: number
  timestamp: string
  values: SimulationProgressMetrics
}

export interface SimulationResultSummary {
  total_energy?: number
  anomaly_count?: number
  max_inflow_rate?: number
  total_discharge?: number
  max_gate_opening?: number
  max_outflow_rate?: number
  max_upstream_level?: number
  min_upstream_level?: number
  max_downstream_level?: number
}

export interface SimulationResultData {
  summary: SimulationResultSummary
  total: number
  points: SimulationResultPoint[]
}

// ---------- 仿真实时数据 ----------
export interface SimulationRealtimeData {
  status: SimulationStatus
  elapsedSec: number
  currentLevel: number
  currentDownstreamLevel: number
  currentFlow: number
  currentOpening: number
  historyLevels: Array<{ time: number; value: number }>
  historyFlows: Array<{ time: number; value: number }>
}

// ---------- 仿真摘要 ----------
export interface SimulationSummary {
  maxLevel: number
  minLevel: number
  totalDischarge: number
  estimatedPower: number
}

// ---------- 仿真运行记录 ----------
export interface SimulationRun {
  id: number
  scene: SimulationScene
  params: SimulationParams
  status: SimulationStatus
  summary: SimulationSummary | null
  createdAt: string
}

// ---------- AI 模型 ----------
export interface AiModel {
  id: number
  type: ModelType
  version: string
  filePath: string
  status: ModelStatus
  metrics: ModelMetrics | null
  remark: string | null
  createdAt: string
  activatedAt: string | null
}

// ---------- 模型评估指标 ----------
export interface ModelMetrics {
  mae?: number
  rmse?: number
  accuracy?: number
  overallScore?: number
  healthGrade?: 'S' | 'A' | 'B' | 'C' | 'D'
  [key: string]: number | string | undefined
}

// ---------- 训练配置 ----------
export interface TrainingConfig {
  epochs?: number
  learningRate?: number
  batchSize?: number
  dataSource?: 'history' | 'csv'
  csvFile?: string
}

// ---------- 训练任务 ----------
export interface TrainingTask {
  taskId: string
  modelId: number
  config: TrainingConfig
  progress: number // 0-100
  lossCurve: Array<{ epoch: number; loss: number }>
  status: 'running' | 'completed' | 'failed'
}

// ---------- 评估报告 ----------
export interface SimulationReport {
  id: number
  runId: number
  scene: SimulationScene
  params: SimulationParams
  summary: SimulationSummary
  content: string
  filePath: string | null
  createdAt: string
  operatorName: string
}

// ---------- 故障复盘 ----------
export interface FaultReview {
  id: number
  alarmId: number
  faultType: string
  impactScope: string
  reviewed: boolean
  timeline: FaultTimelineEvent[]
  conclusion: FaultConclusion | null
  status: ReviewStatus
  createdAt: string
}

// ---------- 复盘时间线事件 ----------
export interface FaultTimelineEvent {
  time: string
  event: string
}

// ---------- 复盘结论 ----------
export interface FaultConclusion {
  rootCause: string
  improvements: string
  responsibleDept: string
  reviewedBy: string
  reviewedAt: string
}

// ---------- 仿真控制命令 ----------
export interface SimulationControlCommand {
  action: 'start' | 'pause' | 'resume' | 'reset'
  params?: SimulationParams
}

// ---------- 预设场景配置 ----------
export interface ScenePreset {
  scene: SimulationScene
  label: string
  description: string
  defaultParams: SimulationParams
}
