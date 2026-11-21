// ============================================================
// Mock 数据仓库 — 向家坝水电站实时模拟（后端就绪后移除）
// ============================================================
import type {
  AlarmRecord,
  AlarmExceedLog,
  AlarmFilterParams,
  AlarmStatsResult,
  AlarmPushMessage,
} from '@/types/alarm'
import { ALARM_TYPE_MAP } from '@/constants/alarm'
import { filterAlarmsByKeyword } from './alarmAdapter'
import { fuzzyMatch } from '@/utils/fuzzyMatch'
import { createAlarmSeed, createExceedLogSeed } from './mock/alarmSeed'
import { gateaiSharedStore } from './gateaiSharedStore'
import type {
  DispatchStatus,
  DecisionDetail,
  PredictionData,
  DispatchRecord,
  PhysicsValidation,
  PhysicsGuardSummary,
  GateAction,
} from '@/types/dispatch'
import type {
  SimulationRealtimeData,
  SimulationParams,
  SimulationStartPayload,
  SimulationSpeed,
  AiModel,
  SimulationReport,
  FaultReview,
  TrainingTask,
} from '@/types/simulation'
import { XIANGJIABA_HYDRO } from '@/constants/xiangjiaba'
import { getScenePreset } from '@/constants/simulation'
import { faultReviewToSimulationParams } from './simulationAdapter'
import {
  createInitialTelemetry,
  stepHydrology,
  diurnalInflowOffset,
  type StationTelemetry,
} from '@/utils/xiangjiabaTelemetry'

const STATION = '向家坝水电站'
let tick = 0
const TICK_SEC = 3

/** 向家坝水文站实时遥测（调度 / 孪生共用） */
let stationLive: StationTelemetry = createInitialTelemetry()

function jitter(base: number, range: number) {
  return +(base + Math.sin(tick * 0.3) * range * 0.5 + (Math.random() - 0.5) * range * 0.3).toFixed(
    2,
  )
}

function pushHistory() {
  const t =
    simState.status === 'running' ? simState.elapsedSec : Math.floor(Date.now() / 1000) % 100000
  simState.historyLevels.push({ time: t, value: simState.currentLevel })
  simState.historyFlows.push({ time: t, value: simState.currentFlow })
  if (simState.historyLevels.length > 180) simState.historyLevels.shift()
  if (simState.historyFlows.length > 180) simState.historyFlows.shift()
}

function syncSimFromStation() {
  simState.currentLevel = stationLive.upstreamLevel
  simState.currentDownstreamLevel = stationLive.downstreamLevel
  simState.currentFlow = stationLive.inflowRate
  simState.currentOpening = stationLive.gateOpening
}

function syncDispatchFromStation() {
  dispatchStatus.upstreamLevel = stationLive.upstreamLevel
  dispatchStatus.downstreamLevel = stationLive.downstreamLevel
  dispatchStatus.flowRate = stationLive.inflowRate
  dispatchStatus.gateOpening = stationLive.gateOpening
}

function nowIso(offsetMin = 0) {
  return new Date(Date.now() - offsetMin * 60000).toISOString()
}

// ---------- 告警 ----------
const ALARM_MOCK_VERSION = '20260704-v2'
const ALARM_MOCK_KEY = 'wc_alarm_mock'

function initAlarmStore(): AlarmRecord[] {
  try {
    const ver = localStorage.getItem(`${ALARM_MOCK_KEY}_ver`)
    const raw = localStorage.getItem(ALARM_MOCK_KEY)
    if (ver === ALARM_MOCK_VERSION && raw) {
      const parsed = JSON.parse(raw) as AlarmRecord[]
      if (Array.isArray(parsed) && parsed.length > 0) return parsed
    }
  } catch {
    /* ignore */
  }
  return persistAlarmStore(createAlarmSeed(nowIso))
}

function persistAlarmStore(next: AlarmRecord[]) {
  localStorage.setItem(`${ALARM_MOCK_KEY}_ver`, ALARM_MOCK_VERSION)
  localStorage.setItem(ALARM_MOCK_KEY, JSON.stringify(next))
  return next
}

let alarmStore: AlarmRecord[] = initAlarmStore()

function ensureAlarmStore() {
  if (alarmStore.length === 0) {
    alarmStore = persistAlarmStore(createAlarmSeed(nowIso))
  }
}

const exceedLogs: AlarmExceedLog[] = createExceedLogSeed(nowIso)
let alarmPushSeq = 0

// ---------- 调度 ----------
type AutoLevel = 1 | 2 | 3

let dispatchStatus: DispatchStatus = {
  mode: (localStorage.getItem('dispatch_mode') as 'auto' | 'manual') || 'auto',
  autoLevel: 2 as AutoLevel,
  upstreamLevel: stationLive.upstreamLevel,
  downstreamLevel: stationLive.downstreamLevel,
  flowRate: stationLive.inflowRate,
  gateOpening: stationLive.gateOpening,
  lastDispatchAt: nowIso(30),
  isExecuting: false,
  executingTarget: null,
}

const MOCK_PHYSICS_VALIDATION: PhysicsValidation = {
  passed: true,
  physics_violation_m: 0.12,
  physics_correction_steps: 0,
  trend_direction: 'match',
  risk_level: 'safe',
  risk_probability: 0.08,
  shadow_levels: [380.2, 380.3, 380.5],
  command_smoothed: false,
  smooth_reason: '',
  safety_overridden: false,
  safety_override_reason: '',
  decision_level: 'L2_SUGGEST',
  gate_limit_touched: false,
  rate_exceeded: false,
  interlock: {
    triggered: false,
    rules: [],
    reason: '',
  },
  contribution: {
    prediction: 0.01,
    decision: 0.02,
    compliance: 0.01,
    overall: 0.04,
  },
}

const decisionBase: DecisionDetail = {
  id: 1,
  trace_id: 'trace-001',
  reservoir_id: 1,
  decision_time: nowIso(0),
  decision_mode: 'L2',
  risk_rank: 1,
  upstream_level: 380.65,
  downstream_level: 278.42,
  inflow_rate: 1920,
  current_opening: 45,
  lstm_predictions: {
    '1h': { level: 380.8, flow: 1950 },
    '3h': { level: 381.1, flow: 2100 },
    '6h': { level: 381.5, flow: 2300 },
  },
  recommended_opening: 52,
  confidence: 87,
  factors: [
    { name: '当前水位', value: '380.65 m', direction: 'up', weight: 0.22 },
    { name: 'LSTM预测水位', value: '381.10 m', direction: 'up', weight: 0.25 },
    { name: '实时流量', value: '1920 m³/s', direction: 'up', weight: 0.18 },
    { name: '阀门开度', value: '45%', direction: 'neutral', weight: 0.12 },
    { name: '防洪安全约束', value: '0.85 m', direction: 'down', weight: 0.13 },
    { name: '生态流量要求', value: '达标', direction: 'neutral', weight: 0.1 },
  ],
  alternatives: [
    {
      id: '推荐',
      opening: 52,
      expectedLevel: 380.2,
      power: 2850,
      safetyScore: 92,
      totalScore: 91,
      recommended: true,
      confidence: 87,
    },
    {
      id: '保守',
      opening: 38,
      expectedLevel: 380.8,
      power: 2100,
      safetyScore: 96,
      totalScore: 85,
      recommended: false,
      confidence: 72,
    },
    {
      id: '激进',
      opening: 65,
      expectedLevel: 379.6,
      power: 3200,
      safetyScore: 78,
      totalScore: 82,
      recommended: false,
      confidence: 58,
    },
  ],
  weights_used: { power_weight: 0.4, safety_weight: 0.35, ecology_weight: 0.25 },
  reward_score: 0.87,
  physics_validation: MOCK_PHYSICS_VALIDATION,
  execution_status: 'pending',
  executed_opening: null,
  actual_level_after: null,
  actual_power_after: null,
  created_at: nowIso(0),
}

