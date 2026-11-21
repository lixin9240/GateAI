# 水电站阀门智能调度系统

> 本项目为水利业务管理前后端协同开发系统
> 技术栈：前端 Vue 3 + Vite + TypeScript + Element Plus + Pinia | 后端 Laravel

## 开发流程
1. 整理客户需求、绘制原型图
2. 设计数据库表结构
3. 编写接口文档、后端接口开发
4. 前端页面开发与接口联调
5. 系统整体测试

## 项目结构

```
src/
├── app/                      # 应用入口
│   ├── layouts/              # 布局组件 (MainLayout.vue)
│   ├── providers/            # 上下文提供者
│   └── router/               # 路由配置
├── assets/                   # 静态资源
│   ├── images/
│   └── styles/               # 全局样式 & 设计 Token
├── constants/                # 全局常量 & 字典
├── features/                 # 业务模块（高内聚）
│   ├── auth/                 # 认证模块
│   ├── dashboard/            # 仪表盘
│   ├── flood-control/        # 防汛抗旱
│   ├── hydrological/         # 水文监测
│   ├── system/               # 系统管理
│   ├── water-project/        # 水利工程
│   ├── water-quality/        # 水质监测
│   └── water-resource/       # 水资源管理
├── shared/                   # 全局共享
│   ├── api/                  # Axios 请求封装
│   ├── components/           # 共享业务组件
│   ├── composables/          # 共享组合式函数
│   ├── types/                # 全局类型定义
│   ├── ui/                   # 基础 UI 组件
│   └── utils/                # 工具函数
└── stores/                   # Pinia 状态管理
docs/                         # 项目文档
├── adr/                      # 架构决策记录
├── api.md                    # 通用接口清单
└── components.md             # 通用组件清单
```

## 命名规范

- **目录名**：`kebab-case`（如 `water-quality/`）
- **组件文件**：`PascalCase`（如 `MonitorPage.vue`）
- **Composable**：`camelCase`，`use` 前缀（如 `usePagination.ts`）
- **路径别名**：`@` → `src/`

## 开发命令

```bash
npm run dev      # 启动开发服务器
npm run build    # 构建生产版本
npm run lint     # ESLint 检查并自动修复
npm run format   # Prettier 格式化
npm run preview  # 预览构建结果
```

## 开发规范

本项目严格遵循《前端开发规范与最佳实践（优化版）》，关键约束：
- 单文件 ≤ 600 行
- `<style>` 必须添加 `scoped`
- 禁止 `as any` / `@ts-ignore` 绕过类型检查
- 接口字段以接口文档为准，禁止自行编造
- 字典集中维护在 `constants/` 或 Pinia Store
