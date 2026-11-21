<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import VChart from 'vue-echarts'
import { use } from 'echarts/core'
import { LineChart } from 'echarts/charts'
import {
  GridComponent,
  TooltipComponent,
  LegendComponent,
  DataZoomComponent,
  MarkLineComponent,
  MarkPointComponent,
} from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
use([
  LineChart,
  GridComponent,
  TooltipComponent,
  LegendComponent,
  DataZoomComponent,
  MarkLineComponent,
  MarkPointComponent,
  CanvasRenderer,
])
import { ElTag, ElMessage } from 'element-plus'
import { HEALTH_GRADE } from '@/constants/aiHealth'
import {
  fetchModelMetricsHistory,
  fetchModelMetricsLatest,
  fetchModelHealthOverview,
} from '@/api/gateaiSettings'
import type { ModelMetricHistoryPoint, ModelMetricLatest } from '@/types/gateai'

const reservoirId = ref(1)
const hours = ref(24)
const loading = ref(false)
const historyError = ref<'none' | 'empty' | 'failed'>('none')
const latest = ref<ModelMetricLatest | null>(null)
const reservoirOptions = ref<{ value: number; label: string }[]>([])
const hasHistory = computed(() => scoreData.value.length > 0)

interface ScorePoint {
  key: string
  time: string
  overall: number
  prediction: number
  decision: number
  compliance: number
  grade: string
}
const scoreData = ref<ScorePoint[]>([])

function gradeFromScore(s: number): string {
  if (s >= 0.85) return 'S'
  if (s >= 0.70) return 'A'
  if (s >= 0.55) return 'B'
  if (s >= 0.40) return 'C'
  return 'D'
}

function mapHistoryPoint(p: ModelMetricHistoryPoint): ScorePoint {
  const rawTime = p.time ?? ''
  const displayTime = rawTime.length >= 10 ? rawTime.slice(0, 16) : rawTime
  return {
    key: rawTime || displayTime,
    time: displayTime,
    overall: p.overall_score,
    prediction: p.prediction_score,
    decision: p.decision_score,
    compliance: p.compliance_score,
    grade: p.health_grade ?? p.grade_event?.to ?? gradeFromScore(p.overall_score),
  }
}

async function loadReservoirs() {
  try {
    const overview = await fetchModelHealthOverview()
    if (overview.length > 0) {
      reservoirOptions.value = overview.map((r) => ({
        value: Number(r.reservoir_id),
        label: r.reservoir_name || `水库 #${r.reservoir_id}`,
      }))
      const current = Number(reservoirId.value)
      const inList = reservoirOptions.value.some((r) => r.value === current)
      if (!inList) {
        const prefer = reservoirOptions.value.find((r) => r.value === 1)?.value
          ?? reservoirOptions.value[0]?.value
        if (prefer != null) reservoirId.value = prefer
      }
    }
  } catch {
    if (reservoirOptions.value.length === 0) {
      reservoirOptions.value = [{ value: 1, label: '水库 #1' }]
    }
  }
}

async function loadScores() {
  loading.value = true
  historyError.value = 'none'
  const rid = Number(reservoirId.value)
  try {
    const [historyResult, latestResult] = await Promise.allSettled([
      fetchModelMetricsHistory(rid, hours.value),
      fetchModelMetricsLatest(rid),
    ])

    if (historyResult.status === 'fulfilled') {
      scoreData.value = historyResult.value.map(mapHistoryPoint)
      if (scoreData.value.length === 0) historyError.value = 'empty'
    } else {
      scoreData.value = []
      historyError.value = 'failed'
      console.warn('[ModelScoreTab] history failed:', historyResult.reason)
      ElMessage.warning('历史趋势加载失败，请检查网络连接或稍后重试')
    }

    latest.value = latestResult.status === 'fulfilled' ? latestResult.value : null
    if (latestResult.status === 'rejected') {
      console.warn('[ModelScoreTab] latest failed:', latestResult.reason)
    }
  } catch {
    ElMessage.error('加载模型评分失败')
    scoreData.value = []
    latest.value = null
    historyError.value = 'failed'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await loadReservoirs()
  await loadScores()
})

