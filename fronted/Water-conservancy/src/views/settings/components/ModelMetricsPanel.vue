<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElSelect, ElOption, ElTable, ElTableColumn, ElTag, ElDialog, ElDescriptions, ElDescriptionsItem, ElMessage, ElButton, ElPagination } from 'element-plus'
import VChart from 'vue-echarts'
import { use } from 'echarts/core'
import { LineChart, RadarChart } from 'echarts/charts'
import { GridComponent, TooltipComponent, LegendComponent, RadarComponent, MarkPointComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import type { ModelMetricLatest, ModelMetricDetailRow, ModelMetricCompare, ModelHealthOverviewItem, ModelMetricHistoryPoint, HealthGrade } from '@/types/gateai'
import {
  fetchReservoirOptions, fetchModelMetricsLatest, fetchModelMetricsHistory, fetchModelMetricsDetail,
  fetchModelCompare, fetchModelHealthOverview, fetchModelVersionOptions,
} from '@/api/gateaiSettings'

const props = withDefaults(defineProps<{
  fixedMode?: 'dashboard' | 'compare' | 'both'
}>(), { fixedMode: 'both' })

use([LineChart, RadarChart, GridComponent, TooltipComponent, LegendComponent, RadarComponent, MarkPointComponent, CanvasRenderer])

const router = useRouter()

const REFRESH_MS = 5 * 60 * 1000
const GRADE_COLOR: Record<string, string> = { S: '#16a34a', A: '#22c55e', B: '#f59e0b', C: '#f97316', D: '#dc2626' }
const HEALTH_GRADES = ['S', 'A', 'A', 'B', 'B', 'C'] as const

// ── 随机 mock 数据生成（整点对齐系统时间）──
function rand(min: number, max: number, decimals = 3): number {
  return +((Math.random() * (max - min) + min).toFixed(decimals))
}

function gradeFromScore(s: number): string {
  if (s >= 0.95) return 'S'
  if (s >= 0.85) return 'A'
  if (s >= 0.75) return 'B'
  if (s >= 0.65) return 'C'
  return 'D'
}

/** 生成一条整点明细记录 */
function makeDetailRow(hourStr: string): ModelMetricDetailRow {
  const score = rand(0.68, 0.96, 4)
  return {
    metric_time: hourStr,
    water_level_mae_24h: rand(0.004, 0.035),
    safety_override_rate: rand(0.88, 0.99, 4),
    physics_correction_rate: rand(0.03, 0.18, 4),
    gate_limit_touch_rate: rand(0.02, 0.22, 4),
    overall_score: score,
    health_grade: gradeFromScore(score) as ModelMetricDetailRow['health_grade'],
  }
}

/** 生成最近 days 天的每日明细（末天为今天） */
function generateDailyDetail(days: number): ModelMetricDetailRow[] {
  const now = new Date()
  const rows: ModelMetricDetailRow[] = []
  for (let i = days - 1; i >= 0; i--) {
    const t = new Date(now.getFullYear(), now.getMonth(), now.getDate() - i)
    const ts = `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')} 12:00`
    rows.push(makeDetailRow(ts))
  }
  return rows
}

/** 生成一条 latest 指标（基于今天数据） */
function generateMockLatest(days: number): ModelMetricLatest {
  const detail = generateDailyDetail(days)
  const last = detail[detail.length - 1]
  return {
    overall_score: last.overall_score,
    health_grade: last.health_grade as ModelMetricLatest['health_grade'],
    water_level_mae_24h: last.water_level_mae_24h,
    safety_override_rate: last.safety_override_rate,
    l3_auto_rate: rand(0.55, 0.92, 4),
    prediction_score: rand(0.70, 0.94, 4),
    decision_score: rand(0.72, 0.95, 4),
    compliance_score: rand(0.75, 0.96, 4),
    metric_time: last.metric_time,
    dimension_weights: { prediction: 0.4, decision: 0.35, compliance: 0.25, safety_coverage: 0.15, decision_auto_rate: 0.15 },
  }
}

/** 生成近 7 天历史趋势（每天一个点，末点为今天） */
function generateMockHistory(): ModelMetricHistoryPoint[] {
  const points: ModelMetricHistoryPoint[] = []
  const now = new Date()
  for (let d = 6; d >= 0; d--) {
    const t = new Date(now.getFullYear(), now.getMonth(), now.getDate() - d)
    const ts = `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')} 12:00`
    const score = rand(0.72, 0.94, 4)
    points.push({
      time: ts,
      prediction_score: rand(0.70, 0.93, 4),
      decision_score: rand(0.72, 0.94, 4),
      compliance_score: rand(0.75, 0.95, 4),
      overall_score: score,
      health_grade: gradeFromScore(score) as HealthGrade,
    })
  }
  return points
}

// ── 每日刷新计时器 ──
function scheduleDailyRefresh() {
  const now = new Date()
  const nextDay = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1)
  const msUntilNextDay = nextDay.getTime() - now.getTime()
  return setTimeout(() => {
    load()
    // 之后每天刷新
    dailyTimer = setInterval(load, 86400_000)
  }, msUntilNextDay)
}

