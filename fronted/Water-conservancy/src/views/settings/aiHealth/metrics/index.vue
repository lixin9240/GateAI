<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { ElSelect, ElOption, ElMessage } from 'element-plus'
import { RESERVOIR_OPTIONS } from '@/constants/settings'
import { AI_HEALTH_REFRESH_INTERVAL } from '@/constants/aiHealth'
import { getAIMetrics, getAIMetricsHistory } from '@/api/settings'
import { useAIHealthStore } from '@/stores/aiHealth'
import type { HealthOverview, TrendPoint } from '@/stores/aiHealth'
import HealthOverviewCards from './components/HealthOverview.vue'
import ScoreTrendChart from './components/ScoreTrendChart.vue'
import MetricsDetailTable from './components/MetricsDetailTable.vue'

const store = useAIHealthStore()

const reservoirId = ref<number>(1)
const overview = ref<HealthOverview | null>(null)
const overviewLoading = ref(false)
const trendData = ref<TrendPoint[]>([])
const trendLoading = ref(false)

let refreshTimer: ReturnType<typeof setInterval> | null = null

// ── Mock helpers ──
function generateMockOverview(): HealthOverview {
  const grades: Array<'S' | 'A' | 'B' | 'C' | 'D'> = ['S', 'A', 'B', 'C', 'D']
  const score = 0.65 + Math.random() * 0.3
  const gradeIdx = score >= 0.85 ? 0 : score >= 0.7 ? 1 : score >= 0.55 ? 2 : score >= 0.4 ? 3 : 4
  return {
    overallScore: +score.toFixed(3),
    healthGrade: grades[gradeIdx],
    waterLevelMAE: +(0.02 + Math.random() * 0.06).toFixed(3),
    safetyCoverageRate: +(0.85 + Math.random() * 0.12).toFixed(3),
    decisionAutoRate: +(0.6 + Math.random() * 0.35).toFixed(3),
  }
}

function generateMockTrend(): TrendPoint[] {
  const data: TrendPoint[] = []
  const now = new Date()
  for (let i = 6; i >= 0; i--) {
    for (let h = 0; h < 24; h += 4) {
      const d = new Date(now)
      d.setDate(d.getDate() - i)
      d.setHours(h, 0, 0, 0)
      data.push({
        time: `${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(h).padStart(2, '0')}:00`,
        overall: +(0.65 + Math.random() * 0.25).toFixed(3),
        prediction: +(0.7 + Math.random() * 0.22).toFixed(3),
        decision: +(0.65 + Math.random() * 0.28).toFixed(3),
        compliance: +(0.75 + Math.random() * 0.18).toFixed(3),
      })
    }
  }
  return data
}

// ── Fetch ──
async function fetchOverview() {
  overviewLoading.value = true
  try {
    const res = await getAIMetrics({ reservoir_id: reservoirId.value })
    if (res.data?.code === 0 && res.data.data) {
      overview.value = res.data.data
      return
    }
  } catch {
    /* fallback */
  }
  overview.value = generateMockOverview()
  overviewLoading.value = false
}

async function fetchTrend() {
  trendLoading.value = true
  try {
    const res = await getAIMetricsHistory({ reservoir_id: reservoirId.value, days: 7 })
    if (res.data?.code === 0 && res.data.data) {
      trendData.value = res.data.data
      return
    }
  } catch {
    /* fallback */
  }
  trendData.value = generateMockTrend()
  trendLoading.value = false
}

async function fetchAll() {
  store.setReservoir(reservoirId.value)
  await Promise.all([fetchOverview(), fetchTrend()])
}

function onReservoirChange() {
  fetchAll()
}

// ── Auto-refresh ──
function startAutoRefresh() {
  refreshTimer = setInterval(() => {
    fetchAll()
  }, AI_HEALTH_REFRESH_INTERVAL)
}

function stopAutoRefresh() {
  if (refreshTimer) {
    clearInterval(refreshTimer)
    refreshTimer = null
  }
}

onMounted(() => {
  fetchAll()
  startAutoRefresh()
})

onBeforeUnmount(() => {
  stopAutoRefresh()
})
</script>

<template>
  <div class="ai-health-metrics">
    <div class="page-header">
      <h2 class="page-title">AI 模型健康度</h2>
      <ElSelect
        v-model="reservoirId"
        placeholder="选择水库"
        style="width: 180px"
        @change="onReservoirChange"
      >
        <ElOption
          v-for="opt in RESERVOIR_OPTIONS"
          :key="opt.value"
          :label="opt.label"
          :value="opt.value"
        />
      </ElSelect>
    </div>

    <HealthOverviewCards :overview="overview" :loading="overviewLoading" />

    <ScoreTrendChart :data="trendData" :loading="trendLoading" />

    <MetricsDetailTable :reservoir-id="reservoirId" />

    <div class="auto-refresh-hint">
      数据每 5 分钟自动刷新
      <span class="refresh-dot" />
    </div>
  </div>
</template>

<style scoped lang="scss">
@use '@/assets/styles/text-mixins.scss' as *;
.ai-health-metrics {
  padding: var(--spacing-md);
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--spacing-lg);
}

.page-title {
  font-size: var(--font-size-xxl);
  font-weight: 700;
  color: var(--color-text);
  margin: 0;
}

.auto-refresh-hint {
  display: flex;
  align-items: center;
  gap: var(--spacing-xs);
  margin-top: var(--spacing-md);
  font-size: var(--font-size-xs);
  color: var(--color-text-secondary);
}

.refresh-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--color-success);
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.3;
  }
}
</style>
