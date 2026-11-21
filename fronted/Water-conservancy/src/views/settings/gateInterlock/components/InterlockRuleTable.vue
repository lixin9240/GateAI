<script setup lang="ts">
import { ElTable, ElTableColumn, ElTag, ElSwitch, ElButton } from 'element-plus'
import { Top, Bottom, Edit } from '@element-plus/icons-vue'
import { RULE_SCOPE } from '@/constants/gateInterlock'
import type { InterlockRule } from '@/stores/gateInterlock'

const props = defineProps<{
  rules: InterlockRule[]
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'edit', rule: InterlockRule): void
  (e: 'toggle', rule: InterlockRule): void
  (e: 'reorder', data: { ruleId: number; direction: 'up' | 'down' }): void
}>()

function getConditionText(rule: InterlockRule): string {
  return rule.trigger_conditions
    .map((c) => `${getFieldLabel(c.field)} ${getOperatorLabel(c.operator)} ${c.threshold}`)
    .join(' AND ')
}

function getConstraintText(rule: InterlockRule): string {
  return rule.constraint_actions
    .map((c) => `${getFieldLabel(c.field)} ${getConstraintTypeLabel(c.type)} ${c.threshold}`)
    .join('; ')
}

function getFieldLabel(field: string): string {
  const map: Record<string, string> = {
    spillway_opening: '溢洪道',
    tunnel_opening: '泄洪洞',
    intake_opening: '发电阀',
    total_opening: '总开度',
    upstream_level: '上游水位',
    downstream_level: '下游水位',
    inflow_rate: '入库流量',
  }
  return map[field] ?? field
}

function getOperatorLabel(op: string): string {
  const map: Record<string, string> = {
    gt: '>',
    lt: '<',
    gte: '≥',
    lte: '≤',
    eq: '=',
    rate_gt: '变化率>',
  }
  return map[op] ?? op
}

function getConstraintTypeLabel(type: string): string {
  const map: Record<string, string> = {
    cap: '≤',
    floor: '≥',
    block_same_direction: '禁止同向',
    block_opposite_direction: '禁止反向',
    align: '强制对齐',
  }
  return map[type] ?? type
}

function moveUp(index: number) {
  if (index <= 0) return
  const rule = props.rules[index]
  if (rule) {
    emit('reorder', { ruleId: rule.id, direction: 'up' })
  }
}

function moveDown(index: number) {
  if (index >= props.rules.length - 1) return
  const rule = props.rules[index]
  if (rule) {
    emit('reorder', { ruleId: rule.id, direction: 'down' })
  }
}
</script>

<template>
  <ElTable v-loading="loading" :data="rules" border stripe style="width: 100%">
    <ElTableColumn label="优先级" width="80" align="center">
      <template #default="scope">
        <div class="priority-cell">
          <span class="priority-num">{{ (scope.row as InterlockRule).priority }}</span>
          <div class="priority-arrows">
            <ElButton
              :icon="Top"
              :size="'small'"
              circle
              :disabled="scope.$index === 0"
              @click="moveUp(scope.$index)"
            />
            <ElButton
              :icon="Bottom"
              :size="'small'"
              circle
              :disabled="scope.$index === rules.length - 1"
              @click="moveDown(scope.$index)"
            />
          </div>
        </div>
      </template>
    </ElTableColumn>

    <ElTableColumn prop="name"
label="规则名称" min-width="120" />

    <ElTableColumn label="作用范围" width="110" align="center">
      <template #default="scope">
        <ElTag
          :type="(scope.row as InterlockRule).scope === 'global' ? 'info' : 'primary'"
          size="small"
        >
          {{ RULE_SCOPE[(scope.row as InterlockRule).scope] ?? (scope.row as InterlockRule).scope }}
        </ElTag>
      </template>
    </ElTableColumn>

    <ElTableColumn prop="description"
label="说明" min-width="200" show-overflow-tooltip />

    <ElTableColumn label="触发条件" min-width="200">
      <template #default="scope">
        <span class="condition-text">{{ getConditionText(scope.row as InterlockRule) }}</span>
      </template>
    </ElTableColumn>

    <ElTableColumn label="约束动作" min-width="200">
      <template #default="scope">
        <span class="condition-text">{{ getConstraintText(scope.row as InterlockRule) }}</span>
      </template>
    </ElTableColumn>

    <ElTableColumn label="状态" width="80" align="center">
      <template #default="scope">
        <ElSwitch
          :model-value="(scope.row as InterlockRule).is_enabled"
          @change="emit('toggle', scope.row as InterlockRule)"
        />
      </template>
    </ElTableColumn>

    <ElTableColumn label="近7天触发" width="110" align="center">
      <template #default="scope">
        <ElTag
          :type="(scope.row as InterlockRule).trigger_count_7d > 0 ? 'warning' : 'info'"
          size="small"
        >
          {{ (scope.row as InterlockRule).trigger_count_7d }} 次
        </ElTag>
      </template>
    </ElTableColumn>

    <ElTableColumn label="操作" width="80" fixed="right" align="center">
      <template #default="scope">
        <ElButton
          type="primary"
          link
          :icon="Edit"
          @click="emit('edit', scope.row as InterlockRule)"
        >
          编辑
        </ElButton>
      </template>
    </ElTableColumn>
  </ElTable>
</template>

<style scoped lang="scss">
.priority-cell {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

.priority-num {
  font-weight: 700;
  font-size: var(--font-size-lg);
  color: var(--color-primary);
  min-width: 24px;
  text-align: center;
}

.priority-arrows {
  display: flex;
  flex-direction: column;
  gap: 2px;

  :deep(.el-button) {
    width: 20px;
    height: 20px;
    padding: 0;
  }

  :deep(.el-icon) {
    font-size: 10px;
  }
}

.condition-text {
  font-size: var(--font-size-xs);
  color: var(--color-text-secondary);
  line-height: 1.4;
}
</style>
