// ============================================================
// 大坝 GLB 加载器 — 保持原始坐标，PBR 烘焙，阀门开度绑定
// ============================================================
import * as THREE from 'three'
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js'
import {
  DAM_MODEL_URL,
  DAM_MODEL_SCALE,
  DAM_MODEL_OFFSET,
  DAM_MODEL_ROTATION_Y,
  DAM_GATE_NAME_PREFIX,
  DAM_HERO_ANCHOR,
} from '@/constants/damModel'
import { PIER_MAT, DAM_BODY_MAT, GATE_FRAME_COLOR } from '@/utils/gateVisualTheme'
import { applyGateLeafTransform } from '@/utils/gateKinematics'
import { buildDamBody } from './terrainBuilder'
import { createConcreteTextures, createMetalTextures } from './proceduralTextures'

export interface GateLeafState {
  object: THREE.Object3D
  baseY: number
  baseScaleY: number
}

export interface DamModelInstance {
  root: THREE.Group
  gateLeaves: GateLeafState[]
  powerhouse: THREE.Object3D | null
  hoverables: THREE.Object3D[]
  fromGltf: boolean
  heroCenter: THREE.Vector3
  applyGateOpening: (ratio: number) => void
  applyPerGateOpening: (openingsPct: number[]) => void
  dispose: () => void
}

function enhanceMaterials(root: THREE.Object3D, envMap?: THREE.Texture | null) {
  root.traverse((obj) => {
    if (!(obj instanceof THREE.Mesh)) return
    obj.castShadow = true
    obj.receiveShadow = true
    const mats = Array.isArray(obj.material) ? obj.material : [obj.material]
    mats.forEach((m) => {
      if (!(m instanceof THREE.MeshStandardMaterial)) return
      if (envMap) {
        m.envMap = envMap
        m.envMapIntensity = m.metalness > 0.5 ? 0.95 : 0.75
      }
      m.needsUpdate = true
    })
  })
}

function collectGateLeaves(root: THREE.Object3D): GateLeafState[] {
  const gates: GateLeafState[] = []
  root.traverse((obj) => {
    if (!obj.name.startsWith(DAM_GATE_NAME_PREFIX)) return
    gates.push({
      object: obj,
      baseY: obj.position.y,
      baseScaleY: obj.scale.y || 1,
    })
  })
  gates.sort((a, b) => a.object.name.localeCompare(b.object.name))
  return gates
}

function collectHoverables(root: THREE.Object3D): THREE.Object3D[] {
  const list: THREE.Object3D[] = []
  root.traverse((obj) => {
    if (!obj.name || obj.name === '坝顶') return
    if (
      obj.name.includes('坝') ||
      obj.name.includes('阀门') ||
      obj.name.includes('厂房') ||
      obj.name.startsWith(DAM_GATE_NAME_PREFIX) ||
      obj.name.startsWith('pier_')
    ) {
      list.push(obj)
    }
  })
  const ph = root.getObjectByName('向家坝电站厂房')
  if (ph && !list.includes(ph)) list.push(ph)
  return list
}

function applyGateOpeningToLeaves(gates: GateLeafState[], ratio: number) {
  const r = Math.min(1, Math.max(0, ratio))
  gates.forEach((g) => applyGateLeafTransform(g.object, r, g.baseY, g.baseScaleY))
}

function applyPerGateOpeningToLeaves(gates: GateLeafState[], openingsPct: number[]) {
  gates.forEach((g, i) => {
    const pct = openingsPct[i] ?? openingsPct[openingsPct.length - 1] ?? 0
    const r = Math.min(1, Math.max(0, pct / 100))
    applyGateLeafTransform(g.object, r, g.baseY, g.baseScaleY)
  })
}

function computeHeroCenter(root: THREE.Object3D): THREE.Vector3 {
  const body = root.getObjectByName('向家坝大坝')
  if (body) {
    const box = new THREE.Box3().setFromObject(body)
    return box.getCenter(new THREE.Vector3())
  }
  const box = new THREE.Box3().setFromObject(root)
  return box.getCenter(new THREE.Vector3())
}

