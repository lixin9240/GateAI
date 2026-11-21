// ============================================================
// 阀门互锁规则 — Pinia Store
// 按需求文档 5.11 节结构
// ============================================================
import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'

export interface InterlockRule {
  id: number
  code: string
  name: string
  description: string
  scope: 'global' | 'reservoir'
  reservoir_id: number | null
  reservoir_name?: string
  priority: number
  trigger_conditions: InterlockCondition[]
  constraint_actions: InterlockConstraint[]
  is_enabled: boolean
  trigger_count_7d: number
  updated_at: string
}

export interface InterlockCondition {
  id?: number
  field: string
  operator: string
  threshold: number
}

export interface InterlockConstraint {
  id?: number
  field: string
  type: string
  threshold: number
}

export interface InterlockLog {
  id: number
  triggered_at: string
  reservoir_id: number
  reservoir_name: string
  rule_name: string
  water_snapshot: {
    upstream_level: number
    downstream_level: number
    inflow_rate: number
  }
  gate_opening_before: number[]
  gate_opening_after: number[]
  dispatch_id: number | null
  action_detail: Record<string, unknown>
}

export interface InterlockStats {
  rule_id: number
  rule_name: string
  trigger_count: number
  last_triggered: string
}

export const useGateInterlockStore = defineStore('gateInterlock', () => {
  const currentReservoirId = ref<number>(1)

  // 规则列表
  const rules = ref<InterlockRule[]>([])
  const rulesLoading = ref(false)

  // 触发日志
  const logs = ref<InterlockLog[]>([])
  const logsLoading = ref(false)
  const logsPagination = reactive({ page: 1, pageSize: 15, total: 0 })
  const logsFilters = reactive({
    reservoirId: null as number | null,
    ruleIds: [] as number[],
    start: null as string | null,
    end: null as string | null,
  })

  // 触发统计
  const stats = ref<InterlockStats[]>([])
  const statsLoading = ref(false)

  // ── Actions ──

  function setReservoir(id: number) {
    currentReservoirId.value = id
  }

  function setRules(data: InterlockRule[]) {
    rules.value = data
  }

  function updateRulePriority(ruleId: number, newPriority: number) {
    const rule = rules.value.find((r) => r.id === ruleId)
    if (rule) rule.priority = newPriority
  }

  function setLogs(data: InterlockLog[], total: number) {
    logs.value = data
    logsPagination.total = total
  }

  function setStats(data: InterlockStats[]) {
    stats.value = data
  }

  function setLogsFilters(filters: Partial<typeof logsFilters>) {
    Object.assign(logsFilters, filters)
  }

  function reset() {
    rules.value = []
    logs.value = []
    stats.value = []
    logsPagination.page = 1
    logsPagination.total = 0
    logsFilters.reservoirId = null
    logsFilters.ruleIds = []
    logsFilters.start = null
    logsFilters.end = null
  }

  return {
    currentReservoirId,
    rules,
    rulesLoading,
    logs,
    logsLoading,
    logsPagination,
    logsFilters,
    stats,
    statsLoading,
    setReservoir,
    setRules,
    updateRulePriority,
    setLogs,
    setStats,
    setLogsFilters,
    reset,
  }
})
