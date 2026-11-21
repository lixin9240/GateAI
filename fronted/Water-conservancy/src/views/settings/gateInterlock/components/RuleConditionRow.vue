<script setup lang="ts">
import { computed } from 'vue'
import { ElSelect, ElOption, ElInputNumber, ElButton } from 'element-plus'
import { Delete } from '@element-plus/icons-vue'
import { CONDITION_FIELDS, CONDITION_OPERATORS, CONSTRAINT_TYPES } from '@/constants/gateInterlock'
import type { InterlockCondition, InterlockConstraint } from '@/stores/gateInterlock'

const props = defineProps<{
  condition: InterlockCondition | InterlockConstraint
  index: number
  mode?: 'condition' | 'constraint'
}>()

const emit = defineEmits<{
  (e: 'update:condition', condition: InterlockCondition | InterlockConstraint): void
  (e: 'remove'): void
}>()

const fieldOptions = CONDITION_FIELDS
const operatorOptions = CONDITION_OPERATORS
const constraintTypeOptions = CONSTRAINT_TYPES

function updateField(val: string) {
  emit('update:condition', { ...props.condition, field: val })
}

function updateOperator(val: string) {
  emit('update:condition', { ...props.condition, operator: val, type: val } as InterlockCondition &
    InterlockConstraint)
}

// 标准 computed get/set，解决 :model-value 单向绑定无法实时输入的问题
const threshold = computed({
  get: () => props.condition.threshold,
  set: (val: number | undefined) => {
    emit('update:condition', { ...props.condition, threshold: val ?? 0 })
  },
})

// For constraint mode, operator is actually the type
function updateConstraintType(val: string) {
  emit('update:condition', { ...props.condition, type: val } as InterlockConstraint)
}
</script>

<template>
  <div class="rule-condition-row">
    <span class="row-index">{{ index + 1 }}</span>

    <!-- Field select -->
    <ElSelect
      :model-value="condition.field"
      placeholder="选择字段"
      style="width: 150px"
      @change="updateField"
    >
      <ElOption
        v-for="opt in fieldOptions"
        :key="opt.value"
        :label="opt.label"
        :value="opt.value"
      />
    </ElSelect>

    <!-- Operator / Constraint type -->
    <ElSelect
      v-if="mode === 'constraint'"
      :model-value="(condition as InterlockConstraint).type"
      placeholder="约束方式"
      style="width: 150px"
      @change="updateConstraintType"
    >
      <ElOption
        v-for="opt in constraintTypeOptions"
        :key="opt.value"
        :label="opt.label"
        :value="opt.value"
      />
    </ElSelect>
    <ElSelect
      v-else
      :model-value="condition.operator"
      placeholder="运算符"
      style="width: 150px"
      @change="updateOperator"
    >
      <ElOption
        v-for="opt in operatorOptions"
        :key="opt.value"
        :label="opt.label"
        :value="opt.value"
      />
    </ElSelect>

    <!-- Threshold -->
    <ElInputNumber
      v-model="threshold"
      :step="1"
      :precision="1"
      placeholder="阈值"
      style="width: 130px"
    />

    <!-- Remove button -->
    <ElButton :icon="Delete" circle size="small" type="danger" @click="emit('remove')" />
  </div>
</template>

<style scoped lang="scss">
.rule-condition-row {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
  padding: var(--spacing-xs) 0;
}

.row-index {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: var(--color-primary);
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  flex-shrink: 0;
}
</style>
