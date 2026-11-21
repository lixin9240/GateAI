<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import {
  ElDialog,
  ElForm,
  ElFormItem,
  ElInput,
  ElRadioGroup,
  ElRadio,
  ElSelect,
  ElOption,
  ElButton,
  ElMessage,
  ElDivider,
} from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { RESERVOIR_OPTIONS } from '@/constants/settings'
import {
  RULE_SCOPE,
  CONDITION_FIELDS,
  CONDITION_OPERATORS,
  CONSTRAINT_TYPES,
} from '@/constants/gateInterlock'
import type { InterlockRule, InterlockCondition, InterlockConstraint } from '@/stores/gateInterlock'
import RuleConditionRow from './RuleConditionRow.vue'

const props = defineProps<{
  visible: boolean
  rule: InterlockRule | null
}>()

const emit = defineEmits<{
  (e: 'update:visible', val: boolean): void
  (e: 'save', rule: InterlockRule): void
}>()

const defaultRule: InterlockRule = {
  id: 0,
  code: '',
  name: '',
  description: '',
  scope: 'global',
  reservoir_id: null,
  priority: 1,
  trigger_conditions: [{ field: 'spillway_opening', operator: 'gt', threshold: 80 }],
  constraint_actions: [{ field: 'intake_opening', type: 'cap', threshold: 50 }],
  is_enabled: true,
  trigger_count_7d: 0,
  updated_at: '',
}

const form = ref<InterlockRule>({ ...defaultRule })
const isEdit = computed(() => props.rule !== null)

watch(
  () => props.visible,
  (val) => {
    if (val) {
      if (props.rule) {
        form.value = JSON.parse(JSON.stringify(props.rule))
      } else {
        form.value = { ...defaultRule, id: Date.now() }
      }
    }
  },
)

function addCondition() {
  form.value.trigger_conditions.push({
    field: 'spillway_opening',
    operator: 'gt',
    threshold: 80,
  })
}

function removeCondition(index: number) {
  if (form.value.trigger_conditions.length > 1) {
    form.value.trigger_conditions.splice(index, 1)
  } else {
    ElMessage.warning('至少保留一个触发条件')
  }
}

function updateCondition(index: number, condition: InterlockCondition | InterlockConstraint) {
  form.value.trigger_conditions[index] = condition as InterlockCondition
}

function addConstraint() {
  form.value.constraint_actions.push({
    field: 'intake_opening',
    type: 'cap',
    threshold: 50,
  })
}

function removeConstraint(index: number) {
  if (form.value.constraint_actions.length > 1) {
    form.value.constraint_actions.splice(index, 1)
  } else {
    ElMessage.warning('至少保留一个约束动作')
  }
}

function updateConstraint(index: number, constraint: InterlockCondition | InterlockConstraint) {
  form.value.constraint_actions[index] = constraint as InterlockConstraint
}

function handleSave() {
  if (!form.value.name.trim()) {
    ElMessage.warning('请输入规则名称')
    return
  }
  form.value.updated_at = new Date().toISOString()
  emit('save', { ...form.value })
}

// Import for RESERVOIR_OPTIONS
const reservoirOpts = RESERVOIR_OPTIONS
</script>

<template>
  <ElDialog
    :model-value="visible"
    :title="isEdit ? '编辑互锁规则' : '新建互锁规则'"
    width="680px"
    :close-on-click-modal="false"
    @update:model-value="(val: boolean) => emit('update:visible', val)"
  >
    <ElForm :model="form" label-width="100px">
      <ElFormItem label="规则名称" required>
        <ElInput v-model="form.name" placeholder="请输入规则名称" maxlength="50" />
      </ElFormItem>

      <ElFormItem label="说明">
        <ElInput v-model="form.description" type="textarea" :rows="2" placeholder="规则说明" />
      </ElFormItem>

      <ElFormItem label="作用范围">
        <ElRadioGroup v-model="form.scope">
          <ElRadio value="global"> 全局默认 </ElRadio>
          <ElRadio value="reservoir"> 水库专属 </ElRadio>
        </ElRadioGroup>
        <ElSelect
          v-if="form.scope === 'reservoir'"
          v-model="form.reservoir_id"
          placeholder="选择水库"
          style="width: 180px; margin-left: var(--spacing-md)"
        >
          <ElOption
            v-for="opt in reservoirOpts"
            :key="opt.value"
            :label="opt.label"
            :value="opt.value"
          />
        </ElSelect>
      </ElFormItem>

      <ElDivider />

      <!-- Trigger conditions -->
      <div class="conditions-section">
        <div class="section-header">
          <span class="section-title">触发条件</span>
          <ElButton :icon="Plus" size="small" type="primary" @click="addCondition">
            添加条件
          </ElButton>
        </div>
        <RuleConditionRow
          v-for="(cond, idx) in form.trigger_conditions"
          :key="idx"
          :condition="cond"
          :index="idx"
          mode="condition"
          @update:condition="(c) => updateCondition(idx, c)"
          @remove="removeCondition(idx)"
        />
      </div>

      <ElDivider />

      <!-- Constraint actions -->
      <div class="conditions-section">
        <div class="section-header">
          <span class="section-title">约束动作</span>
          <ElButton :icon="Plus" size="small" type="primary" @click="addConstraint">
            添加动作
          </ElButton>
        </div>
        <RuleConditionRow
          v-for="(act, idx) in form.constraint_actions"
          :key="idx"
          :condition="act"
          :index="idx"
          mode="constraint"
          @update:condition="(c) => updateConstraint(idx, c)"
          @remove="removeConstraint(idx)"
        />
      </div>
    </ElForm>

    <template #footer>
      <ElButton @click="emit('update:visible', false)"> 取消 </ElButton>
      <ElButton type="primary" @click="handleSave">
        {{ isEdit ? '保存' : '创建' }}
      </ElButton>
    </template>
  </ElDialog>
</template>

<style scoped lang="scss">
.conditions-section {
  .section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--spacing-sm);
  }

  .section-title {
    font-size: var(--font-size-sm);
    font-weight: 600;
    color: var(--color-text);
  }
}
</style>
