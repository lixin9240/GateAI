<script setup lang="ts">
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ElSelect,
  ElOption,
  ElCollapse,
  ElCollapseItem,
  ElForm,
  ElFormItem,
  ElInputNumber,
  ElInput,
  ElButton,
  ElMessage,
  ElMessageBox,
  ElDialog,
  ElTable,
  ElTableColumn,
} from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import type { PhysicsGuardConfig } from '@/types/gateai'
import {
  fetchReservoirOptions,
  fetchPhysicsGuardConfig,
  savePhysicsGuardConfig,
  fetchInterlockStats,
  clonePhysicsGuardConfig,
  fetchPhysicsGuardHistoryVersions,
} from '@/api/gateaiSettings'
import { buildSettingsPath, type SettingsTabName } from '@/constants/settings'

const route = useRoute()
const router = useRouter()

function goSettingsTab(tab: SettingsTabName) {
  router.push(buildSettingsPath(tab, { reservoir_id: reservoirId.value }))
}

function applyReservoirQuery() {
  const id = Number(route.query.reservoir_id)
  if (id >= 1 && reservoirs.value.some((r) => r.id === id)) {
    reservoirId.value = id
  }
}
const reservoirs = ref<{ id: number; name: string }[]>([])
const reservoirId = ref(1)
const cloneTargetId = ref(2)
const form = ref<PhysicsGuardConfig | null>(null)
const original = ref<PhysicsGuardConfig | null>(null)
const interlockStats = ref({ enabled_count: 0, trigger_24h: 0, trigger_7d: 0 })
const loading = ref(false)
const saving = ref(false)
const editing = ref(false)
const saveDialogVisible = ref(false)
const saveDescription = ref('')
const saveFormRef = ref<FormInstance>()
const saveFormRules: FormRules = {
  saveDescription: [
    {
      validator: (_rule, value, callback) => {
        if (!value || !String(value).trim()) {
          callback(new Error('请填写变更说明'))
        } else {
          callback()
        }
      },
      trigger: ['blur', 'change'],
    },
  ],
}
const cloneDialogVisible = ref(false)
const cloneSourceId = ref(1)
const cloneVersion = ref('')
const historyVersions = ref<{ config_version: string; description: string }[]>([])

const isDirty = computed(() => JSON.stringify(form.value) !== JSON.stringify(original.value))

const levelMarks = computed(() => {
  if (!form.value) return []
  const f = form.value
  return [
    { label: '死水位', value: f.upstream_min, color: '#94a3b8' },
    { label: '预警', value: f.upstream_warning, color: '#f59e0b' },
    { label: '危险', value: f.upstream_danger, color: '#ef4444' },
    { label: '紧急', value: f.upstream_emergency, color: '#dc2626' },
  ]
})

const diffRows = computed(() => {
  if (!form.value || !original.value) return []
  const keys: (keyof PhysicsGuardConfig)[] = [
    'upstream_danger',
    'upstream_emergency',
    'upstream_warning',
    'upstream_min',
    'ideal_min',
    'ideal_max',
    'downstream_danger',
    'downstream_max',
    'downstream_min',
    'eco_flow_min',
    'max_level_change_per_hour',
    'reservoir_area',
    'shadow_lookahead_steps',
    'shadow_danger_offset',
    'deadband_percent',
    'max_rate_per_hour',
    'fusion_l3_confidence',
    'fusion_l3_risk',
    'fusion_l2_confidence',
    'fusion_l2_risk',
  ]
  return keys
    .filter((k) => JSON.stringify(form.value![k]) !== JSON.stringify(original.value![k]))
    .map((k) => ({ field: k, before: original.value![k], after: form.value![k] }))
})

async function load() {
  loading.value = true
  try {
    form.value = await fetchPhysicsGuardConfig(reservoirId.value)
    original.value = JSON.parse(JSON.stringify(form.value))
    interlockStats.value = await fetchInterlockStats(reservoirId.value)
    editing.value = false
  } finally {
    loading.value = false
  }
}

function startEdit() {
  editing.value = true
}

async function cancelEdit() {
  if (isDirty.value) {
    try {
      await ElMessageBox.confirm('放弃未保存的修改？', '取消编辑', { type: 'warning' })
    } catch {
      return
    }
  }
  handleReset()
  editing.value = false
}

function openSaveDialog() {
  if (!form.value || !isDirty.value) return
  saveDescription.value = ''
  saveDialogVisible.value = true
  nextTick(() => saveFormRef.value?.clearValidate())
}

async function confirmSave() {
  if (!form.value || !saveFormRef.value) return
  try {
    await saveFormRef.value.validate()
  } catch {
    return
  }
  const description = saveDescription.value.trim()
  saving.value = true
  try {
    await savePhysicsGuardConfig(form.value, { description })
    ElMessage.success('配置已保存，边缘端下一同步周期生效')
    saveDialogVisible.value = false
    editing.value = false
    await load()
  } finally {
    saving.value = false
  }
}

