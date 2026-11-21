<script setup lang="ts">
// ============================================================
// 主布局 — 顶栏（Logo + 页眉一体）+ 侧边栏 + 内容区
// ============================================================
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElContainer, ElAside, ElMain } from 'element-plus'
import AppHeader from './AppHeader.vue'
import AppSidebar from './AppSidebar.vue'
import { pollLatestUnhandledAlarm } from '@/api/warning'
import { handlePushMessage } from '@/composables/useAlarmNotify'
import { APP_TITLE } from '@/constants'
import logoUrl from '@/assets/images/logo.png'
const route = useRoute()
const collapsed = ref(false)

/** 数字孪生等全幅驾驶舱页：内容区与顶栏无缝衔接，去掉双层边距 */
const isFlushPage = computed(() => route.path.startsWith('/simulation'))
/** 告警 / 调度：纯白内容区底 */
const isWhitePage = computed(() =>
  route.path.startsWith('/warning') || route.path.startsWith('/dispatch'),
)

// ── 全局告警通知：轮询真实后端，检测最新未处理告警并弹窗 ──
let alarmTimer: ReturnType<typeof setInterval> | null = null
let lastAlarmId = 0
async function pollAlarm() {
  try {
    const latest = await pollLatestUnhandledAlarm()
    if (!latest) return
    // 仅在新告警（id 递增）时弹窗，避免重复提示
    if (latest.alarm.id > lastAlarmId) {
      lastAlarmId = latest.alarm.id
      handlePushMessage({ type: 'alarm_new', data: latest.alarm, pendingCount: latest.pendingCount })
    }
  } catch {
    /* 静默 */
  }
}
onMounted(() => {
  pollAlarm()
  alarmTimer = setInterval(pollAlarm, 15000)
})
onUnmounted(() => {
  if (alarmTimer) clearInterval(alarmTimer)
})
</script>

<template>
  <el-container
    direction="vertical"
    class="main-layout"
    :class="{ 'main-layout--collapsed': collapsed }"
  >
    <header class="main-layout__topbar">
      <div class="main-layout__brand">
        <img :src="logoUrl" alt="logo" class="main-layout__logo" />
        <span v-if="!collapsed" class="main-layout__title">{{ APP_TITLE }}</span>
      </div>
      <AppHeader :collapsed="collapsed" @toggle-collapse="collapsed = !collapsed" />
    </header>

    <el-container class="main-layout__body">
      <el-aside :width="collapsed ? '64px' : 'var(--sider-width)'" class="main-layout__aside">
        <AppSidebar :collapsed="collapsed" />
      </el-aside>
      <el-main
        class="main-layout__content"
        :class="{
          'main-layout__content--flush': isFlushPage,
          'main-layout__content--white': isWhitePage,
        }"
      >
        <router-view />
      </el-main>
    </el-container>
  </el-container>
</template>

<style scoped lang="scss">
@use '@/assets/styles/cockpit.scss' as *;

.main-layout {
  height: 100vh;

  &__topbar {
    display: flex;
    flex-shrink: 0;
    align-items: stretch;
    height: var(--header-height);
    background: var(--color-layout-gradient-topbar);
  }

  &__brand {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    gap: var(--spacing-sm);
    width: var(--sider-width);
    padding: 0 var(--spacing-md);
    transition: width 0.3s;
  }

  &__logo {
    width: 34px;
    height: 34px;
    flex-shrink: 0;
    object-fit: contain;
  }

  &__title {
    font-size: var(--font-size-sm);
    font-weight: 500;
    line-height: 1.4;
    color: var(--color-layout-blue-text);
    letter-spacing: 0.5px;
  }

  &--collapsed &__brand {
    justify-content: center;
    width: 64px;
    padding: 0;
  }

  &--collapsed &__logo {
    width: 32px;
    height: 32px;
  }

  &__body {
    flex: 1;
    min-height: 0;
    overflow: hidden;
  }

  &__aside {
    overflow: hidden;
    transition: width 0.3s;
  }

  &__content {
    flex: 1;
    min-height: 0;
    background: #fff;
    padding: var(--spacing-lg);
    overflow-y: auto;
    @include hide-scrollbar;

    &--flush {
      padding: 0;
      overflow-x: hidden;
      overflow-y: auto;
      background: #ffffff;
    }

    &--white {
      background: #ffffff;
    }
  }
}
</style>
