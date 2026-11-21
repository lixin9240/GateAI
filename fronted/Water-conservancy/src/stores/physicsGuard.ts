// ============================================================
// 物理防护配置 — Pinia Store
// 按需求文档 5.11 节结构
// ============================================================
import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface PhysicsGuardConfig {
  id: number
  reservoir_id: number
  version: string
  // 上游水位阈值
  upstream_danger: number
  upstream_emergency: number
  upstream_warning: number
  upstream_min: number
  ideal_min: number
  ideal_max: number
  // 下游水位阈值
  downstream_danger: number
  downstream_max: number
  downstream_min: number
  // 生态流量
  eco_flow_min: number
  // 物理参数
  reservoir_area: number
  max_level_change_per_hour: number
  // 影子水位模型
  shadow_lookahead_steps: number
  shadow_danger_offset: number
  // 指令平滑
  deadband_percent: number
  max_rate_per_hour: number
  // 熔断阈值
  fusion_l3_confidence: number
  fusion_l3_risk: number
  fusion_l2_confidence: number
  fusion_l2_risk: number
  // 阀门参数
  gate_max_discharge: number[]
}

export interface ConfigHistoryItem {
  id: number
  reservoir_id: number
  version: string
  changed_by: string
  changed_at: string
  description: string
  config_snapshot: PhysicsGuardConfig
}

export const usePhysicsGuardStore = defineStore('physicsGuard', () => {
  const currentReservoirId = ref<number>(1)
  const currentConfig = ref<PhysicsGuardConfig | null>(null)
  const configLoading = ref(false)

  // 表单编辑状态
  const editedConfig = ref<PhysicsGuardConfig | null>(null)
  const isDirty = ref(false)

  // 变更历史
  const historyList = ref<ConfigHistoryItem[]>([])
  const historyLoading = ref(false)

  // ── Actions ──

  function setReservoir(id: number) {
    currentReservoirId.value = id
  }

  function setConfig(config: PhysicsGuardConfig) {
    currentConfig.value = config
    editedConfig.value = JSON.parse(JSON.stringify(config))
    isDirty.value = false
  }

  function markDirty() {
    isDirty.value = true
  }

  function resetEdit() {
    if (currentConfig.value) {
      editedConfig.value = JSON.parse(JSON.stringify(currentConfig.value))
    }
    isDirty.value = false
  }

  function setHistory(data: ConfigHistoryItem[]) {
    historyList.value = data
  }

  function reset() {
    currentConfig.value = null
    editedConfig.value = null
    isDirty.value = false
    historyList.value = []
  }

  return {
    currentReservoirId,
    currentConfig,
    configLoading,
    editedConfig,
    isDirty,
    historyList,
    historyLoading,
    setReservoir,
    setConfig,
    markDirty,
    resetEdit,
    setHistory,
    reset,
  }
})
