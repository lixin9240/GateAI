import axios from 'axios'
import { isApiBusinessError } from './apiError'

/** 连续失败锁定阈值（需求 L-20） */
export const LOCKOUT_MAX_ATTEMPTS = 5

/** 后端业务码：账号已锁定 */
export const LOGIN_CODE_LOCKED = 20007

/** 后端业务码：账号或密码错误 */
export const LOGIN_CODE_WRONG_PASSWORD = 20008

/** 需求文档错误码 AUTH_002 */
export const LOGIN_AUTH_CODE_LOCKED = 'AUTH_002'

const FAIL_COUNT_PREFIX = 'login_fail_count:'

/** 登录相关错误码 → 用户可读文案 */
const LOGIN_FRIENDLY_MESSAGES: Record<number, string> = {
  10001: '请输入登录账号',
  10002: '请输入用户名和密码',
  10003: '输入格式不正确，请检查后重试',
  20006: '账号已被禁用，请联系系统管理员',
  20007: '账号已锁定，请稍后再试',
  20008: '账号或密码错误，请重新输入',
}

export interface ParsedLoginApiError {
  code: number
  message: string
  data: unknown
}

function asRecord(value: unknown): Record<string, unknown> | null {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : null
}

function decodeBackendMsg(msg: unknown): string {
  if (typeof msg !== 'string' || !msg.trim()) return ''
  return msg.trim()
}

/** 是否为技术性/不可直接展示的文案 */
function isTechnicalMessage(msg: string): boolean {
  if (!msg) return true
  if (/^(AUTH_|ERR_|HTTP_)\w+/i.test(msg)) return true
  if (/^\d{5}$/.test(msg)) return true
  if (/network error|timeout|ECONNABORTED/i.test(msg)) return true
  return false
}

export function toFriendlyLoginMessage(code: number, backendMsg?: string): string {
  if (LOGIN_FRIENDLY_MESSAGES[code]) return LOGIN_FRIENDLY_MESSAGES[code]
  const msg = decodeBackendMsg(backendMsg)
  if (msg && !isTechnicalMessage(msg)) return msg
  return '登录失败，请稍后重试'
}

/** 从 ApiBusinessError / Axios 响应中统一提取业务错误 */
export function extractLoginApiError(err: unknown): ParsedLoginApiError | null {
  if (isApiBusinessError(err)) {
    return {
      code: err.code,
      message: decodeBackendMsg(err.message),
      data: err.data,
    }
  }

  if (axios.isAxiosError(err) && err.response?.data && typeof err.response.data === 'object') {
    const body = err.response.data as Record<string, unknown>
    if (body.code !== undefined) {
      return {
        code: Number(body.code),
        message: decodeBackendMsg(body.msg ?? body.message),
        data: body.data ?? null,
      }
    }
  }

  return null
}

export function isAccountLocked(code: number, msg?: string, data?: unknown): boolean {
  const row = asRecord(data)
  if (row?.error_code === LOGIN_AUTH_CODE_LOCKED) return true
  if (code === LOGIN_CODE_LOCKED) return true
  if (msg === LOGIN_AUTH_CODE_LOCKED) return true
  const text = decodeBackendMsg(msg)
  if (text.includes('锁定') || text.includes('冻结')) return true
  return false
}

export function parseLockRemainSeconds(data: unknown): number {
  const row = asRecord(data)
  if (!row) return 30 * 60

  const direct = Number(row.lock_remain_seconds ?? row.remaining_seconds ?? row.lock_seconds)
  if (Number.isFinite(direct) && direct > 0) return Math.floor(direct)

  const expire = row.lock_expire_time
  if (typeof expire === 'string' && expire) {
    const diff = Math.floor((new Date(expire.replace(/-/g, '/')).getTime() - Date.now()) / 1000)
    if (diff > 0) return diff
  }

  return 30 * 60
}

export function parseRemainingAttempts(data: unknown): number | null {
  const row = asRecord(data)
  if (!row) return null

  const remaining = Number(row.remaining_attempts)
  if (Number.isFinite(remaining) && remaining >= 0) return Math.floor(remaining)

  const failCount = Number(row.fail_count ?? row.failCount)
  if (Number.isFinite(failCount) && failCount >= 0) {
    return Math.max(0, LOCKOUT_MAX_ATTEMPTS - Math.floor(failCount))
  }

  return null
}

