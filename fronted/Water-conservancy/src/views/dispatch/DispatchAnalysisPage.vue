<script setup lang="ts">
// ============================================================
// 调度决策 — 严格对照需求文档 §3.1~§3.12
// ============================================================
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ElButton, ElSelect, ElOption, ElInput, ElMessage, ElMessageBox,
  ElDialog, ElFormItem, ElTable, ElTableColumn, ElProgress, ElTag, ElPagination, ElCollapse, ElCollapseItem,
  ElTimeline, ElTimelineItem,
} from 'element-plus'
import { Refresh, View, Search } from '@element-plus/icons-vue'
import VChart from 'vue-echarts'
import { use } from 'echarts/core'
import { LineChart } from 'echarts/charts'
import { GridComponent, TooltipComponent, MarkLineComponent, LegendComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import GlassPanel3D from '@/components/cockpit/GlassPanel3D.vue'
import GateActionsPanel from './components/GateActionsPanel.vue'
import EmergencyStopPanel from './components/EmergencyStopPanel.vue'
import DecisionDetailDialog from './components/DecisionDetailDialog.vue'
import { XIANGJIABA_HYDRO } from '@/constants/xiangjiaba'
import {
  getConfidenceColor, FACTOR_DIRECTION_MAP,
} from '@/constants/dispatch'
import type { DispatchRecord, PhysicsValidation, PhysicsGuardSummary, PhysicsGuardHistoryItem, CommandTrace } from '@/types/dispatch'
import {
  fetchDispatchLogs,
  fetchPhysicsGuardSummary, fetchPhysicsGuardHistory, postPhysicsGuardRollback, fetchCommandTrace,
} from '@/api/dispatchPage'
import { useDispatchStore } from '@/stores/dispatch'
import { storeToRefs } from 'pinia'
import { useOperationLog } from '@/composables/useOperationLog'
import { fuzzyMatch } from '@/utils/fuzzyMatch'
import { buildSettingsPath } from '@/constants/settings'

use([LineChart, GridComponent, TooltipComponent, MarkLineComponent, LegendComponent, CanvasRenderer])

const dispatchStore = useDispatchStore()
const { status, decision, prediction } = storeToRefs(dispatchStore)
const route = useRoute()
const router = useRouter()
const { record: recordLog } = useOperationLog()

const predictTerm = ref<1 | 2 | 3>(2)
const metricKey = ref<'water' | 'flow'>('water')
const recordKeyword = ref('')
const appliedRecordKeyword = ref('')
const recordsLoading = ref(false)
const recordPageNum = ref(1)
const RECORD_PAGE_SIZE = 10
const expandedRecordId = ref<number | null>(null)
const records = ref<DispatchRecord[]>([])

const decisionDialogVisible = ref(false)
const metricsCollapse = ref(['prediction', 'decision', 'compliance'])

const physicsGuard = ref<PhysicsGuardSummary | null>(null)
const physicsHistory = ref<PhysicsGuardHistoryItem[]>([])
const historyVisible = ref(false)
const historyDetailVisible = ref(false)
const historyDetailRow = ref<PhysicsGuardHistoryItem | null>(null)

const lastCommandId = ref<string | null>(null)
const traceVisible = ref(false)
const traceLoading = ref(false)
const commandTrace = ref<CommandTrace | null>(null)

const SYNC_STATUS_MAP: Record<string, { label: string; type: 'success' | 'warning' | 'danger' }> = {
  synced: { label: '已同步', type: 'success' },
  stale: { label: '待同步', type: 'warning' },
  offline: { label: '离线缓存', type: 'danger' },
}

const physicsValidation = computed(() => decision.value?.physics_validation ?? null)

const DECISION_LEVEL_MAP: Record<string, string> = {
  L3_AUTO: 'L3 全自动',
  L2_SUGGEST: 'L2 建议模式',
  L1_MANUAL: 'L1 强制人工',
  OVERRIDE: '安全覆盖 OVERRIDE',
}

const RISK_LEVEL_MAP: Record<string, { label: string; color: string }> = {
  safe: { label: 'safe', color: '#22c55e' },
  warning: { label: 'warning', color: '#f59e0b' },
  danger: { label: 'danger', color: '#ef4444' },
  critical: { label: 'critical', color: '#dc2626' },
}

const INTERLOCK_RULE_MAP: Record<string, string> = {
  spillway_intake_mutex: '泄洪-发电互斥',
  downstream_impact_protect: '下游冲击保护',
  symmetry_constraint: '对称性约束',
  min_discharge_guarantee: '最小下泄保障',
}

const COMMAND_STATUS_MAP: Record<string, { label: string; type: 'success' | 'warning' | 'danger' | 'info' | 'primary' }> = {
  pending: { label: '待下发', type: 'warning' },
  sent: { label: '已发送', type: 'info' },
  acknowledged: { label: '边缘已确认', type: 'primary' },
  verified: { label: '校验通过', type: 'primary' },
  executed: { label: '已执行', type: 'success' },
  failed: { label: '失败', type: 'danger' },
}

const COMMAND_TYPE_MAP: Record<string, string> = {
  manual_adjust: '手动调开度',
  auto_dispatch: '自动调度',
  emergency_stop: '全局急停',
}

const traceSteps = computed(() => {
  const t = commandTrace.value
  if (!t) return []
  return [
    { key: 'sent', label: '云端下发', time: t.sent_at ?? t.created_at },
    { key: 'ack', label: '边缘确认', time: t.acknowledged_at },
    { key: 'verified', label: '校验通过', time: t.verified_at },
    { key: 'executed', label: 'PLC 执行', time: t.executed_at },
    { key: 'feedback', label: '执行回执', time: t.feedback_at },
  ]
})

function isTraceStepDone(stepKey: string, trace: CommandTrace) {
  const order = ['sent', 'ack', 'verified', 'executed', 'feedback']
  const statusIdx: Record<string, number> = {
    pending: 0,
    sent: 1,
    acknowledged: 2,
    verified: 3,
    executed: 4,
    failed: -1,
  }
  const current = statusIdx[trace.status] ?? 0
  const step = order.indexOf(stepKey)
  if (trace.status === 'failed') return step < current
  return step <= current && !!(
    stepKey === 'sent' ? (trace.sent_at || trace.created_at)
    : stepKey === 'ack' ? trace.acknowledged_at
    : stepKey === 'verified' ? trace.verified_at
    : stepKey === 'executed' ? trace.executed_at
    : trace.feedback_at
  )
}

async function openCommandTrace(commandId: string) {
  lastCommandId.value = commandId
  traceVisible.value = true
  traceLoading.value = true
  try {
    const res = await fetchCommandTrace(commandId)
    commandTrace.value = res.data
  } catch {
    commandTrace.value = null
    ElMessage.error('获取指令追踪失败')
  } finally {
    traceLoading.value = false
  }
}

async function refreshCommandTrace() {
  if (!lastCommandId.value) return
  await openCommandTrace(lastCommandId.value)
}

function contributionClass(val: number) {
  if (val > 0.005) return 'contrib-pos'
  if (val < -0.005) return 'contrib-neg'
  return 'contrib-neutral'
}

function formatContribution(val: number) {
  const sign = val > 0 ? '+' : ''
  return `${sign}${val.toFixed(2)}`
}

function overallContributionMeta(pv: PhysicsValidation) {
  const v = pv.contribution.overall
  if (v > 0.02) return { icon: '↑', text: '正面拖升模型评分', cls: 'contrib-pos' }
  if (v < -0.05) return { icon: '↓↓', text: '明显拖累模型评分', cls: 'contrib-neg' }
  if (v < 0) return { icon: '↓', text: '轻微拖累模型评分', cls: 'contrib-neg' }
  return { icon: '→', text: '对模型评分影响轻微', cls: 'contrib-neutral' }
}

function trendLabel(pv: PhysicsValidation) {
  if (pv.trend_direction === 'match') return '↑ 预测涨实涨'
  if (pv.trend_direction === 'mismatch') return '✗ 方向相反'
  return '待下周期回填'
}

function physicsCheckLabel(pv: PhysicsValidation) {
  const steps = pv.physics_correction_steps ?? 0
  return steps > 0 ? `✗ 修正 ${steps} 步` : '✓ 通过'
}

function physicsViolationLabel(pv: PhysicsValidation) {
  const v = pv.physics_violation_m
  const status = v > 0.5 ? '超过容差 0.5m' : '正常'
  return `${v.toFixed(2)}m（${status}）`
}

function interlockRecordLabel(pv: PhysicsValidation | null) {
  if (!pv?.interlock?.triggered) return '—'
  return pv.interlock.rules.map((r) => INTERLOCK_RULE_MAP[r] ?? r).join(' / ')
}

async function loadPhysicsGuard() {
  const [summary, history] = await Promise.all([
    fetchPhysicsGuardSummary(),
    fetchPhysicsGuardHistory(),
  ])
  physicsGuard.value = summary.data
  physicsHistory.value = history.data.list
}

function openHistoryDetail(row: PhysicsGuardHistoryItem) {
  historyDetailRow.value = row
  historyDetailVisible.value = true
}

async function handleRollback(row: PhysicsGuardHistoryItem) {
  if (row.is_active) return
  try {
    await ElMessageBox.confirm(
      `确认回滚至版本 ${row.config_version}？边缘端下一同步周期生效。`,
      '回滚物理防护配置',
      { type: 'warning' },
    )
    const res = await postPhysicsGuardRollback(row.id)
    physicsGuard.value = res.data
    await loadPhysicsGuard()
    recordLog('调度决策', '回滚物理防护配置', `版本 → ${res.data.config_version}`, 1)
    ElMessage.success(res.msg || `已回滚至 ${res.data.config_version}`)
    historyVisible.value = false
  } catch { /* cancel */ }
}

function goPhysicsGuardHistorySettings() {
  historyVisible.value = false
  router.push(buildSettingsPath('physics-guard-history', { reservoir_id: 1 }))
}

function interlockLabel(pv: PhysicsValidation) {
  if (!pv.interlock?.triggered) return '✓ 通过'
  const names = pv.interlock.rules.map((r) => INTERLOCK_RULE_MAP[r] ?? r).join(' · ')
  return `✗ ${names} · ${pv.interlock.reason}`
}

let pollTimer: ReturnType<typeof setInterval> | null = null
let executionRefreshTimer: ReturnType<typeof setTimeout> | null = null
const HIST_LEN = 12

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

const confidenceValue = computed(() => decision.value?.confidence ?? 0)
const confidenceColor = computed(() => getConfidenceColor(confidenceValue.value))

const filteredRecords = computed(() => {
  const kw = appliedRecordKeyword.value.trim()
  if (!kw) return records.value
  return records.value.filter((r) => fuzzyMatch(
    kw,
    r.decision_mode,
    r.decision_mode_label,
    r.action,
    r.operator_name,
    String(r.recommended_opening),
    `${r.recommended_opening}%`,
    r.execution_status,
    statusLabel(r.execution_status),
    formatTime(r.decision_time),
  ))
})

const recordTotal = computed(() => filteredRecords.value.length)

const pagedRecords = computed(() => {
  const start = (recordPageNum.value - 1) * RECORD_PAGE_SIZE
  return filteredRecords.value.slice(start, start + RECORD_PAGE_SIZE)
})

const chartOption = computed(() => {
  const data = prediction.value
  if (!data) return {}
  const seq = metricKey.value === 'water' ? data.water_seq : data.flow_seq
  const isWater = metricKey.value === 'water'
  const histLen = seq.length > HIST_LEN ? HIST_LEN : 1
  const times = seq.map((p) => {
    const d = new Date(p.time)
    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
  })
  const histValues = seq.slice(0, histLen).map((p) => p.value)
  const predValues = seq.slice(histLen - 1).map((p) => p.value)
  const histData = [...histValues, ...Array(seq.length - histLen).fill(null)]
  const predData = [...Array(histLen - 1).fill(null), ...predValues]
  const nowLabel = times[histLen - 1] ?? ''
  const warnLevel = XIANGJIABA_HYDRO.warningLevel

  return {
    animation: false,
    backgroundColor: 'transparent',
    legend: { data: ['实测', '预测'], top: 0, right: 0, textStyle: { color: '#64748b', fontSize: 11 } },
    grid: { top: 36, right: 48, bottom: 48, left: 58, containLabel: true },
    tooltip: {
      trigger: 'axis',
      formatter: (params: { seriesName: string; value: number; axisValue: string }[]) => {
        const p = params.find((x) => x.value != null)
        if (!p) return ''
        const unit = isWater ? ' m' : ' m³/s'
        return `${p.axisValue}<br/>${p.seriesName}: ${isWater ? p.value.toFixed(2) : p.value}${unit}`
      },
    },
    xAxis: {
      type: 'category', boundaryGap: false, data: times,
      axisLine: { lineStyle: { color: '#cbd5e1' } },
      axisLabel: { color: '#64748b', fontSize: 11, interval: Math.max(0, Math.floor(times.length / 8)) },
    },
    yAxis: {
      type: 'value', scale: true,
      axisLine: { lineStyle: { color: '#cbd5e1' } },
      axisLabel: {
        color: '#64748b', fontSize: 11,
        formatter: (v: number) => isWater ? v.toFixed(1) : String(Math.round(v)),
      },
      splitLine: { lineStyle: { color: '#e2e8f0', type: 'dashed' } },
    },
    series: [
      {
        name: '实测', type: 'line', data: histData, smooth: true,
        lineStyle: { color: '#334155', width: 2 },
        itemStyle: { color: '#334155' }, showSymbol: false,
      },
      {
        name: '预测', type: 'line', data: predData, smooth: true,
        lineStyle: { color: '#1890ff', width: 2, type: 'dashed' },
        itemStyle: { color: '#1890ff' }, showSymbol: false,
        markLine: isWater ? {
          silent: true,
          symbol: 'none',
          data: [
            { xAxis: nowLabel, lineStyle: { color: '#f59e0b', type: 'solid', width: 1 }, label: { formatter: '当前', color: '#f59e0b', fontSize: 10 } },
            { yAxis: warnLevel, lineStyle: { color: '#ef4444', type: 'dashed', width: 1 }, label: { formatter: '预警', color: '#ef4444', fontSize: 10 } },
          ],
        } : {
          silent: true, symbol: 'none',
          data: [{ xAxis: nowLabel, lineStyle: { color: '#f59e0b' }, label: { formatter: '当前', fontSize: 10 } }],
        },
      },
    ],
  }
})

function formatTime(iso: string | null) {
  if (!iso) return '—'
  return iso.replace('T', ' ').substring(0, 19)
}

function statusLabel(s: string) {
  const map: Record<string, string> = {
    executed: '成功', failed: '失败', rejected: '已取消', pending: '待执行',
  }
  return map[s] ?? s
}

function statusTagType(s: string) {
  if (s === 'executed') return 'success'
  if (s === 'failed') return 'danger'
  if (s === 'rejected') return 'info'
  return 'warning'
}

async function queryRecords() {
  recordsLoading.value = true
  recordPageNum.value = 1
  expandedRecordId.value = null
  appliedRecordKeyword.value = recordKeyword.value.trim()
  try {
    const logs = await fetchDispatchLogs(appliedRecordKeyword.value || undefined)
    records.value = logs.data.list
  } catch {
    ElMessage.warning('调度记录查询失败，请稍后重试')
  } finally {
    recordsLoading.value = false
  }
}

async function resetRecordSearch() {
  recordKeyword.value = ''
  appliedRecordKeyword.value = ''
  recordPageNum.value = 1
  expandedRecordId.value = null
  recordsLoading.value = true
  try {
    const logs = await fetchDispatchLogs()
    records.value = logs.data.list
  } catch {
    ElMessage.warning('调度记录加载失败')
  } finally {
    recordsLoading.value = false
  }
}

async function refreshAll() {
  await dispatchStore.ensureCoreLoaded(predictTerm.value)
  try {
    const logs = await fetchDispatchLogs(appliedRecordKeyword.value || undefined)
    records.value = logs.data.list
  } catch {
    /* records optional on poll */
  }
  await loadPhysicsGuard()
  if (status.value.isExecuting && !executionRefreshTimer) {
    executionRefreshTimer = setTimeout(async () => {
      executionRefreshTimer = null
      await dispatchStore.refreshCore(predictTerm.value)
      await refreshRecordsOnly()
    }, 4200)
  }
}

async function refreshRecordsOnly() {
  try {
    const logs = await fetchDispatchLogs(appliedRecordKeyword.value || undefined)
    records.value = logs.data.list
  } catch { /* optional */ }
}

async function pollAnalysis() {
  if (document.hidden) return
  await dispatchStore.refreshGates({ silent: true })
  await refreshRecordsOnly()
}

function toggleRecordExpand(id: number) {
  expandedRecordId.value = expandedRecordId.value === id ? null : id
}

watch(predictTerm, async () => {
  await dispatchStore.refreshCore(predictTerm.value)
  await refreshRecordsOnly()
})
watch(recordPageNum, () => { expandedRecordId.value = null })

function applyRecordQuery() {
  const id = Number(route.query.recordId)
  if (id > 0) expandedRecordId.value = id
}

watch(() => route.query.recordId, applyRecordQuery)

watch(() => route.query.openDetail, (v) => {
  if (v === '1') decisionDialogVisible.value = true
})

onMounted(async () => {
  await refreshAll()
  dispatchStore.applySavedMode()
  applyRecordQuery()
  if (route.query.openDetail === '1') decisionDialogVisible.value = true
  pollTimer = setInterval(pollAnalysis, 20000)
})
onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
  if (executionRefreshTimer) clearTimeout(executionRefreshTimer)
})
</script>

