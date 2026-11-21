<script setup lang="ts">
import { ElTable, ElTableColumn } from 'element-plus'
import type { CompareResult } from '@/stores/aiHealth'

const props = defineProps<{
  data: CompareResult['scoreDiff'] | null
  loading: boolean
}>()

function getDiffClass(diff: number): string {
  if (diff > 0) return 'diff-positive'
  if (diff < 0) return 'diff-negative'
  return 'diff-zero'
}

function formatDiff(diff: number): string {
  const sign = diff > 0 ? '+' : ''
  return `${sign}${(diff * 100).toFixed(1)}%`
}

// Mock data sources mapping
const dataSources: Record<string, string> = {
  prediction: '边缘实时',
  decision: '边缘实时',
  compliance: '数字孪生仿真',
  safety_coverage: '边缘实时',
  decision_auto_rate: '边缘实时',
}
</script>

<template>
  <div v-loading="loading" class="score-diff-table">
    <h3 class="diff-title">评分差异明细</h3>
    <ElTable :data="data ?? []" border stripe style="width: 100%">
      <ElTableColumn prop="dimension" label="评估维度" min-width="140" align="center" />
      <ElTableColumn label="版本1" width="120" align="center">
        <template #default="scope">
          {{ ((scope.row as CompareResult['scoreDiff'][number]).v1 * 100).toFixed(1) }}%
        </template>
      </ElTableColumn>
      <ElTableColumn label="版本2" width="120" align="center">
        <template #default="scope">
          {{ ((scope.row as CompareResult['scoreDiff'][number]).v2 * 100).toFixed(1) }}%
        </template>
      </ElTableColumn>
      <ElTableColumn label="差异" width="120" align="center">
        <template #default="scope">
          <span :class="getDiffClass((scope.row as CompareResult['scoreDiff'][number]).diff)">
            {{ formatDiff((scope.row as CompareResult['scoreDiff'][number]).diff) }}
          </span>
        </template>
      </ElTableColumn>
      <ElTableColumn label="数据来源" min-width="140" align="center">
        <template #default="scope">
          {{
            dataSources[(scope.row as CompareResult['scoreDiff'][number]).dimension] ?? '边缘实时'
          }}
        </template>
      </ElTableColumn>
    </ElTable>
    <div v-if="!data && !loading" class="table-empty">暂无对比数据</div>
  </div>
</template>

<style scoped lang="scss">
.score-diff-table {
  margin-bottom: var(--spacing-lg);
}

.diff-title {
  font-size: var(--font-size-lg);
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: var(--spacing-md);
}

.diff-positive {
  color: #67c23a;
  font-weight: 700;
}

.diff-negative {
  color: #f56c6c;
  font-weight: 700;
}

.diff-zero {
  color: var(--color-text-secondary);
}

.table-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100px;
  color: var(--color-text-placeholder);
  font-size: var(--font-size-sm);
}
</style>
