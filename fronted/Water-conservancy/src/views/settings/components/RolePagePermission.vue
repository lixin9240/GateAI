<script setup lang="ts">
// ============================================================
// 角色页面权限 — 分配/展示每个角色可访问的页面
// 对接后端 §8.5 角色页面权限配置（v1/settings/role-permissions）
// ============================================================
import { ref, reactive, computed, onMounted } from 'vue'
import {
  ElTable,
  ElTableColumn,
  ElCheckbox,
  ElButton,
  ElMessage,
  ElMessageBox,
  ElTag,
  ElCard,
  ElAlert,
} from 'element-plus'
import { Refresh, Check, Close } from '@element-plus/icons-vue'
import { type UserRole } from '@/constants/roles'
import { ROLE_LABEL_MAP, useUserStore, saveCachedRolePages } from '@/stores/user'
import {
  getRolePagePermissions,
  saveRolePagePermissions,
  resetRolePagePermissions,
  type BackendRolePage,
} from '@/api/settings'

// ── 角色列表（可编辑的角色，不含系统管理员 — admin 硬编码拥有所有页面权限） ──
const ALL_ROLES: UserRole[] = ['manager', 'dispatcher', 'operator', 'algorithm_engineer']

// ── 中文角色名 → UserRole 映射（与 user.ts ROLE_CODE_MAP 保持一致） ──
const CN_TO_ROLE: Record<string, UserRole> = {
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

function chineseNameToRole(cn: string): UserRole | null {
  return CN_TO_ROLE[cn] ?? null
}

function roleToChineseName(role: UserRole): string {
  return ROLE_LABEL_MAP[role]
}

// ── 动态页面数据（从后端加载） ──
interface PageEntry {
  pageId: string
  pageName: string
  module: string
  path: string | null
}

const pageEntries = ref<PageEntry[]>([])
const loading = ref(false)

// ── 可编辑的权限状态 ──
interface EditablePerm {
  [pageId: string]: Set<UserRole>
}

const editingPerms = reactive<EditablePerm>({})
const originalPerms = reactive<EditablePerm>({})

function initPermsFromPages(pages: BackendRolePage[]) {
  for (const page of pages) {
    const roles: UserRole[] = []
    for (const cnName of page.authorizedRoleNames) {
      const role = chineseNameToRole(cnName)
      if (role) roles.push(role)
    }
    editingPerms[page.pageId] = new Set(roles)
    originalPerms[page.pageId] = new Set(roles)
  }
}

// ── 按模块分组的页面列表 ──
const moduleGroups = computed(() => {
  const groups: { module: string; pages: PageEntry[] }[] = []
  const seen = new Set<string>()
  for (const entry of pageEntries.value) {
    if (!seen.has(entry.module)) {
      seen.add(entry.module)
      groups.push({ module: entry.module, pages: [] })
    }
    const group = groups.find((g) => g.module === entry.module)!
    group.pages.push(entry)
  }
  return groups
})

// ── 是否有未保存的修改 ──
const hasChanges = computed(() => {
  for (const entry of pageEntries.value) {
    const orig = originalPerms[entry.pageId]
    const edit = editingPerms[entry.pageId]
    if (!orig || !edit) continue
    if (orig.size !== edit.size) return true
    for (const role of orig) {
      if (!edit.has(role)) return true
    }
  }
  return false
})

// ── 变更统计 ──
const changeStats = computed(() => {
  let added = 0
  let removed = 0
  for (const entry of pageEntries.value) {
    const orig = originalPerms[entry.pageId]
    const edit = editingPerms[entry.pageId]
    if (!orig || !edit) continue
    for (const role of ALL_ROLES) {
      if (edit.has(role) && !orig.has(role)) added++
      if (!edit.has(role) && orig.has(role)) removed++
    }
  }
  return { added, removed }
})

// ── 切换角色对页面的访问权限 ──
function toggleRole(pageId: string, role: UserRole) {
  const perms = editingPerms[pageId]
  if (!perms) return
  if (perms.has(role)) {
    if (perms.size <= 1) {
      ElMessage.warning('每个页面至少需要保留一个角色拥有访问权限')
      return
    }
    perms.delete(role)
  } else {
    perms.add(role)
  }
}

// ── 检查某个角色是否有某页面权限 ──
function hasRoleAccess(pageId: string, role: UserRole): boolean {
  return editingPerms[pageId]?.has(role) ?? false
}

// ── 全选 / 取消全选某个角色对所有页面的权限 ──
function toggleRoleForAll(role: UserRole, checked: boolean) {
  if (checked) {
    for (const entry of pageEntries.value) {
      editingPerms[entry.pageId]?.add(role)
    }
  } else {
    for (const entry of pageEntries.value) {
      const perms = editingPerms[entry.pageId]
      if (perms && perms.size > 1) {
        perms.delete(role)
      }
    }
    ElMessage.info('已取消该角色的大部分权限，每个页面至少保留了一个角色')
  }
}

// ── 切换某个模块所有页面对某个角色的权限 ──
function toggleRoleForModule(moduleName: string, role: UserRole, checked: boolean) {
  const group = moduleGroups.value.find((g) => g.module === moduleName)
  if (!group) return
  for (const page of group.pages) {
    const perms = editingPerms[page.pageId]
    if (!perms) continue
    if (checked) {
      perms.add(role)
    } else {
      if (perms.size > 1) {
        perms.delete(role)
      }
    }
  }
}

// ── 从后端加载权限数据 ──
async function loadPermissions() {
  loading.value = true
  try {
    const res = await getRolePagePermissions()
    const body = res.data
    if (body.code !== 0 || !body.data) {
      throw new Error(body.msg || '获取角色页面权限失败')
    }
    const { pages } = body.data
    // 构建页面列表
    pageEntries.value = pages.map((p) => ({
      pageId: p.pageId,
      pageName: p.pageName,
      module: p.module,
      path: p.path,
    }))
    // 初始化权限状态
    initPermsFromPages(pages)
  } catch (err: any) {
    ElMessage.error(err.message || '加载角色页面权限失败')
    console.error('[角色页面权限] 加载失败:', err)
  } finally {
    loading.value = false
  }
}

// ── 保存权限配置 ──
const saving = ref(false)
async function savePermissions() {
  saving.value = true
  try {
    await ElMessageBox.confirm(
      '确定保存角色页面权限配置？修改将立即生效，影响所有在线用户的页面访问。',
      '确认保存',
      { confirmButtonText: '确定保存', cancelButtonText: '取消', type: 'warning' },
    )
  } catch {
    saving.value = false
    return
  }

  try {
    // 构建保存数据：permissions[{pageId, roleNames}]
    const permissions = pageEntries.value.map((entry) => ({
      pageId: entry.pageId,
      roleNames: [...(editingPerms[entry.pageId] ?? [])].map(roleToChineseName),
    }))

    const res = await saveRolePagePermissions({ permissions })
    const body = res.data
    if (body.code !== 0) {
      throw new Error(body.msg || '保存失败')
    }

    // 保存成功后同步原始状态
    for (const entry of pageEntries.value) {
      originalPerms[entry.pageId] = new Set(editingPerms[entry.pageId])
    }

    // 将当前权限配置写入 localStorage 缓存，供非管理员用户登录时读取
    const cachedPages: BackendRolePage[] = pageEntries.value.map((entry) => ({
      pageId: entry.pageId,
      pageName: entry.pageName,
      module: entry.module,
      path: entry.path,
      authorizedRoleNames: [...(editingPerms[entry.pageId] ?? [])].map(roleToChineseName),
    }))
    saveCachedRolePages(cachedPages)

    // 通知 userStore 刷新当前用户的页面权限（使变更即时生效）
    const userStore = useUserStore()
    await userStore.fetchAndSyncPagePermissions()

    ElMessage.success('角色页面权限已保存，用户的页面访问权限将立即更新')
  } catch (err: any) {
    ElMessage.error(err.message || '保存失败，请重试')
    console.error('[角色页面权限] 保存失败:', err)
  } finally {
    saving.value = false
  }
}

// ── 重置为默认配置 ──
async function resetToDefault() {
  try {
    await ElMessageBox.confirm(
      '确定将所有角色页面权限重置为系统默认配置？此操作不可撤销。',
      '确认重置',
      { confirmButtonText: '确定重置', cancelButtonText: '取消', type: 'warning' },
    )
  } catch {
    return
  }

  try {
    const res = await resetRolePagePermissions()
    const body = res.data
    if (body.code !== 0 || !body.data) {
      throw new Error(body.msg || '重置失败')
    }
    // 用后端返回的默认数据重新初始化
    const { pages } = body.data
    pageEntries.value = pages.map((p) => ({
      pageId: p.pageId,
      pageName: p.pageName,
      module: p.module,
      path: p.path,
    }))
    initPermsFromPages(pages)
    // 同步更新 localStorage 缓存
    saveCachedRolePages(pages)
    ElMessage.success('已重置为系统默认权限配置')
  } catch (err: any) {
    ElMessage.error(err.message || '重置失败，请重试')
    console.error('[角色页面权限] 重置失败:', err)
  }
}

// ── 放弃修改恢复至上次保存 ──
async function resetToSaved() {
  try {
    await ElMessageBox.confirm('确定放弃所有修改，恢复为已保存的权限配置？', '确认重置', {
      type: 'warning',
    })
  } catch {
    return
  }
  for (const entry of pageEntries.value) {
    editingPerms[entry.pageId] = new Set(originalPerms[entry.pageId])
  }
  ElMessage.info('已恢复为上次保存的权限配置')
}

// ── 统计某个页面有权限的角色数 ──
// 只统计 ALL_ROLES 内的可编辑角色，避免把 admin（系统管理员，硬编码拥有全部权限）
// 计入分子，导致与表格可见列（4 个复选框）及分母 ALL_ROLES.length 对不上。
function roleCountForPage(pageId: string): number {
  const perms = editingPerms[pageId]
  if (!perms) return 0
  let count = 0
  for (const role of ALL_ROLES) {
    if (perms.has(role)) count++
  }
  return count
}

// ── 用于表格的扁平列表（带模块分组标识） ──
const tableData = computed(() => {
  const rows: (PageEntry & { _isFirst: boolean; _rowSpan: number })[] = []
  for (const group of moduleGroups.value) {
    group.pages.forEach((page, idx) => {
      rows.push({
        ...page,
        _isFirst: idx === 0,
        _rowSpan: group.pages.length,
      })
    })
  }
  return rows
})

onMounted(() => {
  loadPermissions()
})
</script>

<template>
  <div class="role-page-permission">
    <!-- 顶部提示 -->
    <ElAlert
      title="在此页面可配置每个角色能访问的系统页面。修改后需点击「保存权限配置」生效，用户的页面菜单将实时更新。"
      type="info"
      :closable="false"
      show-icon
      style="margin-bottom: 16px"
    />

    <!-- 工具栏 -->
    <div class="rpp-toolbar">
      <div class="rpp-toolbar__left">
        <ElButton :icon="Refresh" @click="resetToSaved" :disabled="!hasChanges">
          恢复至已保存
        </ElButton>
        <ElButton :icon="Refresh" @click="resetToDefault">
          重置为默认配置
        </ElButton>
        <span v-if="hasChanges" class="rpp-change-hint">
          已修改：
          <span v-if="changeStats.added > 0" class="rpp-change-hint--added">
            +{{ changeStats.added }} 项授权
          </span>
          <span v-if="changeStats.added > 0 && changeStats.removed > 0" class="rpp-change-hint--sep">，</span>
          <span v-if="changeStats.removed > 0" class="rpp-change-hint--removed">
            -{{ changeStats.removed }} 项取消
          </span>
        </span>
      </div>
      <ElButton
        type="primary"
        :loading="saving"
        :disabled="!hasChanges"
        @click="savePermissions"
      >
        <ElIcon v-if="!saving"><Check /></ElIcon>
        保存权限配置
      </ElButton>
    </div>

    <!-- 角色全选快捷操作行 -->
    <ElCard shadow="never" class="rpp-quick-row">
      <div class="rpp-quick-row__inner">
        <span class="rpp-quick-row__label">快捷操作：</span>
        <div
          v-for="role in ALL_ROLES"
          :key="role"
          class="rpp-quick-role"
        >
          <ElTag style="margin-right: 4px">{{ ROLE_LABEL_MAP[role] }}</ElTag>
          <ElButton
            size="small"
            text
            type="primary"
            @click="toggleRoleForAll(role, true)"
          >
            全选
          </ElButton>
          <ElButton
            size="small"
            text
            type="danger"
            @click="toggleRoleForAll(role, false)"
          >
            取消全选
          </ElButton>
        </div>
      </div>
    </ElCard>

    <!-- 加载状态 -->
    <div v-if="loading" class="rpp-loading">
      <p>正在加载权限配置…</p>
    </div>

    <!-- 权限矩阵表格 -->
    <div v-else class="rpp-matrix-wrapper">
      <ElTable
        :data="tableData"
        border
        stripe
        style="width: 100%"
        :span-method="({ row }: { row: any }) => {
          if (row._isFirst) {
            return { rowspan: row._rowSpan, colspan: 1 }
          }
          return { rowspan: 0, colspan: 0 }
        }"
      >
        <!-- 模块列（合并单元格） -->
        <ElTableColumn label="功能模块" width="140" fixed="left" align="center">
          <template #default="scope">
            <span v-if="scope.row._isFirst" class="rpp-module-name">
              {{ scope.row.module }}
            </span>
          </template>
        </ElTableColumn>

        <!-- 页面列 -->
        <ElTableColumn label="页面名称" min-width="160" fixed="left">
          <template #default="scope">
            <div class="rpp-page-cell">
              <span class="rpp-page-title">{{ scope.row.pageName }}</span>
              <span v-if="scope.row.path" class="rpp-page-path">{{ scope.row.path }}</span>
              <span v-else class="rpp-page-path rpp-page-path--none">（无固定路径）</span>
            </div>
          </template>
        </ElTableColumn>

        <!-- 角色列（动态生成复选框矩阵） -->
        <ElTableColumn
          v-for="role in ALL_ROLES"
          :key="role"
          :label="ROLE_LABEL_MAP[role]"
          width="110"
          align="center"
        >
          <template #header>
            <div class="rpp-role-header">
              <span>{{ ROLE_LABEL_MAP[role] }}</span>
            </div>
          </template>
          <template #default="scope">
            <ElCheckbox
              :model-value="hasRoleAccess(scope.row.pageId, role)"
              @change="toggleRole(scope.row.pageId, role)"
              :disabled="false"
            />
          </template>
        </ElTableColumn>

        <!-- 统计列 -->
        <ElTableColumn label="可编辑角色数" width="120" align="center" fixed="right">
          <template #default="scope">
            <ElTag
              :type="roleCountForPage(scope.row.pageId) >= 4 ? 'success' : roleCountForPage(scope.row.pageId) >= 2 ? 'warning' : 'danger'"
              size="small"
            >
              {{ roleCountForPage(scope.row.pageId) }} / {{ ALL_ROLES.length }}
            </ElTag>
          </template>
        </ElTableColumn>
      </ElTable>
    </div>

    <!-- 底部说明 -->
    <div class="rpp-footer-note">
      <p>💡 提示：</p>
      <ul>
        <li>勾选表示该角色可以访问对应页面，取消勾选后该角色的用户将无法看到此页面菜单</li>
        <li>每个页面至少需要保留一个可访问角色</li>
        <li>页面列表从后端动态加载，如需新增或删除页面请联系后端开发</li>
        <li>修改保存后将实时生效，已登录用户需刷新页面后菜单更新</li>
        <li>「重置为默认配置」将恢复所有角色对所有页面的系统默认权限</li>
      </ul>
    </div>
  </div>
