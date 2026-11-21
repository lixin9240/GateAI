<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { ElDatePicker } from 'element-plus'
import VChart from 'vue-echarts'
import { fetchHistoryData } from '@/api/history'
import type { HistoryDataPoint } from '@/types/monitoring'
import { use } from 'echarts/core'
import { LineChart } from 'echarts/charts'
import {
  GridComponent,
  TooltipComponent,
  LegendComponent,
  DataZoomComponent,
} from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
use([
  LineChart,
  GridComponent,
  TooltipComponent,
  LegendComponent,
  DataZoomComponent,
  CanvasRenderer,
])

// ── 数据项（单选，模仿 DataQueryTab 的 tag 按钮） ──
const metricOptions = [
  { value: 'upstreamLevel', label: '上游水位', unit: 'm', color: '#3b82f6' },
  { value: 'downstreamLevel', label: '下游水位', unit: 'm', color: '#06b6d4' },
  { value: 'inflowRate', label: '入库流量', unit: 'm³/s', color: '#8b5cf6' },
  { value: 'outflowRate', label: '出库流量', unit: 'm³/s', color: '#22c55e' },
  { value: 'gateOpening', label: '阀门开度', unit: '%', color: '#f59e0b' },
  { value: 'powerOutput', label: '发电功率', unit: 'MW', color: '#ef4444' },
]
const selectedMetric = ref('upstreamLevel')
const metricCfg = computed(() => metricOptions.find((o) => o.value === selectedMetric.value)!)
const metricLabel = computed(() => metricCfg.value.label)
const metricUnit = computed(() => metricCfg.value.unit)
const metricColorA = '#1890ff'
const metricColorB = '#ff7f0e'

// 切换数据项 → 自动清除上一次对比结果
watch(selectedMetric, () => {
  timeError.value = ''
  const aOk = rangeA.value.start && rangeA.value.end && new Date(rangeA.value.end) >= new Date(rangeA.value.start)
  const bOk = rangeB.value.start && rangeB.value.end && new Date(rangeB.value.end) >= new Date(rangeB.value.start)
  if (aOk && bOk) {
    doQuery()
  } else {
    dataA.value = []
    dataB.value = []
    queried.value = false
  }
})

// ── 时段 A / B ──
const rangeA = ref({ start: '', end: '' })
const rangeB = ref({ start: '', end: '' })
const compareMode = ref(false)
const queried = ref(false)
const timeError = ref('')

// el-date-picker 禁用日期
const disableFuture = (time: Date) => time.getTime() > Date.now()
function disableBeforeTime(time: Date, startVal: string) {
  if (!startVal) return false
  const startDay = new Date(startVal)
  startDay.setHours(0, 0, 0, 0)
  return time.getTime() < startDay.getTime()
}
function disableBeforeA(time: Date) { return disableBeforeTime(time, rangeA.value.start) }
function disableBeforeB(time: Date) { return disableBeforeTime(time, rangeB.value.start) }

async function loadRange(startIso: string, endIso: string) {
  const pts = await fetchHistoryData({
    reservoirId: 1,
    start: startIso,
    end: endIso,
    metrics: [selectedMetric.value],
    granularity: 'hour',
  })
  return pts.map((d) => ({
    time: d.time,
    label: new Date(d.time).toLocaleString('zh-CN', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
    }),
    value: (d as any)[selectedMetric.value] as number,
  }))
}

const dataA = ref<any[]>([])
const dataB = ref<any[]>([])