function buildFallbackInstance(envMap?: THREE.Texture | null): DamModelInstance {
  const root = buildDamBody(envMap)
  const gateLeaves = collectGateLeaves(root)
  rebakeDamPBR(root, envMap)
  return {
    root,
    gateLeaves,
    powerhouse: root.getObjectByName('向家坝电站厂房') ?? null,
    hoverables: collectHoverables(root),
    fromGltf: false,
    heroCenter: computeHeroCenter(root),
    applyGateOpening(ratio) {
      applyGateOpeningToLeaves(gateLeaves, ratio)
    },
    applyPerGateOpening(openingsPct) {
      applyPerGateOpeningToLeaves(gateLeaves, openingsPct)
    },
    dispose() {
      root.traverse((obj) => {
        if (obj instanceof THREE.Mesh) {
          obj.geometry?.dispose()
          const mats = Array.isArray(obj.material) ? obj.material : [obj.material]
          mats.forEach((m) => m.dispose())
        }
      })
    },
  }
}

/** 加载 GLB；失败时回退程序化模型。不居中变换，保持与水面/泄洪对齐。 */
export function loadDamModel(
  envMap?: THREE.Texture | null,
  url = DAM_MODEL_URL,
): Promise<DamModelInstance> {
  const loader = new GLTFLoader()

  return new Promise((resolve) => {
    loader.load(
      url,
      (gltf) => {
        const root = gltf.scene as THREE.Group
        root.name = '向家坝大坝场景'
        root.position.set(
          DAM_HERO_ANCHOR.x + DAM_MODEL_OFFSET.x,
          DAM_HERO_ANCHOR.y + DAM_MODEL_OFFSET.y,
          DAM_HERO_ANCHOR.z + DAM_MODEL_OFFSET.z,
        )
        root.scale.setScalar(DAM_MODEL_SCALE)
        root.rotation.y = DAM_MODEL_ROTATION_Y
        root.renderOrder = 1

        enhanceMaterials(root, envMap)
        // 混元 GLB 保留导出材质；程序化 pier/阀门才重烘焙 PBR
        const gateLeaves = collectGateLeaves(root)
        if (gateLeaves.length === 0) {
          enhanceGltfDamVisibility(root, envMap)
        } else {
          rebakeDamPBR(root, envMap)
        }

        let triCount = 0
        root.traverse((obj) => {
          if (obj instanceof THREE.Mesh && obj.geometry) {
            triCount += obj.geometry.index
              ? obj.geometry.index.count / 3
              : obj.geometry.attributes.position.count / 3
          }
        })
        console.info(
          `[damModelLoader] 混元 GLB 已加载 (${Math.round(triCount).toLocaleString()} 面)`,
          url,
        )
        const powerhouse = root.getObjectByName('向家坝电站厂房') ?? null
        if (powerhouse) {
          powerhouse.userData.detail = '向家坝水电站 BIM 工程构件'
        }

        resolve({
          root,
          gateLeaves,
          powerhouse,
          hoverables: collectHoverables(root),
          fromGltf: true,
          heroCenter: computeHeroCenter(root),
          applyGateOpening(ratio) {
            applyGateOpeningToLeaves(gateLeaves, ratio)
          },
          applyPerGateOpening(openingsPct) {
            applyPerGateOpeningToLeaves(gateLeaves, openingsPct)
          },
          dispose() {
            root.traverse((obj) => {
              if (obj instanceof THREE.Mesh) obj.geometry?.dispose()
            })
          },
        })
      },
      undefined,
      (err) => {
        console.warn(
          '[damModelLoader] GLB 加载失败，使用程序化回退模型。请 Ctrl+F5 强刷或检查 public/models/xiangjiaba-dam.glb',
          err,
        )
        resolve(buildFallbackInstance(envMap))
      },
    )
  })
}

