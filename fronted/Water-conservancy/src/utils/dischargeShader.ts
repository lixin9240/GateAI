// ============================================================
// 泄流可视化 — 宽幅水幕 + 水雾 + 底部水花（浅青蓝清爽水流）
// ============================================================
import * as THREE from 'three'
import {
  gateLeafBottomY,
  GATE_SILL_Y,
  LINTEL_BOTTOM_Y,
} from '@/utils/gateKinematics'

export { GATE_SILL_Y, LINTEL_BOTTOM_Y }
export const DISCHARGE_X = 6.2
/** 阀门开孔满宽（与 bay 宽 3.2 对齐，略外扩铺满） */
export const GATE_OPENING_WIDTH = 2.85

export interface DischargeMetrics {
  visible: boolean
  topY: number
  fallH: number
  width: number
  thickness: number
  mistSpread: number
  splashSize: number
  opening: number
}

const INVISIBLE: DischargeMetrics = {
  visible: false,
  topY: 0,
  fallH: 0,
  width: 0,
  thickness: 0,
  mistSpread: 0,
  splashSize: 0,
  opening: 0,
}

function waterfallMaterial() {
  return new THREE.ShaderMaterial({
    uniforms: {
      uTime: { value: 0 },
      uOpening: { value: 0.5 },
    },
    vertexShader: `
      varying vec2 vUv;
      void main() {
        vUv = uv;
        gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
      }
    `,
    fragmentShader: `
      uniform float uTime;
      uniform float uOpening;
      varying vec2 vUv;

      void main() {
        float flow = fract(vUv.y * 3.2 - uTime * 1.55);
        float streak = smoothstep(0.0, 0.06, flow) * (1.0 - smoothstep(0.32, 0.52, flow));
        float refr = 0.18 + streak * 0.38;

        vec3 topCol = vec3(0.85, 0.96, 1.0);
        vec3 midCol = vec3(0.38, 0.78, 0.98);
        vec3 botCol = vec3(0.12, 0.48, 0.82);
        vec3 col = mix(botCol, midCol, smoothstep(0.0, 0.5, vUv.y));
        col = mix(col, topCol, smoothstep(0.5, 1.0, vUv.y));
        col += vec3(0.4, 0.52, 0.58) * refr;

        float foam = smoothstep(0.88, 1.0, vUv.y) * (0.65 + streak * 0.4);
        col = mix(col, vec3(0.95, 0.99, 1.0), foam);

        float edge = smoothstep(0.0, 0.05, vUv.x) * smoothstep(1.0, 0.95, vUv.x);
        float topFade = smoothstep(0.0, 0.02, vUv.y);
        float botFade = smoothstep(1.0, 0.96, vUv.y);
        float alpha = edge * topFade * botFade * (0.42 + uOpening * 0.48);
        gl_FragColor = vec4(col, alpha);
      }
    `,
    transparent: true,
    depthWrite: false,
    side: THREE.DoubleSide,
    toneMapped: true,
  })
}

function splashMaterial() {
  return new THREE.ShaderMaterial({
    uniforms: {
      uTime: { value: 0 },
      uOpening: { value: 0.5 },
    },
    vertexShader: `
      varying vec2 vUv;
      void main() {
        vUv = uv;
        gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
      }
    `,
    fragmentShader: `
      uniform float uTime;
      uniform float uOpening;
      varying vec2 vUv;
      void main() {
        float d = length(vUv - 0.5) * 2.0;
        float ripple = sin(d * 14.0 - uTime * 3.5) * 0.5 + 0.5;
        float foam = smoothstep(0.85, 0.2, d) * (0.4 + ripple * 0.35);
        vec3 col = mix(vec3(0.5, 0.78, 0.95), vec3(0.95, 0.98, 1.0), foam);
        float alpha = foam * (0.22 + uOpening * 0.4) * smoothstep(1.0, 0.3, d);
        gl_FragColor = vec4(col, alpha);
      }
    `,
    transparent: true,
    depthWrite: false,
    side: THREE.DoubleSide,
  })
}

export function gateOutletY(openRatio: number) {
  return gateLeafBottomY(openRatio)
}

