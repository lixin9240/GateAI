// ============================================================
// 通用工具函数
// ============================================================

/**
 * 从环境变量提取后端 API 源站 origin。
 * 优先级：VITE_DEV_API_PROXY（dev 代理目标）> VITE_API_BASE_URL（生产完整地址）> 当前页面 origin
 */
export function getApiOrigin(): string {
  // dev 模式：直接用 Vite 代理目标地址（因为只有 /api 被代理）
  const proxyTarget = import.meta.env.VITE_DEV_API_PROXY as string | undefined
  if (proxyTarget && (proxyTarget.startsWith('http://') || proxyTarget.startsWith('https://'))) {
    try {
      return new URL(proxyTarget).origin
    } catch { /* URL 解析失败则继续 */ }
  }
  // 生产模式：API base URL 包含完整地址
  const base = import.meta.env.VITE_API_BASE_URL as string | undefined
  if (base && (base.startsWith('http://') || base.startsWith('https://'))) {
    try {
      return new URL(base).origin
    } catch { /* URL 解析失败则回退 */ }
  }
  // 兜底
  if (typeof window !== 'undefined') {
    return window.location.origin
  }
  return ''
}

/**
 * 修复头像/文件 URL — 后端可能返回不同格式：
 * - 完整的 http(s) URL（含签名）→ 直接使用
 * - OSS 缺 bucket 前缀（oss-...）→ 补全 https://bucket.
 * - 服务器相对路径（/storage/...）→ 拼接 API 源站
 * - data: base64 → 直接使用
 * - 其他裸路径 → 补 https://（兜底）
 */
export function fixAvatarUrl(url: string): string {
  if (!url) return ''
  if (url.startsWith('data:') || url.startsWith('http://') || url.startsWith('https://')) return url
  if (url.startsWith('oss-')) return 'https://fmy-base.' + url
  if (url.startsWith('/')) return getApiOrigin() + url
  return 'https://' + url
}
