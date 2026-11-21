<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  ElButton, ElSlider, ElInputNumber, ElMessage, ElMessageBox, ElTag, ElProgress, ElDialog, ElFormItem, ElInput,
} from 'element-plus'
import { View, CircleClose } from '@element-plus/icons-vue'
import GlassPanel3D from '@/components/cockpit/GlassPanel3D.vue'
import DecisionDetailDialog from './components/DecisionDetailDialog.vue'
import { storeToRefs } from 'pinia'
import { useDispatchStore } from '@/stores/dispatch'
import { useVirtualSimulationStore } from '@/stores/virtualSimulation'
import { useOperationLog } from '@/composables/useOperationLog'
import {
  AUTO_LEVEL_OPTIONS, AUTO_LEVEL_MAP, DISPATCH_MODE_MAP,
  getConfidenceColor, OPENING_MIN, OPENING_MAX, OPENING_STEP,
} from '@/constants/dispatch'

const router = useRouter()
const store = useDispatchStore()
const simStore = useVirtualSimulationStore()
const { active: simActive, derived: simDerived } = storeToRefs(simStore)
const { record: recordLog } = useOperationLog()

const {
  status, decision, targetOpening, userModifiedTarget, canManualControl,
  aggregateOpening, outflowRate, pendingChanges,
} = storeToRefs(store)

const decisionDetailVisible = ref(false)

function openDecisionDetail() {
  if (!decision.value) {
    ElMessage.info('暂无调度建议详情')
    return
  }
  decisionDetailVisible.value = true
}

function goDecisionAnalysis() {
  router.push('/dispatch/analysis')
}

/** 虚拟仿真激活时，叠加仿真数据到显示值 */
const displayUpstreamLevel = computed(() =>
  simActive.value ? simDerived.value.upstreamLevel : status.value.upstreamLevel,
)
const displayDownstreamLevel = computed(() =>
  simActive.value ? simDerived.value.downstreamLevel : status.value.downstreamLevel,
)
const displayFlowRate = computed(() =>
  simActive.value ? simDerived.value.inflowRate : status.value.flowRate,
)
const displayOutflowRate = computed(() =>
  simActive.value ? simDerived.value.outflowRate : outflowRate.value,
)
const displayGateOpening = computed(() =>
  simActive.value ? simDerived.value.aggregateOpening : aggregateOpening.value,
)

const ignoreVisible = ref(false)
const ignoreReason = ref('')

let pollTimer: ReturnType<typeof setInterval> | null = null

const currentAutoLevel = computed(() => Number(status.value.autoLevel) as 1 | 2 | 3)

async function onLevelCardClick(lv: 1 | 2 | 3) {
  if (currentAutoLevel.value === lv) {
    ElMessage.info(`当前已是 ${AUTO_LEVEL_MAP[lv].label}`)
    return
  }
  try {
    await store.putAutoLevel(lv)
    recordLog('调度决策', '变更权限', AUTO_LEVEL_MAP[lv].label, 1)
    ElMessage.success(`已切换为 ${AUTO_LEVEL_MAP[lv].label}`)
  } catch (e) {
    ElMessage.error(e instanceof Error ? e.message : '切换失败')
  }
}

const executingTarget = computed(() => status.value.executingTarget)

const baselineOpening = computed(() => displayGateOpening.value)

const canSubmitExecute = computed(() =>
  canManualControl.value
  && !status.value.isExecuting
  && Math.abs(targetOpening.value - baselineOpening.value) >= OPENING_STEP,
)

const showSuggestionHint = computed(() =>
  decision.value != null
  && Math.abs(targetOpening.value - decision.value.recommended_opening) < OPENING_STEP,
)

const canIgnoreSuggestion = computed(() => status.value.autoLevel !== 3)

const confidenceValue = computed(() => decision.value?.confidence ?? 0)
const confidenceColor = computed(() => getConfidenceColor(confidenceValue.value))

const recommendedPlan = computed(() =>
  decision.value?.alternatives?.find((p) => p.recommended) ?? decision.value?.alternatives?.[0] ?? null,
)

const openingDirection = computed(() => {
  if (!decision.value) return '→'
  const cur = decision.value.current_opening
  const rec = decision.value.recommended_opening
  if (rec > cur) return '↑ 增大'
  if (rec < cur) return '↓ 减小'
  return '→ 维持'
})