<template>
  <div class="page dispatch-page dispatch-analysis-page">
    <GlassPanel3D v-if="decision" title="调度建议摘要" class="panel-decision-summary">
      <div class="decision-summary">
        <div class="decision-action">
          <span class="lbl">推荐动作</span>
          <p class="action-val">阀门开至 <strong>{{ decision.recommended_opening }}%</strong> <em>{{ openingDirection }}</em></p>
          <span class="lbl">预期效果</span>
          <p class="effect-val">{{ expectedEffect }}</p>
        </div>
        <div class="decision-conf">
          <div class="conf-head">
            <span>置信度</span>
            <strong :style="{ color: confidenceColor }">{{ confidenceValue }}%</strong>
          </div>
          <ElProgress :percentage="confidenceValue" :color="confidenceColor" :stroke-width="10" :show-text="false" />
        </div>
      </div>
      <p class="decision-readonly-hint">本页为只读分析；采纳、忽略与执行请前往「运行控制」。</p>
      <div class="decision-btns">
        <ElButton type="primary" :icon="View" @click="decisionDialogVisible = true">查看详情</ElButton>
        <ElButton type="primary" plain @click="router.push('/dispatch/control')">前往运行控制执行 →</ElButton>
      </div>
    </GlassPanel3D>

    <!-- §3.6 LSTM 预测图 -->
    <GlassPanel3D title="LSTM 预测" class="panel-chart">
      <div class="chart-toolbar">
        <ElSelect v-model="predictTerm" size="small" style="width:72px">
          <ElOption :value="1" label="1h" /><ElOption :value="2" label="3h" /><ElOption :value="3" label="6h" />
        </ElSelect>
        <ElSelect v-model="metricKey" size="small" style="width:80px">
          <ElOption label="水位" value="water" /><ElOption label="流量" value="flow" />
        </ElSelect>
        <ElButton :icon="Refresh" circle size="small" @click="refreshAll" />
        <span v-if="prediction" class="chart-meta">
          准确率 {{ prediction.predict_accuracy }}% · 更新 {{ formatTime(prediction.created_at) }}
        </span>
      </div>
      <VChart class="lstm-chart" :option="chartOption" autoresize />
    </GlassPanel3D>

    <!-- 物理防护配置摘要（文档 §2.5） -->
    <GlassPanel3D title="物理防护配置" class="panel-physics-guard">
      <template v-if="physicsGuard">
        <div class="physics-guard-bar">
          <ElTag type="info" effect="plain">v{{ physicsGuard.config_version }}</ElTag>
          <ElTag :type="SYNC_STATUS_MAP[physicsGuard.sync_status]?.type ?? 'info'" size="small">
            {{ SYNC_STATUS_MAP[physicsGuard.sync_status]?.label ?? physicsGuard.sync_status }}
          </ElTag>
          <span>紧急 <strong>{{ physicsGuard.upstream_emergency }} m</strong></span>
          <span>危险 <strong>{{ physicsGuard.upstream_danger }} m</strong></span>
          <span>预警 <strong>{{ physicsGuard.upstream_warning }} m</strong></span>
          <span>L3 置信度 <strong>{{ physicsGuard.fusion_l3_confidence }}</strong></span>
          <span v-if="physicsGuard.last_sync_at" class="physics-sync">
            同步 {{ formatTime(physicsGuard.last_sync_at) }}
          </span>
          <ElButton size="small" @click="historyVisible = true">变更历史</ElButton>
        </div>
      </template>
    </GlassPanel3D>

    <!-- §3.8 调度记录 — 底部全宽 -->
    <GlassPanel3D title="调度记录" class="panel-records">
      <div class="record-search-bar">
        <ElInput
          v-model="recordKeyword"
          placeholder="搜索模式、动作、操作人、结果..."
          clearable
          class="record-search"
          @keyup.enter="queryRecords"
        />
        <ElButton type="primary" :icon="Search" :loading="recordsLoading" @click="queryRecords">
          查询
        </ElButton>
        <ElButton :loading="recordsLoading" @click="resetRecordSearch">重置</ElButton>
      </div>
      <div class="record-table-wrap">
        <div class="record-head">
          <span>时间</span><span>模式</span><span>动作</span><span>目标开度</span><span>互锁</span><span>结果</span><span>操作人</span>
        </div>
        <template v-for="row in pagedRecords" :key="row.id">
          <div class="record-row" @click="toggleRecordExpand(row.id)">
            <span class="mono">{{ formatTime(row.decision_time) }}</span>
            <span>{{ row.decision_mode_label ?? row.decision_mode }}</span>
            <span>{{ row.action ?? '—' }}</span>
            <span>{{ row.recommended_opening }}%</span>
            <span class="record-interlock">
              <ElTag
                v-if="row.physics_validation?.interlock?.triggered"
                type="warning"
                size="small"
                effect="plain"
              >
                {{ interlockRecordLabel(row.physics_validation) }}
              </ElTag>
              <span v-else class="record-interlock--ok">—</span>
            </span>
            <span><ElTag :type="statusTagType(row.execution_status)" size="default">{{ statusLabel(row.execution_status) }}</ElTag></span>
            <span>{{ row.operator_name ?? '—' }}</span>
          </div>
          <div v-if="expandedRecordId === row.id && row.snapshot" class="record-expand">
            <p><strong>决策快照</strong> · 置信度 {{ row.snapshot.confidence }}% · 推荐开度 {{ row.snapshot.recommended_opening }}%</p>
            <div v-if="row.physics_validation" class="snap-validation">
              <span>推理指标</span>
              <span>等级 {{ DECISION_LEVEL_MAP[row.physics_validation.decision_level] ?? row.physics_validation.decision_level }}</span>
              <span>综合贡献 {{ formatContribution(row.physics_validation.contribution.overall) }}</span>
              <ElTag
                v-if="row.physics_validation.interlock?.triggered"
                type="warning"
                size="small"
                effect="plain"
              >
                互锁 · {{ row.physics_validation.interlock.rules.map((r) => INTERLOCK_RULE_MAP[r] ?? r).join(' / ') }}
              </ElTag>
              <ElTag v-else type="success" size="small" effect="plain">互锁通过</ElTag>
            </div>
            <div class="snap-factors">
              <span v-for="f in row.snapshot.factors" :key="f.name">{{ f.name }} {{ f.value }} ({{ (f.weight * 100).toFixed(0) }}%)</span>
            </div>
            <div class="snap-plans">
              <span v-for="p in row.snapshot.plans" :key="p.id" :class="{ rec: p.recommended }">{{ p.id }} {{ p.opening }}% 得分{{ p.totalScore }}</span>
            </div>
          </div>
        </template>
        <div v-if="filteredRecords.length === 0" class="record-empty">暂无匹配记录</div>
      </div>
      <div v-if="recordTotal > 0" class="record-footer">
        <ElPagination
          v-model:current-page="recordPageNum"
          :page-size="RECORD_PAGE_SIZE"
          :total="recordTotal"
          layout="total, prev, pager, next"
          background
          class="record-pagination"
        />
      </div>
    </GlassPanel3D>

    <GlassPanel3D title="阀门动作历史" class="panel-gate-actions">
      <GateActionsPanel />
    </GlassPanel3D>

    <GlassPanel3D title="急停日志" class="panel-estop-logs">
      <EmergencyStopPanel />
    </GlassPanel3D>

    <DecisionDetailDialog
      v-model="decisionDialogVisible"
      :decision="decision"
      footer-mode="analysis"
      @go-control="router.push('/dispatch/control')"
    />

    <ElDialog v-model="traceVisible" title="指令全链路追踪" width="640px">
      <div v-loading="traceLoading">
        <template v-if="commandTrace">
          <div class="trace-head">
            <p><strong>{{ commandTrace.command_id }}</strong></p>
            <p class="trace-meta">
              <ElTag :type="COMMAND_STATUS_MAP[commandTrace.status]?.type ?? 'info'" size="small">
                {{ COMMAND_STATUS_MAP[commandTrace.status]?.label ?? commandTrace.status }}
              </ElTag>
              · {{ COMMAND_TYPE_MAP[commandTrace.command_type] ?? commandTrace.command_type }}
              · 目标开度 {{ commandTrace.target_opening }}%
              <span v-if="commandTrace.full_delay_ms != null"> · 全链路 {{ commandTrace.full_delay_ms }}ms</span>
            </p>
            <p class="trace-meta muted">trace: {{ commandTrace.trace_id }}</p>
          </div>
          <ElTimeline>
            <ElTimelineItem
              v-for="step in traceSteps"
              :key="step.key"
              :type="isTraceStepDone(step.key, commandTrace) ? 'success' : 'info'"
              :hollow="!isTraceStepDone(step.key, commandTrace)"
              :timestamp="step.time ? formatTime(step.time) : '—'"
            >
              {{ step.label }}
            </ElTimelineItem>
          </ElTimeline>
          <p v-if="commandTrace.reject_reason" class="trace-reject">驳回原因：{{ commandTrace.reject_reason }}</p>
        </template>
        <p v-else-if="!traceLoading" class="trace-empty">暂无追踪数据</p>
      </div>
      <template #footer>
        <ElButton @click="traceVisible = false">关闭</ElButton>
        <ElButton type="primary" :loading="traceLoading" @click="refreshCommandTrace">刷新状态</ElButton>
      </template>
    </ElDialog>

    <!-- 物理防护配置变更历史（当前水库快捷查看） -->
    <ElDialog
      v-model="historyVisible"
      title="物理防护配置变更历史"
      width="900px"
      class="dispatch-history-dialog"
    >
      <p class="history-dialog-hint">当前水库的配置版本记录，可在此查看详情或回滚。</p>
      <ElTable :data="physicsHistory" border stripe>
        <ElTableColumn prop="config_version" label="版本号" width="96" />
        <ElTableColumn prop="changed_at" label="变更时间" width="168">
          <template #default="{ row }">{{ formatTime(row.changed_at) }}</template>
        </ElTableColumn>
        <ElTableColumn prop="changed_by_name" label="变更人" width="100" />
        <ElTableColumn prop="description" label="变更说明" min-width="200" show-overflow-tooltip />
        <ElTableColumn label="状态" width="88" align="center">
          <template #default="{ row }">
            <ElTag :type="row.is_active ? 'success' : 'info'">{{ row.is_active ? '生效中' : '历史' }}</ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn label="操作" width="140" align="center" fixed="right">
          <template #default="scope">
            <ElButton link type="primary" @click="openHistoryDetail(scope.row as PhysicsGuardHistoryItem)">详情</ElButton>
            <ElButton v-if="!(scope.row as PhysicsGuardHistoryItem).is_active" link type="warning" @click="handleRollback(scope.row as PhysicsGuardHistoryItem)">回滚</ElButton>
          </template>
        </ElTableColumn>
      </ElTable>
      <template #footer>
        <div class="history-dialog-footer">
          <span class="history-dialog-footer-hint">需切换水库或编辑配置？</span>
          <ElButton link type="primary" @click="goPhysicsGuardHistorySettings">前往设置页管理 →</ElButton>
        </div>
      </template>
    </ElDialog>

    <ElDialog
      v-model="historyDetailVisible"
      title="配置变更详情"
      width="600px"
      class="dispatch-history-dialog"
    >
      <template v-if="historyDetailRow">
        <p class="history-detail-meta">
          <strong>v{{ historyDetailRow.config_version }}</strong>
          · {{ historyDetailRow.changed_by_name }}
          · {{ formatTime(historyDetailRow.changed_at) }}
        </p>
        <p class="history-detail-desc">{{ historyDetailRow.description }}</p>
        <ElTable :data="historyDetailRow.changes" border style="margin-top: 12px">
          <ElTableColumn prop="label" label="字段" width="120" />
          <ElTableColumn prop="before" label="变更前" width="100" />
          <ElTableColumn prop="after" label="变更后" width="100" />
        </ElTable>
      </template>
    </ElDialog>
  </div>