function failCountKey(account: string): string {
  return `${FAIL_COUNT_PREFIX}${account.trim().toLowerCase()}`
}

export function getLocalFailCount(account: string): number {
  if (!account.trim()) return 0
  const n = Number(sessionStorage.getItem(failCountKey(account)))
  return Number.isFinite(n) && n > 0 ? Math.floor(n) : 0
}

export function recordLocalFailAttempt(account: string): number {
  if (!account.trim()) return 0
  const next = getLocalFailCount(account) + 1
  sessionStorage.setItem(failCountKey(account), String(next))
  return next
}

export function clearLocalFailCount(account: string): void {
  if (!account.trim()) return
  sessionStorage.removeItem(failCountKey(account))
}

/** 第 3、4 次失败时展示剩余次数预警（需求 L-25） */
export function buildAttemptWarning(failCount: number): string {
  if (failCount < 3 || failCount >= LOCKOUT_MAX_ATTEMPTS) return ''
  const remaining = LOCKOUT_MAX_ATTEMPTS - failCount
  if (remaining === 1) return '还剩 1 次尝试机会，再次输入错误将锁定账号 30 分钟'
  return `还剩 ${remaining} 次尝试机会，连续 5 次错误将锁定账号 30 分钟`
}

export function formatLockCountdown(totalSeconds: number): string {
  const sec = Math.max(0, totalSeconds)
  const min = Math.floor(sec / 60)
  const s = sec % 60
  return `${min} 分 ${String(s).padStart(2, '0')} 秒`
}

export type LoginErrorAction =
  | { type: 'locked'; remainSeconds: number; message: string }
  | { type: 'wrong_password'; warning: string; message: string }
  | { type: 'other'; message: string }

function resolveNetworkMessage(err: unknown): string {
  if (axios.isAxiosError(err)) {
    if (err.code === 'ECONNABORTED') return '请求超时，请稍后重试'
    if (!err.response) return '网络连接失败，请检查网络或联系管理员'
    const status = err.response.status
    if (status === 502 || status === 503) return '服务暂时不可用，请稍后重试'
    if (status === 500) return '服务器繁忙，请稍后重试'
  }
  if (err instanceof Error && err.message && !isTechnicalMessage(err.message)) {
    return err.message
  }
  return '登录失败，请稍后重试'
}

export function resolveLoginError(err: unknown, account = ''): LoginErrorAction {
  const apiErr = extractLoginApiError(err)

  if (apiErr) {
    const friendly = toFriendlyLoginMessage(apiErr.code, apiErr.message)

    if (isAccountLocked(apiErr.code, apiErr.message, apiErr.data)) {
      clearLocalFailCount(account)
      return {
        type: 'locked',
        remainSeconds: parseLockRemainSeconds(apiErr.data),
        message: friendly,
      }
    }

    if (apiErr.code === LOGIN_CODE_WRONG_PASSWORD) {
      let failCount: number | null = null
      const remainingFromApi = parseRemainingAttempts(apiErr.data)
      if (account.trim()) {
        failCount = recordLocalFailAttempt(account)
      }
      const failCountForWarn =
        remainingFromApi != null
          ? LOCKOUT_MAX_ATTEMPTS - remainingFromApi
          : (failCount ?? 0)

      // 后端常见实现：第 5 次仍返回 20008，第 6 次才 20007（差一次）
      // 前端按需求：第 5 次错误即视为锁定
      if (failCountForWarn >= LOCKOUT_MAX_ATTEMPTS) {
        clearLocalFailCount(account)
        return {
          type: 'locked',
          remainSeconds: parseLockRemainSeconds(apiErr.data),
          message: '连续 5 次密码错误，账号已锁定 30 分钟',
        }
      }

      return {
        type: 'wrong_password',
        warning: buildAttemptWarning(failCountForWarn),
        message: friendly,
      }
    }

    return { type: 'other', message: friendly }
  }

  return { type: 'other', message: resolveNetworkMessage(err) }
}