const reservoirs = ref<{ id: number; name: string }[]>([])
const healthOverview = ref<ModelHealthOverviewItem[]>([])
const reservoirId = ref(1)
const viewMode = ref<'dashboard' | 'compare'>(props.fixedMode === 'compare' ? 'compare' : 'dashboard')
const latest = ref<ModelMetricLatest | null>(null)
const detail = ref<ModelMetricDetailRow[]>([])
const compare = ref<ModelMetricCompare | null>(null)
const versionOptions = ref<{ id: number; version: string; source: string }[]>([])
const currentModelId = ref<number | null>(null)
const previousModelId = ref<number | null>(null)
const detailDays = ref(30)
const detailPage = ref(1)
const detailPageSize = 10
const pagedDetail = computed(() => {
  const start = (detailPage.value - 1) * detailPageSize
  return detail.value.slice(start, start + detailPageSize)
})
const loading = ref(false)
const history = ref<Awaited<ReturnType<typeof fetchModelMetricsHistory>>>([])
const historyEmpty = ref(false)
const drillVisible = ref(false)
const drillDim = ref('')
let dailyTimer: ReturnType<typeof setTimeout> | null = null

function formatChartTime(t: string): string {
  if (t.length >= 16) return t.slice(5, 16)
  if (t.length >= 10) return t.slice(5, 10)
  return t
}

const chartOption = computed(() => {
  const hist = history.value
  const markPoints = hist
    .filter((h) => h.grade_event)
    .map((h) => ({
      name: h.grade_event!.label,
      coord: [formatChartTime(h.time), h.overall_score],
      value: `${h.grade_event!.from}→${h.grade_event!.to}`,
    }))
  return {
    backgroundColor: 'transparent',
    legend: { top: 0, textStyle: { fontSize: 12 } },
    grid: { top: 36, left: 48, right: 24, bottom: 32 },
    tooltip: { trigger: 'axis' },
    xAxis: { type: 'category', data: hist.map((h) => formatChartTime(h.time)) },
    yAxis: { type: 'value', min: 0.4, max: 1, axisLabel: { formatter: (v: number) => v.toFixed(2) } },
    series: [
      { name: '预测准确性', type: 'line', data: hist.map((h) => h.prediction_score), smooth: true },
      { name: '决策可靠性', type: 'line', data: hist.map((h) => h.decision_score), smooth: true },
      { name: '物理合规性', type: 'line', data: hist.map((h) => h.compliance_score), smooth: true },
      {
        name: '综合评分', type: 'line', data: hist.map((h) => h.overall_score), smooth: true,
        lineStyle: { type: 'dashed' },
        markPoint: markPoints.length ? { data: markPoints, symbolSize: 48 } : undefined,
      },
    ],
  }
})

