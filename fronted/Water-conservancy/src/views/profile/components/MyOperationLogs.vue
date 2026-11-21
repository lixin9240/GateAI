<script setup lang="ts">
// ============================================================
// 我的操作日志 — 对接登录日志接口 §1.3，日期筛选
// ============================================================
import { ref, onMounted, onBeforeUnmount } from 'vue'
import {
  ElTable, ElTableColumn, ElPagination,
  ElDatePicker, ElTag, ElMessage, ElButton,
} from 'element-plus'
import { Search } from '@element-plus/icons-vue'
import { useProfileStore } from '@/stores/profile'
import { getOperationLogs } from '@/api/profile'
import { OPERATION_TYPES } from '@/constants/profile'
import type { OperationLog } from '@/shared/types'

// ── Store ──

const profileStore = useProfileStore()

// ── 状态 ──

const loading = ref(false)
const logs = ref<OperationLog[]>([])
const total = ref(0)
const currentPage = ref(1)
const pageSize = 15

/** 取消上一次未完成的请求，避免快速翻页时并发请求 */
let abortController: AbortController | null = null
/** 请求序列号：丢弃过时响应 */
let fetchSeq = 0
/** 是否有请求正在飞行中，防止快速点击导致竞态 */
let requestInFlight = false

// 筛选（仅日期范围，登录日志无模块维度）
const filterDateRange = ref<[string, string] | null>(null)

// ── 网络状态监听 ──

const isOnline = ref(navigator.onLine)

function onOnline() {
  isOnline.value = true
}

function onOffline() {
  isOnline.value = false
}

// ── 方法 ──

/** 请求前置检查：断网或请求飞行中则拦截 */
function canProceed(): boolean {
  if (!navigator.onLine) {
    ElMessage.warning('网络已断开，无法加载数据')
    return false
  }
  if (requestInFlight) {
    return false
  }
  return true
}

async function fetchLogs(targetPage: number) {
  if (!canProceed()) return

  // 取消上一次未完成的请求
  if (abortController) {
    abortController.abort()
  }
  abortController = new AbortController()
  const signal = abortController.signal

  const seq = ++fetchSeq
  requestInFlight = true
  loading.value = true

  try {
    const res = await getOperationLogs({
      page: targetPage,
      page_size: pageSize,
      start_time: filterDateRange.value?.[0] || undefined,
      end_time: filterDateRange.value?.[1] || undefined,
    }, signal)

    if (seq !== fetchSeq) return

    if (res.data?.code === 0 && res.data.data) {
      logs.value = res.data.data.list
      total.value = res.data.data.total
      profileStore.setOperationLogs(res.data.data.list, res.data.data.total)
      // 翻页后回到顶部，保证浏览位置一致
      window.scrollTo({ top: 0, behavior: 'smooth' })
      return
    }
    // API 返回非 0 → 维持当前页和数据不变，仅提示
    if (seq === fetchSeq) {
      ElMessage.warning(res.data?.msg || '登录日志加载失败，请稍后重试')
    }
  } catch (err: unknown) {
    if (err instanceof DOMException && err.name === 'AbortError') return
    if (seq !== fetchSeq) return

    // 网络错误 → 保持当前数据和页码不变，明确提示
    if (!navigator.onLine) {
      ElMessage.warning('网络已断开，请检查网络连接后重试')
    } else if (logs.value.length > 0) {
      ElMessage.warning('网络异常，无法刷新登录日志，当前显示为已缓存数据')
    } else {
      ElMessage.warning('网络不可用，请检查网络连接后刷新页面重试')
    }
  } finally {
    if (seq === fetchSeq) {
      loading.value = false
      requestInFlight = false
    }
  }
}

/** 翻页：防快速点击 */
function onPageChange(page: number) {
  if (requestInFlight) return
  currentPage.value = page
  fetchLogs(page)
}

function onFilterChange() {
  if (requestInFlight) return
  currentPage.value = 1
  fetchLogs(1)
}

function resetFilters() {
  if (requestInFlight) return
  filterDateRange.value = null
  currentPage.value = 1
  fetchLogs(1)
}

onMounted(() => {
  window.addEventListener('online', onOnline)
  window.addEventListener('offline', onOffline)
  fetchLogs(currentPage.value)
})

onBeforeUnmount(() => {
  window.removeEventListener('online', onOnline)
  window.removeEventListener('offline', onOffline)
  if (abortController) {
    abortController.abort()
  }
})
</script>

<template>
  <div class="operation-logs">
    <div class="operation-logs__filters">
      <ElDatePicker
        v-model="filterDateRange"
        type="daterange"
        range-separator="至"
        start-placeholder="开始日期"
        end-placeholder="结束日期"
        size="default"
        value-format="YYYY-MM-DD"
      />
      <ElButton type="primary" :icon="Search" :loading="loading" @click="onFilterChange">
        查询
      </ElButton>
      <ElButton @click="resetFilters">重置</ElButton>
    </div>

    <ElTable
      v-loading="loading"
      :data="logs"
      class="operation-logs__table"
      style="width: 100%; margin-top: 14px"
    >
      <ElTableColumn prop="time" label="时间" width="190" />
      <ElTableColumn prop="module" label="操作模块" width="120" />
      <ElTableColumn label="操作类型" width="130">
        <template #default="scope">
          {{ (OPERATION_TYPES as Record<string, string>)[scope.row.type] || scope.row.type }}
        </template>
      </ElTableColumn>
      <ElTableColumn prop="description" label="操作描述" min-width="220" show-overflow-tooltip />
      <ElTableColumn label="操作结果" width="100">
        <template #default="scope">
          <ElTag :type="(scope.row as OperationLog).result === 1 ? 'success' : 'danger'">
            {{ (scope.row as OperationLog).result === 1 ? '成功' : '失败' }}
          </ElTag>
        </template>
      </ElTableColumn>
    </ElTable>

    <ElPagination
      :current-page="currentPage"
      :page-size="pageSize"
      :total="total"
      layout="total, pager"
      background
      class="operation-logs__pagination"
      style="margin-top: 16px; justify-content: flex-end"
      @current-change="onPageChange"
    />
  </div>
</template>

<style scoped lang="scss">
.operation-logs {
  &__filters {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;

    :deep(.el-date-editor) {
      font-size: 15px;
    }
  }

  &__table {
    :deep(.el-table__header th.el-table__cell) {
      font-size: 16px;
      font-weight: 600;
      padding: 14px 0;
    }

    :deep(.el-table__body td.el-table__cell) {
      font-size: 16px;
      padding: 14px 0;
    }

    :deep(.el-tag) {
      font-size: 14px;
      padding: 4px 10px;
    }
  }

  &__pagination {
    :deep(.el-pagination__total) { font-size: 15px; }
    :deep(.btn-prev), :deep(.btn-next), :deep(.el-pager li) {
      min-width: 36px; height: 36px; line-height: 36px; font-size: 15px;
    }
  }
}
</style>
