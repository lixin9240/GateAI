<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'
import { ElMessageBox } from 'element-plus'
import { useDispatchStore } from '@/stores/dispatch'

const route = useRoute()
const router = useRouter()
const store = useDispatchStore()

const tabs = [
  { path: '/dispatch/control', label: '运行控制' },
  { path: '/dispatch/gates', label: '节点控制' },
  { path: '/dispatch/analysis', label: '决策分析' },
]

const activeTab = computed(() => route.path)

function goTab(path: string) {
  if (path === route.path) return
  if (store.pendingChanges > 0 && route.path.endsWith('/gates') && path !== '/dispatch/gates') {
    ElMessageBox.confirm(
      `节点控制有 ${store.pendingChanges} 处开度未提交，确认离开？`,
      '未提交的变更',
      { type: 'warning' },
    )
      .then(() => router.push(path))
      .catch(() => {})
    return
  }
  router.push(path)
}

onBeforeRouteLeave((_to, from, next) => {
  if (from.path.endsWith('/gates') && store.pendingChanges > 0) {
    ElMessageBox.confirm(
      `有 ${store.pendingChanges} 处开度未提交，确认离开调度决策？`,
      '未提交的变更',
      { type: 'warning' },
    )
      .then(() => next())
      .catch(() => next(false))
    return
  }
  next()
})
</script>

<template>
  <div class="dispatch-shell">
    <nav class="dispatch-tabs">
      <button
        v-for="tab in tabs"
        :key="tab.path"
        type="button"
        class="dispatch-tabs__item"
        :class="{ active: activeTab === tab.path }"
        @click="goTab(tab.path)"
      >
        {{ tab.label }}
        <span v-if="tab.path === '/dispatch/gates' && store.pendingChanges > 0" class="dispatch-tabs__badge">
          {{ store.pendingChanges }}
        </span>
      </button>
    </nav>
    <div class="dispatch-shell__body">
      <router-view />
    </div>
  </div>
</template>

<style scoped lang="scss">
.dispatch-shell {
  display: flex;
  flex-direction: column;
  min-height: calc(100vh - 56px);
  background: #fff;
}

.dispatch-tabs {
  display: flex;
  gap: 4px;
  padding: 12px 16px 0;
  background: #fff;
  border-bottom: 1px solid #e2e8f0;
  flex-shrink: 0;

  &__item {
    position: relative;
    padding: 10px 20px;
    border: none;
    border-radius: 8px 8px 0 0;
    background: transparent;
    color: #64748b;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: color 0.2s, background 0.2s;

    &:hover {
      color: #1890ff;
      background: rgba(24, 144, 255, 0.06);
    }

    &.active {
      color: #1890ff;
      background: #fff;
      box-shadow: inset 0 -2px 0 #1890ff;
    }
  }

  &__badge {
    position: absolute;
    top: 4px;
    right: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 9px;
    background: #f59e0b;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
  }
}

.dispatch-shell__body {
  flex: 1;
  min-height: 0;
  overflow: auto;
  background: #fff;
}
</style>

<style lang="scss">
.dispatch-shell__body > * {
  min-height: 100%;
}
</style>
