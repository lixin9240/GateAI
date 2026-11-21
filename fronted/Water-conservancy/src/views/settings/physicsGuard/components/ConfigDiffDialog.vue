<script setup lang="ts">
import { computed } from 'vue'
import { ElDialog, ElTable, ElTableColumn, ElButton } from 'element-plus'
import { PHYSICS_GUARD_SECTIONS } from '@/constants/physicsGuard'
import type { PhysicsGuardConfig } from '@/stores/physicsGuard'

const props = defineProps<{
  visible: boolean
  oldConfig: PhysicsGuardConfig | null
  newConfig: PhysicsGuardConfig | null
}>()

const emit = defineEmits<{
  (e: 'update:visible', val: boolean): void
  (e: 'confirm'): void
}>()

// Flatten config into displayable fields
interface DiffRow {
  section: string
  fieldLabel: string
  oldValue: number | string
  newValue: number | string
  changed: boolean
}

const diffRows = computed<DiffRow[]>(() => {
  const rows: DiffRow[] = []
  if (!props.oldConfig || !props.newConfig) return rows

  for (const section of PHYSICS_GUARD_SECTIONS) {
    for (const field of section.fields) {
      let oldVal: number | string = '--'
      let newVal: number | string = '--'

      const old = props.oldConfig as unknown as Record<string, number | number[]>
      const newConf = props.newConfig as unknown as Record<string, number | number[]>

      if (field.key.startsWith('gate_max_discharge_')) {
        const idx = parseInt(field.key.replace('gate_max_discharge_', ''), 10)
        oldVal = (old['gate_max_discharge'] as number[])?.[idx] ?? '--'
        newVal = (newConf['gate_max_discharge'] as number[])?.[idx] ?? '--'
      } else {
        oldVal = (old[field.key] as number) ?? '--'
        newVal = (newConf[field.key] as number) ?? '--'
      }

      rows.push({
        section: section.label,
        fieldLabel: field.label,
        oldValue: oldVal,
        newValue: newVal,
        changed: oldVal !== newVal,
      })
    }
  }
  return rows
})

const changedCount = computed(() => diffRows.value.filter((r) => r.changed).length)
</script>

<template>
  <ElDialog
    :model-value="visible"
    :title="`配置变更确认 (${changedCount} 项变更)`"
    width="700px"
    @update:model-value="(val: boolean) => emit('update:visible', val)"
  >
    <ElTable :data="diffRows" max-height="400" border stripe size="small">
      <ElTableColumn prop="section" label="配置分组" width="140" />
      <ElTableColumn prop="fieldLabel" label="字段" min-width="180" />
      <ElTableColumn label="旧值" width="120" align="center">
        <template #default="scope">
          {{ (scope.row as DiffRow).oldValue }}
        </template>
      </ElTableColumn>
      <ElTableColumn label="新值" width="120" align="center">
        <template #default="scope">
          <span :class="{ 'changed-value': (scope.row as DiffRow).changed }">
            {{ (scope.row as DiffRow).newValue }}
          </span>
        </template>
      </ElTableColumn>
    </ElTable>

    <template #footer>
      <ElButton @click="emit('update:visible', false)"> 取消 </ElButton>
      <ElButton type="primary" @click="emit('confirm')"> 确认保存 (生成新版本) </ElButton>
    </template>
  </ElDialog>
</template>

<style scoped lang="scss">
.changed-value {
  color: #67c23a;
  font-weight: 700;
  background: rgba(103, 194, 58, 0.1);
  padding: 2px 6px;
  border-radius: 2px;
}
</style>
