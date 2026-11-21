// ============================================================
// 向家坝 — 超写实泄洪大坝 / 丘陵植被 / 远景（禁止 lowpoly 卡通）
// ============================================================
import * as THREE from 'three'
import {
  createConcreteTextures,
  createMetalTextures,
  createTerrainTextures,
  createForestTextures,
} from './proceduralTextures'

function valleyHeight(x: number, z: number): number {
  const river = Math.exp(-z * z * 0.004) * 3.0
  // 大坝核心区压低 — 主体留给坝体/水面，不在镜头前抢戏
  if (Math.abs(x) < 30 && Math.abs(z) < 28) {
    return river + Math.max(0, (Math.abs(x) - 10) * 0.06)
  }
  const edge = Math.max(0, Math.abs(x) - 30)
  const left = Math.max(0, Math.sin(x * 0.06 + 0.5) * 16 + Math.cos(z * 0.05) * 7)
  const right = Math.max(0, Math.sin(x * 0.055 - 0.2) * 14 + Math.cos(z * 0.06 + 0.8) * 8)
  const micro = Math.sin(x * 0.38) * Math.cos(z * 0.32) * 0.8
  const side = x < -3 ? left : right
  return river + side * Math.min(1, edge / 22 + 0.12) + micro
}

function makeConcreteMat(
  tex: ReturnType<typeof createConcreteTextures>,
  envMap?: THREE.Texture | null,
  intensity = 0.72,
) {
  return new THREE.MeshStandardMaterial({
    map: tex.map,
    normalMap: tex.normalMap,
    roughnessMap: tex.roughnessMap,
    normalScale: new THREE.Vector2(1.45, 1.45),
    roughness: tex.roughness,
    metalness: tex.metalness,
    envMap: envMap ?? null,
    envMapIntensity: envMap ? intensity : 0,
  })
}

function displaceConcreteSurface(geo: THREE.BufferGeometry, strength = 1) {
  const pos = geo.attributes.position
  for (let i = 0; i < pos.count; i++) {
    const x = pos.getX(i)
    const y = pos.getY(i)
    const z = pos.getZ(i)
    const n = Math.sin(x * 1.8 + z * 2.1) * 0.04 + Math.sin(y * 3.2 + z * 1.4) * 0.03
    const seam = Math.abs(((y * 2.8) % 1) - 0.5) < 0.04 ? -0.06 : 0
    pos.setXYZ(i, x + n * strength, y + seam * strength, z + n * 0.5 * strength)
  }
  geo.computeVertexNormals()
}

export function buildValleyTerrain(envMap?: THREE.Texture | null): THREE.Mesh {
  const geo = new THREE.PlaneGeometry(200, 130, 128, 96)
  const pos = geo.attributes.position
  for (let i = 0; i < pos.count; i++) {
    const x = pos.getX(i)
    const y = pos.getY(i)
    pos.setZ(i, valleyHeight(x, y))
  }
  geo.computeVertexNormals()

  const tex = createTerrainTextures(1024)
  const mat = new THREE.MeshStandardMaterial({
    map: tex.map,
    normalMap: tex.normalMap,
    roughnessMap: tex.roughnessMap,
    normalScale: new THREE.Vector2(0.9, 0.9),
    roughness: tex.roughness,
    metalness: tex.metalness,
    color: new THREE.Color(0x6a8068),
    envMap: envMap ?? null,
    envMapIntensity: envMap ? 0.18 : 0,
  })
  const mesh = new THREE.Mesh(geo, mat)
  mesh.rotation.x = -Math.PI / 2
  mesh.position.y = -2.5
  mesh.receiveShadow = true
  mesh.name = '峡谷远景地形'
  mesh.renderOrder = -2
  return mesh
}

/** 远景丘陵 — 仅作背景，不抢大坝主体 */
export function buildForestHillside(envMap?: THREE.Texture | null): THREE.Mesh {
  const geo = new THREE.PlaneGeometry(90, 35, 64, 24)
  const pos = geo.attributes.position
  for (let i = 0; i < pos.count; i++) {
    const x = pos.getX(i)
    const y = pos.getY(i)
    const ridge = Math.sin(x * 0.08) * 2 + Math.cos(y * 0.1) * 1.5
    pos.setZ(i, ridge)
  }
  geo.computeVertexNormals()

  const tex = createForestTextures(512)
  const mat = new THREE.MeshStandardMaterial({
    map: tex.map,
    normalMap: tex.normalMap,
    roughnessMap: tex.roughnessMap,
    normalScale: new THREE.Vector2(0.8, 0.8),
    roughness: 0.94,
    metalness: 0.01,
    transparent: true,
    opacity: 0.42,
    envMap: envMap ?? null,
    envMapIntensity: 0.12,
  })
  const mesh = new THREE.Mesh(geo, mat)
  mesh.rotation.x = -Math.PI / 2
  mesh.rotation.z = -0.05
  mesh.position.set(18, 4, 68)
  mesh.name = '远景山林'
  mesh.renderOrder = -3
  return mesh
}

