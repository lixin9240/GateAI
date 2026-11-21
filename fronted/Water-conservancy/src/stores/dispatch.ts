// ============================================================
// 调度决策 — 跨子页共享状态（运行控制 / 节点控制 / 决策分析）
// ============================================================
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { DecisionDetail, DispatchStatus, PredictionData } from '@/types/dispatch'
import type { GateNodeControl } from '@/types/gateControl'
import {
  fetchDispatchStatus,
  fetchDecisionDetail,
  fetchPrediction,
  postExecute,
  postCancelExecute,
  postAcceptDecision,
  postIgnoreDecision,
  putDispatchMode,
  putAutoLevel,
  fetchCommandTrace,
  postGateExecute,
  postGateExecuteBatch,
} from '@/api/dispatchPage'
import { fetchGates, fetchRealtimeKpi } from '@/api/monitoring'
import {
  IN_PROGRESS_COMMAND_STATUSES,
  PENDING_COMMAND_STORAGE_KEY,
} from '@/constants/dispatch'
import {
  buildGateNode,
  calcAggregateOpening,
  calcImpactPreview,
  distributeTotalOpening,
  isGateOnline,
  pendingChangeCount,
  precheckGateChanges,
} from '@/utils/gateControl'
import { clearGateOpeningOverrides, saveGateOpeningOverrides } from '@/utils/gateOpeningPersist'
import type { GatePrecheckResult } from '@/types/gateControl'
import { useVirtualSimulationStore } from '@/stores/virtualSimulation'

