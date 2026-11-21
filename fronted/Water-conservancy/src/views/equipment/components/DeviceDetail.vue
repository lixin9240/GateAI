<script setup lang="ts">
// ============================================================
// 设备详情面板 — 基本参数 + 实时数据 + 配置同步 + 维护操作
// ============================================================

import { computed } from 'vue'
import {
  ElCard,
  ElDescriptions,
  ElDescriptionsItem,
  ElTag,
  ElButton,
  ElSkeleton,
} from 'element-plus'
import { Close } from '@element-plus/icons-vue'
// DEVICE_TYPE/STATUS 已由子组件自行引用
import { useEquipmentStore } from '@/stores/equipment'
import DeviceBasicInfo from './DeviceBasicInfo.vue'
import DeviceRealtimeData from './DeviceRealtimeData.vue'
import DeviceOperations from './DeviceOperations.vue'

const store = useEquipmentStore()

const detail = computed(() => store.currentDevice)
const loading = computed(() => store.detailLoading)

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'restart', device: { name: string; type: string }): void
}>()

function handleClose() {
  emit('close')
}
function handleRestart() {
  if (detail.value) emit('restart', { name: detail.value.name, type: detail.value.type })
}

function handleEditConfig() {
  // TODO: 对接设备参数配置
  console.warn('[DeviceDetail] 参数配置功能待实现')
}
function handleMarkFault() {
  // TODO: 对接标记故障 API
  console.warn('[DeviceDetail] 标记故障功能待实现')
}
function handleMarkNormal() {
  // TODO: 对接标记正常 API
  console.warn('[DeviceDetail] 标记正常功能待实现')
}
function handleViewLogs() {
  // TODO: 对接查看日志功能
  console.warn('[DeviceDetail] 查看日志功能待实现')
}

const isSensorOrActuator = computed(() => {
  if (!detail.value) return false
  return detail.value.type === 'sensor' || detail.value.type === 'actuator'
})

const isGateway = computed(() => {
  if (!detail.value) return false
  return detail.value.type === 'gateway'
})

const syncInfo = computed(() => {
  if (!detail.value) return null
  return {
    last_sync_time: detail.value.last_online ?? '-',
    config_version: detail.value.ip ? `v2.1.${detail.value.id}` : '-',
    sync_status:
      detail.value.status === 'online'
        ? 'synced'
        : detail.value.status === 'offline'
          ? 'failed'
          : 'pending',
    config_hash: detail.value.ip ? `a1b2c3d${detail.value.id}` : '-',
  }
})

function getSyncStatusType(status: string): 'success' | 'warning' | 'danger' {
  const map: Record<string, 'success' | 'warning' | 'danger'> = {
    synced: 'success',
    pending: 'warning',
    failed: 'danger',
  }
  return map[status] ?? 'warning'
}
function getSyncStatusLabel(status: string): string {
  const map: Record<string, string> = { synced: '已同步', pending: '待同步', failed: '同步失败' }
  return map[status] ?? status
}
</script>

<template>
  <div class="device-detail">
    <ElSkeleton :loading="loading" animated :throttle="0">
      <template v-if="detail">
        <!-- 标题栏 -->
        <div class="device-detail__header">
          <span class="device-detail__title">{{ detail.name }}</span>
          <ElButton text size="small" :icon="Close" @click="handleClose" />
        </div>

        <!-- 基本参数区 -->
        <ElCard shadow="never" class="device-detail__section">
          <template #header><span class="device-detail__section-title">基本参数</span></template>
          <DeviceBasicInfo :detail="detail" />
        </ElCard>

        <!-- 实时数据区 -->
        <ElCard v-if="isSensorOrActuator" shadow="never" class="device-detail__section">
          <template #header><span class="device-detail__section-title">实时数据</span></template>
          <DeviceRealtimeData :detail="detail" />
        </ElCard>

        <!-- 配置同步状态区 -->
        <ElCard v-if="isGateway" shadow="never" class="device-detail__section">
          <template #header
            ><span class="device-detail__section-title">配置同步状态</span></template
          >
          <ElDescriptions :column="2" border size="small">
            <ElDescriptionsItem label="最后同步时间">{{
              syncInfo?.last_sync_time ?? '-'
            }}</ElDescriptionsItem>
            <ElDescriptionsItem label="当前配置版本">{{
              syncInfo?.config_version ?? '-'
            }}</ElDescriptionsItem>
            <ElDescriptionsItem label="同步状态">
              <ElTag
                v-if="syncInfo"
                :type="getSyncStatusType(syncInfo.sync_status)"
                size="small"
                disable-transitions
              >
                {{ getSyncStatusLabel(syncInfo.sync_status) }}
              </ElTag>
            </ElDescriptionsItem>
            <ElDescriptionsItem label="配置哈希">
              <code class="device-detail__hash">{{ syncInfo?.config_hash ?? '-' }}</code>
            </ElDescriptionsItem>
          </ElDescriptions>
        </ElCard>

        <!-- 维护操作区 -->
        <ElCard shadow="never" class="device-detail__section">
          <template #header><span class="device-detail__section-title">维护操作</span></template>
          <DeviceOperations
            :detail="detail"
            @restart="handleRestart"
            @edit-config="handleEditConfig"
            @mark-fault="handleMarkFault"
            @mark-normal="handleMarkNormal"
            @view-logs="handleViewLogs"
          />
        </ElCard>
      </template>
    </ElSkeleton>
  </div>
</template>

<style scoped lang="scss">
.device-detail {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
  overflow-y: auto;
  flex: 1;
  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--spacing-sm) 0;
  }
  &__title {
    font-weight: 600;
    font-size: var(--font-size-lg);
  }
  &__section {
    :deep(.el-card__header) {
      padding: var(--spacing-sm) var(--spacing-md);
      background: var(--color-bg-dark);
    }
    :deep(.el-card__body) {
      padding: var(--spacing-md);
    }
  }
  &__section-title {
    font-weight: 600;
    font-size: var(--font-size-base);
    color: var(--color-text);
  }
  &__hash {
    font-family: 'Consolas', 'Courier New', monospace;
    font-size: var(--font-size-xs);
    color: var(--color-text-secondary);
    background: var(--color-bg-dark);
    padding: 2px 6px;
    border-radius: var(--border-radius-sm);
  }
}
</style>
