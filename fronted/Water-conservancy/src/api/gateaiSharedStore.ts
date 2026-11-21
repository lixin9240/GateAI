/**
 * GateAI 配置统一 Mock 数据源 — mockStore 与 gateaiSettings 共用
 */
import type {
  PhysicsGuardConfig,
  PhysicsGuardHistoryItem,
  PhysicsGuardHistoryChange,
  GateInterlockRule,
  GateInterlockLog,
} from '@/types/gateai'
import type { PhysicsGuardSummary } from '@/types/dispatch'

export const RESERVOIR_OPTIONS = [
  { id: 1, name: '向家坝' },
  { id: 2, name: '溪洛渡' },
  { id: 3, name: '三峡' },
  { id: 4, name: '模拟水库' },
]

const RESERVOIR_NAMES = Object.fromEntries(RESERVOIR_OPTIONS.map((r) => [r.id, r.name]))

function nowStr(offsetMin = 0) {
  const d = new Date(Date.now() - offsetMin * 60000)
  return d.toISOString().replace('T', ' ').slice(0, 19)
}

function bumpVersion(v: string) {
  const p = v.split('.').map(Number)
  p[2] = (p[2] ?? 0) + 1
  return p.join('.')
}

const FIELD_LABELS: Partial<Record<keyof PhysicsGuardConfig, string>> = {
  upstream_danger: '危险水位',
  upstream_emergency: '紧急水位',
  upstream_warning: '预警水位',
  upstream_min: '死水位',
  ideal_min: '理想区间下限',
  ideal_max: '理想区间上限',
  downstream_danger: '下游危险',
  downstream_max: '下游上限',
  downstream_min: '下游下限',
  eco_flow_min: '最小生态流量',
  max_level_change_per_hour: '最大水位变化/h',
  reservoir_area: '水库水面面积',
  shadow_lookahead_steps: '影子前瞻步数',
  shadow_danger_offset: '影子危险偏移',
  deadband_percent: '死区百分比',
  max_rate_per_hour: '最大变化率/h',
  fusion_l3_confidence: 'L3置信度',
  fusion_l3_risk: 'L3风险概率',
  fusion_l2_confidence: 'L2置信度',
  fusion_l2_risk: 'L2风险概率',
  gate_max_discharge: '阀门最大泄量',
}

function defaultConfig(reservoirId: number): PhysicsGuardConfig {
  const scale = reservoirId === 1 ? 1 : reservoirId === 2 ? 0.98 : reservoirId === 3 ? 1.02 : 0.95
  const base = 380 * scale
  return {
    id: reservoirId,
    reservoir_id: reservoirId,
    config_version: reservoirId === 1 ? '1.1.0' : '1.0.0',
    upstream_danger: +base.toFixed(1),
    upstream_emergency: +(base + 0.5).toFixed(1),
    upstream_warning: +(base - 0.8).toFixed(1),
    upstream_min: +(base - 13).toFixed(1),
    ideal_min: +(base - 4).toFixed(1),
    ideal_max: +(base - 0.8).toFixed(1),
    downstream_danger: +(269 * scale).toFixed(1),
    downstream_max: +(270 * scale).toFixed(1),
    downstream_min: +(265 * scale).toFixed(1),
    eco_flow_min: reservoirId === 1 ? 5000 : 3000,
    max_level_change_per_hour: 0.5,
    reservoir_area: 15000000,
    shadow_lookahead_steps: 3,
    shadow_danger_offset: 3,
    deadband_percent: reservoirId === 1 ? 0.03 : 0.02,
    max_rate_per_hour: 0.1,
    fusion_l3_confidence: 0.7,
    fusion_l3_risk: 0.3,
    fusion_l2_confidence: 0.5,
    fusion_l2_risk: 0.1,
    gate_max_discharge: [300, 200, 250],
  }
}

const physicsConfigs: Record<number, PhysicsGuardConfig> = {
  1: defaultConfig(1),
  2: defaultConfig(2),
  3: defaultConfig(3),
  4: defaultConfig(4),
}

