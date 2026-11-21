<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { ElIcon } from 'element-plus'
import { DataAnalysis } from '@element-plus/icons-vue'
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

// ═══ 主题 ═══
const darkMode = ref(localStorage.getItem('history-theme') === 'dark')
watch(darkMode, (v) => localStorage.setItem('history-theme', v ? 'dark' : 'light'))

// ═══ 离线检测 ═══
const isOnline = ref(navigator.onLine)
onMounted(() => {
  window.addEventListener('online', () => (isOnline.value = true))
  window.addEventListener('offline', () => (isOnline.value = false))
})

// ═══ 筛选 ═══
const dateRange = ref({ start: '', end: '' })
const selectedMetrics = ref<string[]>(['upstreamLevel', 'flowRate'])
const granularity = ref<'raw' | '5min' | 'hour' | 'day'>('5min')
const metricOptions = [
  { value: 'upstreamLevel', label: '上游水位', unit: 'm', color: '#3b82f6' },
  { value: 'downstreamLevel', label: '下游水位', unit: 'm', color: '#06b6d4' },
  { value: 'inflowRate', label: '入库流量', unit: 'm³/s', color: '#8b5cf6' },
  { value: 'outflowRate', label: '出库流量', unit: 'm³/s', color: '#22c55e' },
  { value: 'gateOpening', label: '阀门开度', unit: '%', color: '#f59e0b' },
  { value: 'powerOutput', label: '发电功率', unit: 'MW', color: '#ef4444' },
]
const granularityOptions = [
  { value: 'raw', label: '原始' },
  { value: '5min', label: '5分钟' },
  { value: 'hour', label: '小时' },
  { value: 'day', label: '日' },
]
const queried = ref(false)

// ═══ 模拟数据 ═══
const allData = ref<any[]>([])
const tableData = ref<any[]>([])
const tablePage = ref(1)
const tablePageSize = 15
const chartZoom = ref<[number, number] | null>(null)

function generateMock() {
  const now = Date.now()
  const arr = []
  for (let i = 96; i >= 0; i--) {
    const t = now - i * 15 * 60000
    arr.push({
      time: t,
      label: new Date(t).toLocaleString('zh-CN'),
      upstreamLevel: +(378.5 + Math.sin(i / 8) * 1.2).toFixed(2),
      downstreamLevel: +(269.2 + Math.sin(i / 12) * 0.2).toFixed(2),
      inflowRate: Math.round(6350 + Math.sin(i / 6) * 800),
      outflowRate: Math.round(5820 + Math.sin(i / 6) * 700),
      gateOpening: +(34 + Math.sin(i / 4) * 15).toFixed(1),
      powerOutput: Math.round(680 + Math.sin(i / 5) * 15),
      event: null as any,
    })
  }
  arr[20].event = { type: 'alarm', label: '水位超预警', color: '#ef4444' }
  arr[45].event = { type: 'dispatch', label: '阀门调度', color: '#3b82f6' }
  arr[70].event = { type: 'gate', label: '3#阀门动作', color: '#f59e0b' }
  allData.value = arr
}

function applyFilters() {
  let data = [...allData.value]
  if (dateRange.value.start)
    data = data.filter((d) => d.time >= new Date(dateRange.value.start).getTime())
  if (dateRange.value.end)
    data = data.filter((d) => d.time <= new Date(dateRange.value.end).getTime())
  tableData.value = data
  tablePage.value = 1
  queried.value = true
}
function resetFilters() {
  dateRange.value = { start: '', end: '' }
  selectedMetrics.value = ['upstreamLevel', 'flowRate']
  granularity.value = '5min'
  tableData.value = [...allData.value]
  queried.value = false
  chartZoom.value = null
}

const metricLabel = (v: string) => metricOptions.find((o) => o.value === v)?.label ?? v
const metricUnit = (v: string) => metricOptions.find((o) => o.value === v)?.unit ?? ''

const pagedData = computed(() => {
  const s = (tablePage.value - 1) * tablePageSize
  return tableData.value.slice(s, s + tablePageSize)
})
const totalPages = computed(() => Math.ceil(tableData.value.length / tablePageSize))

// ═══ 图表双向联动 ═══
function onChartZoom(params: any) {
  if (params.batch?.[0]) {
    const z = params.batch[0]
    chartZoom.value = [z.start, z.end]
    const startIdx = Math.floor((z.start / 100) * tableData.value.length)
    const endIdx = Math.floor((z.end / 100) * tableData.value.length)
    tableData.value = allData.value.slice(startIdx, endIdx + 1)
    tablePage.value = 1
  }
}

