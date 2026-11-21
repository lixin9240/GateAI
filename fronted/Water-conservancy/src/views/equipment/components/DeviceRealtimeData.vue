<script setup lang="ts">
// ============================================================
// 设备实时数据 — 仅传感器 / 执行器类型显示
// ============================================================

import { computed } from 'vue'
import { ElDescriptions, ElDescriptionsItem } from 'element-plus'
import type { EquipmentDetail } from '@/shared/types'

const props = defineProps<{
  detail: EquipmentDetail
}>()

const monitoring = computed(() => props.detail.latest_monitoring ?? null)

const isSensor = computed(() => {
  return props.detail.type === 'sensor' || props.detail.type === 'level_sensor' || props.detail.type === 'flow_sensor'
})

const isActuator = computed(() => {
  return props.detail.type === 'actuator'
})

const refreshTime = computed(() => {
  return monitoring.value?.timestamp ?? props.detail.last_online ?? '-'
})
</script>

<template>
  <div class="device-realtime-data">
    <!-- 传感器类设备 -->
    <template v-if="isSensor && monitoring">
      <ElDescriptions :column="2"
border size="small">
        <ElDescriptionsItem label="当前读数">
          <span class="device-realtime-data__reading">{{ monitoring.upstream_level }}</span>
          <span class="device-realtime-data__unit"> m</span>
        </ElDescriptionsItem>
        <ElDescriptionsItem label="单位"> m（米） </ElDescriptionsItem>
        <ElDescriptionsItem label="上游水位">
          {{ monitoring.upstream_level }} m
        </ElDescriptionsItem>
        <ElDescriptionsItem label="下游水位">
          {{ monitoring.downstream_level }} m
        </ElDescriptionsItem>
        <ElDescriptionsItem label="入库流量">
          {{ monitoring.inflow_rate }} m³/s
        </ElDescriptionsItem>
        <ElDescriptionsItem label="出库流量">
          {{ monitoring.outflow_rate }} m³/s
        </ElDescriptionsItem>
        <ElDescriptionsItem label="最近刷新时间" :span="2">
          {{ refreshTime }}
        </ElDescriptionsItem>
      </ElDescriptions>
    </template>

    <!-- 执行器类设备 -->
    <template v-else-if="isActuator && monitoring">
      <ElDescriptions :column="2"
border size="small">
        <ElDescriptionsItem label="当前读数">
          <span class="device-realtime-data__reading">{{ monitoring.gate_opening }}</span>
          <span class="device-realtime-data__unit"> %</span>
        </ElDescriptionsItem>
        <ElDescriptionsItem label="单位"> %（开度百分比） </ElDescriptionsItem>
        <ElDescriptionsItem label="阀门开度"> {{ monitoring.gate_opening }}% </ElDescriptionsItem>
        <ElDescriptionsItem label="当前位置"> {{ monitoring.gate_opening }}% </ElDescriptionsItem>
        <ElDescriptionsItem label="目标值"> {{ monitoring.power_output }} kW </ElDescriptionsItem>
        <ElDescriptionsItem label="最近刷新时间">
          {{ refreshTime }}
        </ElDescriptionsItem>
      </ElDescriptions>
    </template>

    <!-- 无实时数据 -->
    <template v-else>
      <div class="device-realtime-data__empty">暂无实时监测数据</div>
    </template>
  </div>
</template>

<style scoped lang="scss">
.device-realtime-data {
  &__reading {
    font-size: var(--font-size-xl);
    font-weight: 700;
    color: var(--color-primary);
    font-family: 'DIN', 'Consolas', monospace;
  }

  &__unit {
    font-size: var(--font-size-sm);
    color: var(--color-text-secondary);
    margin-left: 2px;
  }

  &__empty {
    text-align: center;
    padding: var(--spacing-xl);
    color: var(--color-text-secondary);
    font-size: var(--font-size-sm);
  }
}
</style>
