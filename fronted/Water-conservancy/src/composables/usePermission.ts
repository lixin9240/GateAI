import { computed } from 'vue'
import { ROUTE_ROLES, type UserRole } from '@/constants/roles'
import { useUserStore } from '@/stores/user'

export function usePermission() {
  const userStore = useUserStore()

  const roles = computed(() => userStore.userInfo?.roles ?? [])

  function hasRole(target: UserRole | UserRole[]): boolean {
    if (roles.value.length === 0) return true
    const list = Array.isArray(target) ? target : [target]
    return list.some((role) => roles.value.includes(role))
  }

  /**
   * 判断当前用户是否有某个页面路由的访问权限。
   * 优先使用后端配置的 permissions 列表；
   * 如果 permissions 为空（尚未加载），回退到硬编码 ROUTE_ROLES。
   *
   * 重要：仅对 ROUTE_ROLES 中已注册的路径做权限校验；
   * 未注册的路径（如父级路由 /dashboard）默认放行。
   */
  function hasRoutePermission(path: string): boolean {
    // 系统管理员硬保证：始终拥有所有页面权限
    if (roles.value.includes('admin')) return true

    const perms = userStore.userInfo?.permissions

    // 如果 permissions 已从后端或硬编码加载，用它来判断
    if (perms && perms.length > 0) {
      // 该路径在 ROUTE_ROLES 中有角色限制 → 必须在 permissions 中
      if (path in ROUTE_ROLES) return perms.includes(path)
      // 未在 ROUTE_ROLES 中定义 → 无限制，放行
      return true
    }

    // 兜底：permissions 为空时使用硬编码 ROUTE_ROLES
    const allowed = ROUTE_ROLES[path]
    if (!allowed) return true
    if (roles.value.length === 0) return true
    return allowed.some((role) => roles.value.includes(role))
  }

  return { roles, hasRole, hasRoutePermission }
}
