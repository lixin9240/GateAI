<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  ElButton, ElSlider, ElInputNumber, ElMessage, ElMessageBox, ElTag, ElDialog, ElTooltip, ElSwitch,
} from 'element-plus'
import GlassPanel3D from '@/components/cockpit/GlassPanel3D.vue'
import DamSectionDiagram from './components/DamSectionDiagram.vue'
import { storeToRefs } from 'pinia'
import { useDispatchStore } from '@/stores/dispatch'
import { useVirtualSimulationStore } from '@/stores/virtualSimulation'
import { useOperationLog } from '@/composables/useOperationLog'
import { isGateOnline, openingBarColor } from '@/utils/gateControl'
import { confirmInterlockAction } from '@/utils/interlockNotify'
import type { GateGroup, GateNodeControl } from '@/types/gateControl'
import { OPENING_MIN, OPENING_MAX, OPENING_STEP } from '@/constants/dispatch'

const INTERLOCK_BYPASS_KEY = 'dispatch_interlock_manual_bypass'

const router = useRouter()
const store = useDispatchStore()
const simStore = useVirtualSimulationStore()
const { active: simActive, derived: simDerived } = storeToRefs(simStore)
const { record: recordLog } = useOperationLog()

/** 演示：手动绕过=可强制提交；互锁严格=阻断不可过 */
const manualBypass = ref(localStorage.getItem(INTERLOCK_BYPASS_KEY) !== 'false')
function onBypassChange(v: boolean | string | number) {
  manualBypass.value = Boolean(v)
  localStorage.setItem(INTERLOCK_BYPASS_KEY, manualBypass.value ? 'true' : 'false')
  ElMessage.info(manualBypass.value ? '已开启手动绕过：互锁阻断仍可强制执行' : '已切换互锁严格：阻断时不可执行')
}

const {
  status, gates, selectedGateId, selectedGate, gatesInitialLoading, canManualControl,
  aggregateOpening, pendingChanges, impactPreview,
  precheckResult,
} = storeToRefs(store)

/** 虚拟仿真激活时，叠加仿真数据（静态工况） */
const displayGates = computed(() => {
  void simDerived.value.aggregateOpening
  return simActive.value ? simStore.overlayGates(gates.value) : gates.value
})
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
  simActive.value ? simDerived.value.outflowRate : impactPreview.value.outflow,
)
const displayAggregateOpening = computed(() =>
  simActive.value ? simDerived.value.aggregateOpening : aggregateOpening.value,
)

const submitting = ref(false)
const warnDialogVisible = ref(false)
const groupLabels: Record<GateGroup, string> = { surface: '表孔', mid: '中孔', bottom: '底孔' }

const groupedGates = computed(() => {
  const groups: Record<GateGroup, GateNodeControl[]> = { surface: [], mid: [], bottom: [] }
  for (const g of displayGates.value) groups[g.group].push(g)
  return groups
})

const interlockStatus = computed(() => {
  const violations = precheckResult.value?.violations ?? []
  if (!precheckResult.value) return { label: '待校验', type: 'info' as const }
  const blocks = violations.filter((v) => v.severity === 'block').length
  const warns = violations.filter((v) => v.severity === 'warn').length
  if (!blocks && !warns) return { label: '互锁正常', type: 'success' as const }
  if (blocks) return { label: `${blocks} 处阻断`, type: 'danger' as const }
  return { label: `${warns} 处警告`, type: 'warning' as const }
})

const violationStats = computed(() => {
  const list = precheckResult.value?.violations ?? []
  return {
    total: list.length,
    blocks: list.filter((v) => v.severity === 'block').length,
    warns: list.filter((v) => v.severity === 'warn').length,
  }
})

/** 按规则类型汇总，详情弹窗用 */
const violationSummary = computed(() => {
  const map = new Map<string, {
    ruleName: string
    severity: 'block' | 'warn'
    count: number
    message: string
  }>()
  for (const v of precheckResult.value?.violations ?? []) {
    const key = `${v.ruleCode}:${v.severity}`
    const hit = map.get(key)
    if (hit) {
      hit.count += 1
    } else {
      map.set(key, {
        ruleName: v.ruleName,
        severity: v.severity,
        count: 1,
        message: v.message,
      })
    }
  }
  return [...map.values()]
})

const waterTrendLabel = computed(() => {
  if (impactPreview.value.waterTrend === 'up') return '水位趋升'
  if (impactPreview.value.waterTrend === 'down') return '水位趋降'
  return '基本平衡'
})

const openGateCount = computed(() =>
  displayGates.value.filter((g) => isGateOnline(g.status) && g.currentOpening > 0).length,
)
const onlineGateCount = computed(() => displayGates.value.filter((g) => isGateOnline(g.status)).length)

