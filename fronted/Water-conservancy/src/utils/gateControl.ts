// ============================================================
// 阀门节点控制 — 聚合 / 分配 / 互锁预校验
// ============================================================
import type {
  GateGroup,
  GateImpactPreview,
  GateNodeControl,
  GatePrecheckResult,
  GatePrecheckViolation,
} from '@/types/gateControl'

const GROUP_ALPHA: Record<GateGroup, number> = {
  surface: 1.0,
  mid: 0.6,
  bottom: 0.4,
}

const GROUP_FLOW_COEFF: Record<GateGroup, number> = {
  surface: 12,
  mid: 8,
  bottom: 5,
}

export const ECO_FLOW_MIN = 500
export const SYMMETRY_MAX_DIFF = 10
export const ADJACENT_MAX_DIFF = 15
export const SINGLE_STEP_MAX = 10

export function inferGateGroup(code: string, name: string): GateGroup {
  const numMatch = code.match(/(\d+)/)
  const num = numMatch ? Number(numMatch[1]) : 0
  if ((num >= 9 && num <= 10) || name.includes('中')) return 'mid'
  if ((num >= 11 && num <= 12) || name.includes('底')) return 'bottom'
  return 'surface'
}

export function isGateOnline(status: string): boolean {
  return status === 'online' || status === 'executing'
}

/** 加权聚合总开度 */
export function calcAggregateOpening(
  gates: GateNodeControl[],
  useTarget = false,
): number | null {
  const online = gates.filter((g) => isGateOnline(g.status))
  if (!online.length) return null
  let weighted = 0
  let alphaSum = 0
  for (const g of online) {
    const opening = useTarget ? g.targetOpening : g.currentOpening
    weighted += opening * g.alpha
    alphaSum += g.alpha
  }
  return alphaSum > 0 ? Math.round((weighted / alphaSum) * 10) / 10 : null
}

/** 估算出库流量 */
export function calcOutflow(
  gates: GateNodeControl[],
  head: number,
  useTarget = false,
): number {
  if (head <= 0) return 0
  const sqrtH = Math.sqrt(head)
  let total = 0
  for (const g of gates) {
    if (!isGateOnline(g.status)) continue
    const opening = useTarget ? g.targetOpening : g.currentOpening
    if (opening <= 0) continue
    total += g.flowCoeff * g.alpha * sqrtH * (opening / 100)
  }
  return Math.round(total)
}

/** 等比缩放分配总开度到各节点 */
export function distributeTotalOpening(
  gates: GateNodeControl[],
  targetTotal: number,
  surfaceOnly = false,
): GateNodeControl[] {
  const online = gates.filter(
    (g) => isGateOnline(g.status) && (!surfaceOnly || g.group === 'surface'),
  )
  if (!online.length) return gates

  const curTotal = calcAggregateOpening(gates, false) ?? 0
  const next = gates.map((g) => ({ ...g }))

  if (curTotal <= 0) {
    const perGate = Math.min(100, Math.max(0, targetTotal))
    for (const node of next) {
      if (!isGateOnline(node.status)) continue
      if (surfaceOnly && node.group !== 'surface') continue
      node.targetOpening = perGate
    }
    return next
  }

  const k = targetTotal / curTotal
  for (const node of next) {
    if (!isGateOnline(node.status)) continue
    if (surfaceOnly && node.group !== 'surface') continue
    node.targetOpening = Math.min(100, Math.max(0, Math.round(node.currentOpening * k)))
  }
  return next
}

export function buildGateNode(raw: {
  id: number
  name: string
  code: string
  status: string
  opening: number
  target_opening: number
  mode: string
  flow_rate: number
  last_action_at: string
}): GateNodeControl {
  const group = inferGateGroup(raw.code, raw.name)
  return {
    id: raw.id,
    code: raw.code,
    name: raw.name,
    group,
    status: raw.status === 'executing' ? 'executing' : raw.status === 'offline' ? 'offline' : 'online',
    mode: raw.mode,
    currentOpening: raw.opening,
    targetOpening: raw.target_opening ?? raw.opening,
    flowRate: raw.flow_rate,
    lastActionAt: raw.last_action_at,
    alpha: GROUP_ALPHA[group],
    flowCoeff: GROUP_FLOW_COEFF[group],
  }
}

