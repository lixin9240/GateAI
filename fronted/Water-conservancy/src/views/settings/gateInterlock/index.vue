<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElSelect, ElOption, ElButton, ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { RESERVOIR_OPTIONS } from '@/constants/settings'
import { getInterlockRules, toggleInterlockRule } from '@/api/settings'
import {
  createInterlockRule,
  updateInterlockRule as updateInterlockRuleWrapped,
} from '@/api/gateaiSettings'
import { normalizeInterlockRules, toInterlockRule } from '@/api/interlockAdapter'
import type { InterlockRule } from '@/stores/gateInterlock'
import InterlockRuleTable from './components/InterlockRuleTable.vue'
import RuleEditDialog from './components/RuleEditDialog.vue'

const reservoirId = ref<number>(1)
const rules = ref<InterlockRule[]>([])
const loading = ref(false)

const editDialogVisible = ref(false)
const editingRule = ref<InterlockRule | null>(null)

// ── Mock ──
function generateMockRules(): InterlockRule[] {
  return [
    {
      id: 1,
      code: 'spillway_intake_mutex',
      name: '泄洪-发电互斥',
      description: '溢洪道开度 > 80% 时，发电引水阀 ≤ 50%',
      scope: 'global',
      reservoir_id: null,
      priority: 1,
      trigger_conditions: [{ field: 'spillway_opening', operator: 'gt', threshold: 80 }],
      constraint_actions: [{ field: 'intake_opening', type: 'cap', threshold: 50 }],
      is_enabled: true,
      trigger_count_7d: 12,
      updated_at: '2026-07-03T10:00:00',
    },
    {
      id: 2,
      code: 'downstream_impact_protect',
      name: '下游冲击保护',
      description: '任两阀门同时增开 > 30%，第三个阀门禁止同向动作',
      scope: 'global',
      reservoir_id: null,
      priority: 2,
      trigger_conditions: [{ field: 'total_opening', operator: 'rate_gt', threshold: 30 }],
      constraint_actions: [{ field: 'total_opening', type: 'block_same_direction', threshold: 0 }],
      is_enabled: true,
      trigger_count_7d: 3,
      updated_at: '2026-07-02T08:00:00',
    },
    {
      id: 3,
      code: 'symmetry_constraint',
      name: '对称性约束',
      description: '溢洪道与泄洪洞开度差 > 40%，强制调整至差值 ≤ 40%',
      scope: 'global',
      reservoir_id: null,
      priority: 3,
      trigger_conditions: [{ field: 'spillway_opening', operator: 'gt', threshold: 40 }],
      constraint_actions: [{ field: 'spillway_opening', type: 'align', threshold: 40 }],
      is_enabled: true,
      trigger_count_7d: 0,
      updated_at: '2026-06-28T14:00:00',
    },
    {
      id: 4,
      code: 'min_discharge_guarantee',
      name: '最小下泄保障',
      description: '三阀门总开度 < 5% 时禁止再关',
      scope: 'reservoir',
      reservoir_id: 1,
      reservoir_name: '示范水库',
      priority: 4,
      trigger_conditions: [{ field: 'total_opening', operator: 'lt', threshold: 5 }],
      constraint_actions: [
        { field: 'total_opening', type: 'block_opposite_direction', threshold: 0 },
      ],
      is_enabled: false,
      trigger_count_7d: 0,
      updated_at: '2026-06-20T09:00:00',
    },
  ]
}

// ── Fetch ──
async function fetchRules() {
  loading.value = true
  try {
    const res = await getInterlockRules({ reservoir_id: reservoirId.value })
    if (import.meta.env.DEV) {
      console.log(
        `[GateInterlock] GET reservoir_id=${reservoirId.value} 返回:`,
        `code=${res.data?.code}`,
        `条数=${Array.isArray(res.data?.data) ? res.data.data.length : 'N/A'}`,
        res.data?.data,
      )
    }
    if (res.data?.code === 0 && res.data.data) {
      rules.value = normalizeInterlockRules(res.data.data).map(toInterlockRule)
      return
    }
  } catch {
    /* fallback */
  } finally {
    loading.value = false
  }
  rules.value = generateMockRules()
}

function onReservoirChange() {
  fetchRules()
}

function handleEdit(rule: InterlockRule) {
  editingRule.value = rule
  editDialogVisible.value = true
}

function handleNew() {
  editingRule.value = null
  editDialogVisible.value = true
}

async function handleToggle(rule: InterlockRule) {
  try {
    await ElMessageBox.confirm(
      `确定${rule.is_enabled ? '禁用' : '启用'}规则「${rule.name}」？`,
      '确认操作',
      { type: 'warning' },
    )
    await toggleInterlockRule(rule.id, !rule.is_enabled)
    rule.is_enabled = !rule.is_enabled
    ElMessage.success(`规则「${rule.name}」已${rule.is_enabled ? '启用' : '禁用'}`)
  } catch {
    /* 取消或失败 */
  }
}