function bumpVersion(v: string) {
  const p = v.split('.').map(Number)
  p[2] = (p[2] ?? 0) + 1
  return p.join('.')
}

function handleReset() {
  if (original.value) form.value = JSON.parse(JSON.stringify(original.value))
}

async function openCloneDialog() {
  cloneSourceId.value = reservoirId.value
  historyVersions.value = await fetchPhysicsGuardHistoryVersions(cloneSourceId.value)
  cloneVersion.value = historyVersions.value[0]?.config_version ?? ''
  cloneDialogVisible.value = true
}

async function confirmClone() {
  try {
    const res = await clonePhysicsGuardConfig(
      cloneSourceId.value,
      cloneTargetId.value,
      cloneVersion.value || undefined,
    )
    ElMessage.success(res.msg || '配置已克隆')
    cloneDialogVisible.value = false
    if (cloneTargetId.value === reservoirId.value) {
      await load()
    }
  } catch {
    ElMessage.error('克隆失败')
  }
}

watch(reservoirId, async (_newVal, oldVal) => {
  if (editing.value && isDirty.value) {
    try {
      await ElMessageBox.confirm('切换水库将放弃未保存的修改，是否继续？', '提示', {
        type: 'warning',
      })
    } catch {
      reservoirId.value = oldVal
      return
    }
  }
  await load()
})
watch(cloneSourceId, async (id) => {
  historyVersions.value = await fetchPhysicsGuardHistoryVersions(id)
  cloneVersion.value = historyVersions.value[0]?.config_version ?? ''
})
watch(() => route.query.reservoir_id, applyReservoirQuery)
onMounted(async () => {
  reservoirs.value = await fetchReservoirOptions()
  applyReservoirQuery()
  await load()
})
</script>