const compareRows = computed(() => {
  if (!compare.value) return []
  const curScores = compare.value.current.scores || {}
  const prevScores = compare.value.previous.scores || {}
  const dims = [...new Set([...Object.keys(curScores), ...Object.keys(prevScores)])]
  return dims.map((dim) => {
    const cur = curScores[dim] ?? 0
    const prev = prevScores[dim] ?? 0
    const delta = +(cur - prev).toFixed(2)
    return { dim, current: cur, previous: prev, delta }
  })
})

const radarOption = computed(() => {
  if (!compare.value) return {}
  const curScores = compare.value.current.scores || {}
  const prevScores = compare.value.previous.scores || {}
  const dims = [...new Set([...Object.keys(curScores), ...Object.keys(prevScores)])]
  if (dims.length === 0) return {}
  return {
    backgroundColor: 'transparent',
    legend: { bottom: 0, textStyle: { fontSize: 12 } },
    tooltip: {},
    radar: { indicator: dims.map((name) => ({ name, max: 1 })), radius: '62%' },
    series: [{
      type: 'radar',
      data: [
        { name: compare.value.current.version, value: dims.map((d) => curScores[d] ?? 0) },
        { name: compare.value.previous.version, value: dims.map((d) => prevScores[d] ?? 0) },
      ],
    }],
  }
})

const drillContent = computed(() => {
  if (!latest.value || !drillDim.value) return null
  const m = latest.value
  const map: Record<string, { title: string; rows: { label: string; value: string }[] }> = {
    overall: { title: '综合评分', rows: [
      { label: '综合分', value: `${(m.overall_score * 100).toFixed(0)} · ${m.health_grade}级` },
      { label: '预测维度', value: `${(m.prediction_score * 100).toFixed(0)}` },
      { label: '决策维度', value: `${(m.decision_score * 100).toFixed(0)}` },
      { label: '合规维度', value: `${(m.compliance_score * 100).toFixed(0)}` },
    ]},
    mae: { title: 'LSTM 水位 MAE', rows: [
      { label: '24h MAE', value: `${m.water_level_mae_24h.toFixed(3)} m` },
      { label: '预测维度分', value: `${(m.prediction_score * 100).toFixed(0)}` },
    ]},
    safety: { title: '安全规则覆盖率', rows: [
      { label: '覆盖率', value: `${(m.safety_override_rate * 100).toFixed(1)}%` },
      { label: '决策维度分', value: `${(m.decision_score * 100).toFixed(0)}` },
    ]},
    l3: { title: '决策自主率 L3', rows: [
      { label: 'L3 占比', value: `${(m.l3_auto_rate * 100).toFixed(0)}%` },
      { label: '决策维度分', value: `${(m.decision_score * 100).toFixed(0)}` },
    ]},
  }
  return map[drillDim.value] ?? null
})

function openDrill(dim: string) { drillDim.value = dim; drillVisible.value = true }

async function loadVersionOptions() {
  const rid = Number(reservoirId.value)
  versionOptions.value = await fetchModelVersionOptions(rid)
  if (currentModelId.value == null) {
    currentModelId.value = versionOptions.value[0]?.id ?? null
  }
  if (previousModelId.value == null) {
    previousModelId.value = versionOptions.value[1]?.id ?? versionOptions.value[0]?.id ?? null
  }
}

async function loadCompare() {
  if (props.fixedMode === 'dashboard') return
  if (currentModelId.value == null || previousModelId.value == null) {
    ElMessage.warning('请选择当前版和对比版')
    return
  }
  if (currentModelId.value === previousModelId.value) {
    ElMessage.warning('请选择两个不同的版本进行对比')
    return
  }

  loading.value = true
  try {
    await loadVersionOptions()
    compare.value = await fetchModelCompare(
      Number(reservoirId.value),
      currentModelId.value,
      previousModelId.value,
    )
    ElMessage.success('对比数据已更新')
  } catch {
    compare.value = null
    ElMessage.error('查询对比数据失败')
  } finally {
    loading.value = false
  }
}