export function buildDistantRidgeline(envMap?: THREE.Texture | null): THREE.Group {
  const group = new THREE.Group()
  const forestTex = createForestTextures(512)
  const layers = [
    { z: -78, y: 8, h: 16, opacity: 0.5 },
    { z: -62, y: 10, h: 20, opacity: 0.62 },
    { z: -48, y: 12, h: 14, opacity: 0.72 },
  ]
  layers.forEach((layer, idx) => {
    const geo = new THREE.PlaneGeometry(220, layer.h, 120, 16)
    const pos = geo.attributes.position
    for (let i = 0; i < pos.count; i++) {
      const x = pos.getX(i)
      const y = pos.getY(i)
      const r = Math.sin(x * 0.06 + idx) * 3 + Math.cos(x * 0.11) * 2
      pos.setZ(i, r)
      pos.setY(i, y + r * 0.2)
    }
    geo.computeVertexNormals()
    const mat = new THREE.MeshStandardMaterial({
      map: forestTex.map,
      color: new THREE.Color(0x4a7058).lerp(new THREE.Color(0x6a9070), idx * 0.15),
      roughness: 0.94,
      transparent: true,
      opacity: layer.opacity,
      envMap: envMap ?? null,
      envMapIntensity: 0.15,
    })
    const m = new THREE.Mesh(geo, mat)
    m.rotation.x = -Math.PI / 2
    m.position.set(0, layer.y, layer.z)
    group.add(m)
  })
  return group
}

function addPier(
  group: THREE.Group,
  mat: THREE.MeshStandardMaterial,
  x: number,
  y: number,
  z: number,
  w: number,
  h: number,
  d: number,
  name?: string,
) {
  const geo = new THREE.BoxGeometry(w, h, d, 6, 16, 6)
  displaceConcreteSurface(geo, 0.8)
  const m = new THREE.Mesh(geo, mat.clone())
  m.position.set(x, y, z)
  if (name) {
    m.name = name
    m.userData.detail = '混凝土阀墩 · 分隔泄洪孔 · 承力结构'
  }
  m.castShadow = true
  m.receiveShadow = true
  group.add(m)
  return m
}

export function buildDamBody(envMap?: THREE.Texture | null): THREE.Group {
  const group = new THREE.Group()
  const concreteTex = createConcreteTextures(1024)
  const metalTex = createMetalTextures(1024)
  const concreteMat = makeConcreteMat(concreteTex, envMap, 0.75)
  const accentMat = makeConcreteMat(createConcreteTextures(1024), envMap, 0.65)
  accentMat.color.multiplyScalar(0.9)

  const steelMat = new THREE.MeshStandardMaterial({
    map: metalTex.map,
    normalMap: metalTex.normalMap,
    roughnessMap: metalTex.roughnessMap,
    normalScale: new THREE.Vector2(1.6, 1.6),
    roughness: metalTex.roughness,
    metalness: metalTex.metalness,
    envMap: envMap ?? null,
    envMapIntensity: 1.0,
  })
  const darkSteel = steelMat.clone()
  darkSteel.color.multiplyScalar(0.65)

  // 主体坝墩 — 高细分曲面混凝土
  const bodyGeo = new THREE.BoxGeometry(14, 28, 52, 20, 48, 40)
  displaceConcreteSurface(bodyGeo, 1.2)
  const body = new THREE.Mesh(bodyGeo, concreteMat.clone())
  body.position.set(-2, 14, 0)
  body.name = '向家坝大坝'
  body.userData.detail = '混凝土重力坝 · 浇筑分割缝 · 长期水流风化痕迹'
  body.castShadow = true
  body.receiveShadow = true
  group.add(body)

  // 泄洪阀墩立柱（参考第二张图多组垂直结构）
  const pierZs = [-18, -10.5, -3, 4.5, 12, 19.5]
  pierZs.forEach((z, i) => {
    const pierName = i < 5 ? `pier_${i + 1}` : undefined
    addPier(group, accentMat, 5.5, 14, z, 2.2, 26, 3.8, pierName)
    if (i < pierZs.length - 1) {
      const midZ = (z + pierZs[i + 1]) / 2
      const bayName = `${i + 1}号阀门`
      const bay = new THREE.Group()
      bay.name = bayName
      bay.userData.detail = '钢制泄洪阀 · 液压启闭 · 开度联动'

      const frame = new THREE.Mesh(new THREE.BoxGeometry(0.55, 11, 3.2, 1, 8, 3), steelMat.clone())
      frame.position.set(6.2, 10, midZ)
      frame.castShadow = true
      bay.add(frame)

      const gateLeaf = new THREE.Mesh(
        new THREE.BoxGeometry(0.42, 10, 2.8, 1, 6, 4),
        steelMat.clone(),
      )
      gateLeaf.name = `gateLeaf_${i}`
      gateLeaf.position.set(6.2, 6, midZ)
      gateLeaf.castShadow = true
      bay.add(gateLeaf)

      const lintel = new THREE.Mesh(
        new THREE.BoxGeometry(1.0, 0.55, 3.4, 2, 1, 2),
        darkSteel.clone(),
      )
      lintel.position.set(6.2, 16.5, midZ)
      bay.add(lintel)

      const hoist = new THREE.Mesh(
        new THREE.CylinderGeometry(0.32, 0.36, 2.4, 16),
        darkSteel.clone(),
      )
      hoist.position.set(6.2, 17.8, midZ)
      bay.add(hoist)

      group.add(bay)
    }
  })

  // 坝顶连梁
  const crestGeo = new THREE.BoxGeometry(12, 1.2, 54, 8, 2, 24)
  displaceConcreteSurface(crestGeo, 0.5)
  const crest = new THREE.Mesh(crestGeo, accentMat.clone())
  crest.position.set(-1, 28.5, 0)
  crest.receiveShadow = true
  group.add(crest)

  // 上游水线侵蚀
  const stain = new THREE.Mesh(
    new THREE.PlaneGeometry(50, 12, 32, 8),
    new THREE.MeshStandardMaterial({
      map: concreteTex.map,
      normalMap: concreteTex.normalMap,
      color: 0x4a5860,
      roughness: 0.96,
      transparent: true,
      opacity: 0.75,
    }),
  )
  stain.position.set(-9.5, 12, 0)
  stain.rotation.y = Math.PI / 2
  group.add(stain)

  // 向家坝电站厂房
  const phGroup = new THREE.Group()
  phGroup.name = '向家坝电站厂房'
  phGroup.userData.detail = '向家坝水电站 BIM 工程构件'

  const phGeo = new THREE.BoxGeometry(14, 9, 18, 8, 4, 6)
  displaceConcreteSurface(phGeo, 0.6)
  const phBody = new THREE.Mesh(phGeo, accentMat.clone())
  phBody.position.set(-12, 4.5, -14)
  phBody.castShadow = true
  phBody.receiveShadow = true
  phGroup.add(phBody)

  for (let row = 0; row < 2; row++) {
    for (let col = 0; col < 4; col++) {
      const win = new THREE.Mesh(
        new THREE.PlaneGeometry(1.8, 2.2),
        new THREE.MeshStandardMaterial({
          color: 0xffdd99,
          emissive: 0xffaa44,
          emissiveIntensity: 1.6,
          roughness: 0.1,
          metalness: 0.3,
        }),
      )
      win.name = `phWindow_${row}_${col}`
      win.position.set(-12, 3 + row * 3.2, -20 + col * 4.5)
      win.rotation.y = Math.PI / 2
      phGroup.add(win)
    }
  }
  group.add(phGroup)

  // 溢洪道消力池
  const apronGeo = new THREE.BoxGeometry(10, 1.0, 44, 8, 2, 20)
  const apron = new THREE.Mesh(apronGeo, accentMat.clone())
  apron.position.set(10, 1.5, 0)
  apron.receiveShadow = true
  group.add(apron)

  return group
}

