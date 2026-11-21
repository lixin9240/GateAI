<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const props = defineProps<{
  active?: string
}>()

const route = useRoute()
const router = useRouter()

const navItems = [
  { label: '总览', path: '/simulation', key: 'simulation' },
  { label: '监测', path: '/dashboard', key: 'dashboard' },
  { label: '分析', path: '/history', key: 'history' },
  { label: '预警', path: '/warning', key: 'warning' },
  { label: '调度', path: '/dispatch', key: 'dispatch' },
  { label: '管理', path: '/equipment', key: 'equipment' },
]

const currentKey = computed(() => {
  if (props.active) return props.active
  const match = navItems.find((n) => route.path.startsWith(n.path))
  return match?.key ?? ''
})

function navigate(path: string) {
  if (route.path !== path) router.push(path)
}

const now = computed(() => {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}:${String(d.getSeconds()).padStart(2, '0')}`
})
</script>

<template>
  <header class="cockpit-header">
    <div class="cockpit-header__weather">
      <span>向家坝 · 晴 26°C</span>
      <span class="cockpit-header__wind">东北风 2级</span>
    </div>
    <div class="cockpit-header__title-wrap">
      <div class="cockpit-header__title-bg" />
      <h1 class="cockpit-header__title">
智慧水利数字孪生驾驶舱
</h1>
      <p class="cockpit-header__subtitle">
向家坝水电站阀门智能调度系统
</p>
    </div>
    <nav class="cockpit-header__nav">
      <button
        v-for="item in navItems"
        :key="item.key"
        class="cockpit-header__nav-btn"
        :class="{ 'is-active': currentKey === item.key }"
        @click="navigate(item.path)"
      >
        {{ item.label }}
      </button>
    </nav>
    <div class="cockpit-header__meta">
      <span class="cockpit-header__time">{{ now }}</span>
    </div>
  </header>
</template>

<style scoped lang="scss">
@use '@/assets/styles/cockpit.scss' as *;

.cockpit-header {
  display: grid;
  grid-template-columns: 180px 1fr auto 160px;
  align-items: center;
  gap: 16px;
  padding: 10px 20px;
  border-bottom: 1px solid rgba(30, 73, 118, 0.35);
  background: linear-gradient(180deg, #1a3a5c 0%, #122a45 55%, #0f2238 100%);
  backdrop-filter: blur(10px);

  &__weather {
    font-size: 11px;
    color: $cockpit-text-dim;
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  &__wind {
    color: rgba(0, 212, 255, 0.6);
  }

  &__title-wrap {
    position: relative;
    text-align: center;
  }

  &__title-bg {
    position: absolute;
    inset: -4px 20%;
    background: linear-gradient(90deg, transparent, rgba(0, 212, 255, 0.08), transparent);
    border-top: 1px solid rgba(0, 212, 255, 0.2);
    border-bottom: 1px solid rgba(0, 212, 255, 0.2);
    transform: perspective(400px) rotateX(8deg);
    pointer-events: none;
  }

  &__title {
    font-size: 18px;
    font-weight: 600;
    letter-spacing: 3px;
    color: rgba(232, 244, 255, 0.95);
    text-shadow: none;
    margin: 0;
  }

  &__subtitle {
    font-size: 11px;
    color: rgba(180, 200, 220, 0.75);
    margin: 2px 0 0;
    letter-spacing: 2px;
  }

  &__nav {
    display: flex;
    gap: 6px;
  }

  &__nav-btn {
    padding: 6px 14px;
    font-size: 12px;
    color: $cockpit-text-dim;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 6px;
    cursor: pointer;
    transform: perspective(600px) rotateX(4deg);
    transition: all 0.25s;
    letter-spacing: 1px;

    &:hover {
      color: $cockpit-cyan;
      border-color: rgba(0, 212, 255, 0.3);
      box-shadow: 0 4px 16px rgba(0, 212, 255, 0.15);
      transform: perspective(600px) rotateX(0deg) translateY(-1px);
    }

    &.is-active {
      color: $cockpit-cyan;
      background: rgba(0, 212, 255, 0.12);
      border-color: $cockpit-cyan;
      box-shadow: 0 0 20px rgba(0, 212, 255, 0.25);
      font-weight: 600;
    }
  }

  &__meta {
    text-align: right;
  }

  &__time {
    font-family: 'SF Mono', monospace;
    font-size: 12px;
    color: $cockpit-cyan;
    @include glow-text;
  }
}
</style>