</template>

<style scoped lang="scss">
@use '@/assets/styles/cockpit.scss' as *;
@use './dispatch-shared.scss';

.dispatch-page {
  @include cockpit-page-white;
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 0 16px 16px;
  font-size: 15px;
}

.panel-decision-summary {
  .decision-summary {
    display: grid;
    grid-template-columns: 1fr 200px;
    gap: 16px;
    margin-bottom: 14px;
    @media (max-width: 700px) { grid-template-columns: 1fr; }
  }
}

.chart-toolbar {
  display: flex; align-items: center; gap: 10px; margin-bottom: 10px; flex-wrap: wrap;
}
.chart-meta { margin-left: auto; font-size: 12px; color: $cockpit-text-dim; }
.lstm-chart { width: 100%; height: 340px; }

.level-card {
  padding: 14px 16px; margin-bottom: 10px; border: 1px solid #e2e8f0; border-radius: 8px;
  cursor: pointer; background: #fff; transition: border-color 0.2s;
  strong { display: block; font-size: 16px; margin-bottom: 6px; }
  p { margin: 0; font-size: 14px; color: $cockpit-text-dim; line-height: 1.55; }
  &.active { border-color: var(--lv-color); background: #f8fafc; box-shadow: inset 3px 0 0 var(--lv-color); }
}
.readonly-tip, .manual-tip { font-size: 13px; color: $cockpit-text-dim; text-align: center; margin-top: 8px; }

.manual-row {
  display: flex; align-items: center; gap: 8px; margin-bottom: 12px; font-size: 15px; flex-wrap: wrap;
  .unit { font-size: 16px; font-weight: 600; color: #1890ff; }
  .delta { font-size: 14px; color: #64748b; margin-left: auto; }
}
.manual-input {
  width: 120px;
  :deep(.el-input__inner) { font-size: 18px; font-weight: 600; text-align: center; }
}
.manual-exec-banner {
  margin-bottom: 10px; padding: 10px 12px; border-radius: 8px;
  background: #fffbeb; border: 1px solid #fcd34d; font-size: 14px; color: #92400e;
  strong { color: #b45309; font-size: 18px; }
}
.manual-hint {
  margin: 8px 0 0; font-size: 13px; color: #64748b; text-align: center;
}
.manual-actions {
  display: flex; gap: 10px; margin-top: 14px; flex-wrap: wrap;
}
.manual-action-btn { flex: 1; min-width: 0; height: 40px; font-size: 15px; }
.manual-exec { margin-top: 0; height: 40px; font-size: 15px; }
.trace-link {
  margin: 12px 0 0; font-size: 13px; color: #64748b;
  code { font-size: 12px; color: #334155; margin-right: 8px; }
}
.trace-head { margin-bottom: 16px; }
.trace-meta { margin: 6px 0 0; font-size: 13px; color: #475569; }
.trace-meta.muted { color: #94a3b8; }
.trace-reject { margin-top: 12px; color: #dc2626; font-size: 13px; }
.trace-empty { color: #94a3b8; text-align: center; padding: 24px 0; }

.panel-gate-actions { flex-shrink: 0; margin-top: 12px; }
.panel-estop-logs { flex-shrink: 0; margin-top: 12px; }
.panel-records { flex: 1; min-height: 320px; }
.record-search-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 14px;
  flex-wrap: wrap;
}
.record-search {
  flex: 1;
  min-width: 220px;
  max-width: 420px;
}
.record-table-wrap { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
.record-head, .record-row {
  display: grid;
  grid-template-columns: 168px 72px 1fr 100px 120px 100px 100px;
  gap: 16px; padding: 16px 20px; font-size: 16px; align-items: center;
  @media (max-width: 900px) {
    grid-template-columns: 140px 60px 1fr 80px 100px 88px 80px;
    font-size: 14px; gap: 10px; padding: 12px 14px;
  }
}
.record-interlock {
  font-size: 13px;
  &--ok { color: #94a3b8; }
}
.physics-guard-bar {
  display: flex; flex-wrap: wrap; align-items: center; gap: 12px 16px;
  font-size: 14px; color: #475569;
  strong { color: #1e293b; font-family: 'SF Mono', monospace; }
  .physics-sync { margin-left: auto; font-size: 13px; color: #64748b; }
  @media (max-width: 900px) { .physics-sync { margin-left: 0; width: 100%; } }
}
.panel-physics-guard { flex-shrink: 0; }
.history-detail-meta { margin: 0 0 10px; font-size: 16px; color: #334155; }
.history-detail-desc { margin: 0; font-size: 15px; line-height: 1.6; color: #475569; }
.history-dialog-hint { margin: 0 0 14px; font-size: 15px; color: #64748b; line-height: 1.5; }
.history-dialog-footer {
  display: flex; align-items: center; justify-content: flex-end; gap: 8px; width: 100%;
  .history-dialog-footer-hint { font-size: 14px; color: #94a3b8; }
}
.record-head {
  color: $cockpit-accent; font-weight: 600; font-size: 15px;
  border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; background: #f8fafc; z-index: 1;
}
.record-row { border-bottom: 1px solid #f1f5f9; cursor: pointer; background: #fff; }
.record-expand {
  padding: 14px 18px 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 14px;
  .snap-validation {
    display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin: 10px 0;
    font-size: 13px; color: #475569;
    > span:first-child { font-weight: 600; color: #334155; }
  }
  .snap-factors, .snap-plans { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; color: #475569; }
  .rec { color: #16a34a; font-weight: 600; }
}
.record-empty { text-align: center; padding: 32px; color: $cockpit-text-dim; font-size: 15px; }
.record-footer {
  display: flex; justify-content: flex-end; margin-top: 16px;
}
.record-pagination {
  :deep(.el-pagination__total) { font-size: 15px; color: #475569; }
  :deep(.btn-prev), :deep(.btn-next), :deep(.el-pager li) {
    min-width: 36px; height: 36px; line-height: 36px; font-size: 15px;
  }
}
.mono { font-family: 'SF Mono', monospace; font-size: 14px; }

.detail-block {
  margin-bottom: 24px;
  h4 {
    font-size: 18px; color: #1e293b; margin: 0 0 14px; padding-left: 12px;
    border-left: 4px solid #1890ff; font-weight: 600;
  }
  p { margin: 0; color: #475569; font-size: 16px; line-height: 1.65; }
}
.detail-summary strong { font-size: 22px; color: #1890ff; }
.factor-dir { font-size: 20px; font-weight: 600; }
.detail-meta { margin-top: 12px; font-size: 14px; color: $cockpit-text-dim; }
.detail-conf .conf-warn { font-size: 15px; }
.sub { color: #64748b; font-size: 14px; }

.inference-metrics {
  :deep(.el-collapse-item__header) {
    font-size: 15px; font-weight: 600; color: #334155;
  }
  :deep(.el-collapse-item__content) { padding-bottom: 8px; }
}
.metric-dl {
  margin: 0; padding: 0;
  &__row {
    display: grid; grid-template-columns: 120px 1fr; gap: 12px;
    padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 15px;
    &:last-child { border-bottom: none; }
  }
  dt { color: #64748b; }
  dd { margin: 0; color: #334155; font-weight: 500; }
  .is-abnormal { color: #dc2626; }
}
.contrib-pos { color: #16a34a !important; }
.contrib-neg { color: #dc2626 !important; }
.contrib-neutral { color: #64748b !important; }
.inference-overall {
  margin-top: 16px; padding: 14px 16px; border-radius: 8px;
  font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 10px;
  background: #f8fafc; border: 1px solid #e2e8f0;
  &.contrib-pos { background: #f0fdf4; border-color: #bbf7d0; }
  &.contrib-neg { background: #fef2f2; border-color: #fecaca; }
  &__icon { font-size: 18px; }
}

.decision-detail-dialog {
  :deep(.el-dialog) { --el-bg-color: #ffffff; max-width: calc(100vw - 48px); }
  :deep(.el-dialog__header) { padding: 20px 28px 12px; margin-right: 0; }
  :deep(.el-dialog__title) { font-size: 22px; font-weight: 700; color: #1e293b; }
  :deep(.el-dialog__body) { padding: 8px 28px 24px; max-height: calc(100vh - 180px); overflow-y: auto; }
  :deep(.el-dialog__footer) { padding: 16px 28px 24px; }
  :deep(.el-dialog__footer .el-button) { min-width: 108px; font-size: 15px; }
  :deep(.detail-table) { font-size: 16px; }
  :deep(.detail-table th.el-table__cell) {
    font-size: 16px; font-weight: 600; color: #334155; padding: 16px 0; background: #f8fafc;
  }
  :deep(.detail-table td.el-table__cell) { padding: 14px 0; font-size: 16px; color: #1e293b; }
  :deep(.el-progress__text) { font-size: 16px !important; font-weight: 600; }
}

.dispatch-history-dialog {
  :deep(.el-dialog__title) { font-size: 18px; font-weight: 600; }
  :deep(.el-dialog__body) { font-size: 16px; line-height: 1.6; }
  :deep(.el-table) { font-size: 15px; }
  :deep(.el-table th.el-table__cell) {
    font-size: 15px; font-weight: 600; padding: 14px 0; background: #f8fafc;
  }
  :deep(.el-table td.el-table__cell) { padding: 12px 0; font-size: 15px; }
  :deep(.el-button.is-link) { font-size: 15px; }
  :deep(.el-tag) { font-size: 14px; }
}

:deep(.row-rec) { background: #f0fdf4 !important; }
:deep(.el-slider__bar) { background: #1890ff; }
</style>
