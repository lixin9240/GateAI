<script setup lang="ts">
import { ref, computed } from 'vue'
import { ElCollapse, ElCollapseItem, ElInputNumber } from 'element-plus'
import { PHYSICS_GUARD_SECTIONS } from '@/constants/physicsGuard'
import type { PhysicsGuardConfig } from '@/stores/physicsGuard'

const props = defineProps<{
  config: PhysicsGuardConfig | null
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'update:config', config: PhysicsGuardConfig): void
}>()

const activePanels = ref<string[]>(['upstream'])

function onFieldChange() {
  if (props.config) {
    emit('update:config', { ...props.config })
  }
}

function getFieldValue(sectionKey: string, fieldKey: string): number {
  if (!props.config) return 0
  const config = props.config as unknown as Record<string, number | number[]>

  // Handle gate_max_discharge array fields
  if (fieldKey.startsWith('gate_max_discharge_')) {
    const idx = parseInt(fieldKey.replace('gate_max_discharge_', ''), 10)
    const arr = config['gate_max_discharge'] as number[] | undefined
    return arr?.[idx] ?? 0
  }

  return (config[fieldKey] as number) ?? 0
}

function setFieldValue(sectionKey: string, fieldKey: string, val: number | undefined) {
  if (!props.config) return
  const config = { ...props.config } as unknown as Record<string, number | number[]>

  if (fieldKey.startsWith('gate_max_discharge_')) {
    const idx = parseInt(fieldKey.replace('gate_max_discharge_', ''), 10)
    const arr = [...((config['gate_max_discharge'] as number[]) ?? [300, 200, 250])]
    arr[idx] = val ?? 0
    config['gate_max_discharge'] = arr
  } else {
    config[fieldKey] = val ?? 0
  }

  emit('update:config', config as unknown as PhysicsGuardConfig)
}
</script>

<template>
  <div v-loading="loading" class="physics-guard-form">
    <ElCollapse v-model="activePanels">
      <ElCollapseItem
        v-for="section in PHYSICS_GUARD_SECTIONS"
        :key="section.key"
        :title="section.label"
        :name="section.key"
      >
        <div class="section-fields">
          <div v-for="field in section.fields" :key="field.key" class="field-row">
            <label class="field-label">{{ field.label }}</label>
            <ElInputNumber
              :model-value="getFieldValue(section.key, field.key)"
              :step="field.defaultValue < 1 ? 0.01 : field.defaultValue < 10 ? 0.1 : 1"
              :precision="field.defaultValue < 1 ? 2 : field.defaultValue < 10 ? 1 : 0"
              :min="0"
              controls-position="right"
              style="width: 200px"
              @change="(val: number | undefined) => setFieldValue(section.key, field.key, val)"
            />
            <span class="field-unit">{{ field.unit }}</span>
          </div>
        </div>
      </ElCollapseItem>
    </ElCollapse>
  </div>
</template>

<style scoped lang="scss">
.physics-guard-form {
  :deep(.el-collapse-item__header) {
    font-size: var(--font-size-base);
    font-weight: 600;
  }
}

.section-fields {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-md);
  padding: var(--spacing-sm) 0;
}

.field-row {
  display: flex;
  align-items: center;
  gap: var(--spacing-md);
}

.field-label {
  width: 200px;
  flex-shrink: 0;
  font-size: var(--font-size-sm);
  color: var(--color-text);
}

.field-unit {
  font-size: var(--font-size-sm);
  color: var(--color-text-secondary);
  width: 50px;
}
</style>