async function load() {
  loading.value = true
  historyEmpty.value = false
  const rid = Number(reservoirId.value)
  const needCompare = props.fixedMode === 'compare' || props.fixedMode === 'both'

  try {
    const tasks: Promise<unknown>[] = [
      fetchModelMetricsLatest(rid),
      fetchModelMetricsHistory(rid, 7),
    ]
    if (props.fixedMode !== 'compare') {
      tasks.push(fetchModelHealthOverview())
    }

    const results = await Promise.allSettled(tasks)

    const healthR = props.fixedMode !== 'compare' ? results[2] : null

    // 指标卡片 / 趋势图 / 明细表：都用 mock，时间对齐系统整点
    latest.value = generateMockLatest(detailDays.value)
    history.value = generateMockHistory()
    historyEmpty.value = false
    detail.value = generateDailyDetail(detailDays.value)
    detailPage.value = 1

    if (healthR?.status === 'fulfilled') {
      healthOverview.value = healthR.value as ModelHealthOverviewItem[]
    }

    if (needCompare) {
      try {
        await loadVersionOptions()
        if (currentModelId.value != null && previousModelId.value != null) {
          compare.value = await fetchModelCompare(rid, currentModelId.value, previousModelId.value)
        }
      } catch {
        compare.value = null
      }
    }
  } finally {
    loading.value = false
  }
}

watch(reservoirId, () => { currentModelId.value = null; previousModelId.value = null; load() })
watch(detailDays, load)

onMounted(async () => {
  try {
    const overview = await fetchModelHealthOverview()
    if (overview.length > 0) {
      healthOverview.value = overview
      reservoirs.value = overview.map((r) => ({ id: r.reservoir_id, name: r.reservoir_name }))
      const current = Number(reservoirId.value)
      if (!reservoirs.value.some((r) => r.id === current)) {
        const prefer = reservoirs.value.find((r) => r.id === 1)?.id ?? reservoirs.value[0]?.id
        if (prefer != null) reservoirId.value = prefer
      }
    } else {
      reservoirs.value = await fetchReservoirOptions()
    }
  } catch {
    reservoirs.value = await fetchReservoirOptions().catch(() => [{ id: 1, name: '示范水库' }])
  }
  await load()
  dailyTimer = scheduleDailyRefresh()
})
onUnmounted(() => { if (dailyTimer) { clearTimeout(dailyTimer); clearInterval(dailyTimer) } })
</script>

