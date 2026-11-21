<script setup lang="ts">
import { computed, ref } from 'vue'
import { isGateOnline } from '@/utils/gateControl'
import type { GateNodeControl } from '@/types/gateControl'

type DetailZone = 'up' | 'down' | null

const props = defineProps<{
  gates: GateNodeControl[]
  selectedGateId: number | null
  upstreamLevel: number
  downstreamLevel: number
  inflowRate: number
  outflowRate: number
}>()

const emit = defineEmits<{ select: [id: number] }>()

const detailPanel = ref<DetailZone>(null)

const sortedGates = computed(() => [...props.gates].sort((a, b) => a.id - b.id))

const selectedGate = computed(() =>
  sortedGates.value.find((g) => g.id === props.selectedGateId) ?? null,
)

const head = computed(() => Math.max(0, props.upstreamLevel - props.downstreamLevel))

const openCount = computed(() =>
  sortedGates.value.filter((g) => isGateOnline(g.status) && g.currentOpening > 0).length,
)

const levelRange = computed(() => {
  const lo = Math.min(props.upstreamLevel, props.downstreamLevel) - 20
  const hi = Math.max(props.upstreamLevel, props.downstreamLevel) + 20
  return { min: lo, max: Math.max(hi, lo + 40) }
})

const gateGridStyle = computed(() => ({
  gridTemplateColumns: `repeat(${sortedGates.value.length}, minmax(0, 1fr))`,
}))

function levelFillPct(level: number) {
  const { min, max } = levelRange.value
  const span = Math.max(max - min, 40)
  const t = (level - min) / span
  return Math.min(100, Math.max(6, t * 100))
}

const upFillPct = computed(() => levelFillPct(props.upstreamLevel))
const downFillPct = computed(() => levelFillPct(props.downstreamLevel))

function gateShortCode(code: string) {
  const m = code.match(/(\d+)/)
  return m ? m[1].padStart(2, '0') : code.slice(-2)
}

function togglePanel(zone: 'up' | 'down') {
  detailPanel.value = detailPanel.value === zone ? null : zone
}

function selectGate(id: number) {
  emit('select', id)
}
</script>

