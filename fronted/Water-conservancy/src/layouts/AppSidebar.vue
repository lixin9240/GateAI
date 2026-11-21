<script setup lang="ts">
// ============================================================
// 侧边栏 — 九大板块导航（Logo 已移至 MainLayout 顶栏）
// ============================================================
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { ElMenu, ElMenuItem, ElSubMenu, ElIcon } from 'element-plus'
import {
  Monitor,
  Ship,
  Switch,
  DataAnalysis,
  VideoCamera,
  Clock,
  Warning,
  Operation,
  Cpu,
  SetUp,
  Setting,
  User,
  MagicStick,
} from '@element-plus/icons-vue'
import { MENU_ITEMS } from '@/constants'
import { usePermission } from '@/composables/usePermission'

defineProps<{
  collapsed: boolean
}>()

const route = useRoute()
const { hasRoutePermission } = usePermission()

const iconMap: Record<string, any> = {
  Monitor,
  Ship,
  Switch,
  DataAnalysis,
  VideoCamera,
  Clock,
  Warning,
  Operation,
  Cpu,
  SetUp,
  Setting,
  User,
  MagicStick,
}

const visibleMenus = computed(() =>
  MENU_ITEMS.filter((item) => {
    if (item.children?.length) return item.children.some((c) => hasRoutePermission(c.path))
    return hasRoutePermission(item.path)
  }),
)
</script>

<template>
  <aside class="app-sidebar"
:class="{ 'is-collapsed': collapsed }">
    <ElMenu
      :default-active="route.path"
      :collapse="collapsed"
      background-color="transparent"
      text-color="var(--color-layout-blue-text-secondary)"
      active-text-color="var(--color-layout-blue-brand)"
      router
    >
      <template v-for="item in visibleMenus" :key="item.path">
        <ElSubMenu
v-if="item.children?.length" :index="item.path"
>
          <template #title>
            <ElIcon><component :is="iconMap[item.icon]" /></ElIcon>
            <span>{{ item.title }}</span>
          </template>
          <ElMenuItem v-for="c in item.children"
:key="`${item.path}-${c.path}-${c.title}`" :index="c.path">
            <span>{{ c.title }}</span>
          </ElMenuItem>
        </ElSubMenu>
        <ElMenuItem
v-else :index="item.path"
>
          <ElIcon><component :is="iconMap[item.icon]" /></ElIcon>
          <span>{{ item.title }}</span>
        </ElMenuItem>
      </template>
    </ElMenu>
  </aside>
</template>

<style scoped lang="scss">
@use '@/assets/styles/cockpit.scss' as *;

.app-sidebar {
  display: flex;
  flex-direction: column;
  width: 100%;
  height: 100%;
  background: var(--color-layout-gradient-sidebar);
  border-right: 1px solid var(--color-layout-blue-border);
  box-shadow: 4px 0 24px rgba(0, 0, 0, 0.2);
  overflow: hidden;

  :deep(.el-menu) {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    border-right: none;
    background: transparent;
    padding: 8px 0;
    --el-menu-base-level-padding: 14px;
    --el-menu-icon-width: 18px;
    @include hide-scrollbar;
  }

  // 一级菜单：可展开标题 与 普通项 左对齐
  :deep(.el-menu > .el-menu-item),
  :deep(.el-menu > .el-sub-menu > .el-sub-menu__title) {
    display: flex;
    align-items: center;
    height: 44px;
    line-height: 44px;
    margin: 4px 8px;
    padding-left: 14px !important;
    border-radius: var(--border-radius-sm);
    font-size: 16px;
    color: var(--color-layout-blue-text-secondary);
    transition: all 0.2s cubic-bezier(0.22, 1, 0.36, 1);
  }

  :deep(.el-menu > .el-menu-item) {
    padding-right: 14px !important;
  }

  :deep(.el-menu > .el-sub-menu > .el-sub-menu__title) {
    position: relative;
    padding-right: 36px !important;
  }

  :deep(.el-sub-menu .el-menu) {
    margin: 2px 8px 4px 26px;
    padding: 2px 0 4px;
    background: transparent;
    border-left: 1px solid rgba(255, 255, 255, 0.18);
  }

  // 二级菜单：缩进 + 引导线区分层级，颜色与一级保持一致
  :deep(.el-sub-menu .el-menu-item) {
    height: 44px;
    line-height: 44px;
    margin: 2px 0 2px 12px;
    padding-left: 16px !important;
    padding-right: 12px !important;
    border-radius: var(--border-radius-sm);
    font-size: 16px;
    color: var(--color-layout-blue-text-secondary);
    transition: all 0.2s cubic-bezier(0.22, 1, 0.36, 1);

    &:hover {
      background: rgba(255, 255, 255, 0.08) !important;
      color: var(--color-layout-blue-text);
    }

    &.is-active {
      background: var(--color-layout-blue-active) !important;
      color: var(--color-layout-blue-brand) !important;
      font-weight: 600;
      box-shadow: inset 0 0 24px rgba(110, 179, 255, 0.12);
    }
  }

  :deep(.el-menu > .el-sub-menu.is-opened > .el-sub-menu__title) {
    color: var(--color-layout-blue-text);
  }

  :deep(.el-menu > .el-menu-item .el-icon),
  :deep(.el-menu > .el-sub-menu > .el-sub-menu__title .el-icon) {
    margin-right: 10px !important;
    width: 18px;
    min-width: 18px;
    font-size: 18px;
    flex-shrink: 0;
    justify-content: center;
  }

  :deep(.el-menu > .el-sub-menu > .el-sub-menu__title .el-sub-menu__icon-arrow) {
    right: 14px;
  }

  :deep(.el-menu-item.is-active) {
    background: var(--color-layout-blue-active) !important;
    color: var(--color-layout-blue-brand) !important;
    font-weight: 600;
    border-left: none;
    box-shadow: inset 0 0 24px rgba(110, 179, 255, 0.12);
  }

  // ── 折叠态：图标统一居中 ──
  &.is-collapsed {
    :deep(.el-menu-item),
    :deep(.el-sub-menu__title) {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0 !important;
    }

    :deep(.el-menu-item .el-icon),
    :deep(.el-sub-menu__title .el-icon) {
      margin-right: 0 !important;
      font-size: 20px;
      text-align: center;
    }
  }
}
</style>

<style lang="scss">
// 侧边栏折叠后子菜单弹出层 — 深色背景 + 亮色文字
.el-menu--popup {
  background: linear-gradient(170deg, #0a1628 0%, #0d2137 35%, #112a45 100%) !important;
  border: 1px solid rgba(110, 179, 255, 0.2) !important;
  border-radius: 8px !important;
  padding: 6px 0 !important;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.45) !important;

  .el-menu-item {
    margin: 2px 6px;
    padding-left: 20px !important;
    border-radius: 6px;
    color: rgba(200, 215, 235, 0.85) !important;
    font-size: 16px;
    font-weight: 500;
    background: transparent !important;

    &:hover {
      color: #fff !important;
      background: rgba(255, 255, 255, 0.1) !important;
    }

    &.is-active {
      color: #6eb3ff !important;
      background: rgba(110, 179, 255, 0.12) !important;
      font-weight: 600 !important;
    }
  }
}
</style>
