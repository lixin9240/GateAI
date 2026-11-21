import type { SystemUser } from '@/shared/types'

/** 解析 lock_expire_time 是否仍在锁定期内 */
export function isLockActive(lockExpireTime?: string | null): boolean {
  if (!lockExpireTime) return false
  const ts = new Date(lockExpireTime.replace(/-/g, '/')).getTime()
  return Number.isFinite(ts) && ts > Date.now()
}

/** 登录失败导致的临时锁定（users.lock_expire_time） */
export function isUserLoginLocked(user: SystemUser): boolean {
  return isLockActive(user.lock_expire_time)
}

/** 管理员手动禁用 */
export function isUserDisabled(user: SystemUser): boolean {
  return user.is_enabled === 0
}

export function userNeedsUnlock(user: SystemUser): boolean {
  return isUserDisabled(user) || isUserLoginLocked(user)
}

export type UserStatusTagType = 'success' | 'warning' | 'danger' | 'info'

export function getUserStatusMeta(user: SystemUser): {
  label: string
  type: UserStatusTagType
  detail?: string
} {
  if (isUserDisabled(user)) {
    return { label: '已禁用', type: 'danger' }
  }
  if (isUserLoginLocked(user)) {
    return {
      label: '已锁定',
      type: 'warning',
      detail: user.lock_expire_time ? `至 ${formatLockTime(user.lock_expire_time)}` : undefined,
    }
  }
  return { label: '正常', type: 'success' }
}

function formatLockTime(value: string): string {
  if (value.length >= 16) return value.slice(0, 16)
  return value
}

/** 规范化用户列表项，兼容后端扩展字段 */
export function normalizeSystemUser(raw: Record<string, unknown>): SystemUser {
  return {
    id: Number(raw.id),
    account: String(raw.account ?? ''),
    realname: String(raw.realname ?? ''),
    role_id: Number(raw.role_id ?? 0),
    role_name: String(raw.role_name ?? ''),
    phone: String(raw.phone ?? ''),
    is_enabled: Number(raw.is_enabled ?? 1),
    created_at: String(raw.created_at ?? ''),
    lock_expire_time:
      typeof raw.lock_expire_time === 'string' ? raw.lock_expire_time : undefined,
    lock_reason: typeof raw.lock_reason === 'string' ? raw.lock_reason : undefined,
    last_login_at: typeof raw.last_login_at === 'string' ? raw.last_login_at : undefined,
  }
}