<template>
  <div class="dam-section">
    <div class="dam-section__main">
      <!-- 顶栏：三列等高 -->
      <div class="dam-head dam-head--left">
        <div class="dam-rail__meta" :class="{ 'is-active': detailPanel === 'up' }">
          <span class="dam-rail__label">上游</span>
          <div class="dam-rail__reading">
            <strong>{{ upstreamLevel.toFixed(1) }}</strong>
            <em>m</em>
          </div>
        </div>
      </div>

      <div class="dam-head dam-head--center">
        <div class="dam-section__legend">
          <span class="dam-legend__item">
            <i class="dam-legend__swatch dam-legend__swatch--cur" />
            实际
          </span>
          <span class="dam-legend__item">
            <i class="dam-legend__swatch dam-legend__swatch--tgt" />
            预测
          </span>
        </div>
      </div>

      <div class="dam-head dam-head--right">
        <div class="dam-rail__meta" :class="{ 'is-active': detailPanel === 'down' }">
          <span class="dam-rail__label">下游</span>
          <div class="dam-rail__reading">
            <strong>{{ downstreamLevel.toFixed(1) }}</strong>
            <em>m</em>
          </div>
        </div>
      </div>

      <!-- 水柱区：顶底对齐 -->
      <div class="dam-viz dam-viz--left">
        <button
          type="button"
          class="dam-rail__gauge"
          :class="{ active: detailPanel === 'up' }"
          @click="togglePanel('up')"
        >
          <div class="dam-rail__fill" :style="{ height: `${upFillPct}%` }" />
        </button>
      </div>

      <div class="dam-viz dam-viz--center">
        <div class="dam-gates__grid dam-gates__grid--water" :style="gateGridStyle">
          <button
            v-for="g in sortedGates"
            :key="`w-${g.id}`"
            type="button"
            class="gate-col gate-col--water"
            :class="{
              active: selectedGateId === g.id,
              offline: !isGateOnline(g.status),
            }"
            @click="selectGate(g.id)"
          >
            <div v-if="isGateOnline(g.status)" class="gate-col__waterbox">
              <div
                class="gate-col__water gate-col__water--tgt"
                :style="{ height: `${g.targetOpening}%` }"
              />
              <div
                v-if="g.currentOpening > 0"
                class="gate-col__water gate-col__water--cur"
                :style="{ height: `${g.currentOpening}%` }"
              />
              <div
                class="gate-col__leaf"
                :style="{ height: `${100 - g.currentOpening}%` }"
              />
            </div>
            <div v-else class="gate-col__offline" />
          </button>
        </div>
      </div>

      <div class="dam-viz dam-viz--right">
        <button
          type="button"
          class="dam-rail__gauge"
          :class="{ active: detailPanel === 'down' }"
          @click="togglePanel('down')"
        >
          <div class="dam-rail__fill" :style="{ height: `${downFillPct}%` }" />
        </button>
      </div>

      <!-- 底栏：阀门编号 + pill，与上游/下游底对齐 -->
      <div class="dam-foot dam-foot--left">
        <div v-show="detailPanel === 'up'" class="dam-detail-strip">
          <div class="dam-detail-strip__item">
            <span>坝前水位</span>
            <strong>{{ upstreamLevel.toFixed(1) }} m</strong>
          </div>
          <div class="dam-detail-strip__item">
            <span>入库流量</span>
            <strong>{{ inflowRate.toFixed(1) }} m³/s</strong>
          </div>
          <div class="dam-detail-strip__item">
            <span>水头差</span>
            <strong>{{ head.toFixed(1) }} m</strong>
          </div>
        </div>
      </div>

      <div class="dam-foot dam-foot--center">
        <div class="dam-gates__grid dam-gates__grid--pillar" :style="gateGridStyle">
          <button
            v-for="g in sortedGates"
            :key="`p-${g.id}`"
            type="button"
            class="gate-col gate-col--pillar"
            :class="{
              active: selectedGateId === g.id,
              offline: !isGateOnline(g.status),
            }"
            @click="selectGate(g.id)"
          >
            <span class="gate-col__code">{{ gateShortCode(g.code) }}</span>
            <div class="gate-col__pill">
              <div
                v-if="isGateOnline(g.status)"
                class="gate-col__pill-bar gate-col__pill-bar--tgt"
                :style="{ height: `${g.targetOpening}%` }"
              />
              <div
                v-if="isGateOnline(g.status) && g.currentOpening > 0"
                class="gate-col__pill-bar gate-col__pill-bar--cur"
                :style="{ height: `${g.currentOpening}%` }"
              />
            </div>
          </button>
        </div>
      </div>

      <div class="dam-foot dam-foot--right">
        <div v-show="detailPanel === 'down'" class="dam-detail-strip">
          <div class="dam-detail-strip__item">
            <span>尾水位</span>
            <strong>{{ downstreamLevel.toFixed(1) }} m</strong>
          </div>
          <div class="dam-detail-strip__item">
            <span>出库流量</span>
            <strong>{{ outflowRate.toFixed(0) }} m³/s</strong>
          </div>
          <div class="dam-detail-strip__item">
            <span>开启孔数</span>
            <strong>{{ openCount }} 孔</strong>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="selectedGate && isGateOnline(selectedGate.status)"
      class="dam-gate-detail"
    >
      <span class="dam-gate-detail__name">{{ selectedGate.name }}（{{ gateShortCode(selectedGate.code) }}）</span>
      <span class="dam-gate-detail__item">
        <i class="dam-legend__swatch dam-legend__swatch--cur" />
        实际 <strong>{{ selectedGate.currentOpening.toFixed(1) }}%</strong>
      </span>
      <span class="dam-gate-detail__item">
        <i class="dam-legend__swatch dam-legend__swatch--tgt" />
        预测 <strong>{{ selectedGate.targetOpening.toFixed(1) }}%</strong>
      </span>
    </div>
  </div>
