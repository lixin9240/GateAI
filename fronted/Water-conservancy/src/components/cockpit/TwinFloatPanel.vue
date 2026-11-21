<script setup lang="ts">
defineProps<{
  title: string
  side?: 'left' | 'right'
}>()
</script>

<template>
  <div class="twin-panel"
:class="`twin-panel--${side ?? 'left'}`">
    <header class="twin-panel__head">
      <span class="twin-panel__bar" />
      {{ title }}
    </header>
    <div class="twin-panel__body">
      <slot />
    </div>
  </div>
</template>

<style scoped lang="scss">
@use '@/assets/styles/cockpit.scss' as *;

.twin-panel {
  @include glass-panel-3d;
  position: relative;
  pointer-events: auto;

  &__head {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px 8px;
    font-size: $cockpit-font-sm;
    font-weight: 700;
    color: $cockpit-text;
    letter-spacing: 0.04em;
    border-bottom: 1px solid rgba(24, 144, 255, 0.12);
  }

  &__bar {
    width: 3px;
    height: 14px;
    background: linear-gradient(180deg, $cockpit-accent, #3b82f6);
    border-radius: 2px;
  }

  &__body {
    padding: 12px 14px 14px;
    color: $cockpit-text;
    font-size: $cockpit-font-sm;
  }
}
</style>