const changedList = computed(() =>
  displayGates.value.filter((g) => isGateOnline(g.status) && g.targetOpening !== g.currentOpening),
)

const displaySelectedGate = computed(() =>
  displayGates.value.find((g) => g.id === selectedGateId.value) ?? null,
)

const submitDisabledReason = computed(() => {
  if (!canManualControl.value) return '当前为自动模式且非 L1，请先在运行控制切换手动模式'
  if (pendingChanges.value === 0) return '暂无待提交变更：请先将某孔目标开度调到与当前开度不同'
  if (submitting.value) return '正在提交中…'
  return ''
})

let pollTimer: ReturnType<typeof setInterval> | null = null
let pollInFlight = false

async function pollGates() {
  if (pollInFlight || document.hidden) return
  pollInFlight = true
  try {
    await store.refreshGates({ silent: true })
  } finally {
    pollInFlight = false
  }
}

function selectGate(id: number) { selectedGateId.value = id }

/** 开度 > 0 视为开启 */
function isGateOpen(g: GateNodeControl) {
  return g.currentOpening > 0
}

function setSelectedGateTarget(opening: number) {
  if (!selectedGate.value) return
  store.setGateTarget(selectedGate.value.id, opening)
}

/** 单孔开/关：即时生效（状态跟着按钮走） */
async function setGateOpenClose(gateId: number, open: boolean) {
  if (!canManualControl.value) {
    ElMessage.warning('请先切换手动模式')
    return
  }
  const g = gates.value.find((x) => x.id === gateId)
  if (!g || !isGateOnline(g.status)) {
    ElMessage.info('该节点离线或不可控')
    return
  }
  if ((g.currentOpening > 0) === open && (g.targetOpening > 0) === open) {
    return
  }
  submitting.value = true
  try {
    await store.applyGateOpenClose(gateId, open)
    recordLog('调度决策', '节点启闭', `${g.name} → ${open ? '开启' : '关闭'}`, 1)
    ElMessage.success(`${g.name} 已${open ? '开启' : '关闭'}`)
  } finally {
    submitting.value = false
  }
}

async function onGateOpenSwitch(gateId: number, open: boolean | string | number) {
  await setGateOpenClose(gateId, Boolean(open))
}

function onSliderChange(val: number | number[]) {
  const opening = Array.isArray(val) ? val[0] : val
  if (selectedGateId.value && opening != null) setSelectedGateTarget(opening)
}

function fmtNum(n: number, d = 1) { return Number(n.toFixed(d)) }
function fmtDelta(cur: number, tgt: number) {
  const d = tgt - cur
  return `${d >= 0 ? '+' : ''}${fmtNum(d)}%`
}

async function executeOne() {
  if (!selectedGate.value || !canManualControl.value) return
  const g = selectedGate.value
  if (g.targetOpening === g.currentOpening) {
    ElMessage.info('目标开度与当前相同，无需执行')
    return
  }
  const ok = await confirmInterlockAction({
    title: '执行本孔',
    actionSummary: `将 <strong>${g.name}</strong> 开度由 ${g.currentOpening}% 调整至 ${g.targetOpening}%`,
    confirmText: '确认执行',
    violations: precheckResult.value?.violations ?? [],
    allowForce: manualBypass.value,
  })
  if (!ok) return
  try {
    submitting.value = true
    await store.mockExecuteGate(g.id)
    recordLog('调度决策', '节点控制', `${g.name} → ${g.targetOpening}%`, 1)
    ElMessage.success(`${g.name} 执行完成`)
    await store.refreshGates({ silent: true })
  } catch {
    ElMessage.error(`${g.name} 下发失败，请检查登录态或接口`)
  } finally { submitting.value = false }
}

async function submitAll() {
  if (!canManualControl.value) {
    ElMessage.warning('请先切换手动模式')
    router.push('/dispatch/control')
    return
  }
  if (!changedList.value.length) {
    ElMessage.info('没有待提交变更')
    return
  }
  const n = changedList.value.length
  const ok = await confirmInterlockAction({
    title: '提交变更',
    actionSummary: `批量下发 <strong>${n}</strong> 处节点开度变更`,
    confirmText: '确认提交',
    violations: precheckResult.value?.violations ?? [],
    allowForce: manualBypass.value,
  })
  if (!ok) return
  try {
    submitting.value = true
    await store.mockBatchExecute()
    recordLog('调度决策', '节点批量控制', `${n} 孔`, 1)
    ElMessage.success('全部变更已下发')
    await store.refreshGates({ silent: true })
  } catch {
    ElMessage.error('批量下发失败，请检查登录态或接口')
  } finally { submitting.value = false }
}

function syncGroup(group: GateGroup) {
  if (!selectedGate.value || selectedGate.value.group !== group) return
  store.syncGroupTargets(group, selectedGate.value.targetOpening)
  ElMessage.success(`已同步${groupLabels[group]}组`)
}