<template>
  <div v-loading="loading" class="gateai-panel physics-panel">
    <div class="gateai-panel__toolbar">
      <span>水库</span>
      <ElSelect v-model="reservoirId" style="width: 200px">
        <ElOption v-for="r in reservoirs" :key="r.id" :label="r.name" :value="r.id" />
      </ElSelect>
      <span>克隆到</span>
      <ElSelect v-model="cloneTargetId" style="width: 180px">
        <ElOption
          v-for="r in reservoirs.filter((x) => x.id !== reservoirId)"
          :key="r.id"
          :label="r.name"
          :value="r.id"
        />
      </ElSelect>
      <ElButton @click="openCloneDialog">克隆自其他水库</ElButton>
      <ElButton type="primary" link @click="goSettingsTab('physics-guard-history')"
        >变更历史</ElButton
      >
      <template v-if="editing">
        <div v-if="isDirty" class="physics-dirty">
          配置已修改，保存后将生成新版本 v{{ bumpVersion(original?.config_version ?? '1.0.0') }}
        </div>
        <ElButton @click="cancelEdit">取消</ElButton>
        <ElButton @click="handleReset">重置</ElButton>
        <ElButton type="primary" :disabled="!isDirty" @click="openSaveDialog">保存</ElButton>
      </template>
      <ElButton v-else type="primary" @click="startEdit">编辑</ElButton>
    </div>

    <div v-if="form" class="physics-layout" :class="{ 'physics-layout--readonly': !editing }">
      <ElCollapse :model-value="['upstream', 'downstream', 'shadow', 'fusion', 'gate']">
        <ElCollapseItem title="上游水位阈值" name="upstream">
          <div class="form-grid">
            <ElFormItem label="危险水位(m)"
              ><ElInputNumber :disabled="!editing" v-model="form.upstream_danger" :step="0.1" controls-position="right"
            /></ElFormItem>
            <ElFormItem label="紧急水位(m)"
              ><ElInputNumber :disabled="!editing"
                v-model="form.upstream_emergency"
                :step="0.1"
                controls-position="right"
            /></ElFormItem>
            <ElFormItem label="预警水位(m)"
              ><ElInputNumber :disabled="!editing" v-model="form.upstream_warning" :step="0.1" controls-position="right"
            /></ElFormItem>
            <ElFormItem label="死水位(m)"
              ><ElInputNumber :disabled="!editing" v-model="form.upstream_min" :step="0.1" controls-position="right"
            /></ElFormItem>
            <ElFormItem label="理想区间下限"
              ><ElInputNumber :disabled="!editing" v-model="form.ideal_min" :step="0.1" controls-position="right"
            /></ElFormItem>
            <ElFormItem label="理想区间上限"
              ><ElInputNumber :disabled="!editing" v-model="form.ideal_max" :step="0.1" controls-position="right"
            /></ElFormItem>
            <ElFormItem label="最大变化/h(m)"
              ><ElInputNumber :disabled="!editing"
                v-model="form.max_level_change_per_hour"
                :step="0.1"
                :min="0"
                controls-position="right"
            /></ElFormItem>
          </div>
        </ElCollapseItem>
        <ElCollapseItem title="下游 / 生态" name="downstream">
          <div class="form-grid">
            <ElFormItem label="下游危险(m)"
              ><ElInputNumber :disabled="!editing"
                v-model="form.downstream_danger"
                :step="0.1"
                controls-position="right"
            /></ElFormItem>
            <ElFormItem label="下游上限(m)"
              ><ElInputNumber :disabled="!editing" v-model="form.downstream_max" :step="0.1" controls-position="right"
            /></ElFormItem>
            <ElFormItem label="下游下限(m)"
              ><ElInputNumber :disabled="!editing" v-model="form.downstream_min" :step="0.1" controls-position="right"
            /></ElFormItem>
            <ElFormItem label="最小生态流量"
              ><ElInputNumber :disabled="!editing" v-model="form.eco_flow_min" :step="1" controls-position="right"
            /></ElFormItem>
          </div>
        </ElCollapseItem>
        <ElCollapseItem title="物理参数 / 影子模型" name="shadow">
          <div class="form-grid">
            <ElFormItem label="水面面积(m²)"
              ><ElInputNumber :disabled="!editing"
                v-model="form.reservoir_area"
                :step="100000"
                :min="0"
                controls-position="right"
            /></ElFormItem>
            <ElFormItem label="影子前瞻步数"
              ><ElInputNumber :disabled="!editing"
                v-model="form.shadow_lookahead_steps"
                :step="1"
                :min="1"
                :max="12"
                controls-position="right"
            /></ElFormItem>
            <ElFormItem label="影子危险偏移"
              ><ElInputNumber :disabled="!editing"
                v-model="form.shadow_danger_offset"
                :step="0.1"
                controls-position="right"
            /></ElFormItem>
            <ElFormItem label="死区(%)"
              ><ElInputNumber :disabled="!editing"
                v-model="form.deadband_percent"
                :step="0.01"
                :min="0"
                :max="0.2"
                controls-position="right"
            /></ElFormItem>
            <ElFormItem label="最大变化率/h"
              ><ElInputNumber :disabled="!editing"
                v-model="form.max_rate_per_hour"
                :step="0.01"
                :min="0"
                :max="0.5"
                controls-position="right"
            /></ElFormItem>
          </div>
        </ElCollapseItem>
        <ElCollapseItem title="融合决策阈值" name="fusion">
          <div class="form-grid">
            <ElFormItem label="L3置信度"
              ><ElInputNumber :disabled="!editing"
                v-model="form.fusion_l3_confidence"
                :step="0.01"
                :min="0"
                :max="1"
                controls-position="right"
            /></ElFormItem>
            <ElFormItem label="L3风险概率"
              ><ElInputNumber :disabled="!editing"
                v-model="form.fusion_l3_risk"
                :step="0.01"
                :min="0"
                :max="1"
                controls-position="right"
            /></ElFormItem>
            <ElFormItem label="L2置信度"
              ><ElInputNumber :disabled="!editing"
                v-model="form.fusion_l2_confidence"
                :step="0.01"
                :min="0"
                :max="1"
                controls-position="right"
            /></ElFormItem>
            <ElFormItem label="L2风险概率"
              ><ElInputNumber :disabled="!editing"
                v-model="form.fusion_l2_risk"
                :step="0.01"
                :min="0"
                :max="1"
                controls-position="right"
            /></ElFormItem>
          </div>
        </ElCollapseItem>
        <ElCollapseItem title="阀门最大泄量" name="gate">
          <div class="form-grid">
            <ElFormItem
              v-for="(_, idx) in form.gate_max_discharge"
              :key="idx"
              :label="`${idx + 1}号阀`"
            >
              <ElInputNumber :disabled="!editing"
                v-model="form.gate_max_discharge[idx]"
                :step="10"
                :min="0"
                controls-position="right"
              />
            </ElFormItem>
          </div>
        </ElCollapseItem>
      </ElCollapse>

      <aside class="physics-preview">
        <h4>水位尺预览</h4>
        <p class="ver">v{{ form.config_version }}</p>
        <ul>
          <li v-for="m in levelMarks" :key="m.label">
            <span :style="{ color: m.color }">{{ m.label }}</span>
            <strong>{{ m.value }} m</strong>
          </li>
        </ul>
        <div class="interlock-summary">
          <h5>互锁规则概要</h5>
          <p>
            启用 {{ interlockStats.enabled_count }} 条 · 近24h 触发
            {{ interlockStats.trigger_24h }} 次 · 近7天 {{ interlockStats.trigger_7d }} 次
          </p>
          <ElButton type="primary" link @click="goSettingsTab('gate-interlock')"
            >查看全部规则 →</ElButton
          >
        </div>
      </aside>
    </div>

    <ElDialog v-model="saveDialogVisible" title="保存物理防护配置" width="520px">
      <p class="save-hint">
        版本 {{ original?.config_version }} → {{ bumpVersion(original?.config_version ?? '1.0.0') }}
      </p>
      <ElTable v-if="diffRows.length" :data="diffRows" stripe border style="margin-bottom: 14px">
        <ElTableColumn prop="field" label="字段" width="160" />
        <ElTableColumn label="变更前" min-width="90"
          ><template #default="{ row }">{{ row.before }}</template></ElTableColumn
        >
        <ElTableColumn label="变更后" min-width="90"
          ><template #default="{ row }">{{ row.after }}</template></ElTableColumn
        >
      </ElTable>
      <ElForm
        ref="saveFormRef"
        :model="{ saveDescription }"
        :rules="saveFormRules"
        label-position="top"
        class="save-form"
        @submit.prevent
      >
        <ElFormItem label="变更说明" prop="saveDescription" required>
          <ElInput
            v-model="saveDescription"
            type="textarea"
            :rows="2"
            maxlength="200"
            show-word-limit
            placeholder="请简要说明本次修改原因（必填）"
          />
          <p class="save-desc-tip">保存前须填写变更说明，便于后续在变更历史中追溯。</p>
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="saveDialogVisible = false">取消</ElButton>
        <ElButton type="primary" :loading="saving" @click="confirmSave">确认保存</ElButton>
      </template>
    </ElDialog>

    <ElDialog v-model="cloneDialogVisible" title="克隆物理防护配置" width="480px">
      <ElFormItem label="源水库">
        <ElSelect v-model="cloneSourceId" style="width: 100%">
          <ElOption v-for="r in reservoirs" :key="r.id" :label="r.name" :value="r.id" />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="源版本">
        <ElSelect v-model="cloneVersion" style="width: 100%">
          <ElOption
            v-for="v in historyVersions"
            :key="v.config_version"
            :label="`v${v.config_version} · ${v.description}`"
            :value="v.config_version"
          />
        </ElSelect>
      </ElFormItem>
      <ElFormItem label="目标水库">
        <ElSelect v-model="cloneTargetId" style="width: 100%">
          <ElOption
            v-for="r in reservoirs.filter((x) => x.id !== cloneSourceId)"
            :key="r.id"
            :label="r.name"
            :value="r.id"
          />
        </ElSelect>
      </ElFormItem>
      <template #footer>
        <ElButton @click="cloneDialogVisible = false">取消</ElButton>
        <ElButton type="primary" @click="confirmClone">确认克隆</ElButton>
      </template>
    </ElDialog>
  </div>
