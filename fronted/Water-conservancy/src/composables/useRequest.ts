// ============================================================
// useRequest — 请求 Loading / Error 统一管理，自动竞态处理
// ============================================================
import { ref, type Ref } from 'vue'

export function useRequest<T>(
  asyncFn: (...args: unknown[]) => Promise<T>,
  options?: { immediate?: boolean },
) {
  const loading = ref(false)
  const data: Ref<T | null> = ref(null)
  const error: Ref<string | null> = ref(null)

  let abortFlag = 0

  async function execute(...args: unknown[]): Promise<T | null> {
    const callId = ++abortFlag
    loading.value = true
    error.value = null
    try {
      const result = await asyncFn(...args)
      // 竞态检查：只保留最新一次调用
      if (callId === abortFlag) {
        data.value = result
        return result
      }
      return null
    } catch (e: unknown) {
      if (callId === abortFlag) {
        error.value = e instanceof Error ? e.message : '请求失败'
      }
      return null
    } finally {
      if (callId === abortFlag) {
        loading.value = false
      }
    }
  }

  // 初始立即执行
  if (options?.immediate) {
    execute()
  }

  return { loading, data, error, execute }
}
