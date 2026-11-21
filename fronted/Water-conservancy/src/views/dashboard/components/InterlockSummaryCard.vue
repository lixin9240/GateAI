<script setup lang="ts">
// ============================================================
// 阀门互锁摘要卡片 (D-99 / D-100 / D-102)
// 综合概览右侧栏 — 触发计数 + 最近规则 + 快捷入口
// ============================================================
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { fetchInterlockDashboardSummary } from '@/api/gateaiSettings'

const router = useRouter()

// ── 从 API 获取数据 ──
const triggerCount24h = ref(3)
const recentRule = ref<{ name: string; time: string } | null>(null)

// ── D-99: 计数颜色 ──
const countColor = computed(() => {
  if (triggerCount24h.value > 10) return 'red'
  if (triggerCount24h.value > 3) return 'yellow'
  return 'gray'
})

const countStyle = computed(() => {
  const map: Record<string, { bg: string; text: string }> = {
    red: { bg: '#fef2f2', text: '#dc2626' },
    yellow: { bg: '#fffbeb', text: '#d97706' },
    gray: { bg: '#f8fafc', text: '#475569' },
  }
  return map[countColor.value]
})

// ── D-100: 相对时间 ──
const recentTimeAgo = computed(() => {
  if (!recentRule.value) return ''
  const diffMin = Math.floor((Date.now() - new Date(recentRule.value.time).getTime()) / 60000)
  if (diffMin < 1) return '刚刚'
  if (diffMin < 60) return `${diffMin} 分钟前`
  const diffH = Math.floor(diffMin / 60)
  if (diffH < 24) return `${diffH} 小时前`
  return `${Math.floor(diffH / 24)} 天前`
})

// ── 导航 ──
function goLogs() {
  router.push('/settings/gate-interlock/logs')
}
function goRules() {
  router.push('/settings/gate-interlock')
}

onMounted(async () => {
  const summary = await fetchInterlockDashboardSummary(1)
  triggerCount24h.value = summary.trigger_24h
  recentRule.value = summary.recent_rule
})
</script>

<template>
  <div class="il-card">
    <!-- 标题 -->
    <div class="il-card__header">
      <h2 class="il-card__title">阀门互锁</h2>
    </div>

    <!-- D-99: 触发计数徽章 -->
    <div class="il-card__count-row" @click="goLogs">
      <div
        class="il-card__count-badge"
        :style="{ background: countStyle.bg, color: countStyle.text }"
      >
        <span class="il-card__count-num">{{ triggerCount24h }}</span>
        <span class="il-card__count-unit">次 / 24h</span>
      </div>
      <span class="il-card__count-arrow">→</span>
    </div>

    <!-- D-100: 最近触发规则 -->
    <div class="il-card__recent">
      <template v-if="recentRule">
        <span class="il-card__recent-dot" />
        <div class="il-card__recent-info">
          <span class="il-card__recent-rule">{{ recentRule.name }}</span>
          <span class="il-card__recent-time">{{ recentTimeAgo }}</span>
        </div>
      </template>
      <template v-else>
        <span class="il-card__recent-empty">近 24h 无互锁触发</span>
      </template>
    </div>

    <!-- D-102: 快捷入口 -->
    <button class="il-card__btn" @click="goRules">互锁规则 →</button>
  </div>
</template>

<style scoped lang="scss">
.il-card {
  padding: 16px 18px;
  background: #fff;
  border: 1px solid #eef0f4;
  border-radius: 8px;
  margin-top: 12px;

  &__header {
    margin-bottom: 12px;
  }

  &__title {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
  }

  // D-99: 计数
  &__count-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    padding: 6px 0;

    &:hover .il-card__count-arrow {
      transform: translateX(3px);
      color: #3b82f6;
    }
  }

  &__count-badge {
    display: flex;
    align-items: baseline;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 8px;
  }

  &__count-num {
    font-size: 30px;
    font-weight: 800;
    font-family: 'SF Mono', 'Cascadia Code', monospace;
    line-height: 1;
  }

  &__count-unit {
    font-size: 13px;
    font-weight: 500;
    opacity: 0.7;
  }

  &__count-arrow {
    font-size: 16px;
    color: #94a3b8;
    transition: all 0.2s;
    margin-right: 8px;
  }

  // D-100: 最近触发
  &__recent {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-top: 10px;
    padding: 10px 12px;
    background: #f8fafc;
    border-radius: 6px;
    font-size: 14px;
  }

  &__recent-dot {
    width: 7px;
    height: 7px;
    margin-top: 5px;
    border-radius: 50%;
    background: #f59e0b;
    flex-shrink: 0;
    animation: pulse-dot 2s infinite;
  }

  @keyframes pulse-dot {
    0%,
    100% {
      opacity: 1;
    }
    50% {
      opacity: 0.3;
    }
  }

  &__recent-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
  }

  &__recent-rule {
    font-weight: 600;
    color: #1e293b;
  }

  &__recent-time {
    font-size: 12px;
    color: #94a3b8;
  }

  &__recent-empty {
    color: #94a3b8;
    font-style: italic;
  }

  // D-102: 入口
  &__btn {
    margin-top: 10px;
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
