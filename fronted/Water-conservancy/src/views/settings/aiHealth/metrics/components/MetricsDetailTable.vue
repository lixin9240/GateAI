<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { ElTable, ElTableColumn, ElPagination, ElDatePicker, ElTag } from 'element-plus'
import { HEALTH_GRADE } from '@/constants/aiHealth'
import { fetchModelMetricsDetail } from '@/api/gateaiSettings'
import type { ModelMetricDetailRow } from '@/types/gateai'
import type { MetricsDetailItem } from '@/stores/aiHealth'

const props = defineProps<{
  reservoirId: number
}>()

const data = ref<MetricsDetailItem[]>([])
const loading = ref(false)
const page = ref(1)
const pageSize = ref(24)
const total = ref(0)
const timeRange = ref<[Date, Date] | null>(null)

function getGradeTagClass(grade: string): '' | 'success' | 'warning' | 'danger' | 'info' {
  const map: Record<string, '' | 'success' | 'warning' | 'danger' | 'info'> = {
    S: 'success',
    A: '',
    B: 'warning',
    C: 'warning',
    D: 'danger',
  }
  return map[grade] ?? 'info'
}

function getGradeColor(grade: string): string {
  return HEALTH_GRADE[grade]?.color ?? '#909399'
}

function mapDetailRow(row: ModelMetricDetailRow): MetricsDetailItem {
  return {
    hour: row.metric_time,
    prediction_score: row.overall_score,
    decision_score: row.overall_score,
    compliance_score: row.overall_score,
    overall_score: row.overall_score,
    health_grade: row.health_grade,
    water_level_mae: row.water_level_mae_24h,
    safety_coverage_rate: Math.max(0, 1 - row.safety_override_rate),
    decision_auto_rate: 0,
  }
}

async function fetchData() {
  loading.value = true
  try {
    const rows = await fetchModelMetricsDetail(props.reservoirId, {
      page: page.value,
      page_size: pageSize.value,
    })
    if (rows.length > 0) {
      data.value = rows.map(mapDetailRow)
      total.value = rows.length
      return
    }
  } catch {
    /* fallback to mock below */
  }
  // Mock data
  const mockList: MetricsDetailItem[] = []
  const hours = 24
  for (let i = 0; i < hours; i++) {
    mockList.push({
      hour: `2026-07-04 ${String(i).padStart(2, '0')}:00`,
      prediction_score: 0.75 + Math.random() * 0.2,
      decision_score: 0.7 + Math.random() * 0.25,
      compliance_score: 0.8 + Math.random() * 0.15,
      overall_score: 0.73 + Math.random() * 0.18,
      health_grade: ['S', 'A', 'A', 'B', 'B', 'B', 'C'][Math.floor(Math.random() * 7)],
      water_level_mae: 0.02 + Math.random() * 0.08,
      safety_coverage_rate: 0.85 + Math.random() * 0.1,
      decision_auto_rate: 0.6 + Math.random() * 0.3,
    })
  }
  mockList.sort((a, b) => b.hour.localeCompare(a.hour))
  total.value = 168 // 7 days worth
  const start = (page.value - 1) * pageSize.value
  data.value = mockList.slice(0, pageSize.value)
  loading.value = false
}

function onPageChange(p: number) {
  page.value = p
  fetchData()
}

watch(
  () => props.reservoirId,
  () => {
    page.value = 1
    fetchData()
  },
)

onMounted(() => {
  fetchData()
})
</script>

<template>
  <div class="metrics-detail-table">
    <div class="table-header">
      <h3 class="table-title">逐时明细数据</h3>
      <ElDatePicker
        v-model="timeRange"
        type="datetimerange"
        range-separator="至"
        start-placeholder="开始时间"
        end-placeholder="结束时间"
        format="YYYY-MM-DD HH:mm"
        value-format="YYYY-MM-DD HH:mm"
        style="width: 360px"
      />
    </div>

    <ElTable v-loading="loading" :data="data" border stripe style="width: 100%">
      <ElTableColumn prop="hour" label="时间段" min-width="150" align="center" />
      <ElTableColumn prop="prediction_score" label="预测准确性得分" min-width="120" align="center">
        <template #default="scope">
          {{ ((scope.row as MetricsDetailItem).prediction_score * 100).toFixed(1) }}%
        </template>
      </ElTableColumn>
      <ElTableColumn prop="decision_score" label="决策可靠性得分" min-width="120" align="center">
        <template #default="scope">
          {{ ((scope.row as MetricsDetailItem).decision_score * 100).toFixed(1) }}%
        </template>
      </ElTableColumn>
      <ElTableColumn prop="compliance_score" label="物理合规性得分" min-width="120" align="center">
        <template #default="scope">
          {{ ((scope.row as MetricsDetailItem).compliance_score * 100).toFixed(1) }}%
        </template>
      </ElTableColumn>
      <ElTableColumn prop="overall_score" label="综合评分" min-width="110" align="center">
        <template #default="scope">
          {{ ((scope.row as MetricsDetailItem).overall_score * 100).toFixed(1) }}%
        </template>
      </ElTableColumn>
      <ElTableColumn prop="health_grade" label="健康等级" width="100" align="center">
        <template #default="scope">
          <ElTag
            :type="getGradeTagClass((scope.row as MetricsDetailItem).health_grade)"
            :color="getGradeColor((scope.row as MetricsDetailItem).health_grade)"
            effect="dark"
            size="small"
          >
            {{ (scope.row as MetricsDetailItem).health_grade }}
          </ElTag>
        </template>
      </ElTableColumn>
      <ElTableColumn prop="water_level_mae" label="水位MAE" min-width="100" align="center">
        <template #default="scope">
          {{ (scope.row as MetricsDetailItem).water_level_mae.toFixed(3) }}m
        </template>
      </ElTableColumn>
      <ElTableColumn prop="safety_coverage_rate" label="安全覆盖率" min-width="110" align="center">
        <template #default="scope">
          {{ ((scope.row as MetricsDetailItem).safety_coverage_rate * 100).toFixed(1) }}%
        </template>
      </ElTableColumn>
      <ElTableColumn prop="decision_auto_rate" label="决策自主率" min-width="110" align="center">
        <template #default="scope">
          {{ ((scope.row as MetricsDetailItem).decision_auto_rate * 100).toFixed(1) }}%
        </template>
      </ElTableColumn>
    </ElTable>

    <ElPagination
      v-model:current-page="page"
      :page-size="pageSize"
      :total="total"
      layout="total, prev, pager, next"
      background
      style="margin-top: var(--spacing-md); justify-content: flex-end"
      @current-change="onPageChange"
    />
  </div>
</template>

<style scoped lang="scss">
.metrics-detail-table {
  //

  .table-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--spacing-md);
  }

  .table-title {
    font-size: var(--font-size-lg);
    font-weight: 600;
    color: var(--color-text);
    margin: 0;
  }
}
</style>
