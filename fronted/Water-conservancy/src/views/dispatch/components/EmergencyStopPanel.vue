<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { ElTable, ElTableColumn, ElTag, ElButton, ElMessage, ElMessageBox } from 'element-plus'
import type { EmergencyStopLog } from '@/types/dispatch'
import { fetchEmergencyStopLogs, postRecoverStop } from '@/api/dispatchPage'

const list = ref<EmergencyStopLog[]>([])
const loading = ref(false)

async function load() {
  loading.value = true
  try {
    const res = await fetchEmergencyStopLogs()
    list.value = res.data.list
  } finally {
    loading.value = false
  }
}

async function handleRecover(row: EmergencyStopLog) {
  if (row.recover_time) return
  try {
    await ElMessageBox.confirm(
      `确认恢复自动模式？将解除急停锁定（日志 #${row.id}）。`,
      '恢复阀门自动模式',
      { type: 'warning' },
    )
    await postRecoverStop(row.id)
    ElMessage.success('已恢复自动模式')
    await load()
  } catch { /* cancel */ }
}

function formatTime(iso: string | null) {
  if (!iso) return '—'
  return iso.replace('T', ' ').substring(0, 19)
}

onMounted(() => {
  load()
  window.addEventListener('gateai:estop', load)
})
onUnmounted(() => window.removeEventListener('gateai:estop', load))
</script>

<template>
  <div class="estop-panel">
    <div class="estop-panel__toolbar">
      <span v-if="list.length" class="estop-panel__count">共 {{ list.length }} 条</span>
      <ElButton size="small" :loading="loading" @click="load">刷新</ElButton>
    </div>
    <ElTable v-loading="loading" :data="list" stripe border class="estop-panel__table">
      <ElTableColumn prop="id" label="ID" width="64" align="center" />
      <ElTableColumn label="触发时间" min-width="168">
        <template #default="{ row }">{{ formatTime((row as EmergencyStopLog).trigger_time) }}</template>
      </ElTableColumn>
      <ElTableColumn prop="stop_reason" label="原因" min-width="160" show-overflow-tooltip />
      <ElTableColumn label="指令" width="80" align="center">
        <template #default="{ row }">
          {{ (row as EmergencyStopLog).command_id ?? '—' }}
        </template>
      </ElTableColumn>
      <ElTableColumn label="边缘确认" min-width="168">
        <template #default="{ row }">{{ formatTime((row as EmergencyStopLog).edge_ack_time) }}</template>
      </ElTableColumn>
      <ElTableColumn label="PLC 停止" min-width="168">
        <template #default="{ row }">{{ formatTime((row as EmergencyStopLog).plc_shut_time) }}</template>
      </ElTableColumn>
      <ElTableColumn label="恢复时间" min-width="168">
        <template #default="{ row }">{{ formatTime((row as EmergencyStopLog).recover_time) }}</template>
      </ElTableColumn>
      <ElTableColumn label="状态" width="100" align="center">
        <template #default="{ row }">
          <ElTag
            :type="(row as EmergencyStopLog).recover_time ? 'success' : 'danger'"
            size="small"
            effect="plain"
          >
            {{ (row as EmergencyStopLog).recover_time ? '已恢复' : '急停中' }}
          </ElTag>
        </template>
      </ElTableColumn>
      <ElTableColumn label="操作" width="120" align="center" fixed="right">
        <template #default="{ row }">
          <ElButton
            v-if="!(row as EmergencyStopLog).recover_time"
            link
            type="primary"
            @click="handleRecover(row as EmergencyStopLog)"
          >
            恢复自动
          </ElButton>
          <span v-else class="muted">—</span>
        </template>
      </ElTableColumn>
    </ElTable>
  </div>
</template>

<style scoped lang="scss">
.estop-panel {
  font-size: 15px;

  &__toolbar {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
  }

  &__count {
    margin-right: auto;
    font-size: 13px;
    color: #64748b;
  }

  &__table {
    width: 100%;
  }

  .muted {
    color: #94a3b8;
  }
}
</style>
