// ============================================================
// 全局常量 / 字典定义
// 按《前端开发规范》6.2 节要求集中维护
// ============================================================
import type { DictMap } from '@/shared/types'

export * from './auth'
export * from './roles'
export * from './equipment'
export * from './validation'
export * from './settings'
export * from './aiHealth'
export * from './physicsGuard'
export * from './gateInterlock'
export * from './profile'

// ---------- 通用状态字典 ----------
export const COMMON_STATUS: DictMap = {
  1: { label: '启用', value: 1, color: 'green' },
  0: { label: '禁用', value: 0, color: 'red' },
}

// ---------- 分页默认配置 ----------
export const DEFAULT_PAGE_SIZE = 10
export const PAGE_SIZE_OPTIONS = [10, 20, 50, 100]

// ---------- 防抖延迟 (ms) ----------
export const DEBOUNCE_DELAY = 300