function handleReorder(data: { ruleId: number; direction: 'up' | 'down' }) {
  const idx = rules.value.findIndex((r) => r.id === data.ruleId)
  if (idx < 0) return
  if (data.direction === 'up' && idx > 0) {
    const temp = rules.value[idx - 1]
    rules.value[idx - 1] = rules.value[idx]
    rules.value[idx] = temp
    // Update priorities
    rules.value.forEach((r, i) => {
      r.priority = i + 1
    })
  } else if (data.direction === 'down' && idx < rules.value.length - 1) {
    const temp = rules.value[idx + 1]
    rules.value[idx + 1] = rules.value[idx]
    rules.value[idx] = temp
    rules.value.forEach((r, i) => {
      r.priority = i + 1
    })
  }
}

/**
 * 将阈值转为 0~1 小数。后端 trigger_conditions / constraint_action 的内部值
 * 统一使用小数（如 0.8 表示 80%），前端输入是百分比（如 80）。
 * 已加载的后端数据本身就是小数，不走此转换。
 */
function toDecimalThreshold(value: number): number {
  // 如果值 > 1，说明是百分比格式（80 = 80%），需除以 100
  return value > 1 ? +(value / 100).toFixed(3) : value
}

/** 将 InterlockRule（UI 格式，数组）转为后端 API 请求体（对象格式） */
function toApiPayload(rule: InterlockRule): Record<string, unknown> {
  // 触发条件：数组 → { field_operator: threshold }
  const triggerObj: Record<string, number> = {}
  for (const cond of rule.trigger_conditions) {
    const key = `${cond.field}_${cond.operator}`
    triggerObj[key] = toDecimalThreshold(cond.threshold)
  }

  // 约束动作：数组 → { action: type, field: threshold }
  const actionObj: Record<string, unknown> = {
    action: rule.constraint_actions[0]?.type ?? 'clamp',
  }
  for (const act of rule.constraint_actions) {
    actionObj[act.field] = toDecimalThreshold(act.threshold)
  }

  // 自动生成 rule_code（后端必填，仅新建时需；编辑时沿用已有 code）
  const code =
    rule.code ||
    rule.name
      .toLowerCase()
      .replace(/\s+/g, '_')
      .replace(/[^a-z0-9_]/g, '')

  return {
    rule_code: code,
    rule_name: rule.name,
    description: rule.description,
    enabled: rule.is_enabled,
    priority: rule.priority,
    reservoir_id: rule.scope === 'reservoir' ? rule.reservoir_id : null,
    trigger_conditions: triggerObj,
    constraint_action: actionObj,
  }
}

async function handleSave(rule: InterlockRule) {
  editDialogVisible.value = false
  const existingIdx = rules.value.findIndex((r) => r.id === rule.id)
  const isNew = existingIdx < 0

  try {
    const payload = toApiPayload(rule)

    if (isNew) {
      // 新建 → POST 创建，成功后重新拉取列表确保与后端一致
      const created = await createInterlockRule(payload as any)
      if (import.meta.env.DEV) {
        console.log('[GateInterlock] POST 创建成功，后端返回:', created)
      }
      ElMessage.success('规则已创建')
    } else {
      // 编辑 → PUT 更新
      await updateInterlockRuleWrapped(rule.id, payload as any)
      ElMessage.success('规则已更新')
    }
    // 保存成功后从后端重新拉取，保证刷新/退出登录后数据不丢
    await fetchRules()
  } catch (e: any) {
    // 降级：本地 Mock
    if (isNew) {
      rule.id = Math.max(...rules.value.map((r) => r.id), 0) + 1
      rule.priority = rules.value.length + 1
      rule.updated_at = new Date().toISOString()
      rules.value.push(rule)
      ElMessage.warning('规则已创建（离线模式，刷新后可能丢失）')
    } else {
      rules.value[existingIdx] = rule
      ElMessage.warning('规则已更新（离线模式）')
    }
    console.warn('[GateInterlock] API 请求失败，使用本地降级:', e)
  }
}

onMounted(() => {
  fetchRules()
})
</script>

<template>
  <div class="gate-interlock-page">
    <div class="page-header">
      <h2 class="page-title">
阀门互锁规则
</h2>
      <div class="header-actions">
        <ElSelect
          v-model="reservoirId"
          placeholder="选择水库"
          style="width: 180px"
          @change="onReservoirChange"
        >
          <ElOption
            v-for="opt in RESERVOIR_OPTIONS"
            :key="opt.value"
            :label="opt.label"
            :value="opt.value"
          />
        </ElSelect>
        <ElButton type="primary" :icon="Plus" @click="handleNew"> 新建规则 </ElButton>
      </div>
    </div>

    <InterlockRuleTable
      :rules="rules"
      :loading="loading"
      @edit="handleEdit"
      @toggle="handleToggle"
      @reorder="handleReorder"
    />

    <RuleEditDialog
      :visible="editDialogVisible"
      :rule="editingRule"
      @update:visible="(v: boolean) => (editDialogVisible = v)"
      @save="handleSave"
    />
  </div>
</template>

<style scoped lang="scss">
@use '@/assets/styles/text-mixins.scss' as *;
.gate-interlock-page {
  padding: var(--spacing-md);
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--spacing-lg);
}

.page-title {
  font-size: var(--font-size-xxl);
  font-weight: 700;
  color: var(--color-text);
  margin: 0;
}

.header-actions {
  display: flex;
  align-items: center;
  gap: var(--spacing-md);
}
</style>
