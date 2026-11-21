// ============================================================
// Pinia Store — 字典数据（全局缓存）
// 按《前端开发规范》6.2 节集中维护字典字段
// ============================================================
import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { DictMap } from '@/shared/types'

export const useDictStore = defineStore('dict', () => {
  // ── State ──
  const dictCache = ref<Record<string, DictMap>>({})

  // ── Actions ──
  async function fetchDict(dictKey: string): Promise<DictMap> {
    if (dictCache.value[dictKey]) {
      return dictCache.value[dictKey]
    }
    // TODO: 对接后端字典接口
    // const res = await request.get(`/dict/${dictKey}`)
    // dictCache.value[dictKey] = res
    return {}
  }

  return { dictCache, fetchDict }
})
