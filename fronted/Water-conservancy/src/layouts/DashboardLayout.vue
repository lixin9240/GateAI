<script setup lang="ts">
// ============================================================
// 大屏布局 — 监控大屏全屏容器（无侧边栏）
// ============================================================
</script>

<template>
  <div class="dashboard-layout">
    <router-view />
  </div>
</template>

<style scoped lang="scss">
.dashboard-layout {
  width: 100vw;
  height: 100vh;
  overflow: hidden;
  background: var(--color-bg-dark);
}
</style>