function buildRecordSnapshot(opening: number, confidence: number): DispatchRecord['snapshot'] {
  return {
    factors: decisionBase.factors.slice(0, 5).map((f) => ({ ...f })),
    confidence,
    recommended_opening: opening,
    plans: decisionBase.alternatives.map((p) => ({
      id: p.id,
      opening: p.opening,
      totalScore: p.totalScore,
      recommended: p.recommended,
    })),
  }
}

const dispatchRecords: DispatchRecord[] = [
  {
    id: 1,
    decision_time: nowIso(15),
    decision_mode: 'L2',
    recommended_opening: 52,
    confidence: 87,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: MOCK_PHYSICS_VALIDATION,
    action: '采纳调度建议',
    operator_name: '张调度',
    snapshot: buildRecordSnapshot(52, 87),
  },
  {
    id: 2,
    decision_time: nowIso(45),
    decision_mode: 'L1',
    recommended_opening: 42,
    confidence: 72,
    risk_rank: 2,
    execution_status: 'executed',
    physics_validation: null,
    action: '手动下发开度',
    operator_name: '李运维',
    snapshot: buildRecordSnapshot(42, 72),
  },
  {
    id: 3,
    decision_time: nowIso(90),
    decision_mode: 'L3',
    recommended_opening: 48,
    confidence: 91,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: {
      ...MOCK_PHYSICS_VALIDATION,
      interlock: { triggered: false, rules: [], reason: '' },
    },
    action: '自动执行',
    operator_name: '系统',
    snapshot: buildRecordSnapshot(48, 91),
  },
  {
    id: 4,
    decision_time: nowIso(120),
    decision_mode: 'L2',
    recommended_opening: 50,
    confidence: 88,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: null,
    action: '采纳调度建议',
    operator_name: '张调度',
    snapshot: buildRecordSnapshot(50, 88),
  },
  {
    id: 5,
    decision_time: nowIso(180),
    decision_mode: 'L1',
    recommended_opening: 40,
    confidence: 65,
    risk_rank: 2,
    execution_status: 'failed',
    physics_validation: null,
    action: '手动下发开度',
    operator_name: '李运维',
    snapshot: buildRecordSnapshot(40, 65),
  },
  {
    id: 6,
    decision_time: nowIso(240),
    decision_mode: 'L2',
    recommended_opening: 55,
    confidence: 82,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: null,
    action: '采纳调度建议',
    operator_name: '张调度',
    snapshot: buildRecordSnapshot(55, 82),
  },
  {
    id: 7,
    decision_time: nowIso(360),
    decision_mode: 'L3',
    recommended_opening: 60,
    confidence: 93,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: null,
    action: '自动执行',
    operator_name: '系统',
    snapshot: buildRecordSnapshot(60, 93),
  },
  {
    id: 8,
    decision_time: nowIso(480),
    decision_mode: 'L1',
    recommended_opening: 38,
    confidence: 58,
    risk_rank: 3,
    execution_status: 'rejected',
    physics_validation: null,
    action: '忽略建议',
    operator_name: '张调度',
    snapshot: buildRecordSnapshot(38, 58),
  },
  {
    id: 9,
    decision_time: nowIso(600),
    decision_mode: 'L2',
    recommended_opening: 47,
    confidence: 84,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: null,
    action: '采纳调度建议',
    operator_name: '李运维',
    snapshot: buildRecordSnapshot(47, 84),
  },
  {
    id: 10,
    decision_time: nowIso(720),
    decision_mode: 'L2',
    recommended_opening: 44,
    confidence: 79,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: null,
    action: '手动下发开度',
    operator_name: '张调度',
    snapshot: buildRecordSnapshot(44, 79),
  },
  {
    id: 11,
    decision_time: nowIso(900),
    decision_mode: 'L1',
    recommended_opening: 36,
    confidence: 62,
    risk_rank: 2,
    execution_status: 'executed',
    physics_validation: null,
    action: '手动下发开度',
    operator_name: '李运维',
    snapshot: buildRecordSnapshot(36, 62),
  },
  {
    id: 12,
    decision_time: nowIso(1080),
    decision_mode: 'L3',
    recommended_opening: 58,
    confidence: 90,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: null,
    action: '自动执行',
    operator_name: '系统',
    snapshot: buildRecordSnapshot(58, 90),
  },
  {
    id: 13,
    decision_time: nowIso(1260),
    decision_mode: 'L2',
    recommended_opening: 51,
    confidence: 86,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: null,
    action: '采纳调度建议',
    operator_name: '张调度',
    snapshot: buildRecordSnapshot(51, 86),
  },
  {
    id: 14,
    decision_time: nowIso(1440),
    decision_mode: 'L1',
    recommended_opening: 43,
    confidence: 70,
    risk_rank: 2,
    execution_status: 'rejected',
    physics_validation: null,
    action: '忽略建议',
    operator_name: '李运维',
    snapshot: buildRecordSnapshot(43, 70),
  },
  {
    id: 15,
    decision_time: nowIso(1620),
    decision_mode: 'L2',
    recommended_opening: 49,
    confidence: 83,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: null,
    action: '采纳调度建议',
    operator_name: '张调度',
    snapshot: buildRecordSnapshot(49, 83),
  },
  {
    id: 16,
    decision_time: nowIso(1800),
    decision_mode: 'L2',
    recommended_opening: 46,
    confidence: 81,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: null,
    action: '手动下发开度',
    operator_name: '李运维',
    snapshot: buildRecordSnapshot(46, 81),
  },
  {
    id: 17,
    decision_time: nowIso(2100),
    decision_mode: 'L3',
    recommended_opening: 62,
    confidence: 89,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: null,
    action: '自动执行',
    operator_name: '系统',
    snapshot: buildRecordSnapshot(62, 89),
  },
  {
    id: 18,
    decision_time: nowIso(2400),
    decision_mode: 'L1',
    recommended_opening: 41,
    confidence: 68,
    risk_rank: 2,
    execution_status: 'failed',
    physics_validation: null,
    action: '手动下发开度',
    operator_name: '张调度',
    snapshot: buildRecordSnapshot(41, 68),
  },
  {
    id: 19,
    decision_time: nowIso(2700),
    decision_mode: 'L2',
    recommended_opening: 53,
    confidence: 85,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: null,
    action: '采纳调度建议',
    operator_name: '李运维',
    snapshot: buildRecordSnapshot(53, 85),
  },
  {
    id: 20,
    decision_time: nowIso(3000),
    decision_mode: 'L2',
    recommended_opening: 45,
    confidence: 80,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: null,
    action: '采纳调度建议',
    operator_name: '张调度',
    snapshot: buildRecordSnapshot(45, 80),
  },
  {
    id: 21,
    decision_time: nowIso(3300),
    decision_mode: 'L2',
    recommended_opening: 54,
    confidence: 86,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: null,
    action: '改派执行',
    operator_name: '张调度',
    snapshot: buildRecordSnapshot(54, 86),
  },
  {
    id: 22,
    decision_time: nowIso(3600),
    decision_mode: 'L1',
    recommended_opening: 39,
    confidence: 64,
    risk_rank: 2,
    execution_status: 'executed',
    physics_validation: null,
    action: '手动下发开度',
    operator_name: '李运维',
    snapshot: buildRecordSnapshot(39, 64),
  },
  {
    id: 23,
    decision_time: nowIso(3900),
    decision_mode: 'L3',
    recommended_opening: 57,
    confidence: 92,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: null,
    action: '自动执行',
    operator_name: '系统',
    snapshot: buildRecordSnapshot(57, 92),
  },
  {
    id: 24,
    decision_time: nowIso(4200),
    decision_mode: 'L2',
    recommended_opening: 48,
    confidence: 81,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: null,
    action: '采纳调度建议',
    operator_name: '李运维',
    snapshot: buildRecordSnapshot(48, 81),
  },
  {
    id: 25,
    decision_time: nowIso(4500),
    decision_mode: 'L2',
    recommended_opening: 52,
    confidence: 0,
    risk_rank: 2,
    execution_status: 'rejected',
    physics_validation: null,
    action: '取消执行',
    operator_name: '张调度',
    snapshot: buildRecordSnapshot(52, 0),
  },
  {
    id: 26,
    decision_time: nowIso(4800),
    decision_mode: 'L1',
    recommended_opening: 37,
    confidence: 60,
    risk_rank: 3,
    execution_status: 'failed',
    physics_validation: null,
    action: '手动下发开度',
    operator_name: '李运维',
    snapshot: buildRecordSnapshot(37, 60),
  },
  {
    id: 27,
    decision_time: nowIso(5100),
    decision_mode: 'L2',
    recommended_opening: 50,
    confidence: 84,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: null,
    action: '采纳调度建议',
    operator_name: '张调度',
    snapshot: buildRecordSnapshot(50, 84),
  },
  {
    id: 28,
    decision_time: nowIso(5400),
    decision_mode: 'L3',
    recommended_opening: 61,
    confidence: 88,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: null,
    action: '自动执行',
    operator_name: '系统',
    snapshot: buildRecordSnapshot(61, 88),
  },
  {
    id: 29,
    decision_time: nowIso(5700),
    decision_mode: 'L2',
    recommended_opening: 46,
    confidence: 78,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: null,
    action: '手动下发开度',
    operator_name: '李运维',
    snapshot: buildRecordSnapshot(46, 78),
  },
  {
    id: 30,
    decision_time: nowIso(6000),
    decision_mode: 'L1',
    recommended_opening: 35,
    confidence: 55,
    risk_rank: 3,
    execution_status: 'rejected',
    physics_validation: null,
    action: '忽略建议',
    operator_name: '张调度',
    snapshot: buildRecordSnapshot(35, 55),
  },
  {
    id: 31,
    decision_time: nowIso(6300),
    decision_mode: 'L2',
    recommended_opening: 51,
    confidence: 83,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: null,
    action: '采纳调度建议',
    operator_name: '张调度',
    snapshot: buildRecordSnapshot(51, 83),
  },
  {
    id: 32,
    decision_time: nowIso(6600),
    decision_mode: 'L2',
    recommended_opening: 47,
    confidence: 79,
    risk_rank: 1,
    execution_status: 'executed',
    physics_validation: null,
    action: '手动下发开度',
    operator_name: '李运维',
    snapshot: buildRecordSnapshot(47, 79),
  },
]