const chartOpt = computed(() => ({
  tooltip: { trigger: 'axis' },
  legend: { top: 0 },
  grid: { left: 56, right: 40, top: 36, bottom: 60 },
  xAxis: {
    type: 'category',
    data: scoreData.value.map((d) => d.time),
    axisLabel: { interval: Math.max(1, Math.floor(scoreData.value.length / 10)), rotate: 30 },
  },
  yAxis: {
    type: 'value',
    min: 0,
    max: 1,
    axisLabel: { formatter: (v: number) => (v * 100).toFixed(0) },
  },
  dataZoom: [{ type: 'slider', bottom: 0, height: 22 }],
  series: [
    {
      name: '预测准确性',
      type: 'line',
      data: scoreData.value.map((d) => d.prediction),
      smooth: true,
      lineStyle: { color: '#5470C6', width: 2 },
      symbol: 'none',
    },
    {
      name: '决策可靠性',
      type: 'line',
      data: scoreData.value.map((d) => d.decision),
      smooth: true,
      lineStyle: { color: '#91CC75', width: 2 },
      symbol: 'none',
    },
    {
      name: '物理合规性',
      type: 'line',
      data: scoreData.value.map((d) => d.compliance),
      smooth: true,
      lineStyle: { color: '#FAC858', width: 2 },
      symbol: 'none',
    },
    {
      name: '综合评分',
      type: 'line',
      data: scoreData.value.map((d) => d.overall),
      smooth: true,
      lineStyle: { color: '#999', width: 1.5, type: 'dashed' },
      symbol: 'none',
    },
  ],
}))

const gradeFilter = ref('')
const filteredScores = computed(() => {
  if (!gradeFilter.value) return scoreData.value
  return scoreData.value.filter((d) => d.grade === gradeFilter.value)
})

function onQuery() {
  loadScores()
}
</script>

<template>
  <div class="ms" v-loading="loading">
    <div v-if="latest" class="ms__summary">
      <span class="ms__summary-label">最新指标</span>
      <span class="ms__summary-item">
        综合评分 <b>{{ (latest.overall_score * 100).toFixed(0) }}</b>
      </span>
      <span class="ms__summary-item">
        健康等级
        <ElTag
          :color="(HEALTH_GRADE as any)[latest.health_grade]?.color"
          size="small"
          effect="dark"
        >
          {{ latest.health_grade }}
        </ElTag>
      </span>
      <span class="ms__summary-item ms__summary-time">{{ latest.metric_time }}</span>
    </div>

    <div class="ms__filters">
      <select v-model="reservoirId" @change="onQuery" class="ms__sel">
        <option v-for="r in reservoirOptions" :key="r.value" :value="r.value">
          {{ r.label }}
        </option>
      </select>
      <select v-model="hours" @change="onQuery" class="ms__sel">
        <option :value="7">近 7 小时</option>
        <option :value="24">近 24 小时</option>
        <option :value="48">近 48 小时</option>
      </select>
      <div class="ms__grades">
        <span class="ms__g" :class="{ on: !gradeFilter }" @click="gradeFilter = ''">全部</span>
        <span
          v-for="g in ['S', 'A', 'B', 'C', 'D']"
          :key="g"
          class="ms__g"
          :class="{ on: gradeFilter === g }"
          :style="{ '--gc': (HEALTH_GRADE as any)[g]?.color }"
          @click="gradeFilter = gradeFilter === g ? '' : g"
        >
          {{ g }}
        </span>
      </div>
      <button class="ms__btn" :disabled="loading" @click="onQuery">刷新</button>
    </div>

    <div v-if="!loading && historyError === 'empty'" class="ms__hint">
      {{ latest ? '该水库暂无历史趋势数据，仅显示最新指标（可切换「示范水库 #1」或缩短查询时间）' : '该水库暂无评分数据' }}
    </div>
    <div v-else-if="!loading && historyError === 'failed'" class="ms__hint ms__hint--error">
      历史趋势接口请求失败，请确认 natapp 隧道可用后点击刷新
    </div>

    <div class="ms__chart">
      <VChart
        v-if="hasHistory"
        :key="`${reservoirId}-${hours}`"
        :option="chartOpt"
        autoresize
        style="width: 100%; height: 100%"
      />
      <div v-else class="ms__chart-empty">暂无趋势图数据</div>
    </div>

    <div class="ms__table-wrap">
      <table class="ms__tbl">
        <thead>
          <tr>
            <th>时间</th>
            <th>综合评分</th>
            <th>健康等级</th>
            <th>预测准确性</th>
            <th>决策可靠性</th>
            <th>物理合规性</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="d in filteredScores" :key="d.key">
            <td>{{ d.time }}</td>
            <td>{{ (d.overall * 100).toFixed(0) }}</td>
            <td>
              <ElTag :color="(HEALTH_GRADE as any)[d.grade]?.color" size="small" effect="dark">
                {{ d.grade }}
              </ElTag>
            </td>
            <td>{{ (d.prediction * 100).toFixed(0) }}</td>
            <td>{{ (d.decision * 100).toFixed(0) }}</td>
            <td>{{ (d.compliance * 100).toFixed(0) }}</td>
          </tr>
          <tr v-if="!loading && filteredScores.length === 0">
            <td colspan="6" class="ms__empty">暂无数据</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped lang="scss">
