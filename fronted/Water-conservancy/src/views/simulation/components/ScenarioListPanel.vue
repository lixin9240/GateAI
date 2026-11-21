<script setup lang="ts">
import { ElButton, ElEmpty, ElTooltip } from 'element-plus'
import type { SimulationScenarioItem } from '@/types/simulation'

defineProps<{
  scenarios: SimulationScenarioItem[]
  loading?: boolean
  selectedId?: number | null
  /** 当前是否处于场景库联动模式 */
  linked?: boolean
}>()

const emit = defineEmits<{
  refresh: []
  create: []
  edit: [item: SimulationScenarioItem]
  delete: [item: SimulationScenarioItem]
  select: [item: SimulationScenarioItem]
}>()

const TYPE_MAP: Record<string, string> = {
  production: '生产',
  energy: '能源',
  fault: '故障',
}

function canDelete(item: SimulationScenarioItem) {
  return (item.usage_count ?? 0) <= 0
}

function formatDuration(sec?: number) {
  if (!sec) return '—'
  const h = Math.floor(sec / 3600)
  const m = Math.floor((sec % 3600) / 60)
  if (h > 0) return `${h}h${m > 0 ? `${m}m` : ''}`
  return `${m}m`
}
</script>

<template>
  <div class="scenario-panel">
    <div class="scenario-panel__toolbar">
      <ElButton size="small" type="primary" @click="emit('create')">新建场景</ElButton>
      <ElButton size="small" :loading="loading" @click="emit('refresh')">刷新</ElButton>
    </div>
    <ElEmpty v-if="!scenarios.length && !loading" description="暂无场景，请新建" />
    <ul v-else class="scenario-panel__list">
      <li
        v-for="item in scenarios"
        :key="item.id"
        class="scenario-panel__item"
        :class="{ 'scenario-panel__item--active': linked && selectedId === item.id }"
        role="button"
        tabindex="0"
        @click="emit('select', item)"
        @keydown.enter="emit('select', item)"
      >
        <div class="scenario-panel__head">
          <strong>{{ item.name }}</strong>
        </div>
        <div class="scenario-panel__meta">
          #{{ item.id }}
          · {{ TYPE_MAP[item.type] ?? item.type }}
          · {{ formatDuration(item.duration) }}
          <template v-if="item.speed"> · {{ item.speed }}x</template>
          <template v-if="(item.usage_count ?? 0) > 0"> · 已仿真{{ item.usage_count }}次</template>
        </div>
        <div class="scenario-panel__actions">
          <ElButton link type="primary" @click.stop="emit('edit', item)">编辑</ElButton>
          <ElTooltip
            v-if="!canDelete(item)"
            content="该场景已有仿真记录，不可删除"
            placement="top"
          >
            <ElButton link type="danger" disabled>删除</ElButton>
          </ElTooltip>
          <ElButton v-else link type="danger" @click.stop="emit('delete', item)">删除</ElButton>
        </div>
      </li>
    </ul>
  </div>
</template>

<style scoped lang="scss">
@use '@/assets/styles/cockpit.scss' as *;

.scenario-panel {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  height: 100%;
  font-size: $cockpit-font-base;

  &__toolbar {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
    flex-shrink: 0;

    :deep(.el-button) {
      padding: 6px 12px;
      font-size: $cockpit-font-sm;
      height: auto;
    }
  }

  &__list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 4px;
    scrollbar-width: thin;
    scrollbar-color: rgba(24, 144, 255, 0.35) transparent;

    &::-webkit-scrollbar {
      width: 6px;
    }

    &::-webkit-scrollbar-thumb {
      background: rgba(24, 144, 255, 0.35);
      border-radius: 3px;
    }
  }

  &__item {
    padding: 8px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: #f8fafc;
    cursor: pointer;
    transition:
      border-color 0.2s ease,
      background 0.2s ease,
      box-shadow 0.2s ease;

    &:hover {
      border-color: rgba(24, 144, 255, 0.35);
      background: #f0f7ff;
    }

    &--active {
      border-color: rgba(24, 144, 255, 0.55);
      background: linear-gradient(180deg, rgba(24, 144, 255, 0.12), rgba(24, 144, 255, 0.04));
      box-shadow: 0 2px 8px rgba(24, 144, 255, 0.12);

      .scenario-panel__head strong {
        color: #1890ff;
      }
    }
  }

  &__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    font-size: $cockpit-font-base;

    strong {
      font-size: $cockpit-font-base;
      font-weight: 600;
    }
  }

  &__meta {
    margin-top: 4px;
    font-size: $cockpit-font-sm;
    color: #64748b;
  }

  &__actions {
    margin-top: 6px;
    display: flex;
    gap: 8px;

    :deep(.el-button) {
      padding: 0;
      font-size: $cockpit-font-sm;
      height: auto;
    }
  }
}
</style>
