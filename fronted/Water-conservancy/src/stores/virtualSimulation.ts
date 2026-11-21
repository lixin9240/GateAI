// ============================================================
// 水情虚拟仿真 — 全局状态（跨页面联动）
// ============================================================
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { RealtimeKpi } from '@/types/monitoring'
import type { GateNodeControl } from '@/types/gateControl'
import {
  clampOpening,
  computeSimulationDerived,
  scaleGateOpening,
  type SimulationBaseline,
} from '@/utils/virtualSimulationEngine'

export const useVirtualSimulationStore = defineStore('virtualSimulation', () => {
  const active = ref(false)
  const locked = ref(false)

  const baseline = ref<SimulationBaseline>({
    // 向家坝量级默认值（与数字孪生实时工况一致，避免启用后跳到错误水位）
    upstreamLevel: 380.0,
    downstreamLevel: 278.4,
    inflowRate: 1850,
    outflowRate: 1850,
    gateOpening: 45,
  })

  const upstreamLevel = ref(baseline.value.upstreamLevel)
  const downstreamLevel = ref(baseline.value.downstreamLevel)
  const rainfall = ref(0)

  /** 应用后按工况静态计算，不再随机微动 */
  const derived = computed(() =>
    computeSimulationDerived({
      upstreamLevel: upstreamLevel.value,
      downstreamLevel: downstreamLevel.value,
      rainfall: rainfall.value,
      baseline: baseline.value,
    }),
  )

  function initBaselineFromKpi(kpi: RealtimeKpi) {
    if (active.value) return
    // 若接口上下游反挂，按常用向家坝量级纠正，避免剖面水头差为负
    let up = kpi.upstreamLevel
    let down = kpi.downstreamLevel
    if (down > up) {
      ;[up, down] = [down, up]
    }
    baseline.value = {
      upstreamLevel: up,
      downstreamLevel: down,
      inflowRate: kpi.inflowRate,
      outflowRate: kpi.outflowRate,
      gateOpening: Math.max(kpi.gateOpening, 5),
    }
    upstreamLevel.value = up
    downstreamLevel.value = down
  }

  function applySimulation() {
    active.value = true
  }

  function resetSimulation() {
    active.value = false
    upstreamLevel.value = baseline.value.upstreamLevel
    downstreamLevel.value = baseline.value.downstreamLevel
    rainfall.value = 0
  }

  function toggleLock() {
    locked.value = !locked.value
  }

  /** 单孔开度：工况缩放后固定显示 */
  function overlayGateOpening(_gateId: number, opening: number): number {
    if (!active.value) return opening
    return clampOpening(scaleGateOpening(opening, derived.value.gateScale))
  }

  /** 将仿真结果叠加到 KPI */
  function overlayKpi(kpi: RealtimeKpi): RealtimeKpi {
    if (!active.value) return kpi
    const d = derived.value
    return {
      ...kpi,
      upstreamLevel: d.upstreamLevel,
      downstreamLevel: d.downstreamLevel,
      inflowRate: d.inflowRate,
      outflowRate: d.outflowRate,
      gateOpening: d.aggregateOpening,
    }
  }

  /** 将仿真结果叠加到阀门节点 */
  function overlayGates(gates: GateNodeControl[]): GateNodeControl[] {
    if (!active.value) return gates
    const scale = derived.value.gateScale
    return gates.map((g) => {
      const cur = clampOpening(scaleGateOpening(g.currentOpening, scale))
      const tgt = clampOpening(scaleGateOpening(g.targetOpening, scale))
      const head = Math.max(0, derived.value.upstreamLevel - derived.value.downstreamLevel)
      const flowFactor =
        head / Math.max(1, baseline.value.upstreamLevel - baseline.value.downstreamLevel)
      return {
        ...g,
        currentOpening: cur,
        targetOpening: tgt,
        flowRate: Math.round(g.flowRate * flowFactor * (cur / Math.max(g.currentOpening, 1))),
      }
    })
  }

  return {
    active,
    locked,
    baseline,
    upstreamLevel,
    downstreamLevel,
    rainfall,
    derived,
    initBaselineFromKpi,
    applySimulation,
    resetSimulation,
    toggleLock,
    overlayGateOpening,
    overlayKpi,
    overlayGates,
  }
})
