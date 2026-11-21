<script setup lang="ts">
// ============================================================
// 设备列表 — 表格 + 多选 + 分页
// Props-driven for clean parent integration
// ============================================================

import { computed } from 'vue'
import { ElTable, ElTableColumn, ElTag, ElPagination } from 'element-plus'
import { DEVICE_TYPE, DEVICE_STATUS } from '@/constants'
import type { Equipment } from '@/shared/types'

// ── Props ──
const props = defineProps<{
  data: Equipment[]
  loading: boolean
  total: number
  currentPage: number
  pageSize: number
}>()

// ── Emits ──
const emit = defineEmits<{
  (e: 'row-click', row: Equipment): void
  (e: 'page-change', page: number): void
  (e: 'size-change', size: number): void
}>()

// ── 字典映射 ──
const typeLabelMap = computed(() => {
  const map: Record<string, string> = {}
  Object.values(DEVICE_TYPE).forEach((d) => {
    map[d.value as string] = d.label
  })
  return map
})

// ── 状态 tag 类型 ──
function getStatusType(status: string): 'success' | 'info' | 'danger' | 'warning' {
  const map: Record<string, 'success' | 'info' | 'danger' | 'warning'> = {
    online: 'success',
    offline: 'info',
    fault: 'danger',
    maintenance: 'warning',
  }
  return map[status] ?? 'info'
}

const pageSizeOptions = [10, 15, 20, 50]
</script>

<template>
  <div class="device-list">
    <ElTable
      v-loading="loading"
      :data="data"
      stripe
      highlight-current-row
      class="device-list__table"
      @row-click="(row: Equipment) => emit('row-click', row)"
    >
      <ElTableColumn type="selection" width="50" />
      <ElTableColumn prop="name" label="设备名称" min-width="160" show-overflow-tooltip />
      <ElTableColumn prop="code" label="设备编号" width="130" />
      <ElTableColumn label="设备类型" width="120">
        <template #default="scope">
          {{ typeLabelMap[scope.row.type] ?? scope.row.type }}
        </template>
      </ElTableColumn>
      <ElTableColumn label="运行状态" width="100">
        <template #default="scope">
          <ElTag :type="getStatusType(scope.row.status)" size="small" disable-transitions>
            {{ DEVICE_STATUS[scope.row.status]?.label ?? scope.row.status }}
          </ElTag>
        </template>
      </ElTableColumn>
      <ElTableColumn label="安装位置" width="140" show-overflow-tooltip>
        <template #default="scope">
          {{ scope.row.install_location ?? '-' }}
        </template>
      </ElTableColumn>
      <ElTableColumn label="所属分组" width="120">
        <template #default="scope">
          {{ scope.row.group ?? '-' }}
        </template>
      </ElTableColumn>
      <ElTableColumn prop="last_online" label="最后心跳时间" width="170" />
    </ElTable>

    <div class="device-list__pagination">
      <ElPagination
        :current-page="currentPage"
        :page-size="pageSize"
        :page-sizes="pageSizeOptions"
        :total="total"
        layout="total, sizes, prev, pager, next, jumper"
        background
        @current-change="(p: number) => emit('page-change', p)"
        @size-change="(s: number) => emit('size-change', s)"
      />
    </div>
  </div>
</template>

<style scoped lang="scss">
.device-list {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
  height: 100%;
  &__table {
    flex: 1;
    min-height: 0;
  }
  &__pagination {
    display: flex;
    justify-content: flex-end;
    flex-shrink: 0;
  }
}
</style>