/** 数字孪生白底场景 — 加深坝体对比度，避免 PBR 反射与环境融为一体 */
export function applyTwinLightBackgroundMaterials(root: THREE.Object3D) {
  root.traverse((obj) => {
    if (!(obj instanceof THREE.Mesh)) return
    const mats = Array.isArray(obj.material) ? obj.material : [obj.material]
    mats.forEach((m) => {
      if (!(m instanceof THREE.MeshStandardMaterial)) return
      const isGateLeaf =
        obj.name.startsWith(DAM_GATE_NAME_PREFIX) || /^gateLeaf_\d+$/.test(obj.name)
      const isPier = obj.name.startsWith('pier_')
      const isGateSteel = obj.name.includes('阀门') && !isGateLeaf

      if (isGateLeaf) {
        m.color.setHex(0x4a5568)
        m.metalness = 0.48
        m.roughness = 0.44
        m.emissive.setHex(0x000000)
        m.emissiveIntensity = 0
      } else if (isPier) {
        m.color.setHex(PIER_MAT.color)
        m.metalness = PIER_MAT.metalness
        m.roughness = PIER_MAT.roughness
        m.emissive.setHex(PIER_MAT.emissive)
        m.emissiveIntensity = PIER_MAT.emissiveIntensity
      } else if (isGateSteel) {
        m.color.setHex(GATE_FRAME_COLOR)
        m.metalness = 0.4
        m.roughness = 0.72
        m.emissive.setHex(0x080a0e)
        m.emissiveIntensity = 0.02
      } else {
        m.color.setHex(DAM_BODY_MAT.color)
        m.metalness = DAM_BODY_MAT.metalness
        m.roughness = DAM_BODY_MAT.roughness
        m.emissive.setHex(DAM_BODY_MAT.emissive)
        m.emissiveIntensity = DAM_BODY_MAT.emissiveIntensity
      }
      m.envMap = null
      m.envMapIntensity = 0
      m.needsUpdate = true
    })
  })
}

/** 混元/外部 GLB 坝体 — 提亮材质便于在暗色场景中辨认 */
export function enhanceGltfDamVisibility(root: THREE.Object3D, envMap?: THREE.Texture | null) {
  root.traverse((obj) => {
    if (!(obj instanceof THREE.Mesh)) return
    obj.castShadow = true
    obj.receiveShadow = true
    obj.material = new THREE.MeshStandardMaterial({
      color: 0xc8d0d8,
      emissive: 0x445566,
      emissiveIntensity: 0.22,
      roughness: 0.68,
      metalness: 0.12,
      envMap: envMap ?? null,
      envMapIntensity: 1.15,
    })
  })
}

/** 收集坝体全部 mesh（用于描边高亮） */
export function collectDamMeshes(root: THREE.Object3D): THREE.Object3D[] {
  const meshes: THREE.Object3D[] = []
  root.traverse((obj) => {
    if (obj instanceof THREE.Mesh) meshes.push(obj)
  })
  return meshes
}

/** 为坝体全部网格烘焙 PBR（含 pier / 阀墩 / 坝体） */
export function rebakeDamPBR(root: THREE.Object3D, envMap?: THREE.Texture | null) {
  const concreteTex = createConcreteTextures(1024)
  const metalTex = createMetalTextures(1024)

  root.traverse((obj) => {
    if (!(obj instanceof THREE.Mesh)) return
    if (obj.name.startsWith('phWindow_')) return

    const isSteel =
      obj.name.startsWith(DAM_GATE_NAME_PREFIX) ||
      obj.name.includes('阀门') ||
      obj.name.startsWith('pier_')

    if (isSteel) {
      obj.material = new THREE.MeshStandardMaterial({
        map: metalTex.map,
        normalMap: metalTex.normalMap,
        roughnessMap: metalTex.roughnessMap,
        roughness: metalTex.roughness,
        metalness: metalTex.metalness,
        envMap: envMap ?? null,
        envMapIntensity: 0.95,
      })
    } else {
      obj.material = new THREE.MeshStandardMaterial({
        map: concreteTex.map,
        normalMap: concreteTex.normalMap,
        roughnessMap: concreteTex.roughnessMap,
        roughness: concreteTex.roughness,
        metalness: concreteTex.metalness,
        envMap: envMap ?? null,
        envMapIntensity: 0.78,
      })
    }
  })
}

/** 数字孪生俯视视角（库区侧 aerial，参考 EasyV p2） */
export function getTwinAerialCamera(root: THREE.Object3D, heroCenter: THREE.Vector3) {
  const box = new THREE.Box3().setFromObject(root)
  const size = box.getSize(new THREE.Vector3())
  const center = box.getCenter(new THREE.Vector3())
  const span = Math.max(size.x, size.y, size.z, 36)
  return {
    position: new THREE.Vector3(
      center.x - span * 0.52,
      center.y + span * 0.92,
      center.z - span * 1.05,
    ),
    target: new THREE.Vector3(
      center.x + span * 0.08,
      heroCenter.y + size.y * 0.16,
      center.z + span * 0.04,
    ),
  }
}