</template>

<style scoped lang="scss">
.physics-panel .physics-dirty {
  flex: 1;
  padding: 8px 14px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 6px;
  font-size: 14px;
  color: #92400e;
}
.physics-layout {
  display: grid;
  grid-template-columns: 1fr 240px;
  gap: 20px;
  @media (max-width: 900px) {
    grid-template-columns: 1fr;
  }

  &--readonly :deep(.el-input-number) {
    pointer-events: none;
  }
  &--readonly :deep(.el-input-number .el-input__wrapper) {
    background-color: #f5f7fa;
    cursor: default;
  }
}
.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px 20px;
  :deep(.el-form-item__label) {
    font-size: 14px;
  }
}
.physics-preview {
  padding: 18px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f8fafc;
  h4 {
    margin: 0 0 6px;
    font-size: 16px;
    font-weight: 600;
  }
  .ver {
    margin: 0 0 14px;
    font-size: 13px;
    color: #64748b;
  }
  ul {
    list-style: none;
    margin: 0;
    padding: 0;
  }
  li {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 14px;
    border-bottom: 1px dashed #e2e8f0;
  }
}
.interlock-summary {
  margin-top: 18px;
  padding-top: 14px;
  border-top: 1px solid #e2e8f0;
  h5 {
    margin: 0 0 8px;
    font-size: 14px;
    font-weight: 600;
    color: #334155;
  }
  p {
    margin: 0;
    font-size: 13px;
    color: #64748b;
    line-height: 1.5;
  }
}
.save-hint {
  margin: 0 0 14px;
  font-size: 14px;
  color: #334155;
}
.save-form {
  :deep(.el-form-item__label) {
    font-size: 14px;
    font-weight: 600;
  }
}
.save-desc-tip {
  margin: 8px 0 0;
  font-size: 12px;
  line-height: 1.5;
  color: #64748b;
}
.save-form :deep(.el-form-item.is-error .save-desc-tip) {
  color: #f56c6c;
}
</style>