const physicsHistory: PhysicsGuardHistoryItem[] = [
  {
    id: 3,
    reservoir_id: 1,
    config_version: '1.1.0',
    changed_at: nowStr(180),
    changed_by_name: '张调度',
    description: '汛期提前：紧急水位 380.5m，L3 置信度维持 0.70',
    is_active: true,
    changes: [
      { field: 'upstream_emergency', label: '紧急水位', before: 381.0, after: 380.5 },
      { field: 'upstream_warning', label: '预警水位', before: 379.5, after: 379.2 },
    ],
  },
  {
    id: 2,
    reservoir_id: 1,
    config_version: '1.0.1',
    changed_at: nowStr(2880),
    changed_by_name: '李运维',
    description: '枯水期保水：死区 2% → 3%，减少老旧阀门微动',
    is_active: false,
    changes: [{ field: 'deadband_percent', label: '死区百分比', before: '2%', after: '3%' }],
  },
  {
    id: 1,
    reservoir_id: 1,
    config_version: '1.0.0',
    changed_at: nowStr(10080),
    changed_by_name: '系统管理员',
    description: '初始配置：从 deploy_config 迁移默认值',
    is_active: false,
    changes: [
      { field: 'upstream_emergency', label: '紧急水位', before: '—', after: 381.0 },
      { field: 'fusion_l3_confidence', label: 'L3置信度', before: '—', after: 0.7 },
    ],
  },
]

let interlockRules: GateInterlockRule[] = [
  {
    id: 1,
    reservoir_id: null,
    rule_code: 'spillway_intake_mutex',
    rule_name: '泄洪-发电互斥',
    description: '溢洪道大开时限制发电阀开度',
    enabled: true,
    priority: 1,
    trigger_label: '溢洪道 > 80%',
    action_label: '发电阀 ≤ 50%',
    trigger_count_7d: 12,
  },
  {
    id: 2,
    reservoir_id: null,
    rule_code: 'downstream_impact_protect',
    rule_name: '下游冲击保护',
    description: '两阀同向大增时锁第三阀',
    enabled: true,
    priority: 2,
    trigger_label: '任两阀同增 > 30%',
    action_label: '第三阀禁止同向',
    trigger_count_7d: 5,
  },
  {
    id: 3,
    reservoir_id: null,
    rule_code: 'symmetry_constraint',
    rule_name: '对称性约束',
    description: '防止偏流冲刷坝体',
    enabled: true,
    priority: 3,
    trigger_label: '开度差 > 40%',
    action_label: '强制对齐',
    trigger_count_7d: 3,
  },
  {
    id: 4,
    reservoir_id: null,
    rule_code: 'min_discharge_guarantee',
    rule_name: '最小下泄保障',
    description: '保证下游生态基流',
    enabled: true,
    priority: 4,
    trigger_label: '总开度 < 5%',
    action_label: '禁止再关',
    trigger_count_7d: 1,
  },
  {
    id: 5,
    reservoir_id: 1,
    rule_code: 'downstream_impact_protect',
    rule_name: '下游冲击保护（向家坝）',
    description: '城区段更保守阈值 20%',
    enabled: true,
    priority: 2,
    trigger_label: '任阀增开 > 20%',
    action_label: '锁第三阀',
    trigger_count_7d: 8,
  },
]

let interlockLogs: GateInterlockLog[] = [
  {
    id: 1,
    trigger_time: nowStr(120),
    reservoir_id: 1,
    reservoir_name: '向家坝',
    rule_name: '泄洪-发电互斥',
    rule_code: 'spillway_intake_mutex',
    decision_id: 1,
    upstream_level: 380.8,
    downstream_level: 278.4,
    openings_before: [85, 60, 30],
    openings_after: [85, 50, 30],
    changed_gates: [1],
    reason: '溢洪道85%>80%，发电阀 60%→50%',
  },
  {
    id: 2,
    trigger_time: nowStr(300),
    reservoir_id: 1,
    reservoir_name: '向家坝',
    rule_name: '对称性约束',
    rule_code: 'symmetry_constraint',
    decision_id: 2,
    upstream_level: 380.5,
    downstream_level: 278.2,
    openings_before: [70, 25, 28],
    openings_after: [70, 30, 30],
    changed_gates: [1, 2],
    reason: '溢洪道与泄洪洞开度差 45%>40%',
  },
  {
    id: 3,
    trigger_time: nowStr(1440),
    reservoir_id: 1,
    reservoir_name: '向家坝',
    rule_name: '下游冲击保护',
    rule_code: 'downstream_impact_protect',
    decision_id: 3,
    upstream_level: 381.0,
    downstream_level: 278.6,
    openings_before: [55, 48, 40],
    openings_after: [55, 48, 40],
    changed_gates: [],
    reason: '1、2号阀同增35%，3号阀禁止同向',
  },
]

