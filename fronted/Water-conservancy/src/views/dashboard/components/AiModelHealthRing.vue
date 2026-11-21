<script setup lang="ts">
// ============================================================
// AI 模型健康度环形组件 (D-85 ~ D-91)
// 综合概览右侧栏 — SVG 仪表盘 + 三维度进度条 + 动画 + 下钻
// ============================================================
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ElSelect, ElOption, ElNotification } from 'element-plus'
import { EVAL_DIMENSIONS } from '@/constants/aiHealth'
import { fetchModelHealthOverview } from '@/api/gateaiSettings'
import { useModelHealth } from '@/composables/useModelHealth'

const router = useRouter()

const {
  reservoirId,
  currentData,
  rollbackVersion,
  loading,
  flashTrigger,
  startPolling,
  stopPolling,
  setReservoir,
} = useModelHealth()

const reservoirOptions = ref<{ value: number; label: string }[]>([])
const healthOverview = ref<Array<{ reservoir_id: number; reservoir_name: string; overall_score: number; health_grade: string }>>([])

const GRADE_CHIP_COLOR: Record<string, string> = {
  S: '#52c41a', A: '#1890ff', B: '#faad14', C: '#fa8c16', D: '#f5222d',
}

// ── D-85 环形图颜色 ──
const GRADE_COLORS: Record<string, string> = {
  S: '#52c41a',
  A: '#1890ff',
  B: '#faad14',
  C: '#fa8c16',
  D: '#f5222d',
}

const GRADE_LABEL: Record<string, string> = {
  S: 'S级·完全自主',
  A: 'A级·自主为主',
  B: 'B级·建议模式',
  C: 'C级·强制人工',
  D: 'D级·自动冻结',
}

const SEGMENTS = [
  { grade: 'D', min: 0.0, max: 0.4, color: GRADE_COLORS.D },
  { grade: 'C', min: 0.4, max: 0.55, color: GRADE_COLORS.C },
  { grade: 'B', min: 0.55, max: 0.7, color: GRADE_COLORS.B },
  { grade: 'A', min: 0.7, max: 0.85, color: GRADE_COLORS.A },
  { grade: 'S', min: 0.85, max: 1.0, color: GRADE_COLORS.S },
] as const

// ── SVG 几何参数 ──
const SVG_SIZE = 200
const CX = 100
const CY = 105
const OUTER_R = 82
const INNER_R = 68
const GAUGE_START_DEG = 130 // SVG 角度 (0=3点钟, CW)
const GAUGE_SPAN_DEG = 280 // 覆盖 280°

function scoreToAngle(score: number): number {
  return ((GAUGE_START_DEG + score * GAUGE_SPAN_DEG) * Math.PI) / 180
}

function polar(cx: number, cy: number, r: number, rad: number) {
  return { x: +(cx + r * Math.cos(rad)).toFixed(2), y: +(cy + r * Math.sin(rad)).toFixed(2) }
}

function segmentPath(fromScore: number, toScore: number): string {
  const a1 = scoreToAngle(fromScore)
  const a2 = scoreToAngle(toScore)
  const p1o = polar(CX, CY, OUTER_R, a1)
  const p2o = polar(CX, CY, OUTER_R, a2)
  const p2i = polar(CX, CY, INNER_R, a2)
  const p1i = polar(CX, CY, INNER_R, a1)
  const large = (toScore - fromScore) * GAUGE_SPAN_DEG > 180 ? 1 : 0
  return `M${p1o.x} ${p1o.y} A${OUTER_R} ${OUTER_R} 0 ${large} 1 ${p2o.x} ${p2o.y} L${p2i.x} ${p2i.y} A${INNER_R} ${INNER_R} 0 ${large} 0 ${p1i.x} ${p1i.y} Z`
}

const score = computed(() => currentData.value?.overall_score ?? null)
const grade = computed(() => currentData.value?.health_grade ?? null)

const pointerAngle = computed(() => {
  if (score.value === null) return scoreToAngle(0)
  return scoreToAngle(Math.max(0, Math.min(1, score.value)))
})

const pointerTip = computed(() => polar(CX, CY, OUTER_R - 4, pointerAngle.value))
const pointerBase1 = computed(() => polar(CX, CY, 20, pointerAngle.value + 0.3))
const pointerBase2 = computed(() => polar(CX, CY, 20, pointerAngle.value - 0.3))

