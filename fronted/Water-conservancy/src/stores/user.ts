// ============================================================
// Pinia Store — 用户状态
// ============================================================
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { loginApi } from '@/api/auth'
import { getRolePagePermissions, type BackendRolePage } from '@/api/settings'
import { type UserRole, ROUTE_ROLES } from '@/constants/roles'

export interface UserInfo {
  id: number
  username: string
  nickname: string
  avatar?: string
  phone?: string
  roles: UserRole[]
  permissions: string[]
}

/** localStorage key：admin 保存权限时同时缓存，供非 admin 用户登录时读取 */
const CACHED_ROLE_PAGES_KEY = '__role_page_permissions_cache'

const ROLE_CODE_MAP: Record<string, UserRole> = {
  admin: 'admin',
  dispatcher: 'dispatcher',
  operator: 'operator',
  manager: 'manager',
  station_master: 'manager',
  algorithm: 'algorithm_engineer',
  algorithm_engineer: 'algorithm_engineer',
  // role_id（与后端数据库一致：1管理员 2调度员 3运维人员 4站长 5算法）
  '1': 'admin',
  '2': 'dispatcher',
  '3': 'operator',
  '4': 'manager',
  '5': 'algorithm_engineer',
  // role_name 兜底（优先匹配 role_id，此处兜底 role_code/role_name 字符串）
  '系统管理员': 'admin',
  '管理员': 'admin',
  '调度员': 'dispatcher',
  '调度工程师': 'dispatcher',
  '调度决策工程师': 'dispatcher',
  '运维人员': 'operator',
  '值班运维人员': 'operator',
  '值班运维': 'operator',
  '站长': 'manager',
  '站长/管理人员': 'manager',
  '站长/管理': 'manager',
  '算法工程师': 'algorithm_engineer',
}

/** UserRole → 中文显示名（与 SettingsPage roleOptions 保持一致） */
export const ROLE_LABEL_MAP: Record<UserRole, string> = {
  admin: '系统管理员',
  dispatcher: '调度员',
  operator: '运维人员',
  manager: '站长',
  algorithm_engineer: '算法工程师',
}

/**
 * 将后端返回的 role_code / role_name 映射为统一的 UserRole。
 * 导出供 ProfileCard 等外部调用，确保整个项目使用同一套映射逻辑。
 */
export function mapRoleCode(roleCode?: string | number, roleName?: string): UserRole {
  const code = String(roleCode ?? '').trim()
  if (code && ROLE_CODE_MAP[code]) return ROLE_CODE_MAP[code]

  const name = String(roleName ?? '').trim()
  if (name && ROLE_CODE_MAP[name]) return ROLE_CODE_MAP[name]

  console.warn(`[userStore] 未知的角色: code="${code}" name="${name}", 回退为 operator`)
  return 'operator'
}

/** 根据角色从硬编码 ROUTE_ROLES 推导操作权限列表（最终兜底） */
function getPermissionsFromHardcoded(role: UserRole): string[] {
  const perms: string[] = []
  for (const [path, roles] of Object.entries(ROUTE_ROLES)) {
    if (roles.includes(role)) {
      perms.push(path)
    }
  }
  return perms
}

/** 使用硬编码 ROUTE_ROLES 推导用户权限（最终兜底） */
function syncPermissionsFromHardcoded(userInfo: { roles: UserRole[]; permissions: string[] }) {
  if (userInfo.roles.length === 0) return
  const allPerms = new Set<string>()
  for (const role of userInfo.roles) {
    for (const perm of getPermissionsFromHardcoded(role)) {
      allPerms.add(perm)
    }
  }
  userInfo.permissions = Array.from(allPerms)
  localStorage.setItem('userInfo', JSON.stringify(userInfo))
  if (sessionStorage.getItem('userInfo')) {
    sessionStorage.setItem('userInfo', JSON.stringify(userInfo))
  }
}