function diffConfig(
  before: PhysicsGuardConfig,
  after: PhysicsGuardConfig,
): PhysicsGuardHistoryChange[] {
  const changes: PhysicsGuardHistoryChange[] = []
  const keys = Object.keys(FIELD_LABELS) as (keyof PhysicsGuardConfig)[]
  for (const key of keys) {
    const b = before[key]
    const a = after[key]
    if (JSON.stringify(b) !== JSON.stringify(a)) {
      changes.push({
        field: key,
        label: FIELD_LABELS[key] ?? key,
        before: b as string | number,
        after: a as string | number,
      })
    }
  }
  return changes
}

function syncHistoryActive(reservoirId: number) {
  const ver = physicsConfigs[reservoirId]?.config_version
  physicsHistory
    .filter((h) => h.reservoir_id === reservoirId)
    .forEach((h) => {
      h.is_active = h.config_version === ver
    })
}

function configToSummary(cfg: PhysicsGuardConfig): PhysicsGuardSummary {
  return {
    reservoir_id: cfg.reservoir_id,
    reservoir_name: RESERVOIR_NAMES[cfg.reservoir_id] ?? '未知水库',
    config_version: cfg.config_version,
    is_active: true,
    upstream_emergency: cfg.upstream_emergency,
    upstream_danger: cfg.upstream_danger,
    upstream_warning: cfg.upstream_warning,
    fusion_l3_confidence: cfg.fusion_l3_confidence,
    fusion_l3_risk: cfg.fusion_l3_risk,
    last_sync_at: nowStr(0),
    sync_status: 'synced',
  }
}