// ── D-86 五维度 ──
const dimensionBars = computed(() => {
  if (!currentData.value) return []
  const w = currentData.value.dimension_weights
  const d = currentData.value
  return [
    { key: 'prediction', label: EVAL_DIMENSIONS.prediction.label, weight: w.prediction ?? 0.4, score: d.prediction_score, color: EVAL_DIMENSIONS.prediction.color },
    { key: 'decision', label: EVAL_DIMENSIONS.decision.label, weight: w.decision ?? 0.35, score: d.decision_score, color: EVAL_DIMENSIONS.decision.color },
    { key: 'compliance', label: EVAL_DIMENSIONS.compliance.label, weight: w.compliance ?? 0.25, score: d.compliance_score, color: EVAL_DIMENSIONS.compliance.color },
    { key: 'safety_coverage', label: EVAL_DIMENSIONS.safety_coverage.label, weight: w.safety_coverage ?? 0.15, score: d.safety_override_rate, color: EVAL_DIMENSIONS.safety_coverage.color },
    { key: 'decision_auto_rate', label: EVAL_DIMENSIONS.decision_auto_rate.label, weight: w.decision_auto_rate ?? 0.15, score: d.l3_auto_rate ?? 0, color: EVAL_DIMENSIONS.decision_auto_rate.color },
  ]
})

// ── D-90 D级回退条显隐 ──
const showRollbackBar = ref(false)
watch(grade, (g) => {
  if (g === 'D') {
    showRollbackBar.value = true
  } else {
    showRollbackBar.value = false
  }
})

// ── D-87 等级变化动画 ──
const isFlashing = ref(false)
watch(flashTrigger, async () => {
  // 降至 C/D 级触发通知
  if (grade.value === 'C' || grade.value === 'D') {
    ElNotification({
      title: '模型健康度告警',
      message: `模型健康度已降至 ${grade.value} 级，当前评分 ${((score.value ?? 0) * 100).toFixed(0)}，请关注`,
      type: grade.value === 'D' ? 'error' : 'warning',
      duration: 8000,
    })
  }
  // 闪烁动画
  isFlashing.value = true
  await nextTick()
  setTimeout(() => {
    isFlashing.value = false
  }, 2400)
})

// ── 水库列表 + 数据刷新 ──
async function loadReservoirs() {
  try {
    const overview = await fetchModelHealthOverview()
    if (overview.length > 0) {
      healthOverview.value = overview
      reservoirOptions.value = overview.map((r) => ({
        value: Number(r.reservoir_id),
        label: r.reservoir_name || `水库 #${r.reservoir_id}`,
      }))
      const current = Number(reservoirId.value)
      if (!reservoirOptions.value.some((r) => r.value === current)) {
        const prefer = reservoirOptions.value.find((r) => r.value === 1)?.value
          ?? reservoirOptions.value[0]?.value
        if (prefer != null) setReservoir(prefer)
      }
    }
  } catch {
    if (reservoirOptions.value.length === 0) {
      reservoirOptions.value = [{ value: 1, label: '示范水库' }]
    }
  }
}

function selectReservoir(id: number) {
  setReservoir(id)
}

function onReservoirChange() {
  setReservoir(Number(reservoirId.value))
}

// ── D-89 点击下钻 ──
function drillDown() {
  router.push('/settings/ai/metrics')
}

// ── 生命周期 ──
onMounted(async () => {
  await loadReservoirs()
  startPolling()
})
onBeforeUnmount(() => {
  stopPolling()
})
</script>

