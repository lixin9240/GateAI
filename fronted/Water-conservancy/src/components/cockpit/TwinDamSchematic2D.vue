<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { XIANGJIABA_HYDRO, getLevelStatus } from '@/constants/xiangjiaba'

const props = defineProps<{
  waterLevel: number
  downstreamLevel: number
  gateOpening: number
  flowRate: number
}>()

const ELEV_MIN = 268
const ELEV_MAX = 388
const W = 920
const H = 360
const PAD = { t: 28, r: 24, b: 36, l: 52 }
const innerW = W - PAD.l - PAD.r
const innerH = H - PAD.t - PAD.b

function elevToY(elev: number) {
  const t = (elev - ELEV_MIN) / (ELEV_MAX - ELEV_MIN)
  return PAD.t + innerH - t * innerH
}

function elevToPct(elev: number) {
  return ((elev - ELEV_MIN) / (ELEV_MAX - ELEV_MIN)) * 100
}

const damX = PAD.l + innerW * 0.46
const damW = innerW * 0.1
const crestY = elevToY(XIANGJIABA_HYDRO.crestElevation)
const baseY = elevToY(272)
const gateCount = 5
const gateW = (damW * 0.72) / gateCount
const gateStartX = damX + damW * 0.14

const wavePhase = ref(0)
let rafId = 0

onMounted(() => {
  const tick = (t: number) => {
    wavePhase.value = t * 0.001
    rafId = requestAnimationFrame(tick)
  }
  rafId = requestAnimationFrame(tick)
})

onUnmounted(() => {
  cancelAnimationFrame(rafId)
})

function surfaceY(xNorm: number, baseLevelY: number, amp: number, speed: number) {
  const p = wavePhase.value * speed
  return (
    baseLevelY + Math.sin(xNorm * 6.2 + p) * amp + Math.sin(xNorm * 11.5 + p * 1.35) * amp * 0.45
  )
}

function buildWaterBody(
  xStart: number,
  xEnd: number,
  levelY: number,
  bottomY: number,
  amp: number,
  speed: number,
) {
  const steps = 28
  const span = xEnd - xStart
  let d = `M ${xStart} ${bottomY}`
  for (let i = 0; i <= steps; i++) {
    const x = xStart + (span * i) / steps
    const xNorm = i / steps
    const y = surfaceY(xNorm, levelY, amp, speed)
    d += ` L ${x} ${y}`
  }
  d += ` L ${xEnd} ${bottomY} Z`
  return d
}

const upstreamY = computed(() => elevToY(props.waterLevel))
const downstreamY = computed(() => elevToY(props.downstreamLevel))
const openRatio = computed(() => props.gateOpening / 100)
const levelStatus = computed(() => getLevelStatus(props.waterLevel))

const upstreamBodyPath = computed(() =>
  buildWaterBody(PAD.l, damX, upstreamY.value, baseY, 2.8, 2.4),
)
const downstreamBodyPath = computed(() =>
  buildWaterBody(damX + damW, PAD.l + innerW, downstreamY.value, baseY, 2.2, 1.8),
)

const upstreamWaveLine = computed(() => {
  const steps = 24
  const span = damX - PAD.l
  let d = ''
  for (let i = 0; i <= steps; i++) {
    const x = PAD.l + (span * i) / steps
    const y = surfaceY(i / steps, upstreamY.value, 3.2, 2.8)
    d += `${i === 0 ? 'M' : 'L'} ${x} ${y}`
  }
  return d
})

const downstreamWaveLine = computed(() => {
  const steps = 24
  const xStart = damX + damW
  const span = PAD.l + innerW - xStart
  let d = ''
  for (let i = 0; i <= steps; i++) {
    const x = xStart + (span * i) / steps
    const y = surfaceY(i / steps, downstreamY.value, 2.6, 2.2)
    d += `${i === 0 ? 'M' : 'L'} ${x} ${y}`
  }
  return d
})

