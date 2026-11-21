// ============================================================
// API 层统一导出
// ============================================================
export { default as http } from './request'
export * from './auth'

// 告警管理 (§3)
export * as warningApi from './warning'

// 调度决策 (§4)
export * as dispatchApi from './dispatch'
export * as dispatchPageApi from './dispatchPage'

// 水库管理 (§5)
export * as reservoirApi from './reservoir'

// 边缘节点管理 (§6)
export * as edgeNodeApi from './edgeNode'

// 数字孪生 (§9)
export * as simulationApi from './simulation'

// 边缘端上报 (§11)
export * as edgeReportApi from './edgeReport'

// 边缘端配置下发 (§12 + §8.3.6)
export * as edgeConfigApi from './edgeConfig'
