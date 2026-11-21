<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ElSelect,
  ElOption,
  ElTable,
  ElTableColumn,
  ElButton,
  ElTag,
  ElDialog,
  ElDescriptions,
  ElDescriptionsItem,
  ElMessage,
  ElMessageBox,
} from 'element-plus'
import type { PhysicsGuardHistoryItem } from '@/types/gateai'
import {
  fetchReservoirOptions,
  fetchPhysicsGuardHistory,
  rollbackPhysicsGuard,
} from '@/api/gateaiSettings'
import { buildSettingsPath } from '@/constants/settings'

const route = useRoute()
const router = useRouter()
const reservoirs = ref<{ id: number; name: string }[]>([])
const reservoirId = ref(1)
const list = ref<PhysicsGuardHistoryItem[]>([])
const loading = ref(false)
const detailRow = ref<PhysicsGuardHistoryItem | null>(null)
const detailVisible = ref(false)

async function load() {
  loading.value = true
  try {
    list.value = await fetchPhysicsGuardHistory(reservoirId.value)
  } finally {
    loading.value = false
  }
}

function openDetail(row: PhysicsGuardHistoryItem) {
  detailRow.value = row
  detailVisible.value = true
}

function goPhysicsGuardConfig() {
  router.push(buildSettingsPath('physics-guard', { reservoir_id: reservoirId.value }))
}

function applyReservoirQuery() {
  const id = Number(route.query.reservoir_id)
  if (id >= 1 && reservoirs.value.some((r) => r.id === id)) {
    reservoirId.value = id
  }
}

async function handleRollback(row: PhysicsGuardHistoryItem) {
  try {
    await ElMessageBox.confirm(
      `确认回滚到版本 ${row.config_version}？当前生效配置将被替换。`,
      '回滚物理防护配置',
      { type: 'warning' },
    )
    const res = await rollbackPhysicsGuard(reservoirId.value, row.id)
    ElMessage.success(res.msg || `已回滚至 v${res.new_version}`)
    await load()
  } catch {
    /* cancel */
  }
}

watch(reservoirId, load)
watch(() => route.query.reservoir_id, applyReservoirQuery)
onMounted(async () => {
  reservoirs.value = await fetchReservoirOptions()
  applyReservoirQuery()
  await load()
})
</script>

<template>
  <div v-loading="loading" class="gateai-panel">
    <div class="gateai-panel__toolbar">
      <span>水库</span>
      <ElSelect v-model="reservoirId" style="width: 200px">
        <ElOption v-for="r in reservoirs" :key="r.id" :label="r.name" :value="r.id" />
      </ElSelect>
      <ElButton type="primary" link @click="goPhysicsGuardConfig">返回配置管理</ElButton>
    </div>

    <ElTable :data="list" stripe border style="width: 100%">
      <ElTableColumn prop="config_version" label="版本号" min-width="120">
        <template #default="{ row }">
          <span>v{{ (row as PhysicsGuardHistoryItem).config_version }}</span>
          <ElTag
            v-if="(row as PhysicsGuardHistoryItem).is_active"
            type="success"
            style="margin-left: 8px"
            >当前</ElTag
          >
        </template>
      </ElTableColumn>
      <ElTableColumn prop="changed_at" label="变更时间" min-width="190" />
      <ElTableColumn prop="changed_by_name" label="变更人" min-width="110" />
      <ElTableColumn prop="description" label="变更说明" min-width="280" show-overflow-tooltip />
      <ElTableColumn label="操作" min-width="150" fixed="right" align="center">
        <template #default="{ row }">
          <ElButton link type="primary" @click="openDetail(row as PhysicsGuardHistoryItem)"
            >详情</ElButton
          >
          <ElButton
            v-if="!(row as PhysicsGuardHistoryItem).is_active"
            link
            type="warning"
            @click="handleRollback(row as PhysicsGuardHistoryItem)"
            >回滚</ElButton
          >
        </template>
      </ElTableColumn>
    </ElTable>

    <ElDialog v-model="detailVisible" title="配置变更详情" width="640px" class="physics-history-detail-dialog">
      <template v-if="detailRow">
        <ElDescriptions :column="1" border>
          <ElDescriptionsItem label="版本">v{{ detailRow.config_version }}</ElDescriptionsItem>
          <ElDescriptionsItem label="变更时间">{{ detailRow.changed_at }}</ElDescriptionsItem>
          <ElDescriptionsItem label="变更人">{{ detailRow.changed_by_name }}</ElDescriptionsItem>
          <ElDescriptionsItem label="说明">{{ detailRow.description }}</ElDescriptionsItem>
        </ElDescriptions>
        <ElTable :data="detailRow.changes" stripe border style="margin-top: 14px">
          <ElTableColumn prop="label" label="字段" width="160" />
          <ElTableColumn label="变更前" min-width="100">
            <template #default="{ row }">{{ row.before }}</template>
          </ElTableColumn>
          <ElTableColumn label="变更后" min-width="100">
            <template #default="{ row }">{{ row.after }}</template>
          </ElTableColumn>
        </ElTable>
      </template>
    </ElDialog>
  </div>
</template>

<style lang="scss">
.physics-history-detail-dialog {
  .el-dialog__title {
    font-size: 18px;
    font-weight: 600;
  }

  .el-dialog__body {
    font-size: 16px;
    line-height: 1.6;
  }

  .el-descriptions__label,
  .el-descriptions__content {
    font-size: 15px;
  }

  .el-table {
    font-size: 15px;
  }

  .el-table th.el-table__cell {
    font-size: 15px;
    font-weight: 600;
    padding: 14px 0;
  }

  .el-table td.el-table__cell {
    padding: 12px 0;
    font-size: 15px;
  }
}
</style>
