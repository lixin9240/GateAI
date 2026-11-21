<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import * as echarts from 'echarts'
import { RADAR_DIMENSIONS } from '@/constants/aiHealth'
import type { CompareResult } from '@/stores/aiHealth'

const props = defineProps<{
  data: CompareResult['radarData'] | null
  loading: boolean
}>()

const chartContainer = ref<HTMLDivElement>()
let chartInstance: echarts.ECharts | null = null
let resizeObserver: ResizeObserver | null = null

function getOption(): echarts.EChartsOption {
  const indicators = RADAR_DIMENSIONS.map((d) => {
    const item = props.data?.find((r) => r.dimension === d.key)
    return {
      name: d.label,
      max: 1.0,
    }
  })

  const v1Data = RADAR_DIMENSIONS.map((d) => {
    const item = props.data?.find((r) => r.dimension === d.key)
    return item?.v1 ?? 0
  })

  const v2Data = RADAR_DIMENSIONS.map((d) => {
    const item = props.data?.find((r) => r.dimension === d.key)
    return item?.v2 ?? 0
  })

  return {
    tooltip: {
      trigger: 'item',
      valueFormatter: (val: unknown) => `${((val as number) * 100).toFixed(1)}%`,
    },
    legend: {
      data: ['版本1', '版本2'],
      bottom: 0,
    },
    radar: {
      indicator: indicators,
      center: ['50%', '48%'],
      radius: '60%',
    },
    series: [
      {
        type: 'radar',
        data: [
          {
            name: '版本1',
            value: v1Data,
            lineStyle: { color: '#5470C6', width: 2 },
            areaStyle: { color: 'rgba(84, 112, 198, 0.2)' },
            itemStyle: { color: '#5470C6' },
          },
          {
            name: '版本2',
            value: v2Data,
            lineStyle: { color: '#91CC75', width: 2 },
            areaStyle: { color: 'rgba(145, 204, 117, 0.2)' },
            itemStyle: { color: '#91CC75' },
          },
        ],
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
  () => [props.data, props.loading],
  () => {
    if (!props.loading && props.data) {
      updateChart()
    }
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
  <div v-loading="loading" class="compare-radar">
    <div ref="chartContainer" class="radar-container" />
    <div v-if="!data && !loading" class="radar-empty">请选择两个版本进行对比</div>
  </div>
</template>

<style scoped lang="scss">
.compare-radar {
  margin-bottom: var(--spacing-lg);
}

.radar-container {
  width: 100%;
  height: 400px;
}

.radar-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 300px;
  color: var(--color-text-placeholder);
  font-size: var(--font-size-sm);
}
</style>