const expectedEffect = computed(() => {
  const p = recommendedPlan.value
  if (!p) return '—'
  return `上游水位预计 ${p.expectedLevel} m · 发电 ${p.power} kW · 安全评分 ${p.safetyScore}`
})

function formatTime(iso: string | null) {
  if (!iso) return '—'
  return iso.replace('T', ' ').substring(0, 19)
}

function onTargetInput() {
  userModifiedTarget.value = true
}

async function refresh() {
  await store.refreshGates({ silent: true })
}

async function toggleMode(next: 'auto' | 'manual') {
  if (status.value.mode === next) return
  try {
    await ElMessageBox.confirm(
      `确认切换运行模式为「${DISPATCH_MODE_MAP[next].label}」？`,
      '二次确认',
      { type: 'warning' },
    )
    await store.putDispatchMode(next)
    localStorage.setItem('dispatch_mode', next)
    recordLog('调度决策', '切换模式', `运行模式 → ${DISPATCH_MODE_MAP[next].label}`, 1)
    ElMessage.success('运行模式已更新')
    await refresh()
  } catch { /* cancel */ }
}

async function handleDistributeToGates() {
  if (!canManualControl.value || status.value.isExecuting) return
  if (!canSubmitExecute.value) {
    ElMessage.info('目标总开度与当前相同，无需分配')
    return
  }
  const delta = targetOpening.value - baselineOpening.value
  try {
    await ElMessageBox.confirm(
      `将总开度 ${targetOpening.value}%（较当前 ${delta >= 0 ? '+' : ''}${delta}%）按各孔当前开度比例写入各节点目标值。\n\n分配后请前往「节点控制」确认互锁并提交。`,
      '分配到各孔',
      { type: 'warning', confirmButtonText: '确认分配' },
    )
    store.applyTotalOpeningDistribution(targetOpening.value, false)
    recordLog('调度决策', '分配到各孔', `总开度 → ${targetOpening.value}%`, 1)
    ElMessage.success(`已写入各孔目标开度（${pendingChanges.value} 处变更），请前往节点控制提交`)
  } catch { /* cancel */ }
}

function handleGoNodeControl() {
  if (!canManualControl.value) return
  router.push('/dispatch/gates')
}

async function handleExecuteDirect() {
  if (!canSubmitExecute.value) return
  const delta = targetOpening.value - baselineOpening.value
  try {
    await ElMessageBox.confirm(
      `目标开度 ${targetOpening.value}%（较当前 ${delta >= 0 ? '+' : ''}${delta}%）`,
      '确认执行',
      { type: 'warning' },
    )
    await store.executeOpening(targetOpening.value)
    userModifiedTarget.value = false
    recordLog('调度决策', '手动执行', `开度 ${targetOpening.value}%`, 1)
    ElMessage.success('指令已下发')
    await refresh()
  } catch { /* cancel */ }
}

async function handleCancelExecute() {
  if (!status.value.isExecuting) return
  try {
    await ElMessageBox.confirm('确认取消正在执行的指令？', '取消执行', { type: 'warning' })
    await store.cancelExecuting()
    recordLog('调度决策', '取消执行', '执行中指令', 1)
    ElMessage.success('已取消执行')
  } catch { /* cancel */ }
}

function applyRecommendation() {
  if (!decision.value) {
    ElMessage.info('暂无调度建议')
    return
  }
  const next = decision.value.recommended_opening
  // 采用开度写入目标；自动模式也要有反馈，避免“点了没反应”
  targetOpening.value = next
  userModifiedTarget.value = true
  decisionDetailVisible.value = false
  if (!canManualControl.value) {
    ElMessage.warning(`已写入目标开度 ${next}%：当前非手动/L1，请先切换手动模式后再执行`)
    return
  }
  ElMessage.success(`已采用建议开度 ${next}%`)
}

async function handleIgnore() {
  const reason = ignoreReason.value.trim()
  if (!reason) {
    ElMessage.warning('请填写忽略原因')
    return
  }
  await store.postIgnoreDecision()
  recordLog('调度决策', '忽略建议', reason, 1)
  ElMessage.info('已忽略本次建议')
  ignoreVisible.value = false
  ignoreReason.value = ''
}

