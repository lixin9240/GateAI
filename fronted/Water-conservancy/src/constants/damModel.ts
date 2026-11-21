// ============================================================
// 大坝 GLB 模型配置 — 可替换为 Sketchfab / Poly Haven 等公开素材
// ============================================================

/** 混元原始导出（约 150 万面，仅备份，勿直接用于 Web） */
export const DAM_HUNYUAN_RAW_URL = '/models/hunyuan-raw.glb'

/** 更新此版本号可强制浏览器重新拉取 GLB（避免缓存旧模型） */
export const DAM_MODEL_VERSION = 'procedural-solid-v1'

/** 当前使用程序化实体大坝 GLB（npm run generate:dam）；混元模型见 hunyuan-raw.glb */
export const DAM_MODEL_URL = `/models/xiangjiaba-dam.glb?v=${DAM_MODEL_VERSION}`

/** 模型绕 Y 轴旋转 */
export const DAM_MODEL_ROTATION_Y = 0

/**
 * 阀门网格命名规则（GLB 内节点名需匹配，便于开度动画绑定）
 * - gateLeaf_0 … gateLeaf_N  阀门叶（沿 Y 轴抬升）
 * - N号阀门                  阀室组（hover 交互）
 */
export const DAM_GATE_NAME_PREFIX = 'gateLeaf_'
export const DAM_GATE_COUNT = 5

/** 大坝在场景中的锚点（与水面/泄洪特效对齐） */
export const DAM_HERO_ANCHOR = { x: 0, y: 0, z: 0 } as const

/** 模型加载后的统一缩放 */
export const DAM_MODEL_SCALE = 1
/** 程序化实体大坝与水面/泄洪特效对齐（勿用混元 offset） */
export const DAM_MODEL_OFFSET = { x: 0, y: 0, z: 0 } as const

/**
 * 推荐公开素材替换来源（CC0 / 可商用，需自行下载 glb 并重命名为 xiangjiaba-dam.glb）：
 * - Sketchfab 搜索 "hydroelectric dam" + Downloadable + CC 许可
 * - Poly Haven https://polyhaven.com/models
 * - Meshy CC0 dam 标签 https://www.meshy.ai/tags/dam
 * 替换后请在 Blender 中为阀门叶命名为 gateLeaf_0 … gateLeaf_4
 */