</template>

<style scoped lang="scss">
.role-page-permission {
  min-height: 400px;
}

.rpp-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
  gap: 12px;

  &__left {
    display: flex;
    align-items: center;
    gap: 12px;
  }
}

.rpp-change-hint {
  font-size: var(--font-size-sm);
  color: var(--color-text-secondary);

  &--added {
    color: #16a34a;
    font-weight: 600;
  }
  &--removed {
    color: #dc2626;
    font-weight: 600;
  }
  &--sep {
    color: var(--color-text-secondary);
  }
}

.rpp-quick-row {
  margin-bottom: 16px;

  :deep(.el-card__body) {
    padding: 12px 16px;
  }

  &__inner {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px 16px;
  }

  &__label {
    font-weight: 600;
    font-size: var(--font-size-sm);
    color: var(--color-text-secondary);
    white-space: nowrap;
  }
}

.rpp-quick-role {
  display: flex;
  align-items: center;
  gap: 2px;
}

.rpp-loading {
  text-align: center;
  padding: 80px 0;
  color: var(--color-text-secondary);
  font-size: var(--font-size-base);
}

.rpp-matrix-wrapper {
  overflow-x: auto;

  :deep(.el-table) {
    font-size: var(--font-size-sm);

    th {
      background: var(--el-fill-color-light);
      font-weight: 600;
      white-space: nowrap;
    }

    .el-checkbox {
      height: auto;
    }
  }
}

.rpp-module-name {
  font-weight: 700;
  font-size: var(--font-size-base);
  color: var(--color-primary);
  white-space: nowrap;
}

.rpp-page-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.rpp-page-title {
  font-weight: 500;
  font-size: var(--font-size-base);
}

.rpp-page-path {
  font-size: 11px;
  color: var(--color-text-secondary);
  font-family: monospace;

  &--none {
    font-style: italic;
    color: var(--color-text-placeholder);
  }
}

.rpp-role-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  font-size: var(--font-size-sm);
}

.rpp-footer-note {
  margin-top: 20px;
  padding: 16px;
  background: var(--el-fill-color-lighter);
  border-radius: var(--border-radius-base);
  font-size: var(--font-size-sm);
  color: var(--color-text-secondary);
  line-height: 1.8;

  p {
    margin: 0 0 4px;
    font-weight: 600;
  }

  ul {
    margin: 0;
    padding-left: 20px;
  }
}
</style>
