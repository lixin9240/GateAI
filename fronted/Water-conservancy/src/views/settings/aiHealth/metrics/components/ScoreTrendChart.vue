<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import * as echarts from 'echarts'
import { EVAL_DIMENSIONS } from '@/constants/aiHealth'
import type { TrendPoint } from '@/stores/aiHealth'

const props = defineProps<{
  data: TrendPoint[]
  loading: boolean
}>()

const chartContainer = ref<HTMLDivElement>()
let chartInstance: echarts.ECharts | null = null
let resizeObserver: ResizeObserver | null = null

function getOption(): echarts.EChartsOption {
  return {
    tooltip: {
      trigger: 'axis',
      valueFormatter: (val: unknown) => {
        const v = val as number
        return `${(v * 100).toFixed(1)}%`
      },
      axisPointer: { type: 'cross' },
    },
    legend: {
      data: ['预测准确性', '决策可靠性', '物理合规性', '综合评分'],
      bottom: 0,
    },
    grid: {
      left: 50,
      right: 30,
      top: 20,
      bottom: 40,
    },
    xAxis: {
      type: 'category',
      data: props.data.map((d) => d.time),
      axisLabel: {
        rotate: 45,
        fontSize: 13,
      },
    },
    yAxis: {
      type: 'value',
      min: 0,
      max: 1,
      axisLabel: {
        formatter: (val: number) => `${(val * 100).toFixed(0)}%`,
      },
    },
    series: [
      {
        name: '预测准确性',
        type: 'line',
        data: props.data.map((d) => d.prediction),
        smooth: true,
        lineStyle: { width: 2, color: EVAL_DIMENSIONS.prediction.color },
        itemStyle: { color: EVAL_DIMENSIONS.prediction.color },
        symbol: 'circle',
        symbolSize: 4,
      },
      {
        name: '决策可靠性',
        type: 'line',
        data: props.data.map((d) => d.decision),
        smooth: true,
        lineStyle: { width: 2, color: EVAL_DIMENSIONS.decision.color },
        itemStyle: { color: EVAL_DIMENSIONS.decision.color },
        symbol: 'circle',
        symbolSize: 4,
      },
      {
        name: '物理合规性',
        type: 'line',
        data: props.data.map((d) => d.compliance),
        smooth: true,
        lineStyle: { width: 2, color: EVAL_DIMENSIONS.compliance.color },
        itemStyle: { color: EVAL_DIMENSIONS.compliance.color },
        symbol: 'circle',
        symbolSize: 4,
      },
      {
        name: '综合评分',
        type: 'line',
        data: props.data.map((d) => d.overall),
        smooth: true,
        lineStyle: { width: 3, type: 'dashed', color: '#EE6666' },
        itemStyle: { color: '#EE6666' },
        symbol: 'diamond',
        symbolSize: 6,
      },
    ],
  }
}

function initChart() {
  if (!chartContainer.value) return
  chartInstance = echarts.init(chartContainer.value)
  chartInstance.setOption(getOption())

  resizeObserver = new ResizeObserver(() => {
    chartInstance?.resize()
  })
  resizeObserver.observe(chartContainer.value)
}

function updateChart() {
  if (!chartInstance) return
  chartInstance.setOption(getOption(), true)
}

watch(
  () => props.data,
  () => {
    updateChart()
  },
  { deep: true },
)

onMounted(() => {
  initChart()
})

onBeforeUnmount(() => {
  resizeObserver?.disconnect()
  chartInstance?.dispose()
  chartInstance = null
})
</script>

<template>
  <div v-loading="loading" class="score-trend-chart">
    <h3 class="chart-title">7日健康度趋势</h3>
    <div ref="chartContainer" class="chart-container" />
    <div v-if="!data.length && !loading" class="chart-empty">暂无趋势数据</div>
  </div>
</template>

<style scoped lang="scss">
.score-trend-chart {
  margin-bottom: var(--spacing-lg);
}

.chart-title {
  font-size: var(--font-size-lg);
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: var(--spacing-md);
}

.chart-container {
  width: 100%;
  height: 360px;
}

.chart-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 200px;
  color: var(--color-text-placeholder);
  font-size: var(--font-size-sm);
}
</style>