/**
 * 泄流水幕：顶端贴阀叶下沿（泄流口），底端接到下游水面，禁止截断后悬空。
 */
export function computeDischargeMetrics(
  openRatio: number,
  downstreamY: number,
  leafBottomY?: number,
): DischargeMetrics {
  const r = Math.min(1, Math.max(0, openRatio))
  if (r < 0.02) return { ...INVISIBLE, opening: r }

  // 泄流口 = 阀叶下沿（全开时贴近门楣）
  let topY = leafBottomY ?? gateOutletY(r)
  topY = Math.min(Math.max(topY, GATE_SILL_Y + 0.6), LINTEL_BOTTOM_Y - 0.12)

  // 底端必须落到尾水面，留一点没入制造落水衔接
  const poolY = Number.isFinite(downstreamY) ? downstreamY : 1.2
  const bottomY = Math.min(poolY - 0.15, topY - 2.0)
  const fallH = topY - bottomY
  if (fallH < 1.0) return { ...INVISIBLE, opening: r }

  return {
    visible: true,
    topY,
    fallH,
    width: GATE_OPENING_WIDTH * (0.9 + r * 0.1),
    thickness: 0.2 + r * 0.35,
    mistSpread: 0.32 + r * 0.7,
    splashSize: 1.0 + r * 1.2,
    opening: r,
  }
}

export interface DischargeJetParts {
  group: THREE.Group
  main: THREE.Mesh
  mist: THREE.Points
  splash: THREE.Mesh
}

export function createDischargeJetGroup(): DischargeJetParts {
  const group = new THREE.Group()

  const mainGeo = new THREE.PlaneGeometry(GATE_OPENING_WIDTH, 12, 1, 40)
  const main = new THREE.Mesh(mainGeo, waterfallMaterial())
  main.rotation.y = Math.PI / 2
  main.name = 'dischargeMain'
  main.userData.baseHeight = 12
  main.userData.baseWidth = GATE_OPENING_WIDTH

  // 交叉水幕，侧视时也能看到流量厚度（避免只剩一条边）
  const cross = new THREE.Mesh(mainGeo.clone(), waterfallMaterial())
  cross.rotation.y = 0
  cross.name = 'dischargeCross'
  cross.userData.baseHeight = 12
  cross.userData.baseWidth = GATE_OPENING_WIDTH
  cross.scale.set(0.42, 1, 1)

  const mistCount = 72
  const mistPos = new Float32Array(mistCount * 3)
  const mistSeeds = new Float32Array(mistCount)
  for (let i = 0; i < mistCount; i++) {
    // 局部坐标归一到柱体：Y∈[0,1] 自下而上，布局时再按 fallH 拉伸
    mistPos[i * 3] = (Math.random() - 0.5) * 0.9
    mistPos[i * 3 + 1] = Math.random()
    mistPos[i * 3 + 2] = (Math.random() - 0.5) * 0.55
    mistSeeds[i] = Math.random()
  }
  const mistGeo = new THREE.BufferGeometry()
  mistGeo.setAttribute('position', new THREE.BufferAttribute(mistPos, 3))
  mistGeo.setAttribute('aSeed', new THREE.BufferAttribute(mistSeeds, 1))
  const mist = new THREE.Points(
    mistGeo,
    new THREE.PointsMaterial({
      color: 0xc5e8f8,
      size: 0.85,
      transparent: true,
      opacity: 0.22,
      depthWrite: false,
      blending: THREE.NormalBlending,
      sizeAttenuation: true,
    }),
  )
  mist.name = 'dischargeMist'
  mist.userData.spanY = 1

  const splash = new THREE.Mesh(
    new THREE.PlaneGeometry(1.8, 1.8, 1, 1),
    splashMaterial(),
  )
  splash.rotation.x = -Math.PI / 2
  splash.name = 'dischargeSplash'

  group.add(main, cross, mist, splash)
  return { group, main, mist, splash }
}