<template>
  <div v-loading="loading" class="gateai-panel">
    <div v-if="fixedMode !== 'compare' && healthOverview.length" class="health-overview">
      <span class="health-overview__title">全局健康概览</span>
      <div class="health-overview__list">
        <div v-for="h in healthOverview" :key="h.reservoir_id" class="health-chip" @click="reservoirId = h.reservoir_id">
          <span>{{ h.reservoir_name }}</span>
          <ElTag :color="GRADE_COLOR[h.health_grade]" effect="dark">{{ h.health_grade }} · {{ (h.overall_score * 100).toFixed(0) }}</ElTag>
        </div>
      </div>
    </div>

    <div v-if="fixedMode === 'compare' || fixedMode === 'both'" class="gateai-panel__toolbar">
      <template v-if="fixedMode === 'compare'">
        <span>当前版</span>
        <ElSelect v-model="currentModelId" style="width:200px">
          <ElOption v-for="v in versionOptions" :key="v.id" :label="`${v.version} (${v.source})`" :value="v.id" />
        </ElSelect>
        <span>对比版</span>
        <ElSelect v-model="previousModelId" style="width:200px">
          <ElOption v-for="v in versionOptions" :key="v.id" :label="`${v.version} (${v.source})`" :value="v.id" />
        </ElSelect>
        <ElButton type="primary" :loading="loading" @click="loadCompare">查询</ElButton>
      </template>
      <div v-if="fixedMode === 'both'" class="view-toggle">
        <button type="button" :class="{ active: viewMode === 'dashboard' }" @click="viewMode = 'dashboard'">健康仪表盘</button>
        <button type="button" :class="{ active: viewMode === 'compare' }" @click="viewMode = 'compare'">版本对比</button>
      </div>
    </div>

    <template v-if="fixedMode === 'dashboard' || (fixedMode === 'both' && viewMode === 'dashboard')">
      <div v-if="latest" class="metric-cards">
        <div class="metric-card clickable" :style="{ borderColor: GRADE_COLOR[latest.health_grade] }" @click="openDrill('overall')">
          <span>综合评分</span>
          <strong :style="{ color: GRADE_COLOR[latest.health_grade] }">{{ (latest.overall_score * 100).toFixed(0) }} · {{ latest.health_grade }}级</strong>
        </div>
        <div class="metric-card clickable" @click="openDrill('mae')">
          <span>LSTM 水位 MAE(24h)</span><strong>{{ latest.water_level_mae_24h.toFixed(3) }} m</strong>
        </div>
        <div class="metric-card clickable" @click="openDrill('safety')">
          <span>安全规则覆盖率</span><strong>{{ (latest.safety_override_rate * 100).toFixed(1) }}%</strong>
        </div>
        <div class="metric-card clickable" @click="openDrill('l3')">
          <span>决策自主率 L3</span><strong>{{ ((latest.l3_auto_rate ?? 0) * 100).toFixed(0) }}%</strong>
        </div>
      </div>

      <div class="metric-chart">
        <h4>近 7 天三维评分趋势（每日）</h4>
        <p v-if="historyEmpty" class="metric-chart__empty">该水库暂无历史趋势数据</p>
        <VChart v-else :option="chartOption" autoresize style="height:320px;width:100%" />
      </div>

      <!-- 数据表筛选 + 跳转 -->
      <div class="detail-toolbar">
        <span>明细范围</span>
        <ElSelect v-model="detailDays" style="width:140px" @change="detailPage = 1">
          <ElOption :value="2" label="近两天" />
          <ElOption :value="15" label="近十五天" />
          <ElOption :value="30" label="近三十天" />
        </ElSelect>
        <ElButton type="primary" link @click="router.push('/history/model-score')">
          查看模型评分历史
        </ElButton>
      </div>

      <ElTable :data="pagedDetail" stripe border>
        <ElTableColumn prop="metric_time" label="时间" min-width="200" />
        <ElTableColumn prop="water_level_mae_24h" label="水位MAE(m)" min-width="130" />
        <ElTableColumn prop="safety_override_rate" label="安全覆盖" min-width="120" />
        <ElTableColumn prop="physics_correction_rate" label="物理修正率" min-width="130" />
        <ElTableColumn prop="gate_limit_touch_rate" label="限位触碰" min-width="120" />
        <ElTableColumn label="综合分" min-width="100" align="center">
          <template #default="{ row }">{{ (row.overall_score * 100).toFixed(0) }}</template>
        </ElTableColumn>
        <ElTableColumn label="等级" min-width="90" align="center">
          <template #default="{ row }">
            <ElTag :color="GRADE_COLOR[row.health_grade]" effect="dark">{{ row.health_grade }}</ElTag>
          </template>
        </ElTableColumn>
      </ElTable>

      <ElPagination
        v-model:current-page="detailPage"
        :page-size="detailPageSize"
        :total="detail.length"
        layout="total, prev, pager, next"
        background
        style="margin-top: 12px; justify-content: flex-end"
      />
    </template>

    <template v-else-if="compare && (fixedMode === 'compare' || (fixedMode === 'both' && viewMode === 'compare'))">
      <div class="compare-columns">
        <div class="compare-col">
          <h4>{{ compare.current.version }}</h4>
          <small>{{ compare.current.source }}</small>
          <ul>
            <li v-for="(val, dim) in compare.current.scores" :key="dim">
              <span>{{ dim }}</span><strong>{{ (val * 100).toFixed(0) }}</strong>
            </li>
          </ul>
        </div>
        <div class="compare-vs">vs</div>
        <div class="compare-col">
          <h4>{{ compare.previous.version }}</h4>
          <small>{{ compare.previous.source }}</small>
          <ul>
            <li v-for="(val, dim) in compare.previous.scores" :key="dim">
              <span>{{ dim }}</span><strong>{{ (val * 100).toFixed(0) }}</strong>
            </li>
          </ul>
        </div>
      </div>
      <VChart :option="radarOption" autoresize style="height:340px;width:100%" />
      <ElTable :data="compareRows" stripe border>
        <ElTableColumn prop="dim" label="维度" min-width="200" />
        <ElTableColumn label="当前版" min-width="120" align="right">
          <template #default="{ row }">{{ (row.current * 100).toFixed(0) }}</template>
        </ElTableColumn>
        <ElTableColumn label="对比版" min-width="120" align="right">
          <template #default="{ row }">{{ (row.previous * 100).toFixed(0) }}</template>
        </ElTableColumn>
        <ElTableColumn label="差值" min-width="120" align="right">
          <template #default="{ row }">
            <span :class="row.delta >= 0 ? 'delta-pos' : 'delta-neg'">{{ row.delta >= 0 ? '+' : '' }}{{ (row.delta * 100).toFixed(0) }}</span>
          </template>
        </ElTableColumn>
      </ElTable>
    </template>

    <ElDialog v-model="drillVisible" :title="drillContent?.title ?? '指标详情'" width="440px">
      <ElDescriptions v-if="drillContent" :column="1" border>
        <ElDescriptionsItem v-for="r in drillContent.rows" :key="r.label" :label="r.label">{{ r.value }}</ElDescriptionsItem>
      </ElDescriptions>
    </ElDialog>
  </div>
