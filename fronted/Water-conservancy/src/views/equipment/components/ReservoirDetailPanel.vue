<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  ElCard,
  ElDescriptions,
  ElDescriptionsItem,
  ElTag,
  ElButton,
  ElSkeleton,
} from 'element-plus'
import type { ReservoirDetail } from '@/api/reservoir'
import { getReservoirDetail, fetchReservoirPhysicsSummary } from '@/api/reservoir'
import type { PhysicsGuardSummary } from '@/types/dispatch'
import { buildSettingsPath } from '@/constants/settings'

const props = defineProps<{ reservoirId: number; equipmentCount?: number }>()

const router = useRouter()
const loading = ref(false)
const detail = ref<ReservoirDetail | null>(null)
const physics = ref<PhysicsGuardSummary | null>(null)

const SYNC_STATUS_MAP: Record<string, { label: string; type: 'success' | 'warning' | 'danger' }> = {
  synced: { label: '已同步', type: 'success' },
  stale: { label: '待同步', type: 'warning' },
  offline: { label: '离线缓存', type: 'danger' },
}

async function load() {
  if (!props.reservoirId) return
  loading.value = true
  try {
    const [res, pg] = await Promise.all([
      getReservoirDetail(props.reservoirId, props.equipmentCount ?? 0),
      fetchReservoirPhysicsSummary(props.reservoirId),
    ])
    detail.value = res.data ?? null
    physics.value = pg
  } finally {
    loading.value = false
  }
}

watch(() => [props.reservoirId, props.equipmentCount], load)
onMounted(load)
</script>

<template>
  <ElCard v-loading="loading" shadow="never" class="reservoir-panel">
    <template #header>
      <div class="reservoir-panel__head">
        <span class="reservoir-panel__title">水库详情</span>
        <span v-if="detail" class="reservoir-panel__sub"
          >{{ detail.name }} · {{ detail.code }}</span
        >
      </div>
    </template>

    <template v-if="detail">
      <ElDescriptions :column="3" border size="small" class="reservoir-panel__base">
        <ElDescriptionsItem label="调节类型">{{ detail.type_label }}</ElDescriptionsItem>
        <ElDescriptionsItem label="正常蓄水位"
          >{{ detail.normal_water_level }} m</ElDescriptionsItem
        >
        <ElDescriptionsItem label="装机容量"
          >{{ (detail.installed_capacity / 10000).toFixed(0) }} 万 kW</ElDescriptionsItem
        >
        <ElDescriptionsItem label="运行状态">
          <ElTag :type="detail.status === 'normal' ? 'success' : 'warning'" size="small">
            {{ detail.status === 'normal' ? '正常运行' : '维护中' }}
          </ElTag>
        </ElDescriptionsItem>
        <ElDescriptionsItem label="边缘节点">{{ detail.edge_node_count }} 个</ElDescriptionsItem>
        <ElDescriptionsItem label="关联设备">{{ detail.equipment_count }} 台</ElDescriptionsItem>
      </ElDescriptions>

      <div v-if="physics" class="reservoir-panel__physics">
        <div class="reservoir-panel__physics-head">
          <h4>物理防护配置摘要</h4>
          <ElButton
            type="primary"
            link
            size="small"
            @click="router.push(buildSettingsPath('physics-guard', { reservoir_id: props.reservoirId }))"
          >
            管理配置 →
          </ElButton>
        </div>
        <div class="reservoir-panel__physics-bar">
          <ElTag type="info" effect="plain">v{{ physics.config_version }}</ElTag>
          <ElTag :type="SYNC_STATUS_MAP[physics.sync_status]?.type ?? 'info'" size="small">
            {{ SYNC_STATUS_MAP[physics.sync_status]?.label ?? physics.sync_status }}
          </ElTag>
          <span
            >紧急 <strong>{{ physics.upstream_emergency }} m</strong></span
          >
          <span
            >危险 <strong>{{ physics.upstream_danger }} m</strong></span
          >
          <span
            >预警 <strong>{{ physics.upstream_warning }} m</strong></span
          >
          <span
            >L3 置信度 <strong>{{ physics.fusion_l3_confidence }}</strong></span
          >
          <span v-if="physics.last_sync_at" class="reservoir-panel__sync"
            >同步 {{ physics.last_sync_at }}</span
          >
        </div>
      </div>
    </template>

    <ElSkeleton v-else-if="loading" :rows="3" animated />
  </ElCard>
</template>

<style scoped lang="scss">
.reservoir-panel {
  flex-shrink: 0;

  &__head {
    display: flex;
    align-items: baseline;
    gap: 12px;
  }

  &__title {
    font-weight: 600;
    font-size: var(--font-size-lg);
  }

  &__sub {
    font-size: var(--font-size-sm);
    color: var(--color-text-secondary);
  }

  &__base {
    margin-bottom: 14px;
  }

  &__physics-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;

    h4 {
      margin: 0;
      font-size: var(--font-size-base);
      font-weight: 600;
      color: var(--color-text);
    }
  }

  &__physics-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px 18px;
    padding: 12px 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: var(--font-size-sm);
    color: var(--color-text-secondary);

    strong {
      color: var(--color-text);
      font-weight: 600;
    }
  }

  &__sync {
    margin-left: auto;
    font-size: var(--font-size-xs);
  }
}
</style>