</template>

<style scoped lang="scss">
@use '@/assets/styles/design-tokens.scss' as *;

$water-actual: linear-gradient(180deg, #5cadff 0%, #228be6 52%, #1864ab 100%);
$water-predict: linear-gradient(180deg, #d0ebff 0%, #91d5ff 100%);
$water-chamber: linear-gradient(180deg, #f8fafc 0%, #edf4fc 100%);
$water-chamber-border: rgba(24, 144, 255, 0.16);
$water-chamber-shadow: inset 0 2px 8px rgba(15, 23, 42, 0.04);
$color-actual-solid: #228be6;
$color-predict-solid: #91d5ff;
$color-select: #1890ff;
$divider: #e8edf2;
$bar-w: 28px;
$pill-h: 40px;
$head-h: 52px;
$foot-h: 58px;

.dam-section {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  background: #fff;
  overflow: hidden;
}

.dam-section__main {
  flex: 1;
  min-height: 0;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  grid-template-rows: $head-h minmax(240px, 1fr) auto;
  align-items: stretch;
  gap: 0;
  padding: 16px 18px 12px;
}

.dam-head {
  display: flex;
  align-items: flex-end;
  justify-content: center;
  min-height: $head-h;
  padding-bottom: 8px;

  &--left {
    padding-right: 18px;
    border-right: 1px solid $divider;
  }

  &--center {
    padding: 0 22px 8px;
    justify-content: flex-end;
  }

  &--right {
    padding-left: 18px;
    border-left: 1px solid $divider;
  }
}

.dam-viz {
  min-height: 0;
  display: flex;
  align-items: stretch;

  &--left {
    padding-right: 18px;
    border-right: 1px solid $divider;
    justify-content: center;
  }

  &--center {
    padding: 0 22px;
    min-width: 0;
  }

  &--right {
    padding-left: 18px;
    border-left: 1px solid $divider;
    justify-content: center;
  }
}

.dam-foot {
  min-height: $foot-h;
  display: flex;
  align-items: flex-start;
  justify-content: center;

  &--left {
    padding-right: 18px;
    border-right: 1px solid $divider;
  }

  &--center {
    padding: 0 22px;
    min-width: 0;
    align-items: flex-start;
  }

  &--right {
    padding-left: 18px;
    border-left: 1px solid $divider;
  }
}

.dam-rail__meta {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  text-align: center;

  &.is-active .dam-rail__label {
    color: $color-actual-solid;
  }
}

.dam-rail__label {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.1em;
  color: #94a3b8;
}

.dam-rail__reading {
  display: flex;
  align-items: baseline;
  justify-content: center;
  gap: 3px;
  line-height: 1.2;

  strong {
    font-size: 18px;
    font-weight: 700;
    font-family: 'SF Mono', Consolas, monospace;
    color: #1e293b;
  }

  em {
    font-style: normal;
    font-size: 12px;
    color: #94a3b8;
  }
}

.dam-rail__gauge {
  width: $bar-w;
  height: 100%;
  min-height: 0;
  padding: 0;
  border-radius: 5px;
  background: $water-chamber;
  border: 1px solid $water-chamber-border;
  box-shadow: $water-chamber-shadow;
  display: flex;
  align-items: flex-end;
  overflow: hidden;
  cursor: pointer;

  &.active {
    box-shadow:
      0 0 0 1.5px $color-select,
      0 0 12px rgba(24, 144, 255, 0.2);
  }
}

.dam-rail__fill {
  width: 100%;
  border-radius: 3px 3px 0 0;
  background: $water-actual;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.32);
}

.dam-detail-strip {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 4px;
  width: 100%;

  &__item {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 11px;
    line-height: 1.3;

    span {
      color: #64748b;
      white-space: nowrap;
      font-weight: 500;
    }

    strong {
      font-family: 'SF Mono', Consolas, monospace;
      font-weight: 700;
      font-size: 13px;
      color: #1e293b;
      white-space: nowrap;
    }
  }
}

.dam-section__legend {
  display: flex;
  align-items: center;
  gap: 14px;
  font-size: 11px;
  color: #64748b;
  line-height: 1;
}

.dam-legend__item {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  white-space: nowrap;
}

.dam-legend__swatch {
  display: inline-block;
  width: 12px;
  height: 12px;
  border-radius: 2px;
  flex-shrink: 0;

  &--cur {
    background: $color-actual-solid;
  }

  &--tgt {
    background: $color-predict-solid;
  }
}

.dam-gates__grid {
  width: 100%;
  height: 100%;
  display: grid;
  gap: 10px;

  &--water {
    align-items: stretch;
    min-height: 0;
  }

  &--pillar {
    align-items: flex-start;
    height: $foot-h;
  }
}

.gate-col {
  padding: 0;
  border: none;
  background: transparent;
  cursor: pointer;

  &--water {
    width: $bar-w;
    height: 100%;
    min-height: 0;
    margin: 0 auto;
    display: flex;
    align-items: stretch;
    justify-content: center;

    &.active .gate-col__waterbox,
    &.active .gate-col__offline {
      box-shadow:
        0 0 0 1.5px $color-select,
        0 0 12px rgba(24, 144, 255, 0.2);
      border-radius: 5px;
    }
  }

  &--pillar {
    width: $bar-w;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;

    &.active .gate-col__code {
      color: $color-actual-solid;
      font-weight: 700;
    }
  }

  &.offline {
    opacity: 0.36;
    cursor: default;
  }
}

.gate-col__waterbox {
  position: relative;
  width: 100%;
  height: 100%;
  border-radius: 5px;
  overflow: hidden;
  background: $water-chamber;
  border: 1px solid $water-chamber-border;
  box-shadow: $water-chamber-shadow;
}

.gate-col__water {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  border-radius: 3px 3px 0 0;
  transition: height 0.35s ease;

  &--cur {
    z-index: 2;
    background: $water-actual;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.3);
  }

  &--tgt {
    z-index: 1;
    background: $water-predict;
  }
}

.gate-col__leaf {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  z-index: 3;
  background: linear-gradient(180deg, #f8fafc 0%, #edf4fc 100%);
  pointer-events: none;
}

.gate-col__offline {
  width: 100%;
  height: 100%;
  border-radius: 5px;
  background: #f1f5f9;
  border: 1px solid #e8edf2;
}

.gate-col__code {
  width: 100%;
  font-size: 12px;
  font-family: Consolas, monospace;
  color: #64748b;
  text-align: center;
  line-height: 1;
}

.gate-col__pill {
  width: $bar-w;
  height: $pill-h;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  gap: 5px;
  padding: 0 1px;
}

.gate-col__pill-bar {
  flex: 1;
  max-width: 11px;
  min-width: 0;
  align-self: flex-end;
  border-radius: 3px 3px 1px 1px;

  &--cur {
    background: $water-actual;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.28);
  }

  &--tgt {
    background: $water-predict;
  }
}

.dam-gate-detail {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 16px 24px;
  margin: 0 18px 10px;
  padding: 10px 16px;
  border-radius: 10px;
  background: #f8fafc;
  border: 1px solid #eef2f6;
  font-size: 13px;
  color: #64748b;

  &__name {
    font-weight: 600;
    color: #1e293b;
  }

  &__item {
    display: inline-flex;
    align-items: center;
    gap: 6px;

    strong {
      font-family: 'SF Mono', Consolas, monospace;
      font-size: 15px;
      color: #1e293b;
    }
  }
}
</style>