// ---------- 仿真 ----------
let simState: SimulationRealtimeData = {
  status: 'idle',
  elapsedSec: 0,
  currentLevel: stationLive.upstreamLevel,
  currentDownstreamLevel: stationLive.downstreamLevel,
  currentFlow: stationLive.inflowRate,
  currentOpening: stationLive.gateOpening,
  historyLevels: [],
  historyFlows: [],
}

// 预填近期水位曲线，进入页面即可见动态趋势
for (let i = 24; i >= 0; i--) {
  const lv = +(stationLive.upstreamLevel - i * 0.012 + Math.sin(i * 0.4) * 0.025).toFixed(2)
  simState.historyLevels.push({ time: i, value: lv })
  simState.historyFlows.push({ time: i, value: stationLive.inflowRate + Math.sin(i * 0.3) * 30 })
}

let simParams: SimulationParams = {
  scene: 'normal',
  initialLevel: XIANGJIABA_HYDRO.normalPoolLevel,
  inflowRate: XIANGJIABA_HYDRO.normalInflow,
  durationMin: 60,
}

let simSpeed: SimulationSpeed = 1

const models: AiModel[] = [
  {
    id: 1,
    type: 'LSTM',
    version: 'v2.1.0',
    filePath: '/models/lstm_v210.pt',
    status: 'active',
    metrics: { mae: 0.042, rmse: 0.068, accuracy: 94.2, overallScore: 0.88, healthGrade: 'A' },
    remark: '向家坝水位预测主模型',
    createdAt: nowIso(43200),
    activatedAt: nowIso(1440),
  },
  {
    id: 2,
    type: 'DQN',
    version: 'v2.3.1',
    filePath: '/models/dqn_v231.pt',
    status: 'active',
    metrics: { mae: 0.035, accuracy: 91.8, overallScore: 0.82, healthGrade: 'A' },
    remark: '阀门调度决策模型',
    createdAt: nowIso(21600),
    activatedAt: nowIso(720),
  },
  {
    id: 3,
    type: 'LSTM',
    version: 'v2.0.5',
    filePath: '/models/lstm_v205.pt',
    status: 'inactive',
    metrics: { mae: 0.055, rmse: 0.082, accuracy: 89.5, overallScore: 0.72, healthGrade: 'B' },
    remark: '上一版本（可回退）',
    createdAt: nowIso(86400),
    activatedAt: null,
  },
]

