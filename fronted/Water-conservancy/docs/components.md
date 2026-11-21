# 通用组件清单

> 按《前端开发规范与最佳实践》2.2 节要求，所有通用组件在此登记。

| 组件路径 | 用途 | Props | Emits | Slots | 负责人 |
|----------|------|-------|-------|-------|--------|
| `@/layouts/MainLayout.vue` | 主框架布局（侧边栏 + 顶栏 + 内容区） | - | - | default | - |
| `@/layouts/AppHeader.vue` | 顶栏（折叠 / 标题 / 时钟 / 用户菜单） | `collapsed: boolean` | `toggleCollapse` | - | - |
| `@/layouts/AppSidebar.vue` | 侧边栏导航（九大板块菜单） | `collapsed: boolean` | - | - | - |
| `@/components/NotFoundPage.vue` | 404 页面不存在 | - | - | - | - |

**待开发（占位文件已创建，暂未登记 Props/Emits）：**

| 组件路径 | 用途 | 状态 |
|----------|------|------|
| `@/components/EmergencyStop.vue` | 全局急停按钮（所有页面强制引用） | 待实现 |
| `@/layouts/AppFooter.vue` | 页脚 | 待实现 |
| `@/layouts/DashboardLayout.vue` | 监控大屏全屏布局（当前未启用） | 备用 |

> 新增通用组件后请及时更新此表。
