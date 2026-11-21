<script setup lang="ts">
// ============================================================
// 驾驶舱模块快捷导航 — 仅告警 / 调度 / 数字孪生三页
// ============================================================
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { MENU_ITEMS, COCKPIT_MODULE_PATHS } from '@/constants'
import { usePermission } from '@/composables/usePermission'

const route = useRoute()
const router = useRouter()
const { hasRoutePermission } = usePermission()

const cockpitMenus = computed(() =>
  MENU_ITEMS.filter(
    (item) =>
      COCKPIT_MODULE_PATHS.includes(item.path as (typeof COCKPIT_MODULE_PATHS)[number]) &&
      hasRoutePermission(item.path),
  ),
)

function isActive(path: string) {
  return route.path === path
}

function navigate(path: string) {
  if (route.path !== path) router.push(path)
}
</script>

<template>
  <nav class="app-top-nav"
aria-label="驾驶舱模块导航">
    <div class="app-top-nav__inner">
      <button
        v-for="item in cockpitMenus"
        :key="item.path"
        type="button"
        class="app-top-nav__item"
        :class="{ 'is-active': isActive(item.path) }"
        @click="navigate(item.path)"
      >
        {{ item.title }}
      </button>
    </div>
  </nav>
</template>

<style scoped lang="scss">
@use '@/assets/styles/admin-glass.scss' as glass;

.app-top-nav {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 10px var(--spacing-xl);
  background: transparent;
  border-bottom: 1px solid rgba(24, 144, 255, 0.08);

  &__inner {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
  }

  &__item {
    flex-shrink: 0;
    padding: 12px 28px;
    font-size: 16px;
    font-weight: 500;
    line-height: 1.4;
    color: #475569;
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid rgba(24, 144, 255, 0.14);
    border-radius: 20px;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s ease;
    @include glass.btn-hover-light;

    &.is-active {
      color: #fff;
      background: linear-gradient(135deg, #1890ff 0%, #0ea5e9 100%);
      border-color: transparent;
      font-weight: 600;
      box-shadow: 0 3px 14px rgba(24, 144, 255, 0.35);
    }
  }
}
</style>
