<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  ElDialog, ElButton, ElTable, ElTableColumn, ElProgress, ElCollapse, ElCollapseItem,
} from 'element-plus'
import type { DecisionDetail, PhysicsValidation } from '@/types/dispatch'
import {
  FACTOR_DIRECTION_MAP, getConfidenceColor,
} from '@/constants/dispatch'

const props = withDefaults(defineProps<{
  modelValue: boolean
  decision: DecisionDetail | null
  /** control = 采用建议；analysis = 前往运行控制 */
  footerMode?: 'control' | 'analysis'
}>(), {
  footerMode: 'control',
})

const emit = defineEmits<{
  'update:modelValue': [boolean]
  apply: []
  goControl: []
}>()

const visible = computed({
  get: () => props.modelValue,
  set: (v: boolean) => emit('update:modelValue', v),
})

const metricsCollapse = ref(['prediction', 'decision', 'compliance'])
watch(() => props.modelValue, (open) => {
  if (open) metricsCollapse.value = ['prediction', 'decision', 'compliance']
})

const FACTOR_NAME_MAP: Record<string, string> = {
  water_level: '上游水位',
  flow_rate: '入库流量',
  power_demand: '发电需求',
  upstream_level: '上游水位',
  inflow: '入库流量',
}

const DECISION_LEVEL_MAP: Record<string, string> = {
  L3_AUTO: 'L3 全自动',
  L2_SUGGEST: 'L2 建议模式',
  L1_MANUAL: 'L1 强制人工',
  OVERRIDE: '安全覆盖 OVERRIDE',
}

const RISK_LEVEL_MAP: Record<string, { color: string }> = {
  safe: { color: '#22c55e' },
  warning: { color: '#f59e0b' },
  danger: { color: '#ef4444' },
  critical: { color: '#dc2626' },
}

const INTERLOCK_RULE_MAP: Record<string, string> = {
  spillway_intake_mutex: '泄洪-发电互斥',
  downstream_impact_protect: '下游冲击保护',
  symmetry_constraint: '对称性约束',
  min_discharge_guarantee: '最小下泄保障',
}

const physicsValidation = computed(() => props.decision?.physics_validation ?? null)

const recommendedPlan = computed(() =>
  props.decision?.alternatives?.find((p) => p.recommended)
  ?? props.decision?.alternatives?.[0]
  ?? null,
)

const expectedEffect = computed(() => {
  const p = recommendedPlan.value
  if (!p) return '—'
  return `上游水位预计 ${p.expectedLevel} m · 发电 ${p.power} kW · 安全评分 ${p.safetyScore}`
})

const confidenceValue = computed(() => props.decision?.confidence ?? 0)
const confidenceColor = computed(() => getConfidenceColor(confidenceValue.value))

const displayFactors = computed(() =>
  (props.decision?.factors ?? []).map((f) => ({
    ...f,
    name: FACTOR_NAME_MAP[f.name] ?? f.name,
    value: f.value === '' || f.value == null ? '—' : f.value,
  })),
)