onMounted(async () => {
  await store.ensureCoreLoaded()
  store.applySavedMode()
  pollTimer = setInterval(refresh, 15000)
})
onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
})
</script>

<template>
  <div class="page ctrl-page">
    <!-- 虚拟仿真联动横幅 -->
    <div v-if="simActive" class="ctrl-sim-banner">
      <ElTag type="success" size="small">虚拟仿真联动中</ElTag>
      <span>水位 {{ displayUpstreamLevel.toFixed(1) }} m · 开度 {{ displayGateOpening.toFixed(1) }}% · 入库 {{ displayFlowRate }} m³/s</span>
      <ElButton link type="primary" @click="router.push('/hydrology/virtual-sim')">调整仿真参数</ElButton>
    </div>

    <!-- 水情 KPI：与节点控制页统一 -->
    <div class="ctrl-kpi-row">
      <div class="ctrl-kpi ctrl-kpi--hero" :class="{ 'ctrl-kpi--sim': simActive }">
        <span class="ctrl-kpi__label">上游水位</span>
        <strong class="ctrl-kpi__value">{{ displayUpstreamLevel.toFixed(2) }}<em>m</em></strong>
      </div>
      <div class="ctrl-kpi" :class="{ 'ctrl-kpi--sim': simActive }">
        <span class="ctrl-kpi__label">下游水位</span>
        <strong class="ctrl-kpi__value">{{ displayDownstreamLevel.toFixed(2) }}<em>m</em></strong>
      </div>
      <div class="ctrl-kpi" :class="{ 'ctrl-kpi--sim': simActive }">
        <span class="ctrl-kpi__label">入库流量</span>
        <strong class="ctrl-kpi__value">{{ displayFlowRate }}<em>m³/s</em></strong>
      </div>
      <div class="ctrl-kpi" :class="{ 'ctrl-kpi--sim': simActive }">
        <span class="ctrl-kpi__label">出库流量</span>
        <strong class="ctrl-kpi__value">{{ displayOutflowRate }}<em>m³/s</em></strong>
      </div>
      <div class="ctrl-kpi" :class="{ 'ctrl-kpi--sim': simActive }">
        <span class="ctrl-kpi__label">阀门总开度</span>
        <strong class="ctrl-kpi__value">{{ displayGateOpening.toFixed(1) }}<em>%</em></strong>
      </div>
    </div>

    <!-- 主区：左操作 + 右建议 -->
    <div class="ctrl-main">
      <div class="ctrl-main__left">
        <GlassPanel3D title="当前运行状态" class="ctrl-panel">
          <div class="mode-row">
            <span class="lbl">运行模式</span>
            <div class="mode-btns">
              <button type="button" class="mode-btn" :class="{ active: status.mode === 'auto' }" @click="toggleMode('auto')">自动</button>
              <button type="button" class="mode-btn" :class="{ active: status.mode === 'manual' }" @click="toggleMode('manual')">手动</button>
            </div>
            <ElTag v-if="status.isExecuting" type="primary" size="small" effect="plain">执行中</ElTag>
          </div>
          <div class="status-meta">
            <span>自动权限 <strong>{{ AUTO_LEVEL_MAP[status.autoLevel]?.label ?? '—' }}</strong></span>
            <span>末次调度 <strong>{{ formatTime(status.lastDispatchAt) }}</strong></span>
          </div>
        </GlassPanel3D>

        <GlassPanel3D title="手动干预" class="ctrl-panel ctrl-panel--manual">
          <p v-if="!canManualControl" class="manual-tip">自动模式且非 L1 时，手动控制已锁定</p>
          <p v-if="showSuggestionHint" class="suggestion-hint">此开度为调度建议开度</p>
          <div v-if="status.isExecuting" class="exec-strip">
            <ElTag type="primary" size="small">执行中</ElTag>
            <span>目标 <strong>{{ executingTarget ?? targetOpening }}%</strong> · 当前 {{ displayGateOpening.toFixed(1) }}%</span>
          </div>
          <div v-if="status.isExecuting" class="exec-cancel-row">
            <ElButton type="danger" plain class="manual-action-btn" @click="handleCancelExecute">取消执行</ElButton>
          </div>
          <div class="manual-row">
            <span class="lbl">目标开度</span>
            <ElInputNumber
              v-model="targetOpening"
              :min="OPENING_MIN"
              :max="OPENING_MAX"
              :step="OPENING_STEP"
              :disabled="!canManualControl"
              controls-position="right"
              class="manual-input"
              @change="onTargetInput"
            />
            <span class="unit">%</span>
            <span class="delta">Δ {{ targetOpening - displayGateOpening >= 0 ? '+' : '' }}{{ Math.round((targetOpening - displayGateOpening) * 10) / 10 }}%</span>
          </div>
          <ElSlider
            v-model="targetOpening"
            :min="OPENING_MIN"
            :max="OPENING_MAX"
            :step="OPENING_STEP"
            :disabled="!canManualControl"
            @change="onTargetInput"
          />
          <div class="manual-actions">
            <ElButton
              type="primary"
              class="manual-action-btn"
              :disabled="!canSubmitExecute || status.isExecuting"
              @click="handleExecuteDirect"
            >
              总开度执行
            </ElButton>
            <ElButton
              class="manual-action-btn"
              :disabled="!canSubmitExecute || status.isExecuting"
              @click="handleDistributeToGates"
            >
              分配到各孔
            </ElButton>
            <ElButton
              class="manual-action-btn"
              :disabled="!canManualControl"
              @click="handleGoNodeControl"
            >
              节点控制{{ pendingChanges > 0 ? ` (${pendingChanges})` : '' }}
            </ElButton>
          </div>
          <p v-if="pendingChanges > 0" class="manual-pending-hint">
            各孔有 {{ pendingChanges }} 处目标开度待提交，请前往节点控制确认
          </p>
        </GlassPanel3D>
      </div>

      <GlassPanel3D v-if="decision" title="调度建议" class="ctrl-panel ctrl-panel--decision">
        <div class="decision-hero">
          <span class="lbl">推荐动作</span>
          <p class="action-val">
            阀门开至
            <strong
              class="action-val__opening"
              :class="{ clickable: canManualControl }"
              @click="applyRecommendation"
            >{{ decision.recommended_opening }}%</strong>
            <em>{{ openingDirection }}</em>
          </p>
        </div>
        <p class="effect-val">{{ expectedEffect }}</p>
        <div class="decision-conf">
          <div class="conf-head">
            <span>置信度</span>
            <strong :style="{ color: confidenceColor }">{{ confidenceValue }}%</strong>
          </div>
          <ElProgress :percentage="confidenceValue" :color="confidenceColor" :stroke-width="8" :show-text="false" />
        </div>
        <div class="decision-btns">
          <ElButton type="primary" native-type="button" :icon="View" @click.stop.prevent="openDecisionDetail">查看详情</ElButton>
          <ElButton
            :icon="CircleClose"
            :disabled="!canIgnoreSuggestion || status.isExecuting"
            @click="ignoreVisible = true"
          >
            忽略
          </ElButton>
        </div>
        <p v-if="!canIgnoreSuggestion" class="readonly-tip">L3 权限下不可忽略调度建议</p>
      </GlassPanel3D>
    </div>

    <!-- 权限：横向三卡（点击即切换） -->
    <GlassPanel3D title="三级自动执行权限" class="ctrl-panel">
      <div class="level-row">
        <div
          v-for="lv in AUTO_LEVEL_OPTIONS"
          :key="lv.value"
          class="level-card"
          :class="{ active: currentAutoLevel === lv.value }"
          :style="{ '--lv-color': lv.color }"
          role="button"
          tabindex="0"
          @click="onLevelCardClick(lv.value as 1|2|3)"
          @keydown.enter.prevent="onLevelCardClick(lv.value as 1|2|3)"
        >
          <strong>{{ lv.label }}</strong>
          <p>{{ lv.description }}</p>
        </div>
      </div>
      <p class="readonly-tip">点击卡片即可切换权限（当前：{{ AUTO_LEVEL_MAP[currentAutoLevel]?.label }}）</p>
    </GlassPanel3D>

    <GlassPanel3D title="快捷入口" class="ctrl-panel ctrl-panel--links">
      <div class="quick-links">
        <ElButton type="primary" @click="router.push('/dispatch/gates')">节点级精确控制</ElButton>
        <ElButton @click="goDecisionAnalysis">决策分析 / LSTM 预测</ElButton>
      </div>
    </GlassPanel3D>

    <ElDialog v-model="ignoreVisible" title="忽略建议" width="420px" append-to-body>
      <ElFormItem label="原因（必填）" required>
        <ElInput v-model="ignoreReason" type="textarea" :rows="3" placeholder="请填写忽略原因" />
      </ElFormItem>
      <template #footer>
        <ElButton @click="ignoreVisible = false">取消</ElButton>
        <ElButton type="warning" @click="handleIgnore">确认忽略</ElButton>
      </template>
    </ElDialog>

    <DecisionDetailDialog
      v-model="decisionDetailVisible"
      :decision="decision"
      footer-mode="control"
      @apply="applyRecommendation"
    />
  </div>