// ═══ 图表配置 ═══
const chartOpt = computed(() => {
  const data =
    tableData.value.length > 0 ? tableData.value.slice(0, 200) : allData.value.slice(0, 200)
  if (data.length === 0) return {}
  const series: any[] = selectedMetrics.value
    .map((m) => {
      const cfg = metricOptions.find((o) => o.value === m)
      if (!cfg) return null
      const s: any = {
        name: cfg.label,
        type: 'line',
        data: data.map((d) => [d.label, d[m]]),
        smooth: true,
        symbol: 'none',
        lineStyle: { color: cfg.color, width: 2 },
      }
      if (m === 'upstreamLevel' || m === 'downstreamLevel') s.yAxisIndex = 0
      else s.yAxisIndex = 1
      return s
    })
    .filter(Boolean)

  const events = data.filter((d) => d.event)
  if (series.length > 0 && events.length > 0) {
    series[0].markPoint = {
      data: events.map((d) => ({
        name: d.event.label,
        coord: [d.label, d.upstreamLevel],
        symbol: 'pin',
        symbolSize: 32,
        itemStyle: { color: d.event.color },
        label: { show: true, fontSize: 12 },
      })),
    }
  }

  return {
    backgroundColor: 'transparent',
    legend: {
      data: selectedMetrics.value.map((m) => metricLabel(m)),
      textStyle: { color: '#64748b', fontSize: 14 },
      top: 0,
    },
    grid: { left: 56, right: 56, top: 36, bottom: 60 },
    tooltip: { trigger: 'axis', textStyle: { fontSize: 14 } },
    xAxis: {
      type: 'category',
      data: data.map((d) => d.label),
      axisLabel: { fontSize: 14, interval: Math.max(1, Math.floor(data.length / 6)) },
    },
    yAxis: [
      {
        type: 'value',
        splitLine: { lineStyle: { color: '#f1f5f9' } },
        axisLabel: { fontSize: 12 },
      },
      { type: 'value', splitLine: { show: false }, axisLabel: { fontSize: 12 } },
    ],
    dataZoom: [
      { type: 'slider', start: 0, end: 100, height: 24, bottom: 0, textStyle: { fontSize: 11 } },
    ],
    series,
  }
})

// ═══ 导出 ═══
const exporting = ref(false)
function doExport(format: 'csv' | 'xlsx') {
  exporting.value = true
  setTimeout(() => {
    const headers = ['时间', ...selectedMetrics.value.map((m) => metricLabel(m))]
    const rows = tableData.value.map((d) => [d.label, ...selectedMetrics.value.map((m) => d[m])])
    const csv = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n')
    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' })
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = `历史数据_${new Date().toISOString().slice(0, 10)}.${format === 'csv' ? 'csv' : 'csv'}`
    a.click()
    exporting.value = false
  }, 800)
}

// ═══ 智能报告 ═══
const reportVisible = ref(false)
const reportData = computed(() => {
  const data = tableData.value.length > 0 ? tableData.value : allData.value.slice(-100)
  const calc = (key: string) => ({
    max: Math.max(...data.map((d) => d[key])).toFixed(2),
    min: Math.min(...data.map((d) => d[key])).toFixed(2),
    avg: (data.reduce((s, d) => s + d[key], 0) / data.length).toFixed(2),
  })
  return {
    upstreamLevel: calc('upstreamLevel'),
    inflowRate: calc('inflowRate'),
    powerOutput: calc('powerOutput'),
    gateOpening: calc('gateOpening'),
    anomalies: data.filter((d) => d.event).length,
    total: data.length,
  }
})

generateMock()
tableData.value = [...allData.value]
</script>