/**
 * 将中文角色名映射为 UserRole 枚举。支持后端返回的各种变体（如"站长"/"站长/管理人员"）。
 */
function cnRoleToUserRole(cnName: string): UserRole | null {
  return ROLE_CODE_MAP[cnName] ?? null
}

/**
 * 从 pages 数据中提取当前用户可访问的路径列表。
 * 使用 UserRole 枚举做比较，避免中文名称变体不匹配（如"站长" vs "站长/管理人员"）。
 */
function deriveAllowedPaths(
  pages: BackendRolePage[],
  userRoles: UserRole[],
  isAdmin: boolean,
): string[] {
  const paths: string[] = []
  for (const page of pages) {
    if (!page.path) continue
    if (isAdmin) {
      paths.push(page.path)
      continue
    }
    // 将后端的授权角色中文名转为 UserRole 枚举再比较
    const authorizedRoles: UserRole[] = []
    for (const cnName of page.authorizedRoleNames) {
      const role = cnRoleToUserRole(cnName)
      if (role) authorizedRoles.push(role)
    }
    const hasAccess = authorizedRoles.some((role) => userRoles.includes(role))
    if (hasAccess) paths.push(page.path)
  }
  return paths
}

function loadUserInfo(): UserInfo | null {
  try {
    const raw = sessionStorage.getItem('userInfo') || localStorage.getItem('userInfo')
    return raw ? (JSON.parse(raw) as UserInfo) : null
  } catch {
    return null
  }
}

/**
 * 读取 localStorage 中的页面权限缓存（admin 保存时写入）。
 * 同一浏览器上，admin 登录并保存权限后，其他用户登录时可直接读取。
 */
export function loadCachedRolePages(): BackendRolePage[] | null {
  try {
    const raw = localStorage.getItem(CACHED_ROLE_PAGES_KEY)
    if (!raw) return null
    const data = JSON.parse(raw)
    if (Array.isArray(data) && data.length > 0) return data as BackendRolePage[]
    return null
  } catch {
    return null
  }
}

/** 将页面权限配置写入 localStorage 缓存 */
export function saveCachedRolePages(pages: BackendRolePage[]) {
  try {
    localStorage.setItem(CACHED_ROLE_PAGES_KEY, JSON.stringify(pages))
  } catch {
    // localStorage 满了，静默失败
  }
}