export function calcImpactPreview(
  gates: GateNodeControl[],
  inflow: number,
  head: number,
): GateImpactPreview {
  const curTotal = calcAggregateOpening(gates, false) ?? 0
  const tgtTotal = calcAggregateOpening(gates, true) ?? curTotal
  const curOut = calcOutflow(gates, head, false)
  const tgtOut = calcOutflow(gates, head, true)
  const deltaQ = inflow - tgtOut
  let waterTrend: GateImpactPreview['waterTrend'] = 'stable'
  if (deltaQ > 50) waterTrend = 'up'
  else if (deltaQ < -50) waterTrend = 'down'

  return {
    totalOpening: tgtTotal,
    totalOpeningDelta: Math.round((tgtTotal - curTotal) * 10) / 10,
    outflow: tgtOut,
    outflowDelta: tgtOut - curOut,
    waterTrend,
    ecoFlowOk: tgtOut >= ECO_FLOW_MIN,
  }
}

export function precheckGateChanges(
  gates: GateNodeControl[],
  head: number,
): GatePrecheckResult {
  const violations: GatePrecheckViolation[] = []

  const surfaceOnline = gates.filter((g) => g.group === 'surface' && isGateOnline(g.status))
  if (surfaceOnline.length >= 2) {
    const openings = surfaceOnline.map((g) => g.targetOpening)
    const diff = Math.max(...openings) - Math.min(...openings)
    if (diff > SYMMETRY_MAX_DIFF) {
      violations.push({
        ruleCode: 'symmetry_constraint',
        ruleName: '对称性约束',
        severity: 'block',
        affectedGateIds: surfaceOnline.map((g) => g.id),
        message: `表孔开度差 ${diff.toFixed(0)}% 超过 ${SYMMETRY_MAX_DIFF}% 限制`,
      })
    }
  }

  for (const g of gates) {
    if (!isGateOnline(g.status)) continue
    const step = Math.abs(g.targetOpening - g.currentOpening)
    if (step > SINGLE_STEP_MAX) {
      violations.push({
        ruleCode: 'rate_exceeded',
        ruleName: '单步变化率',
        severity: 'warn',
        affectedGateIds: [g.id],
        message: `${g.name} 单次变化 ${step}% 超过 ${SINGLE_STEP_MAX}%，建议分步执行`,
      })
    }
  }

  const outflow = calcOutflow(gates, head, true)
  if (outflow < ECO_FLOW_MIN) {
    violations.push({
      ruleCode: 'min_discharge_guarantee',
      ruleName: '最小下泄保障',
      severity: 'warn',
      affectedGateIds: gates.filter((g) => isGateOnline(g.status)).map((g) => g.id),
      message: `预估出库 ${outflow} m³/s 低于生态流量 ${ECO_FLOW_MIN} m³/s`,
    })
  }

  const hasBlock = violations.some((v) => v.severity === 'block')
  return { passed: !hasBlock, violations: consolidateViolations(violations) }
}

/** 同类规则合并为一条，演示/界面最多 3 类互锁项 */
function consolidateViolations(list: GatePrecheckViolation[]): GatePrecheckViolation[] {
  const map = new Map<string, GatePrecheckViolation>()
  for (const v of list) {
    const hit = map.get(v.ruleCode)
    if (!hit) {
      map.set(v.ruleCode, {
        ...v,
        affectedGateIds: [...v.affectedGateIds],
      })
      continue
    }
    for (const id of v.affectedGateIds) {
      if (!hit.affectedGateIds.includes(id)) hit.affectedGateIds.push(id)
    }
  }

  const out = [...map.values()]
  const rate = out.find((v) => v.ruleCode === 'rate_exceeded')
  if (rate && rate.affectedGateIds.length > 1) {
    rate.message = `${rate.affectedGateIds.length} 个节点单次变化超过 ${SINGLE_STEP_MAX}%，建议分步执行`
  }
  return out
}

/** 将预检结果标注到各阀门节点，供坝体图/列表直接展示 */
export function annotateGatesWithInterlock(
  gates: GateNodeControl[],
  violations: GatePrecheckViolation[],
): GateNodeControl[] {
  const map = new Map<number, { block: boolean; names: string[] }>()
  for (const v of violations) {
    for (const id of v.affectedGateIds) {
      const cur = map.get(id) ?? { block: false, names: [] }
      if (v.severity === 'block') cur.block = true
      if (!cur.names.includes(v.ruleName)) cur.names.push(v.ruleName)
      map.set(id, cur)
    }
  }
  return gates.map((g) => {
    const hit = map.get(g.id)
    if (!hit) {
      return { ...g, interlockBlocked: false, interlockReason: undefined }
    }
    return {
      ...g,
      interlockBlocked: hit.block,
      interlockReason: hit.names.join('、'),
    }
  })
}

export function openingBarColor(opening: number): string {
  if (opening <= 0) return '#94a3b8'
  if (opening <= 30) return '#74c0fc'
  if (opening <= 70) return '#228be6'
  return '#1864ab'
}

export function pendingChangeCount(gates: GateNodeControl[]): number {
  return gates.filter(
    (g) => isGateOnline(g.status) && g.targetOpening !== g.currentOpening,
  ).length
}
