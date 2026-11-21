<script setup lang="ts">
// ============================================================
// 驾驶舱页面壳 — 预留扩展；数字孪生页已改为与 MainLayout 标签栏直连
// ============================================================
</script>

<template>
  <div class="cockpit-shell">
    <slot />
  </div>
</template>

<style scoped lang="scss">
@use '@/assets/styles/cockpit.scss' as *;

.cockpit-shell {
  min-height: 100%;
  @include cockpit-typography;
}
</style>