</template>

<style scoped lang="scss">
@use '@/assets/styles/cockpit.scss' as *;

.ctrl-page {
  @include cockpit-page-white;
  @include cockpit-typography;
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 20px 24px 28px;
  min-height: calc(100vh - 110px);
}

.ctrl-kpi-row {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 16px;

  @media (max-width: 1200px) {
    grid-template-columns: repeat(3, 1fr);
  }
  @media (max-width: 768px) {
    grid-template-columns: repeat(2, 1fr);
  }
}

.ctrl-kpi {
  padding: 20px 22px;
  background: #fff;
  border: 1px solid #eef2f6;
  border-radius: 14px;
  box-shadow: 0 2px 12px rgba(15, 23, 42, 0.04);
  display: flex;
  flex-direction: column;
  gap: 8px;

  &--hero {
    background: linear-gradient(145deg, #fff, #f0f7ff);
    border-color: rgba(24, 144, 255, 0.18);
  }

  &--sim {
    position: relative;
    border-color: rgba(34, 197, 94, 0.3);
    &::after {
      content: '仿真';
      position: absolute;
      top: 10px;
      right: 12px;
      padding: 2px 7px;
      font-size: 10px;
      font-weight: 600;
      color: #fff;
      background: #22c55e;
      border-radius: 4px;
      letter-spacing: 0.04em;
    }
    .ctrl-kpi__value { color: #16a34a !important; }
  }

  &__label {
    font-size: $cockpit-font-md;
    font-weight: 600;
    color: $cockpit-text-dim;
  }

  &__value {
    font-size: 32px;
    font-weight: 700;
    font-family: monospace;
    color: $cockpit-text;
    line-height: 1;

    em { font-style: normal; font-size: 16px; color: $cockpit-text-dim; margin-left: 2px; }
  }

  &--hero &__value { color: #1890ff; }
}

.ctrl-sim-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 16px;
  border-radius: 10px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  font-size: $cockpit-font-sm;
  color: #15803d;
}

.ctrl-main {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  align-items: stretch;

  @media (max-width: 1100px) {
    grid-template-columns: 1fr;
  }

  &__left {
    display: flex;
    flex-direction: column;
    gap: 20px;
    min-width: 0;
  }
}

.ctrl-panel {
  :deep(.glass-panel__header) {
    padding: 18px 22px 14px;
    .glass-panel__title { font-size: $cockpit-font-lg; font-weight: 700; }
  }

  :deep(.glass-panel__body) {
    padding: 18px 22px 22px;
  }

  &--decision {
    display: flex;
    flex-direction: column;

    :deep(.glass-panel__body) {
      display: flex;
      flex-direction: column;
      gap: 16px;
      flex: 1;
    }

    .decision-btns { margin-top: auto; }
  }

  &--links :deep(.glass-panel__body) {
    padding: 14px 22px 18px;
  }
}

.mode-row {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}

.lbl {
  font-size: $cockpit-font-sm;
  font-weight: 600;
  color: $cockpit-text-dim;
}

.mode-btns {
  display: flex;
  gap: 8px;
}

.mode-btn {
  padding: 8px 22px;
  border: 1px solid #dbeafe;
  border-radius: 8px;
  background: #fff;
  color: #64748b;
  cursor: pointer;
  font-size: 15px;
  font-weight: 600;
  transition: all 0.2s;

  &.active {
    background: #1890ff;
    border-color: #1890ff;
    color: #fff;
  }
}

.status-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  padding-top: 14px;
  border-top: 1px solid #eef2f6;
  font-size: $cockpit-font-sm;
  color: $cockpit-text-dim;

  strong {
    color: $cockpit-text;
    font-family: monospace;
    margin-left: 4px;
  }
}

.suggestion-hint {
  margin: 0 0 12px;
  padding: 8px 12px;
  border-radius: 8px;
  background: #f0f7ff;
  border: 1px solid rgba(24, 144, 255, 0.2);
  font-size: 13px;
  color: #1890ff;
}

.exec-cancel-row {
  margin-bottom: 14px;

  .manual-action-btn {
    width: 100%;
    height: 40px;
  }
}

.exec-strip {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 14px;
  padding: 10px 14px;
  border-radius: 10px;
  background: #f8fbff;
  border: 1px solid #dbeafe;
  border-left: 3px solid #1890ff;
  font-size: $cockpit-font-sm;
  color: #64748b;

  strong {
    font-family: monospace;
    color: #1890ff;
  }
}

.manual-tip {
  font-size: 13px;
  color: $cockpit-text-dim;
  margin: 0 0 12px;
}

.manual-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
  flex-wrap: wrap;

  .unit {
    font-size: 16px;
    font-weight: 600;
    color: #1890ff;
  }

  .delta {
    margin-left: auto;
    font-size: 14px;
    font-family: monospace;
    color: #64748b;
  }
}