/** 下游湍流泡沫区 */
export function buildFoamZone(envMap?: THREE.Texture | null): THREE.Mesh {
  const geo = new THREE.PlaneGeometry(22, 18, 64, 48)
  const pos = geo.attributes.position
  for (let i = 0; i < pos.count; i++) {
    const x = pos.getX(i)
    const z = pos.getY(i)
    pos.setZ(i, Math.sin(x * 0.8 + z * 0.6) * 0.35 + Math.sin(x * 1.2 + z * 0.9) * 0.12)
  }
  geo.computeVertexNormals()
  const mat = new THREE.MeshStandardMaterial({
    color: 0xd8eef8,
    roughness: 0.35,
    metalness: 0.05,
    transparent: true,
    opacity: 0.88,
    envMap: envMap ?? null,
    envMapIntensity: 0.45,
  })
  const mesh = new THREE.Mesh(geo, mat)
  mesh.rotation.x = -Math.PI / 2
  mesh.position.set(18, 0.35, 0)
  mesh.name = '湍流泡沫区'
  mesh.userData.detail = '泄洪冲击区 · 白色翻滚泡沫'
  return mesh
}

export function buildRiverbed(envMap?: THREE.Texture | null): THREE.Mesh {
  const geo = new THREE.PlaneGeometry(70, 36, 128, 64)
  const pos = geo.attributes.position
  for (let i = 0; i < pos.count; i++) {
    const x = pos.getX(i)
    const z = pos.getY(i)
    pos.setZ(i, Math.sin(x * 0.1) * 0.4 + Math.cos(z * 0.15) * 0.3)
  }
  geo.computeVertexNormals()
  const mat = new THREE.MeshStandardMaterial({
    color: 0x3a5850,
    roughness: 0.92,
    metalness: 0,
    envMap: envMap ?? null,
    envMapIntensity: 0.18,
  })
  const mesh = new THREE.Mesh(geo, mat)
  mesh.rotation.x = -Math.PI / 2
  mesh.position.set(28, -0.2, 0)
  mesh.receiveShadow = true
  return mesh
}

// 兼容旧引用
export function buildVegetationScatter(_envMap?: THREE.Texture | null): THREE.Group {
  return new THREE.Group()
}