<template>
  <div class="ai-health-ring" :class="{ 'is-flashing': isFlashing }">
    <!-- 标题 + 水库切换 (D-91) -->
    <div class="ai-health-ring__header">
      <h3 class="ai-health-ring__title" @click="drillDown">AI 模型健康度</h3>
      <ElSelect v-model="reservoirId" size="small" style="width: 130px" @change="onReservoirChange">
        <ElOption
          v-for="opt in reservoirOptions"
          :key="opt.value"
          :label="opt.label"
          :value="opt.value"
        />
      </ElSelect>
    </div>

    <!-- 四水库健康概览 -->
    <div v-if="healthOverview.length" class="ai-health-ring__overview">
      <button
        v-for="h in healthOverview"
        :key="h.reservoir_id"
        type="button"
        class="ai-health-ring__chip"
        :class="{ 'is-active': reservoirId === h.reservoir_id }"
        @click="selectReservoir(h.reservoir_id)"
      >
        <span class="ai-health-ring__chip-name">{{ h.reservoir_name || `水库#${h.reservoir_id}` }}</span>
        <span
          class="ai-health-ring__chip-grade"
          :style="{ color: GRADE_CHIP_COLOR[h.health_grade] ?? '#64748b' }"
        >
          {{ h.health_grade }} · {{ (h.overall_score * 100).toFixed(0) }}
        </span>
      </button>
    </div>

    <!-- D-85: SVG 环形仪表盘 -->
    <div class="ai-health-ring__gauge">
      <svg :viewBox="`0 0 ${SVG_SIZE} ${SVG_SIZE}`" class="ai-health-ring__svg">
        <!-- 底色环 -->
        <path
          v-for="seg in SEGMENTS"
          :key="'bg-' + seg.grade"
          :d="segmentPath(seg.min, seg.max)"
          fill="#e8ecf1"
          opacity="0.5"
        />

        <!-- 着色段 -->
        <path
          v-for="seg in SEGMENTS"
          :key="seg.grade"
          :d="segmentPath(seg.min, seg.max)"
          :fill="seg.color"
          :opacity="score !== null && score >= seg.min ? 1 : 0.25"
          class="ai-health-ring__segment"
          :class="{ 'is-active': score !== null && grade === seg.grade }"
        />

        <!-- 指针 -->
        <g v-if="score !== null" class="ai-health-ring__pointer">
          <polygon
            :points="`${pointerTip.x},${pointerTip.y} ${pointerBase1.x},${pointerBase1.y} ${pointerBase2.x},${pointerBase2.y}`"
            fill="#1e293b"
          />
          <circle :cx="CX" :cy="CY" r="6" fill="#1e293b" />
          <circle :cx="CX" :cy="CY" r="3" fill="#fff" />
        </g>

        <!-- 中心分数 + 等级 -->
        <text
          v-if="score !== null"
          :x="CX"
          :y="CY - 10"
          text-anchor="middle"
          class="ai-health-ring__score"
        >
          {{ (score * 100).toFixed(0) }}
        </text>
        <text
          v-if="score !== null"
          :x="CX"
          :y="CY + 18"
          text-anchor="middle"
          class="ai-health-ring__score-unit"
        >
          分
        </text>
        <text
          v-if="grade"
          :x="CX"
          :y="CY + 42"
          text-anchor="middle"
          class="ai-health-ring__grade-label"
          :fill="GRADE_COLORS[grade]"
        >
          {{ GRADE_LABEL[grade] ?? grade + '级' }}
        </text>

        <!-- 空状态 -->
        <text
          v-if="score === null && loading"
          :x="CX"
          :y="CY"
          text-anchor="middle"
          class="ai-health-ring__empty"
        >
          加载中…
        </text>
        <text
          v-if="score === null && !loading"
          :x="CX"
          :y="CY"
          text-anchor="middle"
          class="ai-health-ring__empty"
        >
          暂无数据
        </text>
      </svg>

      <!-- 等级图例 -->
      <div class="ai-health-ring__grade-legend">
        <span
          v-for="g in ['S', 'A', 'B', 'C', 'D']"
          :key="g"
          class="ai-health-ring__grade-dot"
          :style="{ color: GRADE_COLORS[g] }"
        >
          {{ g }}
        </span>
      </div>
    </div>

    <!-- D-86: 五维评分环 -->
    <div class="ai-health-ring__dimensions">
      <div v-for="dim in dimensionBars" :key="dim.key" class="ai-health-ring__dim">
        <div class="ai-health-ring__dim-head">
          <span class="ai-health-ring__dim-label">{{ dim.label }}</span>
          <span class="ai-health-ring__dim-weight">权重 {{ dim.weight.toFixed(2) }}</span>
          <span class="ai-health-ring__dim-score" :style="{ color: dim.color }">
            {{ (dim.score * 100).toFixed(0) }}
          </span>
        </div>
        <div class="ai-health-ring__dim-track">
          <div
            class="ai-health-ring__dim-fill"
            :style="{ width: (dim.score * 100).toFixed(1) + '%', background: dim.color }"
          />
        </div>
      </div>
    </div>

    <!-- D-90: D级回退提示条 -->
    <Transition name="slide-bar">
      <div v-if="showRollbackBar && grade === 'D'" class="ai-health-ring__rollback-bar">
        <span class="ai-health-ring__rollback-icon">⚠️</span>
        <span class="ai-health-ring__rollback-text">
          模型健康度降至 D 级，已自动回退至上一稳定版本 {{ rollbackVersion }}，请联系算法工程师评估
        </span>
        <button class="ai-health-ring__rollback-close" @click="showRollbackBar = false">✕</button>
      </div>
    </Transition>

    <!-- 点击下钻提示 -->
    <div class="ai-health-ring__drilldown-hint" @click="drillDown">查看模型健康度仪表盘 →</div>
  </div>
</template>