const reports: SimulationReport[] = [
  {
    id: 1,
    runId: 101,
    scene: 'flood',
    params: { scene: 'flood', initialLevel: 380.2, inflowRate: 3500, durationMin: 120 },
    summary: { maxLevel: 381.8, minLevel: 380.2, totalDischarge: 125000, estimatedPower: 28500 },
    content:
      '洪水场景下阀门逐步加大开度，最高水位381.8m，未突破汛限。建议汛前预泄方案可提前30min启动。',
    filePath: '/reports/r001.pdf',
    createdAt: nowIso(2880),
    operatorName: '王算法',
  },
  {
    id: 2,
    runId: 102,
    scene: 'normal',
    params: { scene: 'normal', initialLevel: 379.8, inflowRate: 1850, durationMin: 60 },
    summary: { maxLevel: 380.1, minLevel: 379.6, totalDischarge: 68000, estimatedPower: 15200 },
    content: '常规调度仿真：开度45%时水位稳定在379.9m附近，发电效率最优。',
    filePath: '/reports/r002.pdf',
    createdAt: nowIso(1440),
    operatorName: '张调度',
  },
  {
    id: 3,
    runId: 103,
    scene: 'dry',
    params: { scene: 'dry', initialLevel: 378.5, inflowRate: 900, durationMin: 90 },
    summary: { maxLevel: 378.8, minLevel: 378.2, totalDischarge: 32000, estimatedPower: 9800 },
    content: '枯水期仿真：生态流量保障优先，最小下泄流量满足要求。',
    filePath: '/reports/r003.pdf',
    createdAt: nowIso(720),
    operatorName: '李运维',
  },
]

const reviews: FaultReview[] = [
  {
    id: 1,
    alarmId: 1001,
    faultType: '高水位超限',
    impactScope: '上游库区、1-3号阀门',
    reviewed: false,
    status: 'pending',
    createdAt: nowIso(1440),
    timeline: [
      { time: '09:12:05', event: '水位超限持续35s，触发紧急告警' },
      { time: '09:12:40', event: '调度员确认告警' },
      { time: '09:13:10', event: 'AI建议开度60%（置信度72%）' },
      { time: '09:13:25', event: '调度员手动执行开度55%' },
      { time: '09:14:00', event: '水位开始下降' },
      { time: '09:18:30', event: '处置完成' },
    ],
    conclusion: null,
  },
  {
    id: 2,
    alarmId: 1004,
    faultType: '生态流量不足',
    impactScope: '下游河道生态段',
    reviewed: true,
    status: 'reviewed',
    createdAt: nowIso(4320),
    timeline: [
      { time: '14:20:00', event: '生态流量低于阈值，触发一般告警' },
      { time: '14:25:30', event: '调整3号阀门开度' },
      { time: '14:35:00', event: '生态流量恢复达标' },
    ],
    conclusion: {
      rootCause: '枯水期来水偏少，生态流量分配权重不足',
      improvements: '优化枯水期生态流量保障策略',
      responsibleDept: '调度中心',
      reviewedBy: '李运维',
      reviewedAt: nowIso(4000),
    },
  },
  {
    id: 3,
    alarmId: 1006,
    faultType: '阀门执行失败',
    impactScope: '1号表孔阀门、泄洪通道',
    reviewed: false,
    status: 'pending',
    createdAt: nowIso(480),
    timeline: [
      { time: '08:05:12', event: '1号阀门开度指令下发失败' },
      { time: '08:05:57', event: '持续超限42s，触发紧急告警' },
      { time: '08:06:20', event: '现场人员检查液压系统' },
      { time: '08:12:00', event: '切换备用执行器，阀门恢复正常' },
    ],
    conclusion: null,
  },
  {
    id: 4,
    alarmId: 1008,
    faultType: '传感器误报',
    impactScope: '出库流量监测',
    reviewed: true,
    status: 'reviewed',
    createdAt: nowIso(960),
    timeline: [
      { time: '16:30:00', event: '出库流量传感器短时超限' },
      { time: '16:30:33', event: '触发一般告警' },
      { time: '16:35:00', event: '人工复核确认为校准偏差' },
    ],
    conclusion: {
      rootCause: '传感器零点漂移导致读数偏高',
      improvements: '纳入月度校准计划，增加交叉校验',
      responsibleDept: '设备管理',
      reviewedBy: '王算法',
      reviewedAt: nowIso(900),
    },
  },
]

// ---------- 实时 tick（向家坝水文站遥测 + 仿真联动） ----------
setInterval(() => {
  tick++
  const hour = new Date().getHours()
  decisionBase.confidence = Math.round(jitter(87, 3))

  if (simState.status === 'running') {
    simState.elapsedSec += TICK_SEC * simSpeed
    const targetInflow = simParams.inflowRate + diurnalInflowOffset(hour) * 0.25
    stationLive = {
      ...stationLive,
      inflowRate: Math.round(targetInflow + (Math.random() - 0.5) * 40),
      gateOpening: simState.currentOpening,
      upstreamLevel: simState.currentLevel,
      downstreamLevel: simState.currentDownstreamLevel,
    }
    stationLive = stepHydrology(stationLive, tick, TICK_SEC * simSpeed, 1 / 220000)
    simState.currentLevel = stationLive.upstreamLevel
    simState.currentDownstreamLevel = stationLive.downstreamLevel
    simState.currentFlow = stationLive.inflowRate
    pushHistory()
    syncDispatchFromStation()
    if (simState.elapsedSec >= simParams.durationMin * 60) {
      simState.status = 'finished'
    }
  } else if (simState.status === 'paused') {
    // 暂停：保持当前值，仅记录历史点
    pushHistory()
  } else {
    // 待机：实时跟踪向家坝水文站（与调度页同源）
    const targetInflow = XIANGJIABA_HYDRO.normalInflow + diurnalInflowOffset(hour)
    stationLive = {
      ...stationLive,
      inflowRate: Math.round(targetInflow + (Math.random() - 0.5) * 35),
    }
    stationLive = stepHydrology(stationLive, tick, TICK_SEC)
    syncSimFromStation()
    syncDispatchFromStation()
    pushHistory()
  }

  decisionBase.factors[0].value = `${dispatchStatus.upstreamLevel} m`
  decisionBase.factors[2].value = `${dispatchStatus.flowRate} m³/s`
}, TICK_SEC * 1000)

function delay<T>(data: T, ms = 200): Promise<T> {
  return new Promise((resolve) => setTimeout(() => resolve(data), ms))
}

function ok<T>(data: T) {
  return { code: 0, msg: 'success', data, success: true, trace_id: 'mock' }
}

function filterAlarms(params: AlarmFilterParams) {
  let list = alarmStore.filter((a) => a.durationSec >= 30)
  if (params.level) list = list.filter((a) => a.level === params.level)
  if (params.status) list = list.filter((a) => a.status === params.status)
  if (params.type) list = list.filter((a) => a.type === params.type)
  if (params.deviceType) list = list.filter((a) => a.deviceType === params.deviceType)
  if (params.startTime) {
    const start = new Date(params.startTime).getTime()
    list = list.filter((a) => new Date(a.createdAt).getTime() >= start)
  }
  if (params.endTime) {
    const end = new Date(params.endTime).getTime()
    list = list.filter((a) => new Date(a.createdAt).getTime() <= end)
  }
  if (params.keyword) {
    list = filterAlarmsByKeyword(list, params.keyword)
  }
  return list
}

