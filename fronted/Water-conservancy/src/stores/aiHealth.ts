// ============================================================
// AI 模型健康度 — Pinia Store
// 按需求文档 5.11 节结构
// ============================================================
import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'

export interface HealthOverview {
  overallScore: number
  healthGrade: 'S' | 'A' | 'B' | 'C' | 'D'
  waterLevelMAE: number
  safetyCoverageRate: number
  decisionAutoRate: number
}

export interface TrendPoint {
  time: string
  overall: number
  prediction: number
  decision: number
  compliance: number
}

export interface MetricsDetailItem {
  hour: string
  prediction_score: number
  decision_score: number
  compliance_score: number
  overall_score: number
  health_grade: string
  water_level_mae: number
  safety_coverage_rate: number
  decision_auto_rate: number
}

export interface CompareResult {
  version1: HealthOverview & { version: string }
  version2: HealthOverview & { version: string }
  radarData: {
    dimension: string
    v1: number
    v2: number
  }[]
  scoreDiff: {
    dimension: string
    v1: number
    v2: number
    diff: number
  }[]
}

export const useAIHealthStore = defineStore('aiHealth', () => {
  // ── 当前水库 ──
  const currentReservoirId = ref<number>(1)

  // ── 概览指标 ──
  const overview = ref<HealthOverview | null>(null)
  const overviewLoading = ref(false)

  // ── 历史趋势 ──
  const trendData = ref<TrendPoint[]>([])
  const trendLoading = ref(false)

  // ── 明细数据 ──
  const metricsDetail = ref<MetricsDetailItem[]>([])
  const metricsDetailLoading = ref(false)
  const metricsPagination = reactive({ page: 1, pageSize: 24, total: 0 })

  // ── 版本对比 ──
  const compareVersions = reactive({ version1: '', version2: '' })
  const compareData = ref<CompareResult | null>(null)
  const compareLoading = ref(false)

  // ── Actions ──

  function setReservoir(id: number) {
    currentReservoirId.value = id
  }

  function setOverview(data: HealthOverview) {
    overview.value = data
  }

  function setTrendData(data: TrendPoint[]) {
    trendData.value = data
  }

  function setMetricsDetail(data: MetricsDetailItem[], total: number) {
    metricsDetail.value = data
    metricsPagination.total = total
  }

  function setCompareData(data: CompareResult) {
    compareData.value = data
  }

  function reset() {
    overview.value = null
    trendData.value = []
    metricsDetail.value = []
    compareData.value = null
    metricsPagination.page = 1
    metricsPagination.total = 0
  }

  return {
    currentReservoirId,
    overview,
    overviewLoading,
    trendData,
    trendLoading,
    metricsDetail,
    metricsDetailLoading,
    metricsPagination,
    compareVersions,
    compareData,
    compareLoading,
    setReservoir,
    setOverview,
    setTrendData,
    setMetricsDetail,
    setCompareData,
    reset,
  }
})
