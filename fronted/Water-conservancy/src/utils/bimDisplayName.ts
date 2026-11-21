/** 将 BIM 模型内部英文名映射为界面展示用中文名 */

const PIER_RE = /^pier_(\d+)$/
const GATE_LEAF_RE = /^gateLeaf_(\d+)$/
const PH_WINDOW_RE = /^phWindow_(\d+)_(\d+)$/

export function getBimDisplayName(internalName: string): string {
  if (!internalName) return '工程构件'

  const pier = internalName.match(PIER_RE)
  if (pier) return `${pier[1]}号阀墩`

  const gate = internalName.match(GATE_LEAF_RE)
  if (gate) return `${Number(gate[1]) + 1}号阀门`

  if (internalName === '向家坝大坝' || internalName === '向家坝大坝场景') return '向家坝大坝（坝体）'
  if (/^\d+号阀门$/.test(internalName)) return internalName.replace('阀门', '阀室孔口')

  const win = internalName.match(PH_WINDOW_RE)
  if (win) return `厂房窗格 ${win[1]}-${win[2]}`

  return internalName
}

export function getBimDefaultDetail(internalName: string): string {
  if (PIER_RE.test(internalName)) return '混凝土阀墩 · 分隔泄洪孔 · 承力结构'
  if (GATE_LEAF_RE.test(internalName)) return '钢制泄洪阀叶 · 液压启闭 · 开度联动'
  if (PH_WINDOW_RE.test(internalName)) return '厂房外立面采光窗'
  if (internalName === '向家坝大坝' || internalName === '向家坝大坝场景') {
    return '混凝土坝体 · 非阀门叶，点击阀孔开口可选中表孔'
  }
  if (/^\d+号阀门$/.test(internalName)) return '阀室孔口结构 · 请点孔口中的阀叶选中本孔'
  return '向家坝水电站 BIM 工程构件'
}