function stepTargetOpening(delta: number) {
  if (!selectedGate.value || !canManualControl.value || !isGateOnline(selectedGate.value.status)) return
  const next = Math.min(
    OPENING_MAX,
    Math.max(OPENING_MIN, +(selectedGate.value.targetOpening + delta).toFixed(1)),
  )
  setSelectedGateTarget(next)
}

onMounted(async () => {
  await store.ensureCoreLoaded()
  pollTimer = setInterval(pollGates, 15000)
})
onUnmounted(() => { if (pollTimer) clearInterval(pollTimer) })
</script>

<template>
  <div class="page gates-page">
    <!-- 坝体剖面：指标 + 操作 + 剖面图 合一，减少上方框框 -->
    <GlassPanel3D
      title="坝体剖面"
      class="gates-viz-top"
      v-loading="gatesInitialLoading"
      element-loading-background="rgba(255,255,255,0.45)"
    >
      <div class="gates-command">
        <!-- 始终显示：手动绕过开关（不跟告警条一起被 v-if 藏掉） -->
        <div class="gates-command__toolbar">
          <div class="gates-bypass" title="开=互锁阻断仍可强制执行；关=严格拦截">
            <span class="gates-bypass__label">{{ manualBypass ? '手动绕过' : '互锁严格' }}</span>
            <ElSwitch
              :model-value="manualBypass"
              inline-prompt
              active-text="绕过"
              inactive-text="严格"
              @change="onBypassChange"
            />
          </div>
          <ElButton size="small" :disabled="!canManualControl" @click="store.alignSurfaceTargets()">
            表孔拉齐
          </ElButton>
          <span v-if="!canManualControl" class="alert-chip alert-chip--info">
            节点控制已锁定
            <ElButton
              link
              type="primary"
              class="alert-chip__link"
              @click="router.push('/dispatch/control')"
            >
              去运行控制
            </ElButton>
          </span>
          <span
            v-if="interlockStatus.type !== 'success'"
            class="alert-chip"
            :class="{
              'alert-chip--danger': interlockStatus.type === 'danger',
              'alert-chip--warn': interlockStatus.type === 'warning',
            }"
          >
            互锁检查
            <span class="alert-chip__stat alert-chip__stat--block">{{ violationStats.blocks }} 阻断</span>
            <span class="alert-chip__stat alert-chip__stat--warn">{{ violationStats.warns }} 警告</span>
            <ElButton
              v-if="violationStats.total"
              link
              type="primary"
              class="alert-chip__link"
              @click="warnDialogVisible = true"
            >
              查看规则
            </ElButton>
          </span>
          <span v-if="simActive" class="alert-chip alert-chip--ok">
            仿真联动中
            <ElButton
              link
              type="primary"
              class="alert-chip__link"
              @click="router.push('/hydrology/virtual-sim')"
            >
              调参
            </ElButton>
          </span>
        </div>

        <div class="gates-command__main">
          <div class="gates-command__metrics" :class="{ 'gates-command__metrics--sim': simActive }">
            <div class="metric-pill">
              <span class="metric-pill__label">聚合开度</span>
              <strong class="metric-pill__value">{{ displayAggregateOpening.toFixed(1) }}<em>%</em></strong>
            </div>
            <div class="metric-pill">
              <span class="metric-pill__label">开启孔数</span>
              <strong class="metric-pill__value">{{ openGateCount }}<em>/{{ onlineGateCount }}</em></strong>
            </div>
            <div class="metric-pill">
              <span class="metric-pill__label">预估出库</span>
              <strong class="metric-pill__value">{{ fmtNum(displayOutflowRate, 0) }}<em>m³/s</em></strong>
            </div>
            <div class="metric-pill">
              <span class="metric-pill__label">入库流量</span>
              <strong class="metric-pill__value">{{ fmtNum(displayFlowRate, 1) }}<em>m³/s</em></strong>
            </div>
          </div>

          <div class="gates-command__actions">
            <ElButton @click="router.push('/virtual-simulation')">虚拟仿真</ElButton>
            <ElButton @click="router.push('/simulation')">数字孪生</ElButton>
            <ElButton @click="store.resetAllGateTargets()">全部复位</ElButton>
            <ElTooltip
              :content="submitDisabledReason"
              :disabled="!submitDisabledReason"
              placement="top"
            >
              <ElButton
                type="primary"
                :disabled="!canManualControl || pendingChanges === 0 || submitting"
                @click="submitAll"
              >
                提交变更{{ pendingChanges > 0 ? ` (${pendingChanges})` : '' }}
              </ElButton>
            </ElTooltip>
          </div>
        </div>
      </div>

      <DamSectionDiagram
        class="gates-command__diagram"
        :gates="displayGates"
        :selected-gate-id="selectedGateId"
        :upstream-level="displayUpstreamLevel"
        :downstream-level="displayDownstreamLevel"
        :inflow-rate="displayFlowRate"
        :outflow-rate="displayOutflowRate"
        @select="selectGate"
      />
    </GlassPanel3D>

    <!-- 下方：左列表 + 右详情 -->
    <div class="gates-body">
      <GlassPanel3D title="阀门节点" fill class="gates-panel gates-panel--list">
        <template v-for="(list, group) in groupedGates" :key="group">
          <div class="gate-group-label">{{ groupLabels[group as GateGroup] }}</div>
          <div
            v-for="g in list"
            :key="g.id"
            class="gate-item"
            :class="{
              active: selectedGateId === g.id,
              pending: isGateOnline(g.status) && g.targetOpening !== g.currentOpening,
              offline: !isGateOnline(g.status),
            }"
            @click="selectGate(g.id)"
          >
            <div class="gate-item__row">
              <span class="gate-item__code">{{ g.code }}</span>
              <span class="gate-item__name">{{ g.name }}</span>
              <ElTag v-if="g.status === 'executing'" type="warning" size="small">执行中</ElTag>
              <ElTag v-else-if="!isGateOnline(g.status)" type="info" size="small">离线</ElTag>
              <ElTag
                v-else
                size="small"
                :type="isGateOpen(g) ? 'success' : 'info'"
                effect="plain"
              >
                {{ isGateOpen(g) ? '开' : '关' }}
              </ElTag>
              <span class="gate-item__flow">{{ fmtNum(g.flowRate, 1) }} m³/s</span>
            </div>
            <div class="gate-item__track">
              <div class="gate-item__fill" :style="{ width: `${g.currentOpening}%`, background: openingBarColor(g.currentOpening) }" />
              <div
                v-if="g.targetOpening !== g.currentOpening"
                class="gate-item__ghost"
                :style="{ width: `${g.targetOpening}%` }"
              />
            </div>
            <div class="gate-item__nums">
              <span>{{ g.currentOpening }}%</span>
              <span v-if="g.targetOpening !== g.currentOpening" class="gate-item__tgt">→ {{ g.targetOpening }}%</span>
              <div class="gate-item__switch" @click.stop>
                <span class="gate-item__switch-label">{{ isGateOpen(g) ? '开' : '关' }}</span>
                <ElSwitch
                  :model-value="isGateOpen(g)"
                  :disabled="!canManualControl || !isGateOnline(g.status) || submitting"
                  inline-prompt
                  active-text="开"
                  inactive-text="关"
                  @change="(v) => onGateOpenSwitch(g.id, v)"
                />
              </div>
            </div>
          </div>
        </template>
      </GlassPanel3D>

      <GlassPanel3D v-if="selectedGate" fill :title="`${selectedGate.name}`" class="gates-panel gates-panel--detail">
        <template #extra><span class="detail-code">{{ selectedGate.code }}</span></template>

        <div class="detail-open-close">
          <div class="detail-open-close__row">
            <div class="detail-open-close__info">
              <span class="detail-label">节点启闭</span>
              <strong :class="isGateOpen(displaySelectedGate ?? selectedGate) ? 'is-open' : 'is-closed'">
                {{ isGateOpen(displaySelectedGate ?? selectedGate) ? '开启' : '关闭' }}
              </strong>
            </div>
            <ElSwitch
              :model-value="isGateOpen(selectedGate)"
              :disabled="!canManualControl || !isGateOnline(selectedGate.status) || submitting"
              inline-prompt
              active-text="开"
              inactive-text="关"
              size="large"
              @change="(v) => onGateOpenSwitch(selectedGate.id, v)"
            />
          </div>
        </div>

        <div class="detail-opening">
          <div class="detail-opening__block" :class="{ 'detail-opening__block--sim': simActive }">
            <span class="detail-label">当前开度</span>
            <strong class="detail-num">{{ displaySelectedGate?.currentOpening ?? selectedGate.currentOpening }}<em>%</em></strong>
          </div>
          <div class="detail-opening__block detail-opening__block--target">
            <span class="detail-label">目标开度</span>
            <div class="detail-target-ctrl">
              <ElButton
                class="detail-target-btn"
                :disabled="!canManualControl || !isGateOnline(selectedGate.status) || selectedGate.targetOpening <= OPENING_MIN"
                @click="stepTargetOpening(-OPENING_STEP)"
              >
                下调
              </ElButton>
              <ElInputNumber
                :model-value="selectedGate.targetOpening"
                :min="OPENING_MIN" :max="OPENING_MAX" :step="OPENING_STEP"
                :disabled="!canManualControl || !isGateOnline(selectedGate.status)"
                controls-position="right"
                class="detail-target-input"
                @update:model-value="(v: number | undefined) => v != null && setSelectedGateTarget(v)"
              />
              <ElButton
                type="primary"
                class="detail-target-btn"
                :disabled="!canManualControl || !isGateOnline(selectedGate.status) || selectedGate.targetOpening >= OPENING_MAX"
                @click="stepTargetOpening(OPENING_STEP)"
              >
                上调
              </ElButton>
            </div>
          </div>
        </div>

        <ElSlider
          :model-value="selectedGate.targetOpening"
          :min="OPENING_MIN" :max="OPENING_MAX" :step="OPENING_STEP"
          :disabled="!canManualControl || !isGateOnline(selectedGate.status)"
          @update:model-value="onSliderChange"
        />
        <p class="detail-delta">变化量 <strong>{{ fmtDelta(displaySelectedGate?.currentOpening ?? selectedGate.currentOpening, selectedGate.targetOpening) }}</strong></p>

        <div class="detail-impact">
          <div class="detail-impact__row"><span>总开度</span><strong>{{ displayAggregateOpening.toFixed(1) }}%</strong></div>
          <div class="detail-impact__row"><span>出库流量</span><strong>{{ displayOutflowRate }} m³/s</strong></div>
          <div class="detail-impact__row"><span>水位趋势</span><strong>{{ simActive ? simDerived.waterTrend === 'up' ? '水位趋升' : simDerived.waterTrend === 'down' ? '水位趋降' : '基本平衡' : waterTrendLabel }}</strong></div>
          <div class="detail-impact__row">
            <span>生态流量</span>
            <strong :class="impactPreview.ecoFlowOk ? 'ok' : 'bad'">{{ impactPreview.ecoFlowOk ? '满足' : '不足' }}</strong>
          </div>
        </div>

        <p class="detail-flow-tip">
          调开度时顶栏实时显示互锁结果；点「执行本孔」仅下发当前孔，点顶部「提交变更」批量下发全部待改孔。
        </p>
        <div class="detail-btns">
          <ElButton type="primary" size="large" :disabled="!canManualControl || !isGateOnline(selectedGate.status) || submitting" @click="executeOne">
            执行本孔
          </ElButton>
          <ElButton @click="store.resetGateTarget(selectedGate.id)">重置</ElButton>
          <ElButton @click="syncGroup(selectedGate.group)">同组同步</ElButton>
        </div>
      </GlassPanel3D>
    </div>

    <GlassPanel3D v-if="changedList.length" title="待提交变更">
      <div class="changes-table">
        <div class="changes-table__head"><span>节点</span><span>当前</span><span>目标</span><span>Δ</span></div>
        <div v-for="g in changedList" :key="g.id" class="changes-table__row">
          <span>{{ g.code }} {{ g.name }}</span>
          <span>{{ g.currentOpening }}%</span>
          <span>{{ g.targetOpening }}%</span>
          <span>{{ fmtDelta(g.currentOpening, g.targetOpening) }}</span>
        </div>
      </div>
    </GlassPanel3D>
  </div>

  <ElDialog v-model="warnDialogVisible" title="互锁规则说明" width="560px" append-to-body>
    <ul v-if="violationSummary.length" class="warn-dialog-list">
      <li v-for="(v, i) in violationSummary" :key="i" :class="v.severity">
        <strong>{{ v.ruleName }}<template v-if="v.count > 1">（{{ v.count }} 项）</template></strong>
        <p>{{ v.message }}</p>
      </li>
    </ul>
    <p v-else class="warn-dialog-empty">当前无约束项</p>
  </ElDialog>
