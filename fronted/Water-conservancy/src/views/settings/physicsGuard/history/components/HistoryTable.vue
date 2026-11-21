<script setup lang="ts">
import { ElTable, ElTableColumn, ElButton, ElTag } from 'element-plus'
import { View, RefreshLeft } from '@element-plus/icons-vue'
import type { ConfigHistoryItem } from '@/stores/physicsGuard'

const props = defineProps<{
  history: ConfigHistoryItem[]
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'view-detail', item: ConfigHistoryItem): void
  (e: 'rollback', item: ConfigHistoryItem): void
}>()

function formatDate(dateStr: string): string {
  return dateStr.replace('T', ' ')
}
</script>

<template>
  <ElTable
    v-loading="loading"
    :data="history"
    border
    stripe
    style="width: 100%"
    table-layout="auto"
  >
    <ElTableColumn type="index" label="#" width="50" align="center" />

    <ElTableColumn prop="version" label="版本号" width="100" align="center">
      <template #default="scope">
        <ElTag type="primary" size="small">
          {{ (scope.row as ConfigHistoryItem).version }}
        </ElTag>
      </template>
    </ElTableColumn>

    <ElTableColumn label="变更时间" min-width="160" align="center">
      <template #default="scope">
        {{ formatDate((scope.row as ConfigHistoryItem).changed_at) }}
      </template>
    </ElTableColumn>

    <ElTableColumn prop="changed_by" label="变更人" width="120" align="center" />

    <ElTableColumn prop="description" label="变更说明" min-width="300" show-overflow-tooltip />

    <ElTableColumn label="操作" width="180" fixed="right" align="center">
      <template #default="scope">
        <div class="action-buttons">
          <ElButton
            type="primary"
            link
            :icon="View"
            @click="emit('view-detail', scope.row as ConfigHistoryItem)"
          >
            查看详情
          </ElButton>
          <ElButton
            type="warning"
            link
            :icon="RefreshLeft"
            @click="emit('rollback', scope.row as ConfigHistoryItem)"
          >
            回滚
          </ElButton>
        </div>
      </template>
    </ElTableColumn>
  </ElTable>
</template>

<style scoped lang="scss">
.action-buttons {
  display: flex;
  justify-content: center;
  gap: var(--spacing-xs);
}
</style>
