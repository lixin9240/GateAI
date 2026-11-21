<script setup lang="ts">
// ============================================================
// 设备维护操作 — 按钮组
// ============================================================

import { computed } from 'vue'
import { ElButton } from 'element-plus'
import {
  RefreshRight,
  Setting,
  WarningFilled,
  CircleCheckFilled,
  Document,
} from '@element-plus/icons-vue'
import type { EquipmentDetail } from '@/shared/types'

const props = defineProps<{
  detail: EquipmentDetail
}>()

const emit = defineEmits<{
  restart: []
  'edit-config': []
  'mark-fault': []
  'mark-normal': []
  'view-logs': []
}>()

// ── 标记正常按钮仅在故障或维护中状态显示 ──
const showMarkNormal = computed(() => {
  return props.detail.status === 'fault' || props.detail.status === 'maintenance'
})
</script>

<template>
  <div class="device-operations">
    <ElButton type="danger" :icon="RefreshRight" @click="emit('restart')"> 远程重启 </ElButton>

    <ElButton type="primary" :icon="Setting" @click="emit('edit-config')"> 参数配置 </ElButton>

    <ElButton type="warning" :icon="WarningFilled" @click="emit('mark-fault')"> 标记故障 </ElButton>

    <ElButton
      v-if="showMarkNormal"
      type="success"
      :icon="CircleCheckFilled"
      @click="emit('mark-normal')"
    >
      标记正常
    </ElButton>

    <ElButton type="info" :icon="Document" plain @click="emit('view-logs')"> 查看日志 </ElButton>
  </div>
</template>

<style scoped lang="scss">
.device-operations {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-sm);
}
</style>