</template>

<style scoped lang="scss">
@use '@/assets/styles/cockpit.scss' as *;
@use './dispatch-shared.scss';

.gates-page {
  @include cockpit-page-white;
  @include cockpit-typography;
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px 24px 28px;
  min-height: calc(100vh - 110px);
  background: #fff;
}

// ── 坝体剖面：合一工具栏 + 剖面图 ──
.gates-viz-top {
  flex-shrink: 0;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);

  :deep(.glass-panel) {
    border-radius: 16px;
    border-color: #eef2f6;
  }

  :deep(.glass-panel__header) {
    padding: 14px 20px 12px;
    background: #fff;
    border-bottom: none;

    .glass-panel__title {
      font-size: $cockpit-font-lg;
      font-weight: 700;
      color: #1e293b;
    }

    .glass-panel__deco {
      background: linear-gradient(180deg, #1890ff, #69c0ff);
    }
  }

  :deep(.glass-panel__body) {
    display: flex;
    flex-direction: column;
    padding: 0;
    min-height: 0;
    background: #fff;
    overflow: hidden;
  }
}

.gates-command {
  flex-shrink: 0;
  border-bottom: 1px solid #f1f5f9;
  background: #fafbfc;

  &__toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    padding: 10px 20px 0;
  }

  &__main {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 12px 20px;
    flex-wrap: wrap;
  }

  &__metrics {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px 0;
    min-width: 0;

    &--sim .metric-pill__value {
      color: #16a34a;
    }
  }

  &__actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    flex-shrink: 0;

    :deep(.el-button) {
      margin: 0;
      height: 36px;
      padding: 0 16px;
      border-radius: 8px;
      font-weight: 600;
    }

    :deep(.el-button--primary) {
      background: linear-gradient(135deg, #1890ff 0%, #096dd9 100%);
      border: none;
    }
  }

  &__diagram {
    flex: 1;
    min-height: 420px;
    height: 420px;

    :deep(.dam-section) {
      height: 100%;
      min-height: 0;
    }
  }
}

