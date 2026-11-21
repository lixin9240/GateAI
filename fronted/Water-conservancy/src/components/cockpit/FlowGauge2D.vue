<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    label?: string
    value: number
    unit?: string
    min?: number
    max?: number
    size?: 'sm' | 'md' | 'lg'
    variant?: 'inflow' | 'outflow'
  }>(),
  {
    label: '',
    unit: 'm³/s',
    min: 0,
    max: 3500,
    size: 'lg',
    variant: 'inflow',
  },
)

const fillPct = computed(() => {
  const span = Math.max(props.max - props.min, 1)
  const t = (props.value - props.min) / span
  return Math.min(96, Math.max(props.value > 0 ? 8 : 0, t * 100))
})

const displayValue = computed(() => Math.round(props.value).toLocaleString('zh-CN'))
</script>

<template>
  <div class="flow-gauge" :class="[`flow-gauge--${size}`, `flow-gauge--${variant}`]">
    <span v-if="label" class="flow-gauge__label">{{ label }}</span>

    <div class="flow-gauge__body" aria-hidden="true">
      <div class="flow-gauge__track">
        <div class="flow-gauge__fill" :style="{ height: `${fillPct}%` }">
          <span
            v-for="i in 6"
            :key="i"
            class="flow-gauge__stream"
            :style="{ animationDelay: `${i * 0.18}s` }"
          />
        </div>
        <div class="flow-gauge__ticks">
          <i v-for="i in 5" :key="i" />
        </div>
      </div>
      <div class="flow-gauge__arrow" />
    </div>

    <div class="flow-gauge__reading">
      <strong>{{ displayValue }}</strong>
      <em>{{ unit }}</em>
    </div>
  </div>
</template>

<style scoped lang="scss">
.flow-gauge {
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

  &__body {
    display: flex;
    align-items: stretch;
    gap: 8px;
    width: 100%;
    justify-content: center;
  }

  &__track {
    position: relative;
    flex: 1;
    max-width: 88px;
    border-radius: 6px;
    overflow: hidden;
    background: linear-gradient(180deg, #f8fafc 0%, #eef2f6 100%);
    border: 1px solid rgba(14, 165, 233, 0.22);
    box-shadow: inset 0 0 12px rgba(14, 165, 233, 0.06);
  }

  &__fill {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    border-radius: 4px 4px 0 0;
    overflow: hidden;
    transition: height 0.45s cubic-bezier(0.22, 1, 0.36, 1);
    background: linear-gradient(
      180deg,
      rgba(34, 211, 238, 0.55) 0%,
      rgba(14, 165, 233, 0.88) 45%,
      rgba(2, 132, 199, 0.95) 100%
    );
  }

  &--outflow &__fill {
    background: linear-gradient(
      180deg,
      rgba(52, 211, 153, 0.5) 0%,
      rgba(16, 185, 129, 0.85) 45%,
      rgba(5, 150, 105, 0.95) 100%
    );
  }

  &__stream {
    position: absolute;
    left: 15%;
    right: 15%;
    height: 3px;
    border-radius: 2px;
    background: rgba(255, 255, 255, 0.75);
    animation: flow-rise 1.6s ease-in-out infinite;
    opacity: 0.85;
  }

  &__ticks {
    position: absolute;
    inset: 8px 6px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    pointer-events: none;
    z-index: 2;

    i {
      display: block;
      height: 1px;
      background: rgba(100, 116, 139, 0.18);
    }
  }

  &__arrow {
    width: 14px;
    align-self: center;
    height: 28px;
    background: currentColor;
    color: #0ea5e9;
    clip-path: polygon(50% 0%, 0% 40%, 35% 40%, 35% 100%, 65% 100%, 65% 40%, 100% 40%);
    opacity: 0.75;
    flex-shrink: 0;
  }

  &--outflow &__arrow {
    color: #10b981;
    transform: rotate(180deg);
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
      color: #0ea5e9;
    }

    em {
      font-style: normal;
      font-size: 12px;
      color: #94a3b8;
    }
  }

  &--outflow &__reading strong {
    color: #10b981;
  }

  &--sm &__track { height: 120px; }
  &--md &__track { height: 168px; }
  &--lg &__track { height: 200px; }

  &--lg &__reading strong { font-size: 26px; }
}

@keyframes flow-rise {
  0% {
    bottom: 8%;
    opacity: 0;
  }
  20% { opacity: 0.9; }
  100% {
    bottom: 88%;
    opacity: 0;
  }
}
</style>
