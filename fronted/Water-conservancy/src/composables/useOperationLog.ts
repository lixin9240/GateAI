// ============================================================
// useOperationLog — 跨模块共享操作日志
// 所有模块导入此函数即可统一记录
// ============================================================
import type { OperationLog } from '@/shared/types'

const STORAGE_KEY = 'operationLogs'
const MAX_LOGS = 100

export function useOperationLog() {
  /** 读取全部日志 */
  function loadAll(): OperationLog[] {
    try {
      const raw = localStorage.getItem(STORAGE_KEY)
      return raw ? JSON.parse(raw) : []
    } catch {
      return []
    }
  }

  /** 写入一条日志 */
  function record(module: string, type: string, description: string, result: number) {
    try {
      const list = loadAll()
      list.unshift({
        id: Date.now(),
        time: new Date().toLocaleString('zh-CN'),
        module,
        type,
        description,
        result,
      })
      if (list.length > MAX_LOGS) list.length = MAX_LOGS
      localStorage.setItem(STORAGE_KEY, JSON.stringify(list))
    } catch {
      /* ignore */
    }
  }

  return { loadAll, record }
}
