<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { ElIcon, ElMessage } from 'element-plus'
import { DataAnalysis } from '@element-plus/icons-vue'
import VChart from 'vue-echarts'
import { use } from 'echarts/core'
import { LineChart } from 'echarts/charts'
import { GridComponent, TooltipComponent, LegendComponent, DataZoomComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import * as XLSX from 'xlsx'
import { fetchHistoryData, exportHistoryData, getExportStatus, METRIC_MAP } from '@/api/history'
import { getEquipmentAllList } from '@/api/equipment'
import type { HistoryDataPoint } from '@/types/monitoring'
use([LineChart, GridComponent, TooltipComponent, LegendComponent, DataZoomComponent, CanvasRenderer])

// ═══ 主题 ═══
const darkMode = ref(localStorage.getItem('history-theme') === 'dark')
watch(darkMode, (v: boolean) => localStorage.setItem('history-theme', v ? 'dark' : 'light'))

// ═══ 离线检测 ═══
const isOnline = ref(navigator.onLine)
onMounted(() => {
  window.addEventListener('online', () => (isOnline.value = true))
  window.addEventListener('offline', () => (isOnline.value = false))
})

// ═══ 筛选 ═══
const dateRange = ref({ start: '', end: '' })
const nowISO = computed(() => new Date().toISOString().slice(0, 16))
const endMin = computed(() => dateRange.value.start || undefined)
const selectedMetrics = ref<string[]>(['upstreamLevel', 'inflowRate'])
const granularity = ref<'raw' | '5min' | 'hour' | 'day'>('5min')
const metricOptions = [
  { value: 'upstreamLevel', label: '上游水位', unit: 'm', color: '#3b82f6' },
  { value: 'downstreamLevel', label: '下游水位', unit: 'm', color: '#06b6d4' },
  { value: 'inflowRate', label: '入库流量', unit: 'm³/s', color: '#8b5cf6' },
  { value: 'outflowRate', label: '出库流量', unit: 'm³/s', color: '#22c55e' },
  { value: 'gateOpening', label: '阀门开度', unit: '%', color: '#f59e0b' },
  { value: 'powerOutput', label: '发电功率', unit: 'MW', color: '#ef4444' },
]
const queried = ref(false)

// ── 日期快捷选项 ──
const datePreset = ref('近7天')
const datePresets = [
  { label: '今天', days: 1 },
  { label: '近3天', days: 3 },
  { label: '近7天', days: 7 },
  { label: '近30天', days: 30 },
  { label: '本月', days: -1 },
]
function applyDatePreset(p: { label: string; days: number }) {
  datePreset.value = p.label
  const end = new Date()
  let start: Date
  if (p.days === -1) {
    start = new Date(end.getFullYear(), end.getMonth(), 1)
  } else {
    start = new Date(end.getTime() - p.days * 86400000)
  }
  dateRange.value.start = start.toISOString().slice(0, 16)
  dateRange.value.end = end.toISOString().slice(0, 16)
}

// 初始化默认日期
;(() => {
  const end = new Date()
  const start = new Date(end.getTime() - 7 * 86400000)
  dateRange.value.start = start.toISOString().slice(0, 16)
  dateRange.value.end = end.toISOString().slice(0, 16)
})()

// ═══ 数据 ═══
const allData = ref<HistoryDataPoint[]>([])
const tableData = ref<HistoryDataPoint[]>([])
const tablePage = ref(1)
const tablePageSize = 15
const chartZoom = ref<[number, number] | null>(null)

async function loadData() {
  const start =
    dateRange.value.start ||
    new Date(Date.now() - 7 * 86400000).toISOString().replace('T', ' ').slice(0, 19)
  const end =
    dateRange.value.end ||
    new Date().toISOString().replace('T', ' ').slice(0, 19)
  try {
    allData.value = await fetchHistoryData({
      reservoirId: 1,
      start,
      end,
      metrics: selectedMetrics.value,
      granularity: granularity.value,
    })
  } catch {
    // fetchHistoryData 内部已有 Mock 降级
  }
  tableData.value = [...allData.value]
}

async function applyFilters() {
  queried.value = true
  await loadData()
  tablePage.value = 1
}
async function resetFilters() {
  dateRange.value = { start: '', end: '' }
  selectedMetrics.value = ['upstreamLevel', 'inflowRate']
  granularity.value = '5min'
  queried.value = false
  chartZoom.value = null
  await loadData()
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
    animation: true,
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
const exporting = ref('')
async function doExport(format: 'csv' | 'xlsx') {
  exporting.value = format
  try {
    // 先获取水库下所有设备 ID
    const eqRes = await getEquipmentAllList({ reservoir_id: 1 })
    const equipmentIds: number[] = []
    if (eqRes.data?.code === 0 && Array.isArray(eqRes.data.data)) {
      equipmentIds.push(...eqRes.data.data.map((e) => e.id))
    }

    const start = dateRange.value.start
      ? dateRange.value.start.replace('T', ' ').slice(0, 19)
      : new Date(Date.now() - 7 * 86400000).toISOString().replace('T', ' ').slice(0, 19)
    const end = dateRange.value.end
      ? dateRange.value.end.replace('T', ' ').slice(0, 19)
      : new Date().toISOString().replace('T', ' ').slice(0, 19)

    const res = await exportHistoryData({
      equipment_ids: equipmentIds.length > 0 ? equipmentIds : [1],
      start_time: start,
      end_time: end,
      metrics: selectedMetrics.value.map((m) => METRIC_MAP[m] ?? m),
      format: format === 'xlsx' ? 'excel' : 'csv',
    })
    if (res.data?.code === 0 && res.data.data) {
      const data = res.data.data as Record<string, unknown>
      const taskId = (data.task_id ?? data.task_no ?? data.id ?? '') as string
      if (!taskId) throw new Error('未获取到任务ID')
      ElMessage.info('导出任务已提交，正在生成文件...')
      // 轮询等待导出完成
      for (let i = 0; i < 15; i++) {
        await new Promise((r) => setTimeout(r, 2000))
        const statusRes = await getExportStatus(taskId)
        if (statusRes.data?.code === 0) {
          const s = statusRes.data.data as { status: string; download_url?: string }
          if (s.status === 'completed' && s.download_url) {
            window.open(s.download_url, '_blank')
            ElMessage.success('导出完成')
            exporting.value = ''
            return
          }
          if (s.status === 'failed') {
            throw new Error('导出失败')
          }
        }
      }
      ElMessage.warning('导出超时，请稍后在下载中心查看')
      exporting.value = ''
      return
    }
  } catch { /* 降级前端导出 */ }

  // 前端本地导出
  const headers = ['时间', ...selectedMetrics.value.map((m) => metricLabel(m))]
  const data = tableData.value.length > 0 ? tableData.value : allData.value
  const rows = data.map((d: any) => [d.label, ...selectedMetrics.value.map((m: string) => d[m])])
  const dateStr = new Date().toISOString().slice(0, 10)

  if (format === 'xlsx') {
    const ws = XLSX.utils.aoa_to_sheet([headers, ...rows])
    ws['!cols'] = headers.map(() => ({ wch: 18 }))
    const wb = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(wb, ws, '历史数据')
    XLSX.writeFile(wb, `历史数据_${dateStr}.xlsx`)
  } else {
    const csv = ['﻿' + headers.join(','), ...rows.map((r: any) => r.join(','))].join('\n')
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' })
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = `历史数据_${dateStr}.csv`
    a.click()
  }
  exporting.value = ''
}

// ═══ 智能报告 ═══
import SmartReportModal from './SmartReportModal.vue'
const reportVisible = ref(false)

// 初始不自动加载，等用户点击"查询"按钮
// loadData() 由 applyFilters 触发
</script>

<template>
  <div class="hp" :class="{ dark: darkMode }">
    <!-- 筛选区 -->
    <div class="filter">
      <div class="filter__row">
        <label>日期范围</label>
        <div class="date-presets">
          <span
            v-for="d in datePresets"
            :key="d.label"
            class="date-preset"
            :class="{ on: datePreset === d.label }"
            @click="applyDatePreset(d)"
            >{{ d.label }}</span
          >
        </div>
        <input type="datetime-local" v-model="dateRange.start" class="inp" :max="nowISO" />
        <span class="filter__sep">—</span>
        <input type="datetime-local" v-model="dateRange.end" class="inp" :max="nowISO" :min="endMin" />
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
      <div class="filter__btns">
        <button class="btn btn--q" @click="applyFilters" :disabled="!isOnline">查询</button>
        <button class="btn" @click="resetFilters">重置</button>
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
      <div class="chart-hint">↔ 拖动底部滑块缩放时间范围，框选图表区域可聚焦查看</div>
    </div>

    <!-- 表格区 -->
    <div class="tbl-wrap">
      <div class="tbl__hd">
        <span>数据点 · {{ tableData.length }} 条</span>
        <div class="tbl__acts">
          <button class="btn btn--sm" @click="doExport('csv')" :disabled="!isOnline || !!exporting">
            导出 CSV
          </button>
          <button
            class="btn btn--sm"
            @click="doExport('xlsx')"
            :disabled="!isOnline || !!exporting"
          >
            导出 Excel
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
    <SmartReportModal
      :visible="reportVisible"
      :table-data="tableData"
      :all-data="allData"
      @close="reportVisible = false"
    />
  </div>
</template>

<style scoped lang="scss">
@use '@/assets/styles/text-mixins.scss' as *;

.hp {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  overflow: hidden;
  background: #fff;
}

.filter {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  align-items: flex-end;
  padding: 18px 24px;
  background: #fff;
  border-bottom: 1px solid #eef0f2;
  flex-shrink: 0;
}

.filter__row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  label {
    @include text-tag;
    font-weight: 600;
    white-space: nowrap;
  }
  > span {
    font-size: 14px;
    color: #94a3b8;
  }
}

.filter__sep {
  color: #94a3b8;
  font-size: 14px;
}

.date-presets {
  display: flex;
  gap: 4px;
}
.date-preset {
  padding: 5px 12px;
  font-size: 13px;
  font-weight: 500;
  color: #64748b;
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s;
  &:hover {
    border-color: #93c5fd;
    color: #3b82f6;
  }
  &.on {
    color: #fff;
    background: #3b82f6;
    border-color: #3b82f6;
  }
}

.inp {
  padding: 8px 12px;
  @include text-input;
  background: #fff;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  &:disabled {
    opacity: 0.45;
    cursor: not-allowed;
  }
}

.tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tag {
  padding: 7px 16px;
  @include text-tag;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 100px;
  cursor: pointer;
  transition: all 0.15s;
  &:hover {
    border-color: #93c5fd;
  }
  &.on {
    @include text-tag-active;
    background: var(--c, #3b82f6);
    border-color: var(--c, #3b82f6);
  }
}

.filter__btns {
  display: flex;
  gap: 8px;
  align-items: center;
  margin-left: auto;
}

.btn {
  padding: 9px 18px;
  @include text-btn;
  color: #475569;
  background: #fff;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  cursor: pointer;
  &:hover:not(:disabled) {
    background: #f3f4f6;
    border-color: #9ca3af;
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
  @include text-btn;
}
.btn--with-icon {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  .el-icon {
    font-size: 16px;
  }
}

.chart-wrap {
  flex-shrink: 0;
  padding: 12px 24px 0;
}
.chart {
  width: 100%;
  height: 260px;
}
.chart-hint {
  text-align: center;
  font-size: 12px;
  color: #94a3b8;
  padding: 4px 0 8px;
}

.tbl-wrap {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  margin: 0 24px 12px;
  overflow: hidden;
  background: #fff;
  border: 1px solid #eef0f4;
  border-radius: 10px;
}

.tbl__hd {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  @include text-section-title;
  border-bottom: 1px solid #eef0f4;
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
    @include text-table-header;
    text-align: left;
    border-bottom: 1px solid #eef0f2;
    background: #fff;
  }
  td {
    padding: 12px 14px;
    @include text-table-number;
    line-height: 1.45;
    white-space: nowrap;
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

.tbl__pg {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 12px 14px;
  font-size: 14px;
  color: #64748b;
  background: #fff;
  border-top: 1px solid #eef0f4;
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

</style>

<style lang="scss">
.history-shell__body:has(.hp) {
  display: flex;
  flex-direction: column;
  overflow: hidden !important;
}
</style>