<style scoped lang="scss">
.ai-health-ring {
  padding: 20px 18px;
  background: #fff;
  border-radius: 10px;
  border: 1px solid #eef0f2;

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
  }

  &__overview {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    margin-bottom: 14px;
  }

  &__chip {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
    padding: 8px 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    text-align: left;
    transition: border-color 0.15s, background 0.15s;

    &:hover {
      border-color: #93c5fd;
      background: #f0f7ff;
    }

    &.is-active {
      border-color: #3b82f6;
      background: #eff6ff;
    }
  }

  &__chip-name {
    font-size: 13px;
    font-weight: 600;
    color: #334155;
  }

  &__chip-grade {
    font-size: 14px;
    font-weight: 700;
    font-family: 'SF Mono', monospace;
  }

  &__title {
    margin: 0;
    font-size: 17px;
    font-weight: 700;
    color: #1e293b;
    cursor: pointer;

    &:hover {
      color: #3b82f6;
    }
  }

  // ── SVG 环形 ──
  &__gauge {
    cursor: pointer;
    position: relative;
    user-select: none;

    &:hover .ai-health-ring__svg {
      filter: drop-shadow(0 2px 8px rgba(59, 130, 246, 0.2));
    }
  }

  &__svg {
    display: block;
    width: 100%;
    max-width: 320px;
    margin: 0 auto;
    transition: filter 0.3s;
  }

  &__segment {
    transition: opacity 0.6s;

    &.is-active {
      filter: drop-shadow(0 0 6px currentColor);
    }
  }

  &__pointer {
    polygon {
      transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }
  }

  &__score {
    font-size: 38px;
    font-weight: 800;
    font-family: 'SF Mono', 'Cascadia Code', monospace;
    fill: #0f172a;
    transition: fill 0.4s;
  }

  &__score-unit {
    font-size: 14px;
    fill: #64748b;
    font-weight: 500;
  }

  &__grade-label {
    font-size: 15px;
    font-weight: 700;
  }

  &__empty {
    font-size: 15px;
    fill: #94a3b8;
  }

  // ── 等级 D-87 闪烁动画 ──
  &.is-flashing {
    .ai-health-ring__svg {
      animation: flash-ring 800ms 3;
    }
  }

  @keyframes flash-ring {
    0%,
    100% {
      opacity: 1;
    }
    50% {
      opacity: 0.35;
    }
  }

  // ── 等级图例 ──
  &__grade-legend {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-top: 6px;
  }

  &__grade-dot {
    font-size: 13px;
    font-weight: 700;
    font-family: 'SF Mono', monospace;
  }

  // ── D-86 三维度 ──
  &__dimensions {
    margin-top: 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  &__dim-head {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 4px;
    font-size: 14px;
  }

  &__dim-label {
    color: #334155;
    font-weight: 500;
    flex: 1;
  }

  &__dim-weight {
    color: #94a3b8;
    font-size: 12px;
    min-width: 52px;
    text-align: right;
  }

  &__dim-score {
    font-weight: 700;
    font-family: 'SF Mono', monospace;
    font-size: 16px;
    min-width: 30px;
    text-align: right;
  }

  &__dim-track {
    height: 8px;
    background: #e8ecf1;
    border-radius: 4px;
    overflow: hidden;
  }

  &__dim-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    min-width: 2px;
  }

  // ── D-90 回退条 ──
  &__rollback-bar {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-top: 12px;
    padding: 12px 14px;
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    border: 1px solid #fecaca;
    border-radius: 8px;
    font-size: 14px;
    line-height: 1.5;
    color: #991b1b;
    overflow: hidden;
  }

  &__rollback-icon {
    font-size: 18px;
    flex-shrink: 0;
  }

  &__rollback-text {
    flex: 1;
    min-width: 0;
  }

  &__rollback-close {
    flex-shrink: 0;
    border: none;
    background: none;
    color: #991b1b;
    cursor: pointer;
    font-size: 15px;
    padding: 0 2px;
    opacity: 0.6;

    &:hover {
      opacity: 1;
    }
  }

  // ── 下钻提示 ──
  &__drilldown-hint {
    margin-top: 12px;
    text-align: center;
    font-size: 14px;
    color: #64748b;
    cursor: pointer;
    padding: 4px 0;
    border-radius: 4px;
    transition: all 0.2s;

    &:hover {
      color: #3b82f6;
      background: #f0f7ff;
    }
  }
}

// 回退条过渡
.slide-bar-enter-active,
.slide-bar-leave-active {
  transition: all 0.4s ease;
}
.slide-bar-enter-from,
.slide-bar-leave-to {
  opacity: 0;
  max-height: 0;
  margin-top: 0;
  padding-top: 0;
  padding-bottom: 0;
}
.slide-bar-enter-to,
.slide-bar-leave-from {
  opacity: 1;
  max-height: 80px;
}
</style>