async function doQuery() {
  timeError.value = ''

  for (const [label, range] of [['时段 A', rangeA] as const, ['时段 B', rangeB] as const]) {
    const s = range.value.start
    const e = range.value.end
    if (!s || !e) {
      timeError.value = `请为${label}选择完整的起止时间`
      return
    }
    if (new Date(e) < new Date(s)) {
      timeError.value = `${label}：结束时间不能早于开始时间，请重新选择`
      return
    }
  }

  try {
    const [a, b] = await Promise.all([
      loadRange(rangeA.value.start, rangeA.value.end),
      loadRange(rangeB.value.start, rangeB.value.end),
    ])
    dataA.value = a
    dataB.value = b
    // 任一时段无数据：给出提示，且不进入对比展示
    if (!a.length || !b.length) {
      const missing = !a.length && !b.length
        ? '时段 A 与时段 B'
        : !a.length
          ? '时段 A'
          : '时段 B'
      timeError.value = `${missing}没有查询到数据，请调整起止时间后重试`
      compareMode.value = false
      queried.value = false
      return
    }
    compareMode.value = true
    queried.value = true
  } catch {
    timeError.value = '数据查询失败，请检查时间范围是否有效'
  }
}

// ── 差异统计 ──
const diffStats = computed(() => {
  if (!dataA.value.length || !dataB.value.length) return null
  const len = Math.min(dataA.value.length, dataB.value.length)
  let maxDiff = 0,
    sumDiff = 0,
    sumA = 0
  for (let i = 0; i < len; i++) {
    const va = dataA.value[i].value
    const vb = dataB.value[i].value
    const d = Math.abs(va - vb)
    if (d > maxDiff) maxDiff = d
    sumDiff += d
    sumA += va
  }
  const avgBase = sumA / len || 1
  return {
    maxDiff: +maxDiff.toFixed(2),
    avgDiff: +(sumDiff / len).toFixed(2),
    pct: +((sumDiff / len / avgBase) * 100).toFixed(1),
  }
})

// ── 图表（单选数据项，A / B 各一条线）──
const chartOpt = computed(() => {
  const allLabels = dataA.value.map((d, i) => (dataB.value[i] || d).label)
  return {
    tooltip: { trigger: 'axis' },
    legend: { data: [`时段A ${metricLabel.value}`, `时段B ${metricLabel.value}`], top: 0 },
    grid: { left: 60, right: 40, top: 40, bottom: 60 },
    xAxis: {
      type: 'category',
      data: allLabels,
      axisLabel: { interval: 'auto', hideOverlap: true },
    },
    yAxis: { type: 'value', name: `${metricLabel.value}(${metricUnit.value})` },
    dataZoom: [{ type: 'slider', bottom: 0, height: 22 }],
    series: [
      {
        name: `时段A ${metricLabel.value}`,
        type: 'line',
        data: dataA.value.map((d) => d.value),
        smooth: true,
        lineStyle: { color: metricColorA, width: 2 },
        itemStyle: { color: metricColorA },
        symbol: 'none',
      },
      {
        name: `时段B ${metricLabel.value}`,
        type: 'line',
        data: dataB.value.map((d) => d.value),
        smooth: true,
        lineStyle: { color: metricColorB, width: 2, type: 'dashed' },
        itemStyle: { color: metricColorB },
        symbol: 'none',
      },
    ],
  }
})