function todayStartMs() {
  const d = new Date()
  d.setHours(0, 0, 0, 0)
  return d.getTime()
}

// ---------- 阀门动作历史（含互锁标记）----------
const gateActions: GateAction[] = [
  {
    id: 1,
    equipment_id: 1,
    previous_opening: 60,
    target_opening: 50,
    actual_opening: 50,
    action_type: 'maintain',
    action_source: 'dqn_auto',
    duration_ms: 12000,
    is_smoothed: 1,
    acted_at: new Date(Date.now() - 7200000).toISOString().replace('T', ' ').slice(0, 19),
    interlock_rule_id: 1,
    interlock_rule_name: '泄洪-发电互斥',
  },
  {
    id: 2,
    equipment_id: 2,
    previous_opening: 25,
    target_opening: 30,
    actual_opening: 30,
    action_type: 'open',
    action_source: 'physics_corrected',
    duration_ms: 8000,
    is_smoothed: 0,
    acted_at: new Date(Date.now() - 10800000).toISOString().replace('T', ' ').slice(0, 19),
    interlock_rule_id: 3,
    interlock_rule_name: '对称性约束',
  },
  {
    id: 3,
    equipment_id: 3,
    previous_opening: 40,
    target_opening: 42,
    actual_opening: 42,
    action_type: 'maintain',
    action_source: 'manual',
    duration_ms: 6000,
    is_smoothed: 0,
    acted_at: new Date(Date.now() - 14400000).toISOString().replace('T', ' ').slice(0, 19),
    interlock_rule_id: null,
    interlock_rule_name: null,
  },
  {
    id: 4,
    equipment_id: 1,
    previous_opening: 45,
    target_opening: 52,
    actual_opening: 52,
    action_type: 'open',
    action_source: 'dqn_auto',
    duration_ms: 10000,
    is_smoothed: 0,
    acted_at: new Date(Date.now() - 18000000).toISOString().replace('T', ' ').slice(0, 19),
    interlock_rule_id: null,
    interlock_rule_name: null,
  },
]

