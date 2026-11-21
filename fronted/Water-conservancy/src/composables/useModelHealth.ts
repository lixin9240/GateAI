// ============================================================
// AI 模型健康度 — 数据管理 composable
// D-88: 每 5 分钟轮询 + WebSocket 消息接入
// ============================================================
import { ref, watch, onUnmounted } from 'vue'
import { AI_HEALTH_REFRESH_INTERVAL } from '@/constants/aiHealth'
import { fetchModelMetricsLatest } from '@/api/gateaiSettings'
import type { ModelMetricLatest, HealthGrade } from '@/types/gateai'

export interface ModelHealthWsMessage {
  type: 'model_health'
  timestamp: string
  data: {
    reservoir_id: number
    reservoir_name: string
    overall_score: number
    health_grade: HealthGrade
    prediction_score: number
    decision_score: number
    compliance_score: number
    previous_grade: HealthGrade
    grade_changed: boolean
    model_version: string
    rollback_version: string | null
    evaluated_at: string
  }
}

export function useModelHealth() {
  const reservoirId = ref<number>(1)
  const currentData = ref<ModelMetricLatest | null>(null)
  const previousGrade = ref<HealthGrade | null>(null)
  const modelVersion = ref<string>('')
  const rollbackVersion = ref<string | null>(null)
  const reservoirName = ref<string>('')
  const loading = ref(false)
  const flashTrigger = ref(0) // 等级变化时 +1，触发动画
  const flashReason = ref<string>('') // 变化原因（升级/降级）

  let timer: ReturnType<typeof setInterval> | null = null

  // ── 数据获取 ──
  async function fetchData() {
    loading.value = true
    try {
      const data = await fetchModelMetricsLatest(reservoirId.value)
      if (data) {
        // 检测等级变化
        if (currentData.value && data.health_grade !== currentData.value.health_grade) {
          previousGrade.value = currentData.value.health_grade
          flashTrigger.value++
          const isDowngrade =
            gradeRank(data.health_grade) > gradeRank(currentData.value.health_grade)
          flashReason.value = isDowngrade
            ? `模型健康度降至 ${data.health_grade} 级`
            : `模型健康度回升至 ${data.health_grade} 级`
        } else if (!currentData.value) {
          previousGrade.value = data.health_grade
        }

        currentData.value = data
        modelVersion.value = 'v5.1' // mock; real data from WS/api

        // D 级回退模拟
        if (data.health_grade === 'D') {
          rollbackVersion.value = rollbackVersion.value ?? 'v4.9'
        } else {
          rollbackVersion.value = null
        }
      }
    } catch {
      /* 静默处理 */
    } finally {
      loading.value = false
    }
  }

  // ── WebSocket 消息处理 ──
  function handleWsMessage(msg: ModelHealthWsMessage) {
    if (msg.type !== 'model_health') return
    const d = msg.data
    if (d.reservoir_id !== reservoirId.value) return

    const prev = currentData.value?.health_grade
    currentData.value = {
      overall_score: d.overall_score,
      health_grade: d.health_grade,
      water_level_mae_24h: 0,
      safety_override_rate: 0,
      l3_auto_rate: 0,
      prediction_score: d.prediction_score,
      decision_score: d.decision_score,
      compliance_score: d.compliance_score,
      metric_time: d.evaluated_at,
      dimension_weights: { prediction: 0.4, decision: 0.35, compliance: 0.25, safety_coverage: 0.15, decision_auto_rate: 0.15 },
    }
    modelVersion.value = d.model_version
    reservoirName.value = d.reservoir_name

    if (prev && d.grade_changed) {
      previousGrade.value = prev as HealthGrade
      flashTrigger.value++
      flashReason.value =
        gradeRank(d.health_grade) > gradeRank(prev as string)
          ? `模型健康度降至 ${d.health_grade} 级`
          : `模型健康度回升至 ${d.health_grade} 级`
    }

    if (d.rollback_version) {
      rollbackVersion.value = d.rollback_version
    }
  }

  // ── 定时轮询 ──
  function startPolling() {
    stopPolling()
    fetchData()
    timer = setInterval(fetchData, AI_HEALTH_REFRESH_INTERVAL)
  }

  function stopPolling() {
    if (timer) {
      clearInterval(timer)
      timer = null
    }
  }

  function setReservoir(id: number) {
    reservoirId.value = id
    fetchData()
  }

  // 水库变化时重新获取
  watch(reservoirId, () => {
    fetchData()
  })

  onUnmounted(() => {
    stopPolling()
  })

  return {
    reservoirId,
    currentData,
    previousGrade,
    modelVersion,
    rollbackVersion,
    reservoirName,
    loading,
    flashTrigger,
    flashReason,
    fetchData,
    handleWsMessage,
    startPolling,
    stopPolling,
    setReservoir,
  }
}

// ── 辅助：等级排序 (S < A < B < C < D) ──
const GRADE_ORDER: Record<string, number> = { S: 0, A: 1, B: 2, C: 3, D: 4 }
function gradeRank(g: string): number {
  return GRADE_ORDER[g] ?? 99
}