export function layoutDischargeJet(parts: DischargeJetParts, m: DischargeMetrics, downstreamY: number) {
  const { main, mist, splash } = parts
  if (!m.visible || m.fallH < 0.8) {
    parts.group.visible = false
    return
  }
  parts.group.visible = true

  const baseH = (main.userData.baseHeight as number) || 12
  const baseW = (main.userData.baseWidth as number) || GATE_OPENING_WIDTH
  // 以顶/底连续落幅定位：顶贴泄流口，底贴尾水
  const topY = m.topY
  const bottomY = m.topY - m.fallH
  const midY = (topY + bottomY) * 0.5

  main.scale.set(m.width / baseW, m.fallH / baseH, 1)
  main.position.set(DISCHARGE_X, midY, 0)

  const cross = parts.group.getObjectByName('dischargeCross') as THREE.Mesh | undefined
  if (cross) {
    cross.scale.set((m.width / baseW) * 0.38, m.fallH / baseH, 1)
    cross.position.set(DISCHARGE_X, midY, 0)
    if (cross.material instanceof THREE.ShaderMaterial) {
      cross.material.uniforms.uOpening.value = m.opening
    }
  }

  // 水花贴在水幕落入尾水处（与 curtain 底端一致）
  const splashY = Number.isFinite(downstreamY) ? Math.min(downstreamY + 0.08, bottomY + 0.35) : bottomY + 0.2
  splash.scale.set(m.splashSize, m.splashSize * 0.55, 1)
  splash.position.set(DISCHARGE_X + 0.4, splashY, 0)

  // 水雾锁在水幕柱体内，避免飘成半空雨丝（局部 Y∈[0,1] × fallH）
  mist.position.set(DISCHARGE_X, bottomY, 0)
  mist.scale.set(m.width * 0.35, m.fallH, m.width * 0.22)
  const mistMat = mist.material as THREE.PointsMaterial
  mistMat.opacity = 0.08 + m.opening * 0.16
  mistMat.size = 0.55 + m.opening * 0.65

  const setUniforms = (mesh: THREE.Mesh, mat: THREE.ShaderMaterial) => {
    mat.uniforms.uOpening.value = m.opening
  }
  if (main.material instanceof THREE.ShaderMaterial) setUniforms(main, main.material)
  if (splash.material instanceof THREE.ShaderMaterial) setUniforms(splash, splash.material)
}

export function animateDischargeJet(parts: DischargeJetParts, t: number, m: DischargeMetrics) {
  if (!parts.group.visible) return
  if (parts.main.material instanceof THREE.ShaderMaterial) {
    parts.main.material.uniforms.uTime.value = t
    parts.main.material.uniforms.uOpening.value = m.opening
  }
  const cross = parts.group.getObjectByName('dischargeCross') as THREE.Mesh | undefined
  if (cross?.material instanceof THREE.ShaderMaterial) {
    cross.material.uniforms.uTime.value = t
    cross.material.uniforms.uOpening.value = m.opening
  }
  if (parts.splash.material instanceof THREE.ShaderMaterial) {
    parts.splash.material.uniforms.uTime.value = t
    parts.splash.material.uniforms.uOpening.value = m.opening
  }
  const pos = parts.mist.geometry.attributes.position as THREE.BufferAttribute
  const seeds = parts.mist.geometry.attributes.aSeed as THREE.BufferAttribute
  for (let i = 0; i < pos.count; i++) {
    const seed = seeds.getX(i)
    // 局部 Y∈[0,1]，自上向下循环
    let y = pos.getY(i) - 0.012 - m.opening * 0.01
    if (y < 0) y = 0.92 + Math.random() * 0.08
    pos.setY(i, y)
    pos.setX(i, (Math.sin(t * 2.4 + seed * 6.2) * 0.5) * 0.85)
    pos.setZ(i, (Math.cos(t * 1.6 + seed * 4.1) * 0.5) * 0.5)
  }
  pos.needsUpdate = true
}

/** @deprecated */
export const createDischargeWaterfall = () => createDischargeJetGroup().main
export const layoutDischargeWaterfall = (mesh: THREE.Mesh, m: DischargeMetrics) => {
  layoutDischargeJet({ group: mesh.parent as THREE.Group, main: mesh, mist: mesh.parent?.getObjectByName('dischargeMist') as THREE.Points, splash: mesh.parent?.getObjectByName('dischargeSplash') as THREE.Mesh }, m, m.topY - m.fallH)
}
