// ============================================================
// useTable — 表格查询（loading + list + pagination + searchForm + reset）
// ============================================================
import { ref, reactive, watch } from 'vue'
import { usePagination } from './usePagination'

export function useTable<T>(
  fetchFn: (params: Record<string, unknown>) => Promise<{ total: number; list: T[] } | null>,
  options?: { initialPageSize?: number; initialSearch?: Record<string, unknown> },
) {
  const pagination = usePagination(options?.initialPageSize)
  const loading = ref(false)
  const list = ref<T[]>([]) as ReturnType<typeof ref<T[]>>
  const searchForm = reactive<Record<string, unknown>>(options?.initialSearch ?? {})

  async function doFetch(extraParams?: Record<string, unknown>) {
    loading.value = true
    try {
      const params = {
        page: pagination.page.value,
        page_size: pagination.pageSize.value,
        ...searchForm,
        ...(extraParams ?? {}),
      }
      // 清除空值
      Object.keys(params).forEach((key: string) => {
        if (
          (params as Record<string, unknown>)[key] === '' ||
          (params as Record<string, unknown>)[key] === undefined
        ) {
          delete (params as Record<string, unknown>)[key]
        }
      })
      const result = await fetchFn(params)
      if (result) {
        list.value = result.list
        pagination.totalRecords.value = result.total
      }
    } finally {
      loading.value = false
    }
  }

  /** 搜索（重置到第一页并查询） */
  function search() {
    pagination.reset()
    doFetch()
  }

  /** 重置搜索条件并重新查询 */
  function reset() {
    Object.keys(searchForm).forEach((key) => {
      delete searchForm[key]
    })
    Object.assign(searchForm, options?.initialSearch ?? {})
    pagination.reset()
    doFetch()
  }

  /** 翻页时自动查询 */
  watch([pagination.page, pagination.pageSize, pagination.reloadKey], () => {
    doFetch()
  })

  return {
    loading,
    list,
    searchForm,
    pagination,
    doFetch,
    search,
    reset,
  }
}