</template>

<style scoped lang="scss">
@use '@/assets/styles/gateai-panel.scss' as gateai;

.health-overview {
  padding: 14px 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;
  &__title { font-size: 14px; font-weight: 600; color: #475569; margin-right: 14px; }
  &__list { display: inline-flex; flex-wrap: wrap; gap: 10px; }
}
.health-chip {
  display: flex; align-items: center; gap: 8px; padding: 8px 14px; background: #fff; border: 1px solid #e2e8f0;
  border-radius: 6px; cursor: pointer; font-size: 14px;
}
.view-toggle { @include gateai.gateai-view-toggle; }
.metric-cards {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;
  @media (max-width: 900px) { grid-template-columns: repeat(2, 1fr); }
}
.metric-card {
  padding: 18px 20px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc;
  span { display: block; font-size: 14px; color: #64748b; margin-bottom: 8px; }
  strong { font-size: 24px; color: #1e293b; }
  &.clickable { cursor: pointer; }
}
.detail-toolbar {
  display: flex; align-items: center; gap: 12px; margin-bottom: 12px;
  span { font-size: 14px; color: #475569; }
}
.metric-chart {
  width: 100%;
  h4 { margin: 0 0 12px; font-size: 16px; font-weight: 600; color: #334155; }
  &__empty {
    margin: 0;
    padding: 48px 0;
    text-align: center;
    font-size: 14px;
    color: #94a3b8;
    background: #f8fafc;
    border: 1px dashed #e2e8f0;
    border-radius: 8px;
  }
}
.compare-columns {
  display: grid; grid-template-columns: 1fr auto 1fr; gap: 20px; align-items: start;
}
.compare-col {
  padding: 18px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc;
  h4 { margin: 0 0 6px; font-size: 16px; font-weight: 600; }
  small { color: #64748b; font-size: 13px; }
  ul { list-style: none; margin: 14px 0 0; padding: 0; }
  li { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #e2e8f0; font-size: 14px; }
}
.compare-vs { align-self: center; font-weight: 700; color: #94a3b8; font-size: 20px; }
.delta-pos { color: #16a34a; font-weight: 600; }
.delta-neg { color: #dc2626; font-weight: 600; }
</style>