@use '@/assets/styles/text-mixins.scss' as *;
.ms {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 18px 24px;
  gap: 12px;
  overflow: hidden;

  &__summary {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 16px;
    padding: 12px 16px;
    background: #f8fafc;
    border: 1px solid #eef0f2;
    border-radius: 8px;
    flex-shrink: 0;
  }

  &__summary-label {
    font-size: 14px;
    font-weight: 600;
    color: #64748b;
  }

  &__summary-item {
    font-size: 14px;
    color: #475569;

    b {
      font-size: 18px;
      font-weight: 700;
      color: #1e293b;
      margin-left: 4px;
    }
  }

  &__summary-time {
    margin-left: auto;
    font-size: 13px;
    color: #94a3b8;
  }

  &__filters {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
  }
  &__sel {
    padding: 8px 12px;
    font-size: 14px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
  }
  &__grades {
    display: flex;
    gap: 4px;
  }
  &__g {
    padding: 4px 12px;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    background: #f1f5f9;
    border-radius: 4px;
    cursor: pointer;
    &.on {
      color: #fff;
      background: var(--gc, #3b82f6);
    }
  }
  &__btn {
    padding: 8px 16px;
    font-size: 14px;
    color: #fff;
    background: #3b82f6;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    &:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
  }
  &__chart {
    flex-shrink: 0;
    height: 300px;
    background: #fff;
    border-radius: 8px;
    border: 1px solid #eef0f2;
  }
  &__chart-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    font-size: 14px;
    color: #94a3b8;
  }
  &__hint {
    flex-shrink: 0;
    padding: 8px 12px;
    font-size: 13px;
    color: #64748b;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 6px;

    &--error {
      color: #b45309;
      background: #fef2f2;
      border-color: #fecaca;
    }
  }
  &__table-wrap {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    background: #fff;
    border-radius: 8px;
    border: 1px solid #eef0f2;
  }
  &__tbl {
    width: 100%;
    border-collapse: collapse;
    th {
      padding: 10px 12px;
      @include text-table-header;
      text-align: left;
      border-bottom: 1px solid #eef0f2;
      position: sticky;
      top: 0;
      background: #fff;
      z-index: 1;
    }
    td {
      padding: 10px 12px;
      @include text-table-number;
      border-bottom: 1px solid #f8fafc;
    }
    td:first-child {
      @include text-table-label;
      font-family: inherit;
    }
    tr:hover td {
      background: #f8fafc;
    }
  }
  &__empty {
    text-align: center;
    color: #94a3b8;
    padding: 24px !important;
    font-family: inherit !important;
  }
}
</style>