.gates-bypass {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 34px;
  padding: 0 12px;
  border-radius: 8px;
  background: #e6f4ff;
  border: 1px solid #91caff;
  flex-shrink: 0;
  box-shadow: 0 0 0 2px rgba(9, 88, 217, 0.08);

  &__label {
    font-size: 13px;
    font-weight: 700;
    color: #0958d9;
    white-space: nowrap;
  }
}

.alert-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  color: #64748b;
  background: #fff;
  border: 1px solid #e2e8f0;
  white-space: nowrap;

  &__stat {
    font-family: 'SF Mono', Consolas, monospace;
    font-weight: 700;
    font-size: 14px;

    &--block { color: #dc2626; }
    &--warn { color: #ea580c; }
  }

  &--info { border-color: rgba(24, 144, 255, 0.25); color: #1890ff; }
  &--danger { border-color: rgba(239, 68, 68, 0.3); color: #dc2626; }
  &--warn { border-color: rgba(249, 115, 22, 0.3); color: #ea580c; }
  &--ok { border-color: rgba(34, 197, 94, 0.3); color: #16a34a; }

  &__link {
    padding: 0 2px;
    font-size: 16px;
    font-weight: 600;
    vertical-align: baseline;
  }

  :deep(.el-button.is-link) {
    padding: 0 2px;
    font-size: 16px !important;
    font-weight: 600;
    vertical-align: baseline;
  }
}

.metric-pill {
  display: inline-flex;
  align-items: baseline;
  gap: 8px;
  padding: 0 18px;
  border-right: 1px solid #e8edf2;

  &:first-child { padding-left: 0; }
  &:last-child { border-right: none; }

  &__label {
    font-size: 12px;
    font-weight: 500;
    color: #94a3b8;
    white-space: nowrap;
  }

  &__value {
    font-size: 22px;
    font-weight: 700;
    font-family: 'SF Mono', Consolas, monospace;
    color: #1890ff;
    line-height: 1;
    white-space: nowrap;

    em {
      font-style: normal;
      font-size: 12px;
      font-weight: 600;
      color: #94a3b8;
      margin-left: 2px;
    }
  }

  &__sub {
    font-size: 11px;
    color: #94a3b8;
    white-space: nowrap;
  }
}

@media (max-width: 1100px) {
  .gates-command__main {
    flex-direction: column;
    align-items: stretch;
  }

  .gates-command__actions {
    justify-content: flex-end;
  }

  .metric-pill {
    padding: 6px 12px 6px 0;
    border-right: none;
  }
}

.warn-dialog-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: 420px;
  overflow-y: auto;

  li {
    padding: 12px 14px;
    border-radius: 8px;
    border-left: 3px solid #faad14;
    background: #fffbf5;

    &.block {
      border-left-color: #ff7875;
      background: #fff2f0;
    }

    strong {
      display: block;
      font-size: 14px;
      margin-bottom: 4px;
      color: $cockpit-text;
    }

    p {
      margin: 0;
      font-size: 13px;
      line-height: 1.5;
      color: $cockpit-text-dim;
    }
  }
}

.warn-dialog-empty {
  margin: 0;
  text-align: center;
  color: $cockpit-text-dim;
  font-size: $cockpit-font-sm;
}

// ── 下方两栏：列表 + 详情 ──
.gates-body {
  flex: 1;
  display: grid;
  grid-template-columns: 38% 1fr;
  gap: 20px;
  min-height: 420px;

  @media (max-width: 1100px) {
    grid-template-columns: 1fr;
    min-height: auto;
  }
}

.gates-panel {
  min-height: 0;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);

  :deep(.glass-panel) {
    border-radius: 16px;
    border-color: #eef2f6;
  }

  &--list :deep(.glass-panel__body) {
    overflow-y: auto;
    padding: 12px 16px 20px;
    max-height: 520px;
    background: #fff;
  }

  :deep(.glass-panel__header) {
    padding: 18px 22px 14px;
    background: linear-gradient(90deg, rgba(24, 144, 255, 0.03) 0%, transparent 55%);
    border-bottom: 1px solid #f1f5f9;

    .glass-panel__title {
      font-size: $cockpit-font-lg;
      font-weight: 700;
      color: #1e293b;
    }
  }

  &--detail :deep(.glass-panel__body) {
    display: flex;
    flex-direction: column;
    gap: 18px;
    overflow-y: auto;
    max-height: 520px;
    padding: 18px 22px 22px;
    background: #fff;
  }
}

.detail-code {
  font-size: 12px;
  color: #64748b;
  font-family: monospace;
  padding: 2px 10px;
  background: #f1f5f9;
  border-radius: 6px;
}

// ── 节点列表 ──
.gate-group-label {
  font-size: 11px;
  font-weight: 700;
  color: #64748b;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  padding: 14px 10px 8px;
  &:first-child { padding-top: 6px; }
}

.gate-item {
  padding: 16px 18px;
  margin-bottom: 10px;
  border-radius: 14px;
  border: 1px solid #eef2f6;
  background: #fff;
  cursor: pointer;
  transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);

  &:hover {
    border-color: rgba(24, 144, 255, 0.25);
    box-shadow: 0 4px 16px rgba(24, 144, 255, 0.08);
    transform: translateX(2px);
  }

  &.active {
    background: linear-gradient(135deg, #f0f7ff 0%, #fff 100%);
    border-color: rgba(24, 144, 255, 0.35);
    box-shadow:
      0 0 0 1px rgba(24, 144, 255, 0.12),
      0 6px 20px rgba(24, 144, 255, 0.1);
  }

  &.pending {
    border-left: none;
    box-shadow: inset 3px 0 0 #fdba74;
    background: linear-gradient(90deg, #fffdfb 0%, #fff 28%);
  }

  &.offline { opacity: 0.45; filter: grayscale(0.3); }

  &__row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    font-size: $cockpit-font-sm;
  }

  &__code {
    font-weight: 700;
    color: #1890ff;
    font-family: monospace;
    font-size: 13px;
  }

  &__name { color: #64748b; flex: 1; font-weight: 500; }
  &__flow {
    margin-left: auto;
    font-family: monospace;
    font-size: 12px;
    color: #94a3b8;
    padding: 2px 8px;
    background: #f8fafc;
    border-radius: 6px;
  }

  &__track {
    position: relative;
    height: 16px;
    background: linear-gradient(180deg, #eef2f6 0%, #e2e8f0 100%);
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 10px;
    box-shadow: inset 0 1px 3px rgba(15, 23, 42, 0.08);
  }

  &__fill {
    position: absolute;
    left: 0; top: 0; height: 100%;
    border-radius: 8px;
    transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 2;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.25);
  }

  &__ghost {
    position: absolute;
    left: 0; top: 0; height: 100%;
    background: linear-gradient(90deg, rgba(24, 144, 255, 0.12), rgba(24, 144, 255, 0.22));
    border-radius: 8px;
    z-index: 1;
  }

  &__nums {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 15px;
    font-family: monospace;
    font-weight: 700;
    color: #334155;
  }

  &__tgt { color: #ea580c; font-weight: 600; }

  &__switch {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }

  &__switch-label {
    font-size: 12px;
    font-weight: 600;
    font-family: inherit;
    color: #64748b;
    min-width: 1.2em;
  }
}

.detail-open-close {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 16px;
  padding: 14px 16px;
  border-radius: 12px;
  background: linear-gradient(135deg, #f8fafc 0%, #f0f7ff 100%);
  border: 1px solid #e2e8f0;

  &__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
  }

  &__info {
    display: flex;
    align-items: baseline;
    gap: 12px;
    min-width: 0;

    strong {
      font-size: 16px;
      &.is-open { color: #16a34a; }
      &.is-closed { color: #64748b; }
    }
  }
}

// ── 详情 ──
.detail-opening {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.detail-opening__block {
  padding: 16px 18px;
  background: #f8fafc;
  border-radius: 12px;
  border: 1px solid #eef2f6;

  &--target {
    background: linear-gradient(145deg, #fff 0%, #f0f7ff 100%);
    border-color: rgba(24, 144, 255, 0.2);
    box-shadow: 0 2px 12px rgba(24, 144, 255, 0.06);
  }

  &--sim {
    border-color: rgba(34, 197, 94, 0.25);
    background: linear-gradient(145deg, #fff 0%, #f0fdf4 100%);
    .detail-num { color: #16a34a; }
  }
}

.detail-target-ctrl {
  display: flex;
  align-items: center;
  gap: 8px;
}

.detail-target-btn {
  flex-shrink: 0;
  min-width: 58px;
  height: 40px;
  padding: 0 10px;
  font-size: 14px;
  font-weight: 600;
  border-radius: 10px;
}

.detail-target-input {
  flex: 1;
  min-width: 0;

  :deep(.el-input__wrapper) {
    border-radius: 10px;
    box-shadow: 0 0 0 1px #dbeafe inset;
  }

  :deep(.el-input__inner) {
    font-size: 22px;
    font-weight: 700;
    text-align: center;
    color: #1890ff;
  }
}

.detail-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: #94a3b8;
  margin-bottom: 8px;
  letter-spacing: 0.04em;
}

.detail-num {
  font-size: 36px;
  font-weight: 700;
  font-family: 'SF Mono', monospace;
  color: #1e293b;

  em { font-style: normal; font-size: 16px; color: #94a3b8; }
}

.detail-delta {
  text-align: center;
  margin: 0;
  padding: 8px 12px;
  background: #f8fafc;
  border-radius: 10px;
  font-size: $cockpit-font-sm;
  color: #64748b;

  strong { color: #1890ff; font-family: monospace; margin-left: 4px; font-size: 16px; }
}

.detail-impact {
  padding: 16px 18px;
  background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
  border-radius: 12px;
  border: 1px solid #eef2f6;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.detail-impact__row {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 8px 10px;
  background: #fff;
  border-radius: 8px;
  border: 1px solid #f1f5f9;

  span { font-size: 12px; font-weight: 600; color: #94a3b8; }
  strong {
    font-size: 19px;
    font-family: monospace;
    font-weight: 700;
    color: #1e293b;

    &.ok { color: #16a34a; }
    &.bad { color: #dc2626; }
  }
}

.detail-flow-tip {
  margin: 0;
  padding: 10px 12px;
  border-radius: 8px;
  background: #f8fafc;
  border: 1px solid #eef2f6;
  font-size: 13px;
  line-height: 1.55;
  color: #64748b;
}

.detail-btns {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-top: auto;
  padding-top: 12px;
  border-top: 1px solid #f1f5f9;

  :deep(.el-button--primary) {
    background: linear-gradient(135deg, #1890ff, #096dd9);
    border: none;
    box-shadow: 0 4px 12px rgba(24, 144, 255, 0.25);
  }
}

:deep(.el-slider__runway) {
  height: 8px;
  border-radius: 4px;
  background: #e2e8f0;
}

:deep(.el-slider__bar) {
  height: 8px;
  border-radius: 4px;
  background: linear-gradient(90deg, #1890ff, #69c0ff);
}

:deep(.el-slider__button) {
  width: 18px;
  height: 18px;
  border: 3px solid #1890ff;
  box-shadow: 0 2px 8px rgba(24, 144, 255, 0.35);
}

// ── 待提交 ──
.changes-table {
  &__head, &__row {
    display: grid;
    grid-template-columns: 1fr 80px 80px 64px;
    gap: 12px;
    padding: 12px 16px;
    font-size: $cockpit-font-sm;
  }

  &__head {
    font-weight: 600;
    color: #64748b;
    background: #f8fafc;
    border-radius: 10px 10px 0 0;
    border-bottom: 1px solid #eef2f6;
  }

  &__row {
    font-family: monospace;
    border-bottom: 1px solid #f5f5f5;
    transition: background 0.15s;

    &:hover { background: #fafbfc; }
    &:last-child { border-bottom: none; }
    span:first-child { font-family: inherit; font-weight: 600; color: #334155; }
  }
}
</style>

<style lang="scss">
.interlock-modal-box {
  max-width: 480px;
  padding-bottom: 16px;

  .el-message-box__header {
    padding-top: 20px;
  }

  .el-message-box__title {
    font-size: 18px;
    font-weight: 700;
  }

  .el-message-box__message {
    text-align: left;
  }

  .interlock-modal__item {
    margin: 0 0 14px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #f8fafc;
    border-left: 3px solid #faad14;
    line-height: 1.5;

    strong {
      display: block;
      margin-bottom: 4px;
      color: #1e293b;
    }

    span {
      font-size: 14px;
      color: #64748b;
    }

    &:last-child {
      margin-bottom: 0;
    }
  }

  &.el-message-box--error .interlock-modal__item {
    border-left-color: #ff7875;
    background: #fff2f0;
  }

  &--block .interlock-modal__lead {
    margin: 0 0 12px;
    font-size: 14px;
    color: #64748b;
  }
}

.interlock-confirm-box {
  max-width: 500px;

  .interlock-confirm__action {
    margin: 0 0 14px;
    font-size: 15px;
    line-height: 1.55;
    color: #1e293b;
  }

  .interlock-confirm__warns {
    padding-top: 12px;
    border-top: 1px solid #eef2f6;
  }

  .interlock-confirm__warn-title {
    margin: 0 0 10px;
    font-size: 13px;
    font-weight: 600;
    color: #b45309;
  }

  &--plain .interlock-confirm__action {
    margin-bottom: 0;
  }
}
</style>