export const useUserStore = defineStore('user', () => {
  const userInfo = ref<UserInfo | null>(loadUserInfo())
  const token = ref<string>(sessionStorage.getItem('token') || localStorage.getItem('token') || '')

  const isLoggedIn = computed(() => !!token.value && !!userInfo.value)
  const hasPermission = computed(
    () => (perm: string) => userInfo.value?.permissions?.includes(perm) ?? false,
  )

  // 多标签页同步登出：其他标签页清除 token 时自动登出
  if (typeof window !== 'undefined') {
    window.addEventListener('storage', (e) => {
      if (e.key === 'token' && !e.newValue && token.value) {
        token.value = ''
        userInfo.value = null
      }
    })
  }

  function setSession(newToken: string, info: UserInfo) {
    token.value = newToken
    userInfo.value = info
    localStorage.setItem('token', newToken)
    localStorage.setItem('userInfo', JSON.stringify(info))
    sessionStorage.setItem('token', newToken)
    sessionStorage.setItem('userInfo', JSON.stringify(info))
  }

  /**
   * 从后端 API 或 localStorage 缓存获取页面权限配置，更新当前用户 permissions。
   *
   * 优先级：
   * 1. 调用后端 API（管理员可成功，非管理员可能被拒）
   * 2. 读取 localStorage 缓存（admin 保存权限时同步写入）
   * 3. 回退到硬编码 ROUTE_ROLES
   */
  async function fetchAndSyncPagePermissions() {
    if (!userInfo.value || userInfo.value.roles.length === 0) return

    const userRoles = userInfo.value.roles
    const isAdmin = userRoles.includes('admin')

    // 尝试从后端 API 获取（管理员通常可成功）
    try {
      const res = await getRolePagePermissions()
      const body = res.data
      if (body.code === 0 && body.data?.pages?.length) {
        const { pages } = body.data
        // 缓存到 localStorage，供非管理员用户后续使用
        saveCachedRolePages(pages)
        const paths = deriveAllowedPaths(pages, userRoles, isAdmin)
        if (paths.length > 0 || isAdmin) {
          userInfo.value.permissions = paths
          persistUserInfo()
          return
        }
        // deriveAllowedPaths 返回空数组 → 配置可能有问题，继续尝试缓存
      }
    } catch (_err) {
      // API 失败（网络错误或权限不足），继续尝试缓存
    }

    // 尝试从 localStorage 缓存读取
    const cached = loadCachedRolePages()
    if (cached) {
      const paths = deriveAllowedPaths(cached, userRoles, isAdmin)
      if (paths.length > 0) {
        userInfo.value.permissions = paths
        persistUserInfo()
        return
      }
    }

    // 最终兜底：硬编码 ROUTE_ROLES
    console.warn('[userStore] 后端 API 和本地缓存均不可用，回退到硬编码 ROUTE_ROLES')
    syncPermissionsFromHardcoded(userInfo.value)
  }

  function persistUserInfo() {
    if (!userInfo.value) return
    localStorage.setItem('userInfo', JSON.stringify(userInfo.value))
    if (sessionStorage.getItem('userInfo')) {
      sessionStorage.setItem('userInfo', JSON.stringify(userInfo.value))
    }
  }

  /** 登录成功后刷新当前用户的权限（优先后端配置，回退到硬编码） */
  function syncPermissions() {
    if (!userInfo.value || userInfo.value.roles.length === 0) return
    syncPermissionsFromHardcoded(userInfo.value)
  }

  /**
   * 用后端最新数据更新当前用户角色 — 供个人中心调用。
   * @param roleCode 后端返回的 role_code（字符串或数字）
   * @param roleName 后端返回的 role_name（中文标签）
   */
  function updateRole(roleCode?: string | number, roleName?: string) {
    if (!userInfo.value) return
    const role = mapRoleCode(roleCode, roleName)
    if (!userInfo.value.roles.includes(role)) {
      userInfo.value.roles = [role]
      syncPermissions()
    }
  }

  async function login(credentials: {
    username: string
    password: string
    remember?: boolean
  }): Promise<void> {
    const res = await loginApi({
      account: credentials.username,
      password: credentials.password,
      remember: credentials.remember,
    })

    const data = res.data.data
    if (!data?.token || !data.user_info) {
      throw new Error('登录失败')
    }

    const role = mapRoleCode(data.user_info.role_code, data.user_info.role_name)

    setSession(data.token, {
      id: Number(data.user_info.id),
      username: data.user_info.account,
      nickname: data.user_info.realname || data.user_info.account,
      roles: [role],
      permissions: [],
    })

    // 加载页面权限：API → 缓存 → 硬编码兜底
    await fetchAndSyncPagePermissions()

    if (data.token_expire_time) {
      localStorage.setItem('tokenExpireTime', data.token_expire_time)
    }
  }

  function logout(): void {
    token.value = ''
    userInfo.value = null
    localStorage.removeItem('token')
    localStorage.removeItem('userInfo')
    localStorage.removeItem('tokenExpireTime')
    localStorage.removeItem('auto_login_flag')
    sessionStorage.removeItem('token')
    sessionStorage.removeItem('userInfo')
    sessionStorage.removeItem('force_pwd_change_needed')
    window.dispatchEvent(new StorageEvent('storage', { key: 'token', newValue: null, oldValue: 'logged-out' }))
  }

  return { userInfo, token, isLoggedIn, hasPermission, setSession, login, logout, syncPermissions, updateRole, fetchAndSyncPagePermissions }
})
