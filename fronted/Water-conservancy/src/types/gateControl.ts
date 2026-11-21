// ============================================================
// 阀门节点控制 — 类型定义
// ============================================================

export type GateGroup = 'surface' | 'mid' | 'bottom'

export type GateNodeStatus = 'online' | 'offline' | 'fault' | 'executing'

export interface GateNodeControl {
  id: number
  code: string
  name: string
  group: GateGroup
  status: GateNodeStatus
  mode: string
  currentOpening: number
  targetOpening: number
  flowRate: number
  lastActionAt: string
  alpha: number
  flowCoeff: number
  interlockBlocked?: boolean
  interlockReason?: string
}

export interface GatePrecheckViolation {
  ruleCode: string
  ruleName: string
  severity: 'block' | 'warn'
  affectedGateIds: number[]
  message: string
  suggestedFix?: Array<{ gateId: number; opening: number }>
}

export interface GatePrecheckResult {
  passed: boolean
  violations: GatePrecheckViolation[]
}

export interface GateImpactPreview {
  totalOpening: number
  totalOpeningDelta: number
  outflow: number
  outflowDelta: number
  waterTrend: 'up' | 'down' | 'stable'
  ecoFlowOk: boolean
}

export interface GateBatchExecuteItem {
  equipment_id: number
  target_opening: number
}

/** POST /v1/dispatch/gate-execute 与 /gate-execute/batch 返回的指令项 */
export interface GateExecuteCommandResult {
  equipment_id: number
  target_opening: number
  command_id: string
}