/** 数字孪生 — 略俯水平视角，坝体约占画面纵向一半 */
export function getTwinCinematicCamera(root: THREE.Object3D, heroCenter: THREE.Vector3) {
  const box = new THREE.Box3().setFromObject(root)
  const size = box.getSize(new THREE.Vector3())
  const center = box.getCenter(new THREE.Vector3())
  const span = Math.max(size.x, size.y, size.z, 32)
  const dist = span * 3.85
  return {
    position: new THREE.Vector3(
      center.x + dist * 0.4,
      center.y + span * 0.68,
      center.z + dist * 0.58,
    ),
    target: new THREE.Vector3(
      center.x - span * 0.04,
      heroCenter.y + size.y * 0.16,
      center.z - span * 0.02,
    ),
  }
}

/** 仿真运行 — 近景聚焦坝体与阀门区域 */
export function getSimulationFocusCamera(root: THREE.Object3D, heroCenter: THREE.Vector3) {
  const box = new THREE.Box3().setFromObject(root)
  const size = box.getSize(new THREE.Vector3())
  const center = box.getCenter(new THREE.Vector3())
  const span = Math.max(size.x, size.y, size.z, 32)
  const dist = span * 1.75
  return {
    position: new THREE.Vector3(
      center.x + dist * 0.36,
      center.y + span * 0.38,
      center.z + dist * 0.46,
    ),
    target: new THREE.Vector3(
      center.x - span * 0.02,
      heroCenter.y + size.y * 0.18,
      center.z - span * 0.01,
    ),
  }
}

/** 全景弹窗 — 开阔远景，完整呈现坝体与上下游 */
export function getPanoramaCamera(root: THREE.Object3D, heroCenter: THREE.Vector3) {
  const box = new THREE.Box3().setFromObject(root)
  const size = box.getSize(new THREE.Vector3())
  const center = box.getCenter(new THREE.Vector3())
  const span = Math.max(size.x, size.y, size.z, 32)
  const dist = span * 4.5
  return {
    position: new THREE.Vector3(
      center.x + dist * 0.52,
      center.y + span * 0.55,
      center.z + dist * 0.72,
    ),
    target: new THREE.Vector3(
      center.x - span * 0.06,
      heroCenter.y + size.y * 0.12,
      center.z - span * 0.04,
    ),
  }
}

/** 全景弹窗 — 加深灰混凝土材质，强化厚重体量感 */
export function applyPanoramaConcreteMaterial(root: THREE.Object3D, envMap?: THREE.Texture | null) {
  const concreteTex = createConcreteTextures(2048)
  root.traverse((obj) => {
    if (!(obj instanceof THREE.Mesh)) return
    if (obj.name.startsWith('phWindow_')) return
    const isPier = obj.name.startsWith('pier_')
    const isGate = obj.name.startsWith(DAM_GATE_NAME_PREFIX) || obj.name.includes('阀门')
    if (isGate) {
      obj.material = new THREE.MeshStandardMaterial({
        color: 0x6a7580,
        roughness: 0.42,
        metalness: 0.55,
        envMap: envMap ?? null,
        envMapIntensity: 0.88,
      })
    } else {
      obj.material = new THREE.MeshStandardMaterial({
        map: concreteTex.map,
        normalMap: concreteTex.normalMap,
        roughnessMap: concreteTex.roughnessMap,
        color: isPier ? 0x4a5560 : 0x3d4850,
        roughness: 0.88,
        metalness: 0.06,
        envMap: envMap ?? null,
        envMapIntensity: 0.62,
      })
    }
  })
}

/** 相机对准大坝主体（泄洪面） */
export function getDamHeroCamera(root: THREE.Object3D, heroCenter: THREE.Vector3) {
  const box = new THREE.Box3().setFromObject(root)
  const size = box.getSize(new THREE.Vector3())
  const spillFace = new THREE.Vector3(box.max.x, heroCenter.y + size.y * 0.08, heroCenter.z)
  const dist = Math.max(size.x, size.y, size.z) * 1.35
  return {
    position: new THREE.Vector3(
      spillFace.x + dist * 0.55,
      heroCenter.y + size.y * 0.55,
      spillFace.z + dist * 0.72,
    ),
    target: new THREE.Vector3(
      spillFace.x - size.x * 0.12,
      heroCenter.y + size.y * 0.35,
      spillFace.z,
    ),
  }
}
