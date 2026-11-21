<script setup lang="ts">
// ============================================================
// 物理防护配置摘要卡片 (D-95 ~ D-98)
// 综合概览右侧栏 — 版本标签 + 关键阈值 + 同步状态 + 快捷入口
// ============================================================
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import type { PhysicsGuardSummary } from '@/types/dispatch'
import { buildSettingsPath } from '@/constants/settings'

const props = defineProps<{
  data: PhysicsGuardSummary
}>()

const router = useRouter()
const expanded = ref(false)

// ── D-95: 版本 & NEW 徽章 ──
const isNewVersion = computed(() => {
  if (!props.data.last_sync_at) return false
  const syncTime = new Date(props.data.last_sync_at).getTime()
  const hoursAgo = (Date.now() - syncTime) / 3600000
  return hoursAgo <= 24
})

// ── D-97: 同步状态 ──
const syncStatusInfo = computed(() => {
  const map: Record<string, { label: string; color: string; dot: string }> = {
    synced: { label: '已同步', color: '#16a34a', dot: '#22c55e' },
    stale: { label: '同步延迟', color: '#d97706', dot: '#f59e0b' },
    offline: { label: '同步失败', color: '#dc2626', dot: '#ef4444' },
  }
  return map[props.data.sync_status] ?? map.synced
})

const syncTimeDisplay = computed(() => {
  if (!props.data.last_sync_at) return '—'
  const d = new Date(props.data.last_sync_at)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
})

function goSettings() {
  router.push(buildSettingsPath('physics-guard'))
}
</script>

<template>
  <div class="pg-card">
    <!-- 标题 + 入口 -->
    <div class="pg-card__header" @click="expanded = !expanded">
      <div class="pg-card__header-left">
        <h2 class="pg-card__title">物理防护配置</h2>
        <!-- D-95: 版本标签 -->
        <span class="pg-card__version">
          v{{ data.config_version }}
          <span v-if="isNewVersion" class="pg-card__new-badge">NEW</span>
        </span>
      </div>
      <span class="pg-card__arrow" :class="{ on: expanded }">▾</span>
    </div>

    <!-- 默认可见：紧急水位 -->
    <div class="pg-card__hero">
      <span class="pg-card__hero-label">紧急水位</span>
      <span class="pg-card__hero-value">{{ data.upstream_emergency }}<small>m</small></span>
    </div>

    <!-- D-96: 展开 — 关键阈值 -->
    <div v-if="expanded" class="pg-card__detail">
      <div class="pg-card__row">
        <span>危险水位</span>
        <b>{{ data.upstream_danger }}<small>m</small></b>
      </div>
      <div class="pg-card__row">
        <span>预警水位</span>
        <b>{{ data.upstream_warning }}<small>m</small></b>
      </div>
      <div class="pg-card__row">
        <span>L3 置信度</span>
        <b>{{ (data.fusion_l3_confidence * 100).toFixed(0) }}<small>%</small></b>
      </div>
      <div class="pg-card__row">
        <span>L3 风险概率上限</span>
        <b>{{ (data.fusion_l3_risk * 100).toFixed(0) }}<small>%</small></b>
      </div>
    </div>

    <!-- D-97: 同步状态 -->
    <div class="pg-card__sync">
      <span class="pg-card__sync-dot" :style="{ background: syncStatusInfo.dot }" />
      <span class="pg-card__sync-label" :style="{ color: syncStatusInfo.color }">
        {{ syncStatusInfo.label }}
      </span>
      <span class="pg-card__sync-time">· 最后同步 {{ syncTimeDisplay }}</span>
    </div>

    <!-- D-98: 快捷入口 -->
    <button class="pg-card__btn" @click="goSettings">配置管理 →</button>
  </div>
</template>

<style scoped lang="scss">
.pg-card {
  padding: 18px 20px;
  background: #fff;
  border: 1px solid #eef0f4;
  border-radius: 8px;

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    user-select: none;
  }

  &__header-left {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  &__title {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
  }

  // D-95: 版本标签
  &__version {
    font-size: 13px;
    font-weight: 500;
    color: #475569;
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 4px;
    font-family: 'SF Mono', monospace;
    position: relative;
    white-space: nowrap;
  }

  &__new-badge {
    position: absolute;
    top: -8px;
    right: -6px;
    font-size: 9px;
    font-weight: 700;
    color: #fff;
    background: #3b82f6;
    padding: 1px 5px;
    border-radius: 3px;
    letter-spacing: 0.5px;
  }

  &__arrow {
    font-size: 14px;
    color: #94a3b8;
    transition: transform 0.25s;

    &.on {
      transform: rotate(180deg);
    }
  }

  // 紧急水位
  &__hero {
    display: flex;
    align-items: baseline;
    gap: 8px;
    margin-top: 14px;
    padding: 12px 14px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 6px;
  }

  &__hero-label {
    font-size: 14px;
    font-weight: 500;
    color: #991b1b;
  }

  &__hero-value {
    font-size: 26px;
    font-weight: 800;
    color: #dc2626;
    font-family: 'SF Mono', 'Cascadia Code', monospace;
    line-height: 1;

    small {
      font-size: 14px;
      font-weight: 500;
      margin-left: 2px;
      color: #991b1b;
    }
  }

  // D-96: 阈值明细
  &__detail {
    margin-top: 8px;
    padding: 10px 14px;
    background: #f8fafc;
    border-radius: 6px;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  &__row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    font-size: 14px;
    color: #475569;

    b {
      font-weight: 700;
      color: #1e293b;
      font-family: 'SF Mono', monospace;

      small {
        font-size: 12px;
        font-weight: 400;
        color: #64748b;
        margin-left: 2px;
      }
    }
  }

  // D-97: 同步状态
  &__sync {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 12px;
    font-size: 13px;
  }

  &__sync-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  &__sync-label {
    font-weight: 600;
  }

  &__sync-time {
    color: #94a3b8;
  }

  // D-98: 快捷入口
  &__btn {
    margin-top: 12px;
    width: 100%;
    padding: 9px 0;
    font-size: 14px;
    font-weight: 500;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;

    &:hover {
      color: #1e293b;
      background: #f1f5f9;
      border-color: #cbd5e1;
    }
  }
}
</style>