function doExport() {
  const h = metricLabel.value
  const rows = [['时间', `A${h}`, `B${h}`]]
  const len = Math.max(dataA.value.length, dataB.value.length)
  for (let i = 0; i < len; i++) {
    rows.push([
      dataA.value[i]?.label ?? '',
      dataA.value[i]?.value ?? '',
      dataB.value[i]?.value ?? '',
    ])
  }
  const csv = rows.map((r) => r.join(',')).join('\n')
  const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `历史数据_对比_${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
}
</script>

<template>
  <div class="dc">
    <!-- 筛选区 -->
    <div class="dc__filters">
      <!-- 数据项（单选 tag） -->
      <div class="dc__metric-row">
        <label class="dc__label">数据项</label>
        <div class="dc__tags">
          <span
            v-for="m in metricOptions"
            :key="m.value"
            class="dc__tag"
            :class="{ on: selectedMetric === m.value }"
            :style="{ '--c': m.color }"
            @click="selectedMetric = m.value"
          >{{ m.label }}</span>
        </div>
      </div>

      <!-- 时段选择 -->
      <div class="dc__period-row">
        <div class="dc__period">
          <span class="dc__badge dc__badge--a">时段 A</span>
          <ElDatePicker
            v-model="rangeA.start"
            type="datetime"
            placeholder="开始时间"
            format="YYYY-MM-DD HH:mm"
            value-format="YYYY-MM-DD HH:mm:ss"
            :disabled-date="disableFuture"
            style="width: 190px"
          />
          <span>—</span>
          <ElDatePicker
            v-model="rangeA.end"
            type="datetime"
            placeholder="结束时间"
            format="YYYY-MM-DD HH:mm"
            value-format="YYYY-MM-DD HH:mm:ss"
            :disabled-date="disableBeforeA"
            style="width: 190px"
          />
        </div>
        <div class="dc__period">
          <span class="dc__badge dc__badge--b">时段 B</span>
          <ElDatePicker
            v-model="rangeB.start"
            type="datetime"
            placeholder="开始时间"
            format="YYYY-MM-DD HH:mm"
            value-format="YYYY-MM-DD HH:mm:ss"
            :disabled-date="disableFuture"
            style="width: 190px"
          />
          <span>—</span>
          <ElDatePicker
            v-model="rangeB.end"
            type="datetime"
            placeholder="结束时间"
            format="YYYY-MM-DD HH:mm"
            value-format="YYYY-MM-DD HH:mm:ss"
            :disabled-date="disableBeforeB"
            style="width: 190px"
          />
        </div>
        <button class="dc__btn dc__btn--q" @click="doQuery">对比查询</button>
        <button class="dc__btn" @click="doExport" :disabled="!queried">导出 CSV</button>
      </div>

      <p v-if="timeError" class="dc__error">{{ timeError }}</p>
    </div>

    <!-- 差异统计 -->
    <div v-if="diffStats" class="dc__stats">
      <span>最大差 <b>{{ diffStats.maxDiff }}{{ metricUnit }}</b></span>
      <span>平均差 <b>{{ diffStats.avgDiff }}{{ metricUnit }}</b></span>
      <span>变化率 <b>{{ diffStats.pct }}%</b></span>
    </div>

    <!-- 图表 -->
    <div class="dc__chart">
      <VChart v-if="queried" :option="chartOpt" autoresize style="width: 100%; height: 100%" />
      <div v-else class="dc__empty">请选择数据项和 A / B 时段，然后点击"对比查询"</div>
    </div>
  </div>
</template>

<style scoped lang="scss">
@use '@/assets/styles/text-mixins.scss' as *;
.dc {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 18px 24px;
  gap: 14px;

  &__filters {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  &__metric-row {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  &__label {
    @include text-tag;
    font-weight: 600;
    white-space: nowrap;
  }

  &__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  &__tag {
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
      color: #fff;
      background: var(--c, #3b82f6);
      border-color: var(--c, #3b82f6);
    }
  }

  &__period-row {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
  }

  &__period {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  &__badge {
    padding: 4px 14px;
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    border-radius: 4px;
    &--a {
      background: #1890ff;
    }
    &--b {
      background: #ff7f0e;
    }
  }

  &__btn {
    padding: 8px 16px;
    @include text-btn;
    color: #475569;
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    cursor: pointer;
    &:hover:not(:disabled) {
      background: #f3f4f6;
    }
    &:disabled {
      opacity: 0.4;
    }
    &--q {
      color: #fff;
      background: #3b82f6;
      border-color: #3b82f6;
      &:hover:not(:disabled) {
        background: #2563eb;
      }
    }
  }

  &__error {
    width: 100%;
    margin: 0;
    font-size: 14px;
    color: #dc2626;
  }

  &__stats {
    display: flex;
    gap: 20px;
    padding: 10px 16px;
    background: #f8fafc;
    border-radius: 8px;
    @include text-table-header;
    b {
      @include text-table-number;
      margin-left: 4px;
    }
  }

  &__chart {
    flex: 1;
    min-height: 0;
    background: #fff;
    border-radius: 8px;
    border: 1px solid #eef0f2;
  }

  &__empty {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #94a3b8;
    font-size: 16px;
  }
}
</style>