.manual-input {
  width: 120px;

  :deep(.el-input__inner) {
    font-size: 18px;
    font-weight: 600;
    text-align: center;
  }
}

.manual-pending-hint {
  margin: 10px 0 0;
  padding: 8px 12px;
  border-radius: 8px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  font-size: 13px;
  color: #b45309;
}

.manual-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-top: 16px;
}

.manual-action-btn {
  flex: 1;
  min-width: 120px;
  height: 42px;
}

.decision-hero {
  .action-val {
    margin: 8px 0 0;
    font-size: 16px;
    color: #1e293b;

    strong {
      font-size: 36px;
      color: #1890ff;
      margin: 0 6px;
    }

    .action-val__opening.clickable {
      cursor: pointer;
      text-decoration: underline dotted rgba(24, 144, 255, 0.45);
      text-underline-offset: 4px;
    }

    em {
      font-style: normal;
      font-size: 14px;
      color: #64748b;
    }
  }
}

.effect-val {
  margin: 0;
  padding: 12px 14px;
  background: #f8fafc;
  border-radius: 10px;
  border: 1px solid #eef2f6;
  font-size: 14px;
  color: #475569;
  line-height: 1.55;
}

.decision-conf {
  padding: 14px 16px;
  background: #f8fafc;
  border-radius: 10px;
  border: 1px solid #eef2f6;
}