const flowLines = computed(() => {
  const count = Math.min(8, Math.max(3, Math.floor(props.flowRate / 300)))
  const lines = []
  for (let i = 0; i < count; i++) {
    const x = PAD.l + ((damX - PAD.l) * (i + 0.5)) / count
    const yStart = upstreamY.value + 12 + (i % 3) * 8
    const yEnd = upstreamY.value + 28 + (i % 2) * 14
    lines.push({ x, yStart, yEnd, delay: i * 0.35 })
  }
  return lines
})

const refLines = computed(() => [
  { label: '坝顶', v: XIANGJIABA_HYDRO.crestElevation, dash: '4 3', color: '#94a3b8' },
  { label: '汛限', v: XIANGJIABA_HYDRO.floodLimitLevel, dash: '6 4', color: '#f59e0b' },
  { label: '正常蓄水', v: XIANGJIABA_HYDRO.normalPoolLevel, dash: '6 4', color: '#22c55e' },
  { label: '死水位', v: XIANGJIABA_HYDRO.deadLevel, dash: '6 4', color: '#3b82f6' },
])

const gates = computed(() =>
  Array.from({ length: gateCount }, (_, i) => {
    const x = gateStartX + i * gateW + gateW * 0.08
    const slotH = baseY - crestY - 8
    const lift = slotH * openRatio.value * 0.82
    return { x, w: gateW * 0.84, slotH, lift, idx: i + 1 }
  }),
)

const dischargeVisible = computed(() => openRatio.value > 0.03)
const shimmerOffset = computed(() => (wavePhase.value * 120) % 40)
</script>

