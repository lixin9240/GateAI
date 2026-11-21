<script setup lang="ts">
import { ref, computed } from 'vue'

export interface BarChartItem {
  label: string
  value: number
}

const props = withDefaults(
  defineProps<{
    items: BarChartItem[]
    max?: number
    unit?: string
    compact?: boolean
    showValue?: boolean
  }>(),
  {
    max: 100,
    unit: '%',
    compact: false,
    showValue: true,
  },
)

const hoveredIndex = ref<number | null>(null)

const yTicks = computed(() => {
  const max = props.max
  return [max, Math.round(max / 2), 0]
})

function barHeight(value: number) {
  return `${Math.min(100, Math.max(0, (value / props.max) * 100))}%`
}

function onEnter(i: number) {
  hoveredIndex.value = i
}

function onLeave() {
  hoveredIndex.value = null
}
</script>

<template>
  <div class="bar-chart"
:class="{ 'bar-chart--compact': compact }">
    <div class="bar-chart__y-axis">
      <span v-for="(tick, i) in yTicks"
:key="i">{{ tick }}</span>
    </div>
    <div class="bar-chart__plot">
      <div class="bar-chart__grid">
        <div v-for="(_, i) in yTicks"
:key="i" class="bar-chart__grid-line" />
      </div>
      <div class="bar-chart__bars">
        <div
          v-for="(item, i) in items"
          :key="i"
          class="bar-chart__col"
          @mouseenter="onEnter(i)"
          @mouseleave="onLeave"
        >
          <Transition name="tooltip-fade">
            <div v-if="hoveredIndex === i" class="bar-chart__tooltip">
              <strong>{{ item.label }}</strong>
              <span>{{ item.value }}{{ unit }}</span>
            </div>
          </Transition>
          <div class="bar-chart__bar-area">
            <div
              class="bar-chart__bar"
              :class="{ 'is-hovered': hoveredIndex === i }"
              :style="{ height: barHeight(item.value) }"
            >
              <span v-if="showValue"
class="bar-chart__bar-val">{{ item.value }}</span>
            </div>
          </div>
          <span class="bar-chart__x-label">{{ item.label }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
@use '@/assets/styles/cockpit.scss' as *;

.bar-chart {
  display: flex;
  gap: 8px;
  padding: 8px 4px 4px;
  min-height: 148px;
  overflow: visible;

  &--compact {
    min-height: 120px;

    .bar-chart__plot {
      height: 88px;
    }

    .bar-chart__bar-area {
      height: 72px;
    }
  }

  &__y-axis {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    flex-shrink: 0;
    width: 28px;
    height: 100px;
    padding-bottom: 22px;
    font-size: 11px;
    color: $cockpit-text-dim;
    text-align: right;
    line-height: 1;
  }

  &__plot {
    position: relative;
    flex: 1;
    min-width: 0;
    height: 100px;
    margin-bottom: 22px;
  }

  &__grid {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    pointer-events: none;
  }

  &__grid-line {
    height: 1px;
    background: rgba(24, 144, 255, 0.12);
    border-top: 1px dashed rgba(24, 144, 255, 0.08);
  }

  &__bars {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: flex-end;
    gap: 10px;
    height: 100%;
    padding-top: 6px;
  }

  &__col {
    position: relative;
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 0;
    cursor: pointer;
  }

  &__bar-area {
    width: 100%;
    height: 88px;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    padding: 0 2px;
  }

  &__bar {
    width: 100%;
    max-width: 48px;
    min-height: 4px;
    border-radius: 6px 6px 2px 2px;
    background: linear-gradient(180deg, $cockpit-accent 0%, rgba(24, 144, 255, 0.25) 100%);
    box-shadow: 0 2px 8px rgba(24, 144, 255, 0.2);
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding-top: 6px;
    transition:
      transform 0.25s ease,
      box-shadow 0.25s ease,
      filter 0.25s ease,
      background 0.25s ease;

    &.is-hovered {
      transform: translateY(-3px) scaleX(1.06);
      background: linear-gradient(180deg, #40a9ff 0%, rgba(24, 144, 255, 0.45) 100%);
      box-shadow:
        0 6px 20px rgba(24, 144, 255, 0.45),
        0 0 12px rgba(24, 144, 255, 0.25);
      filter: brightness(1.08);
    }
  }

  &__bar-val {
    font-size: 12px;
    font-weight: 700;
    color: #fff;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    line-height: 1;
  }

  &__x-label {
    position: absolute;
    bottom: -20px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 11px;
    color: $cockpit-text-dim;
    white-space: nowrap;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  &__tooltip {
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%);
    z-index: 10;
    padding: 8px 12px;
    background: rgba(255, 255, 255, 0.98);
    border: 1px solid rgba(24, 144, 255, 0.35);
    border-radius: 8px;
    box-shadow: 0 4px 16px rgba(24, 144, 255, 0.18);
    white-space: nowrap;
    pointer-events: none;

    strong {
      display: block;
      font-size: 12px;
      color: $cockpit-text;
      margin-bottom: 2px;
    }

    span {
      font-size: 14px;
      font-weight: 700;
      color: $cockpit-accent;
    }

    &::after {
      content: '';
      position: absolute;
      left: 50%;
      bottom: -5px;
      transform: translateX(-50%) rotate(45deg);
      width: 8px;
      height: 8px;
      background: #fff;
      border-right: 1px solid rgba(24, 144, 255, 0.35);
      border-bottom: 1px solid rgba(24, 144, 255, 0.35);
    }
  }
}

.tooltip-fade-enter-active,
.tooltip-fade-leave-active {
  transition:
    opacity 0.15s ease,
    transform 0.15s ease;
}

.tooltip-fade-enter-from,
.tooltip-fade-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(4px);
}
</style>
