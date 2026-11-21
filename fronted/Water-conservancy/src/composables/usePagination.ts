// ============================================================
// usePagination — 分页逻辑
// ============================================================
import { ref, computed, watch } from 'vue'
import { PAGE_SIZE_OPTIONS } from '@/constants'

export function usePagination(initialPageSize?: number) {
  const page = ref(1)
  const pageSize = ref(initialPageSize ?? PAGE_SIZE_OPTIONS[0])
  const totalRecords = ref(0)
  const reloadKey = ref(0)

  const totalPages = computed(() => Math.max(1, Math.ceil(totalRecords.value / pageSize.value)))

  /** 重置分页到第一页 */
  function reset() {
    page.value = 1
  }

  /** 触发刷新 */
  function refresh() {
    reloadKey.value++
  }

  /** 修改每页条数时自动重置页码 */
  watch(pageSize, () => {
    page.value = 1
  })

  return {
    page,
    pageSize,
    totalRecords,
    totalPages,
    reloadKey,
    reset,
    refresh,
    PAGE_SIZE_OPTIONS,
  }
}