<template>
  <div class="twin-2d">
    <div class="twin-2d__legend">
      <span><i class="dot dot--up" />上游库区 {{ waterLevel.toFixed(2) }} m</span>
      <span><i class="dot dot--down" />下游尾水 {{ downstreamLevel.toFixed(2) }} m</span>
      <span><i class="dot dot--flow" />入库 {{ flowRate }} m³/s</span>
      <span class="twin-2d__status" :style="{ color: levelStatus.color }">{{
        levelStatus.label
      }}</span>
    </div>

    <svg
      class="twin-2d__svg"
      :viewBox="`0 0 ${W} ${H}`"
      preserveAspectRatio="xMidYMid meet"
      aria-label="向家坝大坝 2D 剖面示意"
    >
      <defs>
        <linearGradient id="skyGrad" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#e8f4ff" />
          <stop offset="100%" stop-color="#f7fbff" />
        </linearGradient>
        <linearGradient id="waterUp" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#93c5fd" stop-opacity="0.95" />
          <stop offset="55%" stop-color="#60a5fa" stop-opacity="0.9" />
          <stop offset="100%" stop-color="#3b82f6" stop-opacity="0.92" />
        </linearGradient>
        <linearGradient id="waterDown" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#7dd3fc" stop-opacity="0.85" />
          <stop offset="100%" stop-color="#0ea5e9" stop-opacity="0.88" />
        </linearGradient>
        <linearGradient id="concrete" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#cbd5e1" />
          <stop offset="100%" stop-color="#94a3b8" />
        </linearGradient>
        <pattern id="waterShimmer" width="40" height="8" patternUnits="userSpaceOnUse">
          <rect width="40" height="8" fill="transparent" />
          <line x1="0" y1="4" x2="40" y2="4" stroke="#fff" stroke-width="1.5" opacity="0.25" />
        </pattern>
        <clipPath id="upstreamClip">
          <path :d="upstreamBodyPath" />
        </clipPath>
        <clipPath id="downstreamClip">
          <path :d="downstreamBodyPath" />
        </clipPath>
      </defs>

      <!-- 背景 -->
      <rect :x="PAD.l" :y="PAD.t" :width="innerW" :height="innerH" fill="url(#skyGrad)" rx="6" />

      <!-- 高程标尺 -->
      <g class="twin-2d__axis">
        <line
          :x1="PAD.l"
          :y1="PAD.t"
          :x2="PAD.l"
          :y2="PAD.t + innerH"
          stroke="#94a3b8"
          stroke-width="1"
        />
        <text :x="PAD.l - 8" :y="PAD.t + 4" text-anchor="end" class="axis-label">
          {{ ELEV_MAX }}m
        </text>
        <text :x="PAD.l - 8" :y="PAD.t + innerH" text-anchor="end" class="axis-label">
          {{ ELEV_MIN }}m
        </text>
      </g>

      <!-- 参考水位线 -->
      <g v-for="line in refLines" :key="line.label">
        <line
          :x1="PAD.l"
          :y1="elevToY(line.v)"
          :x2="PAD.l + innerW"
          :y2="elevToY(line.v)"
          :stroke="line.color"
          stroke-width="1"
          :stroke-dasharray="line.dash"
          opacity="0.75"
        />
        <text
          :x="PAD.l + innerW - 4"
          :y="elevToY(line.v) - 4"
          text-anchor="end"
          class="ref-label"
          :fill="line.color"
        >
          {{ line.label }} {{ line.v }}m
        </text>
      </g>

      <!-- 上游水体（动态波浪轮廓） -->
      <path
        :d="upstreamBodyPath"
        fill="url(#waterUp)"
        opacity="0.9"
        class="twin-2d__water twin-2d__water--up"
      />
      <g clip-path="url(#upstreamClip)">
        <rect
          :x="PAD.l - shimmerOffset"
          :y="upstreamY"
          :width="damX - PAD.l + 40"
          :height="baseY - upstreamY"
          fill="url(#waterShimmer)"
          opacity="0.5"
        >
          <animate
            attributeName="x"
            :from="PAD.l - 40"
            :to="PAD.l + 40"
            dur="3s"
            repeatCount="indefinite"
          />
        </rect>
        <g v-for="(fl, i) in flowLines" :key="'flow-' + i">
          <line
            :x1="fl.x"
            :y1="fl.yStart"
            :x2="fl.x"
            :y2="fl.yEnd"
            stroke="#bfdbfe"
            stroke-width="1.5"
            stroke-linecap="round"
            opacity="0.6"
          >
            <animate
              attributeName="y1"
              :values="`${fl.yStart};${fl.yEnd};${fl.yStart}`"
              dur="2.2s"
              :begin="`${fl.delay}s`"
              repeatCount="indefinite"
            />
            <animate
              attributeName="y2"
              :values="`${fl.yEnd};${fl.yStart + 40};${fl.yEnd}`"
              dur="2.2s"
              :begin="`${fl.delay}s`"
              repeatCount="indefinite"
            />
            <animate
              attributeName="opacity"
              values="0.2;0.7;0.2"
              dur="2.2s"
              :begin="`${fl.delay}s`"
              repeatCount="indefinite"
            />
          </line>
        </g>
      </g>
      <path
        :d="upstreamWaveLine"
        fill="none"
        stroke="#dbeafe"
        stroke-width="2"
        opacity="0.95"
        class="twin-2d__wave-line"
      />
      <path
        :d="upstreamWaveLine"
        fill="none"
        stroke="#93c5fd"
        stroke-width="1"
        opacity="0.6"
        transform="translate(0, 3)"
      />

      <!-- 当前上游水位线 -->
      <line
        :x1="PAD.l"
        :y1="upstreamY"
        :x2="damX"
        :y2="upstreamY"
        stroke="#1890ff"
        stroke-width="1.5"
        stroke-dasharray="6 4"
        opacity="0.5"
      />
      <text :x="PAD.l + 8" :y="upstreamY - 6" class="level-tag level-tag--up">
        当前 {{ waterLevel.toFixed(2) }} m
      </text>

      <!-- 坝体 -->
      <path
        :d="`
          M ${damX} ${baseY}
          L ${damX} ${crestY + 6}
          L ${damX + damW * 0.15} ${crestY}
          L ${damX + damW * 0.85} ${crestY}
          L ${damX + damW} ${crestY + 6}
          L ${damX + damW} ${baseY}
          Z
        `"
        fill="url(#concrete)"
        stroke="#64748b"
        stroke-width="1.5"
      />
      <text :x="damX + damW / 2" :y="crestY - 10" text-anchor="middle" class="dam-label">
        向家坝大坝
      </text>

      <!-- 阀门 -->
      <g v-for="g in gates" :key="g.idx">
        <rect
          :x="g.x"
          :y="crestY + 10"
          :width="g.w"
          :height="g.slotH"
          fill="#475569"
          stroke="#334155"
          stroke-width="0.8"
          rx="1"
        />
        <rect
          :x="g.x + 1"
          :y="crestY + 12 + g.lift"
          :width="g.w - 2"
          :height="g.slotH - g.lift - 4"
          fill="#64748b"
          stroke="#94a3b8"
          stroke-width="0.6"
        />
        <text :x="g.x + g.w / 2" :y="baseY + 14" text-anchor="middle" class="gate-label">
          {{ g.idx }}#
        </text>
      </g>

      <!-- 泄洪（动态水柱 + 水雾） -->
      <g v-if="dischargeVisible" opacity="0.9">
        <g v-for="g in gates" :key="'jet-' + g.idx">
          <template v-if="gateOpening > (g.idx - 1) * 18">
            <path
              :d="`M ${g.x + g.w * 0.2} ${baseY - 2} Q ${g.x + g.w * 0.5} ${baseY + 28 + openRatio * 40} ${g.x + g.w * 0.8} ${baseY + 18 + openRatio * 30}`"
              fill="none"
              stroke="#7dd3fc"
              stroke-width="2.5"
              stroke-linecap="round"
            >
              <animate
                attributeName="opacity"
                values="0.45;1;0.45"
                dur="1s"
                repeatCount="indefinite"
              />
            </path>
            <path
              :d="`M ${g.x + g.w * 0.35} ${baseY + 4} Q ${g.x + g.w * 0.5} ${baseY + 40 + openRatio * 35} ${g.x + g.w * 0.65} ${baseY + 30 + openRatio * 28}`"
              fill="none"
              stroke="#bae6fd"
              stroke-width="1.5"
              stroke-linecap="round"
              opacity="0.7"
            >
              <animate
                attributeName="opacity"
                values="0.3;0.8;0.3"
                dur="0.8s"
                repeatCount="indefinite"
              />
            </path>
            <circle
              :cx="g.x + g.w * 0.5"
              :cy="baseY + 22 + openRatio * 20"
              r="3"
              fill="#e0f2fe"
              opacity="0.5"
            >
              <animate
                attributeName="cy"
                :values="`${baseY + 22 + openRatio * 20};${baseY + 50 + openRatio * 30};${baseY + 22 + openRatio * 20}`"
                dur="1.4s"
                repeatCount="indefinite"
              />
              <animate
                attributeName="opacity"
                values="0.6;0;0.6"
                dur="1.4s"
                repeatCount="indefinite"
              />
            </circle>
          </template>
        </g>
      </g>

      <!-- 下游水体 -->
      <path
        :d="downstreamBodyPath"
        fill="url(#waterDown)"
        opacity="0.85"
        class="twin-2d__water twin-2d__water--down"
      />
      <g clip-path="url(#downstreamClip)">
        <rect
          :x="damX + damW - shimmerOffset"
          :y="downstreamY"
          :width="PAD.l + innerW - damX - damW + 40"
          :height="baseY - downstreamY"
          fill="url(#waterShimmer)"
          opacity="0.4"
        >
          <animate
            attributeName="x"
            :from="damX + damW - 40"
            :to="damX + damW + 40"
            dur="3.5s"
            repeatCount="indefinite"
          />
        </rect>
      </g>
      <path
        :d="downstreamWaveLine"
        fill="none"
        stroke="#bae6fd"
        stroke-width="1.8"
        opacity="0.85"
        class="twin-2d__wave-line"
      />
      <line
        :x1="damX + damW"
        :y1="downstreamY"
        :x2="PAD.l + innerW"
        :y2="downstreamY"
        stroke="#0ea5e9"
        stroke-width="1.5"
        stroke-dasharray="6 4"
        opacity="0.45"
      />
      <text :x="damX + damW + 8" :y="downstreamY - 6" class="level-tag level-tag--down">
        尾水 {{ downstreamLevel.toFixed(2) }} m
      </text>

      <!-- 河床 -->
      <rect
        :x="PAD.l"
        :y="baseY"
        :width="innerW"
        :height="PAD.t + innerH - baseY"
        fill="#78716c"
        opacity="0.35"
      />

      <!-- 区域标注 -->
      <text
        :x="PAD.l + (damX - PAD.l) / 2"
        :y="PAD.t + innerH + 22"
        text-anchor="middle"
        class="zone-label"
      >
        上游库区
      </text>
      <text
        :x="damX + damW + (PAD.l + innerW - damX - damW) / 2"
        :y="PAD.t + innerH + 22"
        text-anchor="middle"
        class="zone-label"
      >
        下游河道
      </text>
    </svg>

    <div class="twin-2d__scale">
      <div class="twin-2d__scale-bar">
        <div
          class="twin-2d__scale-fill"
          :style="{ height: elevToPct(waterLevel) + '%', background: levelStatus.color }"
        />
        <div
          v-for="line in refLines"
          :key="'tick-' + line.label"
          class="twin-2d__scale-tick"
          :style="{ bottom: elevToPct(line.v) + '%', borderColor: line.color }"
          :title="`${line.label} ${line.v}m`"
        />
      </div>
      <span class="twin-2d__scale-caption">水位标尺</span>
    </div>
  </div>
</template>

<style scoped lang="scss">
@use '@/assets/styles/cockpit.scss' as *;

.twin-2d {
  position: relative;
  width: 100%;
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
  background: linear-gradient(180deg, #f7fbff 0%, #eef6fc 100%);
  border-radius: 10px;
  overflow: hidden;

  &__legend {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 16px;
    padding: 10px 16px;
    font-size: $cockpit-font-sm;
    color: $cockpit-text-dim;
    background: rgba(255, 255, 255, 0.88);
    border-bottom: 1px solid rgba(24, 144, 255, 0.12);

    span {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
  }

  &__status {
    margin-left: auto;
    font-weight: 700;
  }

  &__svg {
    flex: 1;
    width: 100%;
    min-height: 0;
    padding: 8px 12px 12px;
  }

  &__water {
    transition: d 1s cubic-bezier(0.22, 1, 0.36, 1);
  }

  &__wave-line {
    pointer-events: none;
  }

  &__scale {
    position: absolute;
    top: 52px;
    right: 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
  }

  &__scale-bar {
    position: relative;
    width: 10px;
    height: 120px;
    background: #e2e8f0;
    border-radius: 999px;
    border: 1px solid rgba(24, 144, 255, 0.2);
    overflow: hidden;
  }

  &__scale-fill {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    border-radius: 0 0 999px 999px;
    transition: height 1s cubic-bezier(0.22, 1, 0.36, 1);
    opacity: 0.75;
  }

  &__scale-tick {
    position: absolute;
    left: -3px;
    right: -3px;
    height: 0;
    border-top: 2px solid;
    pointer-events: none;
  }

  &__scale-caption {
    font-size: 10px;
    color: $cockpit-text-dim;
  }
}

.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;

  &--up {
    background: #1890ff;
  }
  &--down {
    background: #0ea5e9;
  }
  &--flow {
    background: #22c55e;
  }
}

.twin-2d__svg {
  .axis-label,
  .ref-label,
  .gate-label,
  .zone-label {
    font-size: 11px;
    fill: #64748b;
  }

  .dam-label {
    font-size: 12px;
    font-weight: 700;
    fill: #1e3a5f;
  }

  .level-tag {
    font-size: 11px;
    font-weight: 600;

    &--up {
      fill: #1890ff;
    }
    &--down {
      fill: #0284c7;
    }
  }
}
</style>