// ---------- 导出 API 函数 ----------
export const mockApi = {
  // 告警
  getAlarmList(params: AlarmFilterParams) {
    ensureAlarmStore()
    let list = filterAlarms(params)
    list.sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime())
    const total = list.length
    const start = (params.pageNum - 1) * params.pageSize
    list = list.slice(start, start + params.pageSize)
    const pendingCount = alarmStore.filter((a) => a.status === 'pending').length
    return delay(
      ok({ list, total, pageNum: params.pageNum, pageSize: params.pageSize, pendingCount }),
    )
  },

  getAlarmDetail(id: number) {
    const item = alarmStore.find((a) => a.id === id)
    if (!item) return delay(Promise.reject(new Error('not found')))
    return delay(ok(item))
  },

  confirmAlarm(id: number) {
    const item = alarmStore.find((a) => a.id === id)
    if (item && item.status === 'pending') {
      item.status = 'confirmed'
      item.confirmedAt = nowIso(0)
      item.confirmedBy = 1
      item.confirmedByName = '张调度'
      persistAlarmStore(alarmStore)
    }
    return delay(ok(null))
  },

  handleAlarm(params: { id: number; remark: string }) {
    const item = alarmStore.find((a) => a.id === params.id)
    if (item && item.status === 'confirmed') {
      item.status = 'handled'
      item.handledAt = nowIso(0)
      item.handledBy = 1
      item.handledByName = '张调度'
      item.remark = params.remark
      persistAlarmStore(alarmStore)
    }
    return delay(ok(null))
  },

  getExceedLogs(params?: {
    keyword?: string
    type?: string
    startTime?: string
    endTime?: string
  }) {
    let list = [...exceedLogs]
    if (params?.type) list = list.filter((log) => log.type === params.type)
    if (params?.startTime) {
      const start = new Date(params.startTime).getTime()
      list = list.filter((log) => new Date(log.createdAt).getTime() >= start)
    }
    if (params?.endTime) {
      const end = new Date(params.endTime).getTime()
      list = list.filter((log) => new Date(log.createdAt).getTime() <= end)
    }
    if (params?.keyword) {
      list = list.filter((log) =>
        fuzzyMatch(params.keyword!, log.point, ALARM_TYPE_MAP[log.type]?.label, String(log.value)),
      )
    }
    list.sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime())
    return delay(ok({ list, total: list.length, pageNum: 1, pageSize: 20 }))
  },

  getAlarmStats() {
    const start = todayStartMs()
    const todayList = alarmStore.filter((a) => new Date(a.createdAt).getTime() >= start)
    const stats: AlarmStatsResult = {
      today: todayList.length,
      pending: alarmStore.filter((a) => a.status === 'pending').length,
      handled: alarmStore.filter((a) => a.status === 'handled').length,
      falseAlarm: alarmStore.filter((a) => a.isFalseAlarm).length,
      levelDistribution: {
        URGENT: alarmStore.filter((a) => a.level === 'URGENT').length,
        IMPORTANT: alarmStore.filter((a) => a.level === 'IMPORTANT').length,
        NORMAL: alarmStore.filter((a) => a.level === 'NORMAL').length,
      },
    }
    return delay(ok(stats))
  },

  /** 模拟 WebSocket 推送：模型健康降级等新告警 */
  pollAlarmPush() {
    alarmPushSeq++
    if (alarmPushSeq % 6 !== 0) return delay(ok(null as AlarmPushMessage | null))
    const newAlarm: AlarmRecord = {
      id: 9000 + alarmPushSeq,
      level: alarmPushSeq % 12 === 0 ? 'URGENT' : 'IMPORTANT',
      type: 'MODEL_HEALTH_DEGRADED',
      content:
        alarmPushSeq % 12 === 0
          ? '模型综合评分连续 3 次 D 级，边缘端已自动回退至上一代模型'
          : `Physics-LSTM v5.1 健康度降至 ${alarmPushSeq % 12 === 0 ? 'D' : 'C'} 级，已切换 L1 人工模式`,
      threshold: alarmPushSeq % 12 === 0 ? 0.4 : 0.55,
      currentValue: alarmPushSeq % 12 === 0 ? 0.36 : 0.48,
      durationSec: 32 + (alarmPushSeq % 10),
      status: 'pending',
      confirmedAt: null,
      confirmedBy: null,
      confirmedByName: null,
      handledAt: null,
      handledBy: null,
      handledByName: null,
      remark: null,
      createdAt: nowIso(0),
      pointName: 'AI 推理引擎',
      deviceType: 'sensor',
      snapshot: {
        upstreamLevel: stationLive.upstreamLevel,
        downstreamLevel: stationLive.downstreamLevel,
        flowRate: stationLive.inflowRate,
        gateOpening: stationLive.gateOpening,
        recordedAt: nowIso(0),
      },
    }
    alarmStore = persistAlarmStore([newAlarm, ...alarmStore])
    const msg: AlarmPushMessage = {
      type: 'alarm_new',
      data: newAlarm,
      pendingCount: alarmStore.filter((a) => a.status === 'pending').length,
    }
    return delay(ok(msg))
  },

  // 调度
  getAutoExecuteBlockReason() {
    if (dispatchStatus.mode !== 'auto') return '当前为手动模式'
    if (dispatchStatus.isExecuting) return '指令执行中'
    if (decisionBase.execution_status !== 'pending') return '当前建议已处理'
    const target = decisionBase.recommended_opening
    const current = dispatchStatus.gateOpening
    if (target === current) return '建议开度与当前一致'
    if (decisionBase.physics_validation?.interlock?.triggered) {
      return `互锁触发：${decisionBase.physics_validation.interlock.reason}`
    }
    if (['danger', 'critical'].includes(decisionBase.physics_validation?.risk_level ?? '')) {
      return '风险等级过高，等待人工介入'
    }
    const confidence = decisionBase.confidence
    const delta = Math.abs(target - current)
    if (dispatchStatus.autoLevel === 1) return 'L1 仅建议，需人工确认'
    if (dispatchStatus.autoLevel === 2) {
      if (confidence < 80) return `置信度 ${confidence}% 不足 80%，降级为仅建议`
      if (delta > 10) return `开度变化 ${delta}% 超过 10%，降级为仅建议`
    }
    return null
  },

  tryAutoExecute() {
    const blockReason = this.getAutoExecuteBlockReason()
    if (blockReason) return { executed: false, message: blockReason }
    const target = decisionBase.recommended_opening
    const level = dispatchStatus.autoLevel
    const currentOpening = dispatchStatus.gateOpening
    const modeLabel = `L${level}` as 'L1' | 'L2' | 'L3'
    decisionBase.decision_mode = modeLabel
    if (decisionBase.physics_validation) {
      decisionBase.physics_validation.decision_level =
        level === 3 ? 'L3_AUTO' : level === 2 ? 'L2_SUGGEST' : 'L1_MANUAL'
    }
    dispatchStatus.isExecuting = true
    dispatchStatus.executingTarget = target
    dispatchStatus.lastDispatchAt = nowIso(0)
    setTimeout(() => {
      dispatchStatus.gateOpening = target
      stationLive.gateOpening = target
      simState.currentOpening = target
      decisionBase.current_opening = target
      decisionBase.executed_opening = target
      decisionBase.execution_status = 'executed'
      dispatchStatus.isExecuting = false
      dispatchStatus.executingTarget = null
    }, 4000)
    dispatchRecords.unshift({
      id: Date.now(),
      decision_time: nowIso(0),
      decision_mode: modeLabel,
      recommended_opening: target,
      confidence: decisionBase.confidence,
      risk_rank: decisionBase.risk_rank,
      execution_status: 'executed',
      physics_validation: decisionBase.physics_validation,
      action: level === 3 ? '自动执行' : '半自动执行',
      operator_name: '系统',
      snapshot: buildRecordSnapshot(target, decisionBase.confidence),
    })
    if (dispatchRecords.length > 100) dispatchRecords.pop()
    return {
      executed: true,
      message:
        level === 3
          ? `L3 全自动：开度 ${currentOpening} → ${target}% 已自动下发`
          : `L2 半自动：开度 ${currentOpening} → ${target}% 已自动下发`,
    }
  },

  getDispatchStatus() {
    this.tryAutoExecute()
    return delay(ok({ ...dispatchStatus }))
  },

  getDecisionDetail() {
    return delay(
      ok({
        ...decisionBase,
        factors: [...decisionBase.factors],
        alternatives: [...decisionBase.alternatives],
      }),
    )
  },

  getPrediction(horizon: '1h' | '3h' | '6h') {
    const termMap = { '1h': 1 as const, '3h': 2 as const, '6h': 3 as const }
    const pts = horizon === '1h' ? 12 : horizon === '3h' ? 36 : 72
    const histPts = 12
    const now = Date.now()
    const step = 5 * 60000
    const baseLevel = dispatchStatus.upstreamLevel
    const baseFlow = dispatchStatus.flowRate
    const hist_water = Array.from({ length: histPts }, (_, i) => ({
      time: new Date(now - (histPts - i) * step).toISOString(),
      value: +(baseLevel + Math.sin(i * 0.25) * 0.08).toFixed(2),
    }))
    const pred_water = Array.from({ length: pts }, (_, i) => ({
      time: new Date(now + i * step).toISOString(),
      value: +(baseLevel + Math.sin(i * 0.2 + tick * 0.1) * 0.4 + i * 0.005).toFixed(2),
    }))
    const water_seq = [...hist_water, ...pred_water]
    const flow_seq = water_seq.map((p, i) => ({
      time: p.time,
      value: Math.round(
        baseFlow + Math.sin(i * 0.15) * 120 + (i > histPts ? (i - histPts) * 2 : 0),
      ),
    }))
    return delay(
      ok({
        id: 1,
        base_time: new Date(now).toISOString(),
        predict_term: termMap[horizon],
        water_seq,
        flow_seq,
        predict_accuracy: 94.2,
        created_at: new Date(now).toISOString(),
      } as PredictionData),
    )
  },

  executeDispatch(params: { targetOpening: number }) {
    const v = Math.max(0, Math.min(100, Math.round(params.targetOpening)))
    dispatchStatus.isExecuting = true
    dispatchStatus.executingTarget = v
    dispatchStatus.lastDispatchAt = nowIso(0)
    setTimeout(() => {
      dispatchStatus.gateOpening = v
      stationLive.gateOpening = v
      simState.currentOpening = v
      decisionBase.current_opening = v
      dispatchStatus.isExecuting = false
      dispatchStatus.executingTarget = null
    }, 4000)
    dispatchRecords.unshift({
      id: Date.now(),
      decision_time: nowIso(0),
      decision_mode: `L${dispatchStatus.autoLevel}` as 'L1',
      recommended_opening: v,
      confidence: 100,
      risk_rank: 1,
      execution_status: 'executed',
      physics_validation: null,
      action: '手动下发开度',
      operator_name: '当前用户',
      snapshot: buildRecordSnapshot(v, 100),
    })
    if (dispatchRecords.length > 100) dispatchRecords.pop()
    return delay(ok({ command_id: `CMD-MOCK-${Date.now()}` }))
  },

  cancelDispatch() {
    if (!dispatchStatus.isExecuting) return delay(ok(null))
    const target = dispatchStatus.executingTarget ?? dispatchStatus.gateOpening
    dispatchStatus.isExecuting = false
    dispatchStatus.executingTarget = null
    dispatchRecords.unshift({
      id: Date.now(),
      decision_time: nowIso(0),
      decision_mode: `L${dispatchStatus.autoLevel}` as 'L1',
      recommended_opening: target,
      confidence: 0,
      risk_rank: 2,
      execution_status: 'rejected',
      physics_validation: null,
      action: '取消执行',
      operator_name: '当前用户',
      snapshot: buildRecordSnapshot(target, 0),
    })
    if (dispatchRecords.length > 100) dispatchRecords.pop()
    return delay(ok(null))
  },

  emergencyStop() {
    dispatchStatus.isExecuting = false
    dispatchStatus.executingTarget = null
    dispatchStatus.mode = 'manual'
    dispatchStatus.autoLevel = 1
    dispatchStatus.gateOpening = 0
    if (simState.status === 'running') simState.status = 'paused'
    stationLive.gateOpening = 0
    simState.currentOpening = 0
    return delay(ok({ stop_log_id: Date.now(), command_id: `estop-${Date.now()}` }))
  },

  /** 全局急停 — 调度 + 仿真联动，阀门归零 */
  globalEmergencyStop() {
    dispatchStatus.isExecuting = false
    dispatchStatus.executingTarget = null
    dispatchStatus.mode = 'manual'
    dispatchStatus.autoLevel = 1
    dispatchStatus.gateOpening = 0
    if (simState.status === 'running') simState.status = 'paused'
    stationLive.gateOpening = 0
    simState.currentOpening = 0
    return delay(ok({ stop_log_id: Date.now(), command_id: `estop-${Date.now()}` }))
  },

  getEmergencyStops() {
    return delay(
      ok({
        list: [
          {
            id: 3,
            trigger_user_id: 1,
            trigger_time: nowIso(30),
            edge_ack_time: nowIso(28),
            plc_shut_time: nowIso(27),
            recover_time: null,
            stop_reason: '人工触发急停',
          },
        ],
        total: 1,
      }),
    )
  },

  recoverEmergencyStop(_id: number) {
    dispatchStatus.mode = 'auto'
    dispatchStatus.autoLevel = 2
    return delay(ok(null))
  },

  changeMode(params: { mode: 'auto' | 'manual' }) {
    dispatchStatus.mode = params.mode
    localStorage.setItem('dispatch_mode', params.mode)
    return delay(ok(null))
  },

  changeAutoLevel(params: { level: AutoLevel }) {
    dispatchStatus.autoLevel = params.level
    const result = this.tryAutoExecute()
    return delay(ok(result))
  },

  acceptDecision() {
    const target = decisionBase.recommended_opening
    dispatchStatus.gateOpening = target
    dispatchStatus.lastDispatchAt = nowIso(0)
    stationLive.gateOpening = target
    simState.currentOpening = target
    decisionBase.current_opening = target
    decisionBase.executed_opening = target
    decisionBase.execution_status = 'executed'
    dispatchRecords.unshift({
      id: Date.now(),
      decision_time: nowIso(0),
      decision_mode: decisionBase.decision_mode,
      recommended_opening: decisionBase.recommended_opening,
      confidence: decisionBase.confidence,
      risk_rank: decisionBase.risk_rank,
      execution_status: 'executed',
      physics_validation: null,
      action: '采纳调度建议',
      operator_name: '当前用户',
      snapshot: buildRecordSnapshot(decisionBase.recommended_opening, decisionBase.confidence),
    })
    if (dispatchRecords.length > 100) dispatchRecords.pop()
    return delay(ok(null))
  },

  ignoreDecision() {
    return delay(ok(null))
  },

  getDispatchLogs(params?: { keyword?: string }) {
    let list = [...dispatchRecords]
    if (params?.keyword) {
      list = list.filter((r) =>
        fuzzyMatch(
          params.keyword!,
          r.decision_mode,
          String(r.recommended_opening),
          r.execution_status,
          String(r.confidence),
        ),
      )
    }
    return delay(ok({ list, total: list.length, pageNum: 1, pageSize: list.length || 1 }))
  },

  getPhysicsGuardSummary() {
    return delay(ok(gateaiSharedStore.getPhysicsSummary(1) as PhysicsGuardSummary))
  },

  getPhysicsGuardHistory() {
    const list = gateaiSharedStore.getPhysicsHistory(1)
    return delay(ok({ list, total: list.length }))
  },

  rollbackPhysicsGuardConfig(id: number) {
    gateaiSharedStore.rollbackPhysics(1, id)
    return delay(ok(gateaiSharedStore.getPhysicsSummary(1) as PhysicsGuardSummary))
  },

  getGateActions(params?: { keyword?: string }) {
    let list = [...gateActions]
    if (params?.keyword) {
      list = list.filter((a) =>
        fuzzyMatch(params.keyword!, a.interlock_rule_name ?? '', String(a.target_opening)),
      )
    }
    return delay(ok({ list, total: list.length, pageNum: 1, pageSize: list.length || 1 }))
  },

  getCommandTrace(commandId: string) {
    return delay(
      ok({
        id: 1,
        command_id: commandId,
        trace_id: 'mock-trace',
        decision_id: null,
        gate_action_id: null,
        edge_node_id: 1,
        operator_id: null,
        command_type: 'manual_adjust',
        payload: { reservoir_id: 1, target_opening: 45 },
        target_equipment: null,
        target_opening: 45,
        status: 'pending' as const,
        sent_at: nowIso(0),
        acknowledged_at: null,
        verified_at: null,
        executed_at: null,
        feedback_at: null,
        full_delay_ms: null,
        execution_result: null,
        reject_reason: null,
        is_emergency: false,
        created_at: nowIso(0),
        updated_at: nowIso(0),
      }),
    )
  },

  getRiskLevel() {
    const safe = 380.0
    const diff = Math.abs(dispatchStatus.upstreamLevel - safe)
    if (diff <= 0.5)
      return delay(ok({ level: 'low' as const, diff, safeMin: safe - 0.5, safeMax: safe + 0.5 }))
    if (diff <= 1.0)
      return delay(ok({ level: 'medium' as const, diff, safeMin: safe - 1, safeMax: safe + 1 }))
    return delay(ok({ level: 'high' as const, diff, safeMin: safe - 1, safeMax: safe + 1 }))
  },

  // 仿真
  getSimulationStatus() {
    return delay(
      ok({
        ...simState,
        historyLevels: [...simState.historyLevels],
        historyFlows: [...simState.historyFlows],
      }),
    )
  },

  startSimulation(params: SimulationStartPayload) {
    simParams = {
      scene: params.scene,
      initialLevel: params.initialLevel,
      inflowRate: params.inflowRate,
      durationMin: params.durationMin,
    }
    simSpeed = params.speed ?? 1
    const opening = Math.max(
      0,
      Math.min(100, Math.round(params.gateOpening ?? stationLive.gateOpening)),
    )
    stationLive = {
      ...createInitialTelemetry(),
      upstreamLevel: params.initialLevel,
      inflowRate: params.inflowRate,
      gateOpening: opening,
    }
    stationLive = stepHydrology(stationLive, tick, 1, 1 / 220000)
    simState = {
      status: 'running',
      elapsedSec: 0,
      currentLevel: stationLive.upstreamLevel,
      currentDownstreamLevel: stationLive.downstreamLevel,
      currentFlow: stationLive.inflowRate,
      currentOpening: opening,
      historyLevels: [{ time: 0, value: stationLive.upstreamLevel }],
      historyFlows: [{ time: 0, value: stationLive.inflowRate }],
    }
    syncDispatchFromStation()
    return delay(ok({ runId: Date.now() }))
  },

  pauseSimulation() {
    if (simState.status !== 'running') return delay(ok(null))
    simState.status = 'paused'
    return delay(ok(null))
  },

  resumeSimulation() {
    if (simState.status !== 'paused') return delay(ok(null))
    simState.status = 'running'
    return delay(ok(null))
  },

  resetSimulation() {
    simSpeed = 1
    stationLive = createInitialTelemetry()
    simState = {
      status: 'idle',
      elapsedSec: 0,
      currentLevel: stationLive.upstreamLevel,
      currentDownstreamLevel: stationLive.downstreamLevel,
      currentFlow: stationLive.inflowRate,
      currentOpening: stationLive.gateOpening,
      historyLevels: [],
      historyFlows: [],
    }
    for (let i = 24; i >= 0; i--) {
      const lv = +(stationLive.upstreamLevel - i * 0.012 + Math.sin(i * 0.4) * 0.025).toFixed(2)
      simState.historyLevels.push({ time: i, value: lv })
      simState.historyFlows.push({
        time: i,
        value: stationLive.inflowRate + Math.sin(i * 0.3) * 30,
      })
    }
    syncDispatchFromStation()
    return delay(ok(null))
  },

  /** 调节阀门开度 — 实时影响泄流量与水位 */
  setGateOpening(opening: number) {
    const v = Math.max(0, Math.min(100, Math.round(opening)))
    stationLive.gateOpening = v
    simState.currentOpening = v
    dispatchStatus.gateOpening = v
    return delay(ok(null))
  },

  emergencyStopSimulation() {
    dispatchStatus.isExecuting = false
    dispatchStatus.executingTarget = null
    dispatchStatus.mode = 'manual'
    dispatchStatus.autoLevel = 1
    dispatchStatus.gateOpening = 0
    if (simState.status === 'running') simState.status = 'paused'
    stationLive.gateOpening = 0
    simState.currentOpening = 0
    return delay(ok(null))
  },

  getModelList(params?: { keyword?: string }) {
    let list = [...models]
    if (params?.keyword) {
      list = list.filter((m) => fuzzyMatch(params.keyword!, m.type, m.version, m.remark, m.status))
    }
    return delay(ok(list))
  },
  activateModel(id: number) {
    models.forEach((m) => {
      m.status = m.id === id ? 'active' : 'inactive'
    })
    return delay(ok(null))
  },
  uploadModel(formData?: FormData) {
    let fileName = 'imported_model.pt'
    let modelType: AiModel['type'] = 'LSTM'
    if (formData) {
      const file = formData.get('file')
      if (file instanceof File) fileName = file.name
      const typeField = String(formData.get('type') ?? '')
      if (typeField === 'DQN' || typeField === 'LSTM') modelType = typeField
      else if (/dqn/i.test(fileName)) modelType = 'DQN'
    }
    const nextId = models.reduce((max, m) => Math.max(max, m.id), 0) + 1
    const imported: AiModel = {
      id: nextId,
      type: modelType,
      version: `v${new Date().toISOString().slice(0, 10).replace(/-/g, '')}`,
      filePath: `/models/${fileName}`,
      status: 'validating',
      metrics: null,
      remark: `本地导入 · ${fileName}`,
      createdAt: nowIso(0),
      activatedAt: null,
    }
    models.unshift(imported)
    return delay(ok(imported))
  },

  startTraining() {
    return delay(ok({ taskId: `task-${Date.now()}` }))
  },
  getTrainingProgress(taskId: string): Promise<{
    code: number
    msg: string
    data: TrainingTask
    success: boolean
    trace_id: string
  }> {
    const progress = Math.min(100, (tick % 20) * 5 + 20)
    return delay(
      ok({
        taskId,
        modelId: 1,
        config: {},
        progress,
        lossCurve: [{ epoch: 1, loss: 0.05 }],
        status: progress >= 100 ? 'completed' : 'running',
      }),
    )
  },

  generateReport() {
    return delay(ok(reports[0]))
  },
  getReportList(params?: { keyword?: string }) {
    let list = [...reports]
    if (params?.keyword) {
      list = list.filter((r) =>
        fuzzyMatch(params.keyword!, r.content, r.operatorName, r.scene, String(r.runId)),
      )
    }
    return delay(ok({ list, total: list.length, pageNum: 1, pageSize: 10 }))
  },
  downloadReport() {
    return delay(new Blob(['报告内容'], { type: 'text/plain' }))
  },

  getFaultReviewList(params?: { keyword?: string; type?: string }) {
    let list = [...reviews]
    if (params?.type) list = list.filter((r) => r.faultType.includes(params.type!))
    if (params?.keyword) {
      list = list.filter((r) => fuzzyMatch(params.keyword!, r.faultType, r.impactScope, r.status))
    }
    return delay(ok({ list, total: list.length, pageNum: 1, pageSize: 10 }))
  },
  getFaultReviewDetail(id: number) {
    const item = reviews.find((r) => r.id === id)
    return item ? delay(ok(item)) : Promise.reject(new Error('not found'))
  },
  submitFaultConclusion(id: number, conclusion: FaultReview['conclusion']) {
    const item = reviews.find((r) => r.id === id)
    if (item) {
      item.conclusion = conclusion
      item.reviewed = true
      item.status = 'reviewed'
    }
    return delay(ok(null))
  },
  importToSimulation(id: number) {
    const review = reviews.find((r) => r.id === id)
    if (review) {
      return delay(ok(faultReviewToSimulationParams(review)))
    }
    const preset = getScenePreset(simParams.scene)
    return delay(ok({ ...simParams, gateOpening: preset.gateOpening }))
  },
  getSimulationRuns() {
    return delay(
      ok({
        list: [
          {
            id: 101,
            scene: 'flood' as const,
            params: simParams,
            status: 'finished' as const,
            summary: reports[0].summary,
            createdAt: nowIso(2880),
          },
        ],
        total: 1,
        pageNum: 1,
        pageSize: 10,
      }),
    )
  },

  // 驾驶舱 KPI
  getCockpitKpi() {
    return delay(
      ok({
        station: STATION,
        upstreamLevel: dispatchStatus.upstreamLevel,
        downstreamLevel: dispatchStatus.downstreamLevel,
        flowRate: dispatchStatus.flowRate,
        gateOpening: dispatchStatus.gateOpening,
        power: Math.round(jitter(2850, 120)),
        inspectionRate: Math.round(jitter(86.5, 2)),
        assetHealth: Math.round(jitter(92, 3)),
        workOrderDone: Math.round(jitter(78, 5)),
        pendingTasks: Math.max(1, Math.round(jitter(5, 2))),
        alarmSummary: {
          urgent: alarmStore.filter((a) => a.level === 'URGENT' && a.status === 'pending').length,
          important: alarmStore.filter((a) => a.level === 'IMPORTANT' && a.status === 'pending')
            .length,
          normal: alarmStore.filter((a) => a.status === 'pending' && a.level === 'NORMAL').length,
        },
      }),
    )
  },
}
