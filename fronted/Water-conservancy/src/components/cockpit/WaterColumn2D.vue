<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    label?: string
    value: number
    targetValue?: number
    unit?: string
    min?: number
    max?: number
    size?: 'xs' | 'sm' | 'md' | 'lg'
    mode?: 'level' | 'percent'
    leafFromTop?: number
    showMeta?: boolean
    stretch?: boolean
    quiet?: boolean
  }>(),
  {
    label: '',
    unit: 'm',
    min: 0,
    max: 100,
    size: 'md',
    mode: 'level',
    showMeta: true,
    stretch: false,
    quiet: false,
  },
)

function toFillPct(v: number) {
  if (props.mode === 'percent') {
    return Math.min(96, Math.max(v > 0 ? 6 : 0, v))
  }
  const span = Math.max(props.max - props.min, 1)
  const t = (v - props.min) / span
  return Math.min(96, Math.max(6, t * 100))
}

const fillPct = computed(() => toFillPct(props.value))
const targetFillPct = computed(() =>
  props.targetValue != null ? toFillPct(props.targetValue) : null,
)
const leafPct = computed(() =>
  props.leafFromTop != null ? Math.min(100, Math.max(0, props.leafFromTop)) : 0,
)

const showTarget = computed(() => targetFillPct.value != null)
const showBubbles = computed(() => !props.quiet && fillPct.value > 12)

const displayValue = computed(() => {
  if (props.unit === 'm³/s') return props.value.toFixed(0)
  return props.value.toFixed(1)
})

const bubbleSeeds = [12, 28, 44, 58, 72, 86]
</script>

<template>
  <div
    class="water-col"
    :class="[
      `water-col--${size}`,
      {
        'water-col--stretch': stretch,
        'water-col--compact': !showMeta,
        'water-col--quiet': quiet,
      },
    ]"
  >
    <span v-if="showMeta && label" class="water-col__label">{{ label }}</span>

    <div class="water-col__tube" aria-hidden="true">
      <div class="water-col__shell">
        <div class="water-col__glass-shine" />
        <div
          v-if="showTarget"
          class="water-col__water water-col__water--tgt"
          :style="{ height: `${targetFillPct}%` }"
        />
        <div class="water-col__water water-col__water--cur" :style="{ height: `${fillPct}%` }">
          <svg
            v-if="fillPct > 0"
            class="water-col__wave"
            viewBox="0 0 120 16"
            preserveAspectRatio="none"
          >
            <path
              d="M0 8 C20 2 40 14 60 8 S100 2 120 8 V16 H0 Z"
              class="water-col__wave-path water-col__wave-path--a"
            />
            <path
              d="M0 10 C20 16 40 4 60 10 S100 16 120 10 V16 H0 Z"
              class="water-col__wave-path water-col__wave-path--b"
            />
          </svg>
          <span
            v-for="(x, i) in bubbleSeeds"
            v-show="showBubbles"
            :key="i"
            class="water-col__bubble"
            :style="{
              left: `${x}%`,
              animationDelay: `${i * 0.55}s`,
              animationDuration: `${2.4 + (i % 3) * 0.35}s`,
            }"
          />
        </div>
        <div v-if="leafPct > 0" class="water-col__leaf" :style="{ height: `${leafPct}%` }" />
      </div>
    </div>

    <div v-if="showMeta" class="water-col__reading">
      <strong>{{ displayValue }}</strong>
      <em>{{ unit }}</em>
    </div>
  </div>
</template>

<style scoped lang="scss">
.water-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  min-width: 0;

  &__label {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    letter-spacing: 0.04em;
  }

  &__tube {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: stretch;
    width: 100%;
    height: 100%;
    min-height: 0;
  }

  &__shell {
    position: relative;
    width: 100%;
    height: 100%;
    border-radius: 5px;
    overflow: hidden;
    background: linear-gradient(180deg, #f8fafc 0%, #edf4fc 100%);
    border: 1px solid rgba(24, 144, 255, 0.16);
    box-shadow: inset 0 2px 8px rgba(15, 23, 42, 0.04);
  }

  &__glass-shine {
    position: absolute;
    inset: 0;
    background: linear-gradient(
      90deg,
      transparent 0%,
      rgba(255, 255, 255, 0.28) 24%,
      transparent 48%
    );
    pointer-events: none;
    z-index: 4;
  }

  &__water {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    border-radius: 3px 3px 0 0;
    transition: height 0.45s cubic-bezier(0.22, 1, 0.36, 1);
    overflow: hidden;

    &--tgt {
      z-index: 1;
      background: linear-gradient(180deg, #d0ebff 0%, #91d5ff 100%);
    }

    &--cur {
      z-index: 2;
      background: linear-gradient(180deg, #5cadff 0%, #228be6 52%, #1864ab 100%);
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.3);
    }
  }

  &__leaf {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    z-index: 5;
    background: linear-gradient(180deg, #f8fafc 0%, #edf4fc 100%);
    pointer-events: none;
  }

  &__wave {
    position: absolute;
    top: -6px;
    left: -50%;
    width: 200%;
    height: 14px;
    opacity: 0.85;
  }

  &__wave-path {
    fill: rgba(186, 224, 255, 0.75);

    &--a {
      animation: wave-drift 3.2s linear infinite;
    }

    &--b {
      opacity: 0.65;
      animation: wave-drift 4.6s linear infinite reverse;
    }
  }

  &__bubble {
    position: absolute;
    bottom: 8%;
    width: 3px;
    height: 3px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.75);
    box-shadow: 0 0 4px rgba(255, 255, 255, 0.6);
    animation: bubble-rise linear infinite;
    opacity: 0;
  }

  &__reading {
    display: flex;
    align-items: baseline;
    gap: 4px;
    line-height: 1;

    strong {
      font-size: 22px;
      font-weight: 700;
      font-family: 'SF Mono', Consolas, monospace;
      color: #228be6;
    }

    em {
      font-style: normal;
      font-size: 12px;
      color: #94a3b8;
    }
  }

  &--xs {
    .water-col__shell { min-height: 40px; }
    .water-col__bubble { width: 2px; height: 2px; }
    .water-col__wave { height: 10px; top: -5px; }
  }

  &--sm {
    .water-col__shell { height: 120px; }
    .water-col__reading strong { font-size: 18px; }
  }

  &--md {
    .water-col__shell { height: 168px; }
  }

  &--lg {
    .water-col__shell { height: 220px; }
    .water-col__reading strong { font-size: 26px; }
  }

  &--stretch {
    display: flex;
    flex-direction: column;
    height: 100%;
    width: 100%;
    min-height: 0;
    gap: 0;

    .water-col__tube {
      flex: 1;
      min-height: 0;
    }

    .water-col__shell {
      flex: 1;
      height: auto;
      min-height: 40px;
    }
  }

  &--compact { gap: 0; }

  &--quiet {
    .water-col__wave-path { animation: none !important; }
  }
}

@keyframes wave-drift {
  from { transform: translateX(0); }
  to { transform: translateX(50%); }
}

@keyframes bubble-rise {
  0% {
    transform: translateY(0) scale(0.6);
    opacity: 0;
  }
  12% { opacity: 0.85; }
  80% { opacity: 0.35; }
  100% {
    transform: translateY(-72px) scale(1.1);
    opacity: 0;
  }
}
</style>
