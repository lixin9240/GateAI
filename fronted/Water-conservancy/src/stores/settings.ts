// ============================================================
// 系统设置 — Pinia Store
// 按需求文档 5.11 节结构
// ============================================================
import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'
import { fetchThresholdList, getWeights, getWeightHistory, getModels, getUsers } from '@/api/settings'
import type { ThresholdRule, WeightConfig, ModelInfo, SystemUser } from '@/shared/types'

export interface WeightHistoryItem {
  id: number
  power_weight: number
  safety_weight: number
  ecology_weight: number
  operator: string
  changed_at: string
}

export const useSettingsStore = defineStore('settings', () => {
  // ── Tab1: 告警阈值 ──
  const thresholds = ref<ThresholdRule[]>([])
  const thresholdsLoading = ref(false)

  // ── Tab2: 多目标权重 ──
  const weights = ref<WeightConfig | null>(null)
  const weightForm = reactive({ power: 0.4, safety: 0.35, eco: 0.25 })
  const weightsLoading = ref(false)
  const weightHistory = ref<WeightHistoryItem[]>([])

  // ── Tab3: 模型管理 ──
  const modelList = ref<ModelInfo[]>([])
  const modelListLoading = ref(false)
  const modelPagination = reactive({ page: 1, pageSize: 10, total: 0 })

  // ── Tab7: 用户管理 ──
  const userList = ref<SystemUser[]>([])
  const userListLoading = ref(false)
  const userPagination = reactive({ page: 1, pageSize: 10, total: 0 })

  // ── Actions ──

  async function fetchThresholds(reservoirId = 1) {
    thresholdsLoading.value = true
    try {
      thresholds.value = await fetchThresholdList(reservoirId)
    } catch {
      thresholds.value = []
    } finally {
      thresholdsLoading.value = false
    }
  }

  async function fetchWeights() {
    weightsLoading.value = true
    try {
      const res = await getWeights()
      if (res.data.code === 0 && res.data.data) {
        weights.value = res.data.data
        weightForm.power = res.data.data.power_weight
        weightForm.safety = res.data.data.safety_weight
        weightForm.eco = res.data.data.ecology_weight
      }
    } catch {
      /* fallback */
    }
    weightsLoading.value = false
  }

  async function fetchWeightHistory() {
    try {
      const res = await getWeightHistory()
      if (res.data.code === 0 && res.data.data) {
        weightHistory.value = res.data.data
      }
    } catch {
      /* ignore */
    }
  }

  async function fetchModels(params?: { page?: number; keyword?: string }) {
    modelListLoading.value = true
    try {
      const res = await getModels({
        page: params?.page ?? modelPagination.page,
        page_size: modelPagination.pageSize,
        keyword: params?.keyword,
      })
      if (res.data.code === 0 && res.data.data) {
        modelList.value = res.data.data.list
        modelPagination.total = res.data.data.total
      }
    } catch {
      /* fallback */
    }
    modelListLoading.value = false
  }

  async function fetchUsers(params?: { page?: number; keyword?: string }) {
    userListLoading.value = true
    try {
      const res = await getUsers({
        page: params?.page ?? userPagination.page,
        page_size: userPagination.pageSize,
        keyword: params?.keyword,
      })
      if (res.data.code === 0 && res.data.data) {
        userList.value = res.data.data.list
        userPagination.total = res.data.data.total
      }
    } catch {
      /* fallback */
    }
    userListLoading.value = false
  }

  function reset() {
    thresholds.value = []
    weights.value = null
    weightForm.power = 0.4
    weightForm.safety = 0.35
    weightForm.eco = 0.25
    weightHistory.value = []
    modelList.value = []
    userList.value = []
  }

  return {
    thresholds,
    thresholdsLoading,
    weights,
    weightForm,
    weightsLoading,
    weightHistory,
    modelList,
    modelListLoading,
    modelPagination,
    userList,
    userListLoading,
    userPagination,
    fetchThresholds,
    fetchWeights,
    fetchWeightHistory,
    fetchModels,
    fetchUsers,
    reset,
  }
})