function formatTime(iso: string | null | undefined) {
  if (!iso) return '—'
  return iso.replace('T', ' ').substring(0, 19)
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

function interlockLabel(pv: PhysicsValidation) {
  if (!pv.interlock?.triggered) return '✓ 通过'
  const names = pv.interlock.rules.map((r) => INTERLOCK_RULE_MAP[r] ?? r).join(' · ')
  return `✗ ${names} · ${pv.interlock.reason}`
}

function onApply() {
  emit('apply')
}

function onGoControl() {
  visible.value = false
  emit('goControl')
}
</script>

<template>
  <ElDialog
    v-model="visible"
    title="调度决策详情"
    width="1200px"
    top="3vh"
    class="decision-detail-dialog"
    destroy-on-close
    append-to-body
  >
    <template v-if="decision">
      <div class="detail-block">
        <h4>推荐动作与预期效果</h4>
        <p class="detail-summary">
          开度 <strong>{{ decision.recommended_opening }}%</strong>
          （当前 {{ decision.current_opening }}%）· {{ expectedEffect }}
        </p>
      </div>

      <div class="detail-block">
        <h4>影响因素</h4>
        <ElTable :data="displayFactors" border stripe class="detail-table">
          <ElTableColumn prop="name" label="名称" width="180" />
          <ElTableColumn prop="value" label="当前值" min-width="160" />
          <ElTableColumn label="方向" width="90" align="center">
            <template #default="{ row }">
              <span class="factor-dir" :style="{ color: FACTOR_DIRECTION_MAP[row.direction]?.color }">
                {{ FACTOR_DIRECTION_MAP[row.direction]?.icon }}
              </span>
            </template>
          </ElTableColumn>
          <ElTableColumn label="权重" width="100" align="right">
            <template #default="{ row }">{{ (row.weight * 100).toFixed(0) }}%</template>
          </ElTableColumn>
        </ElTable>
      </div>

      <div class="detail-block">
        <h4>方案对比</h4>
        <ElTable
          :data="decision.alternatives"
          border
          stripe
          class="detail-table"
          :row-class-name="({ row }) => row.recommended ? 'row-rec' : ''"
        >
          <ElTableColumn prop="id" label="方案" width="120" />
          <ElTableColumn prop="opening" label="目标开度(%)" width="130" align="right" />
          <ElTableColumn prop="expectedLevel" label="预期水位(m)" width="140" align="right" />
          <ElTableColumn prop="power" label="发电量(kW)" width="130" align="right" />
          <ElTableColumn prop="safetyScore" label="安全评分" width="110" align="right" />
          <ElTableColumn prop="totalScore" label="综合得分" width="110" align="right" />
          <ElTableColumn label="推荐" width="90" align="center">
            <template #default="{ row }">{{ row.recommended ? '是' : '—' }}</template>
          </ElTableColumn>
        </ElTable>
      </div>

      <div class="detail-block detail-conf">
        <h4>置信度</h4>
        <ElProgress :percentage="confidenceValue" :color="confidenceColor" :stroke-width="20" />
        <p v-if="confidenceValue < 60" class="conf-warn">建议人工复核后再执行</p>
        <p class="detail-meta">trace: {{ decision.trace_id }} · 决策时间 {{ formatTime(decision.decision_time) }}</p>
      </div>

      <div v-if="physicsValidation" class="detail-block inference-metrics">
        <h4>本次推理指标</h4>
        <ElCollapse v-model="metricsCollapse">
          <ElCollapseItem title="预测准确性（LSTM 这一步的表现）" name="prediction">
            <dl class="metric-dl">
              <div class="metric-dl__row">
                <dt>物理校验</dt>
                <dd :class="{ 'is-abnormal': (physicsValidation.physics_correction_steps ?? 0) > 0 }">
                  {{ physicsCheckLabel(physicsValidation) }}
                </dd>
              </div>
              <div class="metric-dl__row">
                <dt>物理偏差</dt>
                <dd :class="{ 'is-abnormal': physicsValidation.physics_violation_m > 0.5 }">
                  {{ physicsViolationLabel(physicsValidation) }}
                </dd>
              </div>
              <div class="metric-dl__row">
                <dt>趋势方向</dt>
                <dd :class="{ 'is-abnormal': physicsValidation.trend_direction === 'mismatch' }">
                  {{ trendLabel(physicsValidation) }}
                </dd>
              </div>
              <div class="metric-dl__row">
                <dt>贡献</dt>
                <dd :class="contributionClass(physicsValidation.contribution.prediction)">
                  {{ formatContribution(physicsValidation.contribution.prediction) }}（Prediction_Score）
                </dd>
              </div>
            </dl>
          </ElCollapseItem>

          <ElCollapseItem title="决策可靠性（DQN + 安全层这一步的表现）" name="decision">
            <dl class="metric-dl">
              <div class="metric-dl__row">
                <dt>安全约束</dt>
                <dd :class="{ 'is-abnormal': physicsValidation.safety_overridden }">
                  {{ physicsValidation.safety_overridden
                    ? `✗ 覆盖 · ${physicsValidation.safety_override_reason || '安全规则一票否决'}`
                    : '✓ 通过' }}
                </dd>
              </div>
              <div class="metric-dl__row">
                <dt>决策等级</dt>
                <dd :class="{ 'is-abnormal': ['L1_MANUAL', 'OVERRIDE'].includes(physicsValidation.decision_level) }">
                  {{ DECISION_LEVEL_MAP[physicsValidation.decision_level] ?? physicsValidation.decision_level }}
                </dd>
              </div>
              <div class="metric-dl__row">
                <dt>影子风险</dt>
                <dd>
                  <span :style="{ color: RISK_LEVEL_MAP[physicsValidation.risk_level]?.color }">
                    {{ physicsValidation.risk_level }}
                  </span>
                  · p={{ physicsValidation.risk_probability.toFixed(2) }}
                </dd>
              </div>
              <div class="metric-dl__row">
                <dt>指令平滑</dt>
                <dd :class="{ 'is-abnormal': physicsValidation.command_smoothed }">
                  {{ physicsValidation.command_smoothed
                    ? `✗ 过滤 · ${physicsValidation.smooth_reason || '变化率超限'}`
                    : '✓ 通过' }}
                </dd>
              </div>
              <div class="metric-dl__row">
                <dt>互锁约束</dt>
                <dd :class="{ 'is-abnormal': physicsValidation.interlock?.triggered }">
                  {{ interlockLabel(physicsValidation) }}
                </dd>
              </div>
              <div class="metric-dl__row">
                <dt>贡献</dt>
                <dd :class="contributionClass(physicsValidation.contribution.decision)">
                  {{ formatContribution(physicsValidation.contribution.decision) }}（Decision_Score）
                </dd>
              </div>
            </dl>
          </ElCollapseItem>

          <ElCollapseItem title="物理合规性（设备 + 物理边界这一步的表现）" name="compliance">
            <dl class="metric-dl">
              <div class="metric-dl__row">
                <dt>水量平衡偏差</dt>
                <dd :class="{ 'is-abnormal': physicsValidation.physics_violation_m > 0.5 }">
                  {{ physicsValidation.physics_violation_m.toFixed(2) }}m
                </dd>
              </div>
              <div class="metric-dl__row">
                <dt>阀门限位</dt>
                <dd :class="{ 'is-abnormal': physicsValidation.gate_limit_touched }">
                  {{ physicsValidation.gate_limit_touched ? '✗ 已触碰限位' : '✓ 未触碰' }}
                </dd>
              </div>
              <div class="metric-dl__row">
                <dt>变化率超限</dt>
                <dd :class="{ 'is-abnormal': physicsValidation.rate_exceeded }">
                  {{ physicsValidation.rate_exceeded ? '✗ 变化率超限' : '✓ 未超限' }}
                </dd>
              </div>
              <div class="metric-dl__row">
                <dt>贡献</dt>
                <dd :class="contributionClass(physicsValidation.contribution.compliance)">
                  {{ formatContribution(physicsValidation.contribution.compliance) }}（Compliance_Score）
                </dd>
              </div>
            </dl>
          </ElCollapseItem>
        </ElCollapse>

        <div class="inference-overall" :class="overallContributionMeta(physicsValidation).cls">
          <span class="inference-overall__icon">{{ overallContributionMeta(physicsValidation).icon }}</span>
          <span>
            本次综合贡献 {{ formatContribution(physicsValidation.contribution.overall) }} ·
            {{ overallContributionMeta(physicsValidation).text }}
          </span>
        </div>
      </div>
    </template>

    <template #footer>
      <ElButton size="large" @click="visible = false">关闭</ElButton>
      <ElButton
        v-if="footerMode === 'control'"
        size="large"
        type="primary"
        @click="onApply"
      >
        采用建议开度
      </ElButton>
      <ElButton
        v-else
        size="large"
        type="primary"
        @click="onGoControl"
      >
        前往运行控制执行 →
      </ElButton>
    </template>
  </ElDialog>
</template>

<style scoped lang="scss">
.detail-block {
  margin-bottom: 20px;

  h4 {
    margin: 0 0 12px;
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
  }
}

.detail-summary {
  margin: 0;
  font-size: 15px;
  color: #64748b;
  line-height: 1.6;

  strong {
    color: #1890ff;
    font-size: 20px;
  }
}

.detail-meta {
  margin: 12px 0 0;
  font-size: 13px;
  color: #94a3b8;
}

.conf-warn {
  margin: 8px 0 0;
  font-size: 13px;
  color: #f59e0b;
}

.factor-dir {
  font-weight: 700;
}

.metric-dl {
  margin: 0;

  &__row {
    display: grid;
    grid-template-columns: 120px 1fr;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid #f1f5f9;

    dt {
      margin: 0;
      color: #64748b;
      font-size: 14px;
    }

    dd {
      margin: 0;
      color: #1e293b;
      font-size: 14px;
      font-weight: 600;

      &.is-abnormal { color: #ef4444; }
      &.contrib-pos { color: #16a34a; }
      &.contrib-neg { color: #ef4444; }
      &.contrib-neutral { color: #64748b; }
    }
  }
}

.inference-overall {
  margin-top: 16px;
  padding: 14px 16px;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 10px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;

  &.contrib-pos { background: #f0fdf4; border-color: #bbf7d0; }
  &.contrib-neg { background: #fef2f2; border-color: #fecaca; }
  &__icon { font-size: 18px; }
}
</style>

<style lang="scss">
.decision-detail-dialog {
  .el-dialog { --el-bg-color: #ffffff; max-width: calc(100vw - 48px); }
  .el-dialog__header { padding: 20px 28px 12px; margin-right: 0; }
  .el-dialog__title { font-size: 22px; font-weight: 700; color: #1e293b; }
  .el-dialog__body { padding: 8px 28px 24px; max-height: calc(100vh - 180px); overflow-y: auto; }
  .el-dialog__footer { padding: 16px 28px 24px; }
  .el-dialog__footer .el-button { min-width: 108px; font-size: 15px; }
  .detail-table { font-size: 16px; }
  .detail-table th.el-table__cell {
    font-size: 16px; font-weight: 600; color: #334155; padding: 16px 0; background: #f8fafc;
  }
  .detail-table td.el-table__cell { padding: 14px 0; font-size: 16px; color: #1e293b; }
  .el-progress__text { font-size: 16px !important; font-weight: 600; }
  .row-rec { background: #f0fdf4 !important; }
}
</style>