export const useDispatchStore = defineStore('dispatch', () => {
  const status = ref<DispatchStatus>({
    mode: 'auto',
    autoLevel: 2,
    upstreamLevel: 380.65,
    downstreamLevel: 278.42,
    flowRate: 1920,
    gateOpening: 45,
    lastDispatchAt: null,
    isExecuting: false,
    executingTarget: null,
  })
  const decision = ref<DecisionDetail | null>(null)
  const prediction = ref<PredictionData | null>(null)
  const gates = ref<GateNodeControl[]>([])
  const targetOpening = ref(45)
  const userModifiedTarget = ref(false)
  const selectedGateId = ref<number | null>(null)
  const gatesInitialLoading = ref(false)
  const coreReady = ref(false)
  const precheckResult = ref<GatePrecheckResult | null>(null)
  const pendingCommandId = ref<string | null>(null)

  let coreLoadPromise: Promise<void> | null = null
  let gatesRefreshPromise: Promise<void> | null = null

  const outflowRate = ref(0)

  const canManualControl = computed(
    () => status.value.mode === 'manual' || status.value.autoLevel === 1,
  )

  const aggregateOpening = computed(() => {
    const fromGates = calcAggregateOpening(gates.value, false)
    return fromGates ?? status.value.gateOpening
  })

  const aggregateTargetOpening = computed(
    () => calcAggregateOpening(gates.value, true) ?? aggregateOpening.value,
  )

  const selectedGate = computed(
    () => gates.value.find((g) => g.id === selectedGateId.value) ?? null,
  )

  const pendingChanges = computed(() => pendingChangeCount(gates.value))

  const head = computed(
    () => Math.max(0, status.value.upstreamLevel - status.value.downstreamLevel),
  )

  const impactPreview = computed(() =>
    calcImpactPreview(gates.value, status.value.flowRate, head.value),
  )

  const interlockOk = computed(() => precheckResult.value?.passed !== false)

  function applySavedMode() {
    const saved = localStorage.getItem('dispatch_mode')
    if (saved === 'manual' || saved === 'auto') {
      status.value.mode = saved
    } else if (import.meta.env.DEV) {
      status.value.mode = 'manual'
    }
    const savedLevel = Number(localStorage.getItem('dispatch_auto_level'))
    if (savedLevel === 1 || savedLevel === 2 || savedLevel === 3) {
      status.value.autoLevel = savedLevel
    }
  }

  /** 切换三级自动执行权限（本地立即生效；接口无实现时仍走 mock/本地） */
  async function switchAutoLevel(level: 1 | 2 | 3) {
    try {
      await putAutoLevel(level)
    } catch {
      /* 接口失败仍写本地，避免界面点了没反应 */
    }
    // 整对象替换，避免个别环境里字段写了但视图不刷新
    status.value = {
      ...status.value,
      autoLevel: level,
    }
    localStorage.setItem('dispatch_auto_level', String(level))
  }

  function restorePendingCommand() {
    try {
      const raw = sessionStorage.getItem(PENDING_COMMAND_STORAGE_KEY)
      if (!raw) return
      const saved = JSON.parse(raw) as { commandId?: string; target?: number }
      if (saved.commandId) pendingCommandId.value = saved.commandId
      if (saved.target != null) {
        status.value.isExecuting = true
        status.value.executingTarget = saved.target
      }
    } catch { /* ignore */ }
  }

  function savePendingCommand(commandId: string, target: number) {
    pendingCommandId.value = commandId
    sessionStorage.setItem(
      PENDING_COMMAND_STORAGE_KEY,
      JSON.stringify({ commandId, target }),
    )
  }

  function clearExecutingState() {
    pendingCommandId.value = null
    sessionStorage.removeItem(PENDING_COMMAND_STORAGE_KEY)
    status.value.isExecuting = false
    status.value.executingTarget = null
  }

  async function syncExecutingFromTrace() {
    if (!pendingCommandId.value) return
    try {
      const res = await fetchCommandTrace(pendingCommandId.value)
      const trace = res.data
      if (!trace) return
      const inProgress = (IN_PROGRESS_COMMAND_STATUSES as readonly string[]).includes(trace.status)
      if (inProgress) {
        status.value.isExecuting = true
        status.value.executingTarget = trace.target_opening
      } else {
        clearExecutingState()
      }
    } catch { /* 追踪失败时保留本地执行中状态 */ }
  }

  async function executeOpening(targetOpening: number, decisionId?: number) {
    const res = await postExecute(targetOpening, decisionId)
    status.value.isExecuting = true
    status.value.executingTarget = targetOpening
    status.value.lastDispatchAt = new Date().toISOString()
    const commandId = res.data?.command_id
    if (commandId) savePendingCommand(commandId, targetOpening)
    return res
  }

  async function cancelExecuting() {
    try {
      await postCancelExecute(pendingCommandId.value ?? undefined)
    } finally {
      clearExecutingState()
    }
  }

  restorePendingCommand()

  async function refreshGates(options?: { silent?: boolean }) {
    if (gatesRefreshPromise) return gatesRefreshPromise

    const silent = options?.silent ?? false
    const showLoading = !silent && gates.value.length === 0
    if (showLoading) gatesInitialLoading.value = true

    gatesRefreshPromise = (async () => {
      const simStore = useVirtualSimulationStore()
      const prevTargets = new Map(gates.value.map((g) => [g.id, g.targetOpening]))
      const hadPending = pendingChangeCount(gates.value) > 0
      try {
        const [gateList, kpiRaw] = await Promise.all([fetchGates(1), fetchRealtimeKpi(1)])
        simStore.initBaselineFromKpi(kpiRaw)
        const kpi = simStore.overlayKpi(kpiRaw)
        // 阀门列表存接口原值；仿真缩放/抖动仅在展示层 overlay，避免重复叠加
        gates.value = gateList.map(buildGateNode)
        if (hadPending) {
          gates.value = gates.value.map((g) => ({
            ...g,
            targetOpening: prevTargets.get(g.id) ?? g.targetOpening,
          }))
        }
        if (!selectedGateId.value && gates.value.length) {
          selectedGateId.value = gates.value.find((g) => isGateOnline(g.status))?.id ?? gates.value[0].id
        }
        status.value.upstreamLevel = kpi.upstreamLevel
        status.value.downstreamLevel = kpi.downstreamLevel
        status.value.flowRate = kpi.inflowRate
        outflowRate.value = kpi.outflowRate
        const forAgg = simStore.active ? simStore.overlayGates(gates.value) : gates.value
        const agg = calcAggregateOpening(forAgg, false)
        if (agg != null) status.value.gateOpening = agg
        precheckResult.value = precheckGateChanges(gates.value, head.value)
      } finally {
        if (showLoading) gatesInitialLoading.value = false
      }
    })().finally(() => {
      gatesRefreshPromise = null
    })

    return gatesRefreshPromise
  }

  async function refreshCore(predictTerm: 1 | 2 | 3 = 2) {
    const wasExecuting = status.value.isExecuting
    const savedTarget = status.value.executingTarget
    const [st, dec, pred] = await Promise.all([
      fetchDispatchStatus(),
      fetchDecisionDetail(),
      fetchPrediction(predictTerm),
    ])
    status.value = {
      ...status.value,
      ...st.data,
      autoLevel: status.value.autoLevel,
    }
    if (pendingCommandId.value) {
      await syncExecutingFromTrace()
    } else if (wasExecuting && !st.data.isExecuting) {
      status.value.isExecuting = wasExecuting
      status.value.executingTarget = savedTarget
    }
    applySavedMode()
    decision.value = dec.data
    prediction.value = pred.data
    if (dec.data && !userModifiedTarget.value && !st.data.isExecuting) {
      targetOpening.value = dec.data.recommended_opening
    }
    await refreshGates({ silent: gates.value.length > 0 })
    coreReady.value = true
  }

  async function ensureCoreLoaded(predictTerm: 1 | 2 | 3 = 2, force = false) {
    if (coreReady.value && !force) return
    if (coreLoadPromise) return coreLoadPromise
    coreLoadPromise = refreshCore(predictTerm).finally(() => {
      coreLoadPromise = null
    })
    return coreLoadPromise
  }

  function persistGateOpenings() {
    saveGateOpeningOverrides(gates.value)
  }

  function setGateTarget(gateId: number, opening: number) {
    gates.value = gates.value.map((g) =>
      g.id === gateId ? { ...g, targetOpening: Math.min(100, Math.max(0, opening)) } : g,
    )
    precheckResult.value = precheckGateChanges(gates.value, head.value)
    persistGateOpenings()
  }

  /** 开/关即时生效：实际与目标一起到位，并尽量下发接口 */
  async function applyGateOpenClose(gateId: number, open: boolean) {
    const opening = open ? 100 : 0
    gates.value = gates.value.map((g) =>
      g.id === gateId
        ? { ...g, currentOpening: opening, targetOpening: opening, status: 'online' as const }
        : g,
    )
    const agg = calcAggregateOpening(gates.value, false)
    if (agg != null) status.value.gateOpening = agg
    precheckResult.value = precheckGateChanges(gates.value, head.value)
    persistGateOpenings()
    try {
      await postGateExecute(gateId, opening)
    } catch {
      /* 接口失败时仍保留本地即时状态，便于演示 */
    }
  }

  function resetGateTarget(gateId: number) {
    const g = gates.value.find((x) => x.id === gateId)
    if (g) setGateTarget(gateId, g.currentOpening)
  }

  function resetAllGateTargets() {
    gates.value = gates.value.map((g) => ({ ...g, targetOpening: g.currentOpening }))
    precheckResult.value = precheckGateChanges(gates.value, head.value)
    clearGateOpeningOverrides()
  }

  function syncGroupTargets(group: GateNodeControl['group'], opening: number) {
    gates.value = gates.value.map((g) =>
      g.group === group && isGateOnline(g.status)
        ? { ...g, targetOpening: opening }
        : g,
    )
    precheckResult.value = precheckGateChanges(gates.value, head.value)
    persistGateOpenings()
  }

  /** 表孔一键拉齐：以在线表孔当前开度均值（或指定值）同步目标 */
  function alignSurfaceTargets(opening?: number) {
    const surface = gates.value.filter((g) => g.group === 'surface' && isGateOnline(g.status))
    if (!surface.length) return
    const val = opening ?? Math.round(
      surface.reduce((s, g) => s + g.currentOpening, 0) / surface.length,
    )
    syncGroupTargets('surface', Math.min(100, Math.max(0, val)))
  }

  function applyTotalOpeningDistribution(targetTotal: number, surfaceOnly = false) {
    gates.value = distributeTotalOpening(gates.value, targetTotal, surfaceOnly)
    precheckResult.value = precheckGateChanges(gates.value, head.value)
    persistGateOpenings()
  }

  function applyExecutedOpenings(ids: Set<number>) {
    gates.value = gates.value.map((g) =>
      ids.has(g.id)
        ? { ...g, status: 'online' as const, currentOpening: g.targetOpening }
        : g,
    )
    const agg = calcAggregateOpening(gates.value, false)
    if (agg != null) status.value.gateOpening = agg
    precheckResult.value = precheckGateChanges(gates.value, head.value)
    persistGateOpenings()
  }

  /** 单孔执行：优先 POST /v1/dispatch/gate-execute */
  async function mockExecuteGate(gateId: number) {
    const idx = gates.value.findIndex((g) => g.id === gateId)
    if (idx < 0) return
    const node = gates.value[idx]
    gates.value[idx] = { ...node, status: 'executing' }
    try {
      await postGateExecute(node.id, node.targetOpening)
      applyExecutedOpenings(new Set([gateId]))
    } catch (e) {
      gates.value[idx] = { ...gates.value[idx], status: 'online' }
      throw e
    }
  }

  /** 批量提交：优先 POST /v1/dispatch/gate-execute/batch */
  async function mockBatchExecute() {
    const changed = gates.value.filter(
      (g) => isGateOnline(g.status) && g.targetOpening !== g.currentOpening,
    )
    if (!changed.length) return
    const ids = new Set(changed.map((g) => g.id))
    gates.value = gates.value.map((g) =>
      ids.has(g.id) ? { ...g, status: 'executing' as const } : g,
    )
    try {
      await postGateExecuteBatch(
        changed.map((g) => ({
          equipment_id: g.id,
          target_opening: g.targetOpening,
        })),
      )
      applyExecutedOpenings(ids)
    } catch (e) {
      gates.value = gates.value.map((g) =>
        ids.has(g.id) ? { ...g, status: 'online' as const } : g,
      )
      throw e
    }
  }

  async function switchDispatchMode(mode: 'auto' | 'manual') {
    const res = await putDispatchMode(mode)
    status.value.mode = res.data?.mode ?? mode
    localStorage.setItem('dispatch_mode', status.value.mode)
    return res
  }

  return {
    status,
    decision,
    prediction,
    gates,
    targetOpening,
    userModifiedTarget,
    selectedGateId,
    gatesInitialLoading,
    coreReady,
    precheckResult,
    outflowRate,
    canManualControl,
    aggregateOpening,
    aggregateTargetOpening,
    selectedGate,
    pendingChanges,
    head,
    impactPreview,
    interlockOk,
    refreshCore,
    ensureCoreLoaded,
    refreshGates,
    setGateTarget,
    applyGateOpenClose,
    resetGateTarget,
    resetAllGateTargets,
    syncGroupTargets,
    alignSurfaceTargets,
    applyTotalOpeningDistribution,
    mockExecuteGate,
    mockBatchExecute,
    applySavedMode,
    executeOpening,
    cancelExecuting,
    syncExecutingFromTrace,
    pendingCommandId,
    // re-export API helpers for pages
    postExecute,
    postCancelExecute,
    postAcceptDecision,
    postIgnoreDecision,
    putDispatchMode: switchDispatchMode,
    putAutoLevel: switchAutoLevel,
  }
})