<template>
  <div class="hp" :class="{ dark: darkMode }">
    <!-- 筛选区 -->
    <div class="filter">
      <div class="filter__row">
        <label>日期范围</label>
        <input type="datetime-local" v-model="dateRange.start" class="inp" />
        <span>—</span>
        <input type="datetime-local" v-model="dateRange.end" class="inp" />
      </div>
      <div class="filter__row">
        <label>数据项</label>
        <div class="tags">
          <span
            v-for="m in metricOptions"
            :key="m.value"
            class="tag"
            :class="{ on: selectedMetrics.includes(m.value) }"
            @click="selectedMetrics.includes(m.value)
                ? (selectedMetrics = selectedMetrics.filter((x) => x !== m.value))
                : selectedMetrics.push(m.value)"
            :style="{ '--c': m.color }"
            >{{ m.label }}</span
          >
        </div>
      </div>
      <div class="filter__row">
        <label>粒度</label>
        <div class="tags">
          <span
            v-for="g in granularityOptions"
            :key="g.value"
            class="tag"
            :class="{ on: granularity === g.value }"
            @click="granularity = g.value as any"
            >{{ g.label }}</span
          >
        </div>
      </div>
      <div class="filter__btns">
        <button class="btn btn--q" @click="applyFilters" :disabled="!isOnline">
          查询
        </button>
        <button class="btn" @click="resetFilters">重置</button>
        <button class="btn" @click="darkMode = !darkMode">{{ darkMode ? '☀' : '☾' }}</button>
        <span v-if="!isOnline" class="offline">离线 · 查询导出已禁用</span>
      </div>
    </div>

    <!-- 图表区 -->
    <div class="chart-wrap">
      <v-chart
        class="chart"
        :option="chartOpt"
        autoresize
        @datazoom="onChartZoom"
      />
    </div>

    <!-- 表格区 -->
    <div class="tbl-wrap">
      <div class="tbl__hd">
        <span>数据点 · {{ tableData.length }} 条</span>
        <div class="tbl__acts">
          <button
            class="btn btn--sm"
            @click="doExport('csv')"
            :disabled="!isOnline || exporting"
          >
            {{ exporting ? '导出中...' : 'CSV 导出' }}
          </button>
          <button class="btn btn--sm btn--with-icon" @click="reportVisible = true">
            <el-icon><DataAnalysis /></el-icon>
            智能报告
          </button>
        </div>
      </div>
      <div class="tbl__body">
        <table class="tbl">
          <thead>
            <tr>
              <th>时间</th>
              <th v-for="m in selectedMetrics" :key="m">{{ metricLabel(m) }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="d in pagedData"
              :key="d.time"
>
            >
              <td>{{ d.label }}</td>
              <td v-for="m in selectedMetrics" :key="m">{{ d[m] }} {{ metricUnit(m) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="tbl__pg">
        <button :disabled="tablePage <= 1" @click="tablePage--">‹</button>
        <span>{{ tablePage }} / {{ totalPages }}</span>
        <button :disabled="tablePage >= totalPages" @click="tablePage++">›</button>
      </div>
    </div>

    <!-- 智能报告弹窗 -->
    <Transition name="fade">
      <div v-if="reportVisible" class="modal" @click.self="reportVisible = false">
        <div class="modal__box">
          <h2>智能分析报告</h2>
          <div class="rep">
            <div class="rep__item">
              <span>数据点数</span><b>{{ reportData.total }}</b>
            </div>
            <div class="rep__item">
              <span>异常点数</span><b style="color: #ef4444">{{ reportData.anomalies }}</b>
            </div>
            <div class="rep__item">
              <span>上游水位</span
              ><b
                >最大 {{ reportData.upstreamLevel.max }}m / 最小 {{ reportData.upstreamLevel.min }}m
                / 平均 {{ reportData.upstreamLevel.avg }}m</b
              >
            </div>
            <div class="rep__item">
              <span>入库流量</span
              ><b
                >最大 {{ reportData.inflowRate.max }}m³/s / 最小 {{ reportData.inflowRate.min }}m³/s
                / 平均 {{ reportData.inflowRate.avg }}m³/s</b
              >
            </div>
            <div class="rep__item">
              <span>发电功率</span
              ><b
                >最大 {{ reportData.powerOutput.max }}MW / 最小 {{ reportData.powerOutput.min }}MW /
                平均 {{ reportData.powerOutput.avg }}MW</b
              >
            </div>
            <div class="rep__item">
              <span>阀门开度</span
              ><b
                >最大 {{ reportData.gateOpening.max }}% / 最小 {{ reportData.gateOpening.min }}% /
                平均 {{ reportData.gateOpening.avg }}%</b
              >
            </div>
          </div>
          <button class="btn" @click="reportVisible = false">关闭</button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped lang="scss">
.hp {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  overflow: hidden;
  background: #fff;
  transition:
    border-color 0.3s,
    box-shadow 0.3s;

  &.dark {
    background: #0f172a;
    color: #e2e8f0;
  }
// ═══ 原有样式 ═══
.filter {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  align-items: flex-end;
  padding: 18px 24px;
  background: var(--bg, #fff);
  border-bottom: 1px solid var(--br, #eef0f2);
  flex-shrink: 0;
}

.dark .filter {
  --bg: #1e293b;
  --br: #334155;
}

.filter__row {
  display: flex;
  align-items: center;
  gap: 10px;

  label {
    font-size: 14px;
    font-weight: 600;
    color: var(--tx, #64748b);
    white-space: nowrap;
  }

  > span {
    font-size: 14px;
    color: #94a3b8;
  }
}

.dark .filter__row label {
  --tx: #94a3b8;
}

.inp {
  padding: 8px 12px;
  font-size: 14px;
  color: #374151;
  background: #fff;
  border: 1px solid #d1d5db;
  border-radius: 6px;

  &:disabled {
    opacity: 0.45;
    cursor: not-allowed;
  }
}

.dark .inp {
  color: #e2e8f0;
  background: #334155;
  border-color: #475569;
}

.tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tag {
  padding: 7px 14px;
  font-size: 14px;
  color: #64748b;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 100px;
  cursor: pointer;
  transition: all 0.15s;

  &:hover {
    border-color: #93c5fd;
  }

  &.on {
    color: #fff;
    background: var(--c);
    border-color: var(--c);
  }
}

.dark .tag {
  color: #94a3b8;
  background: #334155;
  border-color: #475569;

  &.on {
    color: #fff;
    background: var(--c);
    border-color: var(--c);
  }
}

.filter__btns {
  display: flex;
  gap: 8px;
  align-items: center;
  margin-left: auto;
}

.btn {
  padding: 8px 16px;
  font-size: 14px;
  font-weight: 500;
  color: #64748b;
  background: #fff;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  cursor: pointer;

  &:hover:not(:disabled) {
    background: #f3f4f6;
  }
  &:disabled {
    opacity: 0.4;
    cursor: not-allowed;
  }
}

.btn--q {
  color: #fff;
  background: #3b82f6;
  border-color: #3b82f6;

  &:hover:not(:disabled) {
    background: #2563eb;
  }
}
.btn--sm {
  padding: 7px 14px;
  font-size: 14px;
}

.btn--with-icon {
  display: inline-flex;
  align-items: center;
  gap: 6px;

  .el-icon {
    font-size: 16px;
  }
}

.dark .btn {
  color: #94a3b8;
  background: #334155;
  border-color: #475569;
}

.dark .btn--q {
  color: #fff;
  background: #3b82f6;
  border-color: #3b82f6;
}

.offline {
  margin-left: 4px;
  font-size: 13px;
  color: #ef4444;
}

.chart-wrap {
  flex-shrink: 0;
  padding: 12px 24px 8px;
}

.chart {
  width: 100%;
  height: 240px;
}

.tbl-wrap {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  margin: 0 24px 12px;
  overflow: hidden;
  background: var(--bg, #fff);
  border: 1px solid var(--br, #eef0f4);
  border-radius: 10px;
}

.dark .tbl-wrap {
  --bg: #1e293b;
  --br: #334155;
}

.tbl__hd {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
  border-bottom: 1px solid var(--br, #eef0f4);
}

.dark .tbl__hd {
  color: #e2e8f0;
}

.tbl__acts {
  display: flex;
  gap: 8px;
}

.tbl__body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.tbl {
  width: 100%;
  border-collapse: collapse;

  th {
    padding: 11px 14px;
    font-size: 14px;
    font-weight: 600;
    text-align: left;
    color: #64748b;
    border-bottom: 1px solid var(--br, #f1f5f9);
  }

  td {
    padding: 12px 14px;
    font-size: 15px;
    color: var(--tx, #334155);
    line-height: 1.45;
    white-space: nowrap;
    border-bottom: 1px solid #f8fafc;
  }

  tr:hover td {
    background: var(--hb, #fafbfc);
  }
}

.dark .tbl td {
  --tx: #cbd5e1;
  border-color: #1e293b;
}
.dark .tbl tr:hover td {
  --hb: #283448;
}

.tbl__pg {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 12px 14px;
  font-size: 14px;
  color: #64748b;
  background: var(--bg, #fff);
  border-top: 1px solid var(--br, #eef0f4);

  button {
    padding: 6px 14px;
    font-size: 16px;
    cursor: pointer;
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 4px;

    &:disabled {
      opacity: 0.3;
      cursor: default;
    }
  }
}

.dark .tbl__pg button {
  color: #e2e8f0;
  background: #334155;
  border-color: #475569;
}

.modal {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.4);
}

.modal__box {
  width: 560px;
  max-height: 80vh;
  padding: 28px;
  overflow-y: auto;
  background: #fff;
  border-radius: 12px;

  h2 {
    margin: 0 0 20px;
    font-size: 17px;
    font-weight: 600;
    color: #1e293b;
  }
}

.dark .modal__box {
  color: #e2e8f0;
  background: #1e293b;

  h2 {
    color: #f1f5f9;
  }
}

.rep {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 16px;
}

.rep__item {
  display: flex;
  gap: 12px;
  padding: 12px 14px;
  font-size: 15px;
  line-height: 1.45;
  background: #f8fafc;
  border-radius: 6px;

  span {
    min-width: 88px;
    font-weight: 500;
    color: #64748b;
  }
  b {
    font-weight: 600;
    color: #1e293b;
  }
}

.dark .rep__item {
  background: #334155;
  b {
    color: #e2e8f0;
  }
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>

<style lang="scss">
.main-layout__content:has(.hp) {
  display: flex;
  flex-direction: column;
  padding: 0 !important;
  overflow: hidden !important;
}
</style>