.conf-head {
  display: flex;
  justify-content: space-between;
  margin-bottom: 10px;
  font-size: 14px;

  strong {
    font-size: 20px;
    font-family: monospace;
  }
}

.decision-btns {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  padding-top: 4px;
}

.level-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;

  @media (max-width: 900px) {
    grid-template-columns: 1fr;
  }
}

.level-card {
  padding: 16px 18px;
  border: 1px solid #eef2f6;
  border-radius: 12px;
  background: #fafbfc;
  cursor: pointer;
  transition: all 0.2s;

  strong {
    display: block;
    font-size: 16px;
    margin-bottom: 8px;
    color: $cockpit-text;
  }

  p {
    margin: 0;
    font-size: 13px;
    color: $cockpit-text-dim;
    line-height: 1.5;
  }

  &.active {
    border-color: var(--lv-color, #1890ff);
    background: #fff;
    box-shadow: inset 3px 0 0 var(--lv-color, #1890ff);
  }

  &:not(.active):hover {
    border-color: #cbd5e1;
    background: #fff;
  }
}

.readonly-tip {
  font-size: 13px;
  color: $cockpit-text-dim;
  text-align: center;
  margin: 12px 0 0;
}

.quick-links {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.sub {
  font-size: 14px;
  color: $cockpit-text-dim;
  margin-top: 8px;
}

:deep(.el-slider__bar) { background: #1890ff; }
</style>