export const gateaiSharedStore = {
  getReservoirs() {
    return RESERVOIR_OPTIONS
  },

  getPhysicsConfig(reservoirId: number) {
    if (!physicsConfigs[reservoirId]) physicsConfigs[reservoirId] = defaultConfig(reservoirId)
    return { ...physicsConfigs[reservoirId], reservoir_id: reservoirId }
  },

  getPhysicsSummary(reservoirId = 1) {
    return configToSummary(this.getPhysicsConfig(reservoirId))
  },

  getPhysicsHistory(reservoirId: number) {
    syncHistoryActive(reservoirId)
    return physicsHistory.filter((h) => h.reservoir_id === reservoirId)
  },

  getPhysicsHistoryVersions(reservoirId: number) {
    return this.getPhysicsHistory(reservoirId).map((h) => ({
      id: h.id,
      config_version: h.config_version,
      changed_at: h.changed_at,
      description: h.description,
    }))
  },

  savePhysicsConfig(
    config: PhysicsGuardConfig,
    meta?: { description?: string; changed_by_name?: string },
  ) {
    const prev = physicsConfigs[config.reservoir_id]
    const newVersion = bumpVersion(prev?.config_version ?? config.config_version)
    const saved = { ...config, config_version: newVersion }
    physicsConfigs[config.reservoir_id] = saved
    if (prev) {
      const changes = diffConfig(prev, saved)
      if (changes.length) {
        physicsHistory.unshift({
          id: Date.now(),
          reservoir_id: config.reservoir_id,
          config_version: newVersion,
          changed_at: nowStr(0),
          changed_by_name: meta?.changed_by_name ?? '当前用户',
          description: meta?.description ?? `配置更新至 v${newVersion}`,
          is_active: true,
          changes,
        })
        syncHistoryActive(config.reservoir_id)
      }
    }
    return saved
  },

  rollbackPhysics(reservoirId: number, historyId: number) {
    const item = physicsHistory.find((h) => h.id === historyId && h.reservoir_id === reservoirId)
    if (!item) throw new Error('not found')
    const current = this.getPhysicsConfig(reservoirId)
    const restored = { ...current, config_version: item.config_version }
    for (const ch of item.changes) {
      const key = ch.field as keyof PhysicsGuardConfig
      if (key in restored && ch.after !== '—') {
        const val = ch.after
        ;(restored as Record<string, unknown>)[key] =
          typeof val === 'string' && !Number.isNaN(Number(val)) ? Number(val) : val
      }
    }
    physicsConfigs[reservoirId] = restored
    syncHistoryActive(reservoirId)
    return restored
  },

  clonePhysics(fromId: number, toId: number, version?: string) {
    let src = this.getPhysicsConfig(fromId)
    if (version) {
      const hist = physicsHistory.find(
        (h) => h.reservoir_id === fromId && h.config_version === version,
      )
      if (hist) {
        src = { ...src, config_version: hist.config_version }
        for (const ch of hist.changes) {
          const key = ch.field as keyof PhysicsGuardConfig
          if (key in src && ch.after !== '—') {
            const val = ch.after
            ;(src as Record<string, unknown>)[key] =
              typeof val === 'string' && !Number.isNaN(Number(val)) ? Number(val) : val
          }
        }
      }
    }
    const cloned: PhysicsGuardConfig = {
      ...JSON.parse(JSON.stringify(src)),
      id: Date.now(),
      reservoir_id: toId,
      config_version: '1.0.0',
    }
    physicsConfigs[toId] = cloned
    return cloned
  },

  getInterlockRules(reservoirId: number) {
    return interlockRules
      .filter((r) => r.reservoir_id == null || r.reservoir_id === reservoirId)
      .sort((a, b) => a.priority - b.priority)
  },

  toggleInterlockRule(ruleId: number, enabled: boolean) {
    const rule = interlockRules.find((r) => r.id === ruleId)
    if (rule) rule.enabled = enabled
  },

  updateInterlockRule(ruleId: number, patch: Partial<GateInterlockRule>) {
    const rule = interlockRules.find((r) => r.id === ruleId)
    if (rule) Object.assign(rule, patch)
    return rule ?? null
  },

  createInterlockRule(data: Omit<GateInterlockRule, 'id' | 'trigger_count_7d'>) {
    const rule: GateInterlockRule = { ...data, id: Date.now(), trigger_count_7d: 0 }
    interlockRules.push(rule)
    return rule
  },

  reorderInterlockRules(orderedIds: number[]) {
    orderedIds.forEach((id, idx) => {
      const rule = interlockRules.find((r) => r.id === id)
      if (rule) rule.priority = idx + 1
    })
  },

  getInterlockLogs(params?: {
    reservoirId?: number
    ruleCodes?: string[]
    startTime?: string
    endTime?: string
  }) {
    let list = [...interlockLogs]
    if (params?.reservoirId) {
      const name = RESERVOIR_NAMES[params.reservoirId]
      list = list.filter((l) => l.reservoir_name === name)
    }
    if (params?.ruleCodes?.length) {
      list = list.filter((l) => params.ruleCodes!.includes(l.rule_code ?? ''))
    }
    if (params?.startTime) {
      const s = new Date(params.startTime).getTime()
      list = list.filter((l) => new Date(l.trigger_time).getTime() >= s)
    }
    if (params?.endTime) {
      const e = new Date(params.endTime).getTime()
      list = list.filter((l) => new Date(l.trigger_time).getTime() <= e)
    }
    return list.sort(
      (a, b) => new Date(b.trigger_time).getTime() - new Date(a.trigger_time).getTime(),
    )
  },

  getInterlockStats(reservoirId: number) {
    const rules = this.getInterlockRules(reservoirId)
    const name = RESERVOIR_NAMES[reservoirId]
    const logs24h = interlockLogs.filter((l) => {
      if (l.reservoir_name !== name) return false
      return Date.now() - new Date(l.trigger_time).getTime() < 86400000
    })
    return {
      enabled_count: rules.filter((r) => r.enabled).length,
      trigger_24h: logs24h.length,
      trigger_7d: rules.reduce((s, r) => s + r.trigger_count_7d, 0),
    }
  },

  getEdgeSyncStatus(edgeNodeId: number) {
    const cfg = this.getPhysicsConfig(1)
    return {
      edge_node_id: edgeNodeId,
      config_version: cfg.config_version,
      last_sync_at: nowStr(5),
      sync_status: 'synced' as const,
    }
  },
}
