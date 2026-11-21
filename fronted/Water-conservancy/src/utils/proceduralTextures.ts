// ============================================================
// 程序化 PBR 纹理 — 混凝土 / 金属 / 地形 / 法线贴图
// ============================================================
import * as THREE from 'three'

function noise2D(x: number, y: number, seed = 0): number {
  const n = Math.sin(x * 12.9898 + y * 78.233 + seed) * 43758.5453
  return n - Math.floor(n)
}

function fbm(x: number, y: number, octaves = 5, seed = 0): number {
  let v = 0
  let amp = 0.5
  let freq = 1
  for (let i = 0; i < octaves; i++) {
    v += amp * noise2D(x * freq, y * freq, seed + i * 17.3)
    amp *= 0.5
    freq *= 2.1
  }
  return v
}

function canvasTex(canvas: HTMLCanvasElement, opts?: Partial<THREE.Texture>): THREE.CanvasTexture {
  const tex = new THREE.CanvasTexture(canvas)
  tex.wrapS = tex.wrapT = THREE.RepeatWrapping
  tex.colorSpace = THREE.SRGBColorSpace
  if (opts?.repeat) tex.repeat.copy(opts.repeat as THREE.Vector2)
  tex.anisotropy = 8
  return tex
}

function heightToNormal(
  data: Float32Array,
  w: number,
  h: number,
  strength = 2.5,
): HTMLCanvasElement {
  const canvas = document.createElement('canvas')
  canvas.width = w
  canvas.height = h
  const ctx = canvas.getContext('2d')!
  const img = ctx.createImageData(w, h)
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      const idx = y * w + x
      const l = data[Math.max(0, idx - 1)]
      const r = data[Math.min(w * h - 1, idx + 1)]
      const u = data[Math.max(0, idx - w)]
      const d = data[Math.min(w * h - 1, idx + w)]
      const nx = (l - r) * strength
      const ny = (u - d) * strength
      const nz = 1
      const len = Math.sqrt(nx * nx + ny * ny + nz * nz)
      const i = idx * 4
      img.data[i] = ((nx / len) * 0.5 + 0.5) * 255
      img.data[i + 1] = ((ny / len) * 0.5 + 0.5) * 255
      img.data[i + 2] = ((nz / len) * 0.5 + 0.5) * 255
      img.data[i + 3] = 255
    }
  }
  ctx.putImageData(img, 0, 0)
  return canvas
}

export function createConcreteTextures(size = 512) {
  const canvas = document.createElement('canvas')
  canvas.width = canvas.height = size
  const ctx = canvas.getContext('2d')!
  const heights = new Float32Array(size * size)
  const img = ctx.createImageData(size, size)

  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      const u = x / size
      const v = y / size
      const n = fbm(u * 22, v * 22, 7, 42)
      const stain = fbm(u * 3.5 + 10, v * 3.5, 4, 99) * 0.22
      const streak = fbm(u * 1.2, v * 8, 3, 17) * 0.12
      const seamV = Math.abs(((v * 10) % 1) - 0.5) < 0.018 ? 0.14 : 0
      const seamH = Math.abs(((u * 7) % 1) - 0.5) < 0.012 ? 0.1 : 0
      const pour = Math.abs(((v * 3.5) % 1) - 0.5) < 0.008 ? 0.06 : 0
      const crack = fbm(u * 45 + 5, v * 45, 2, 201) > 0.82 ? 0.08 : 0
      const base = 0.38 + n * 0.2 - stain - streak - seamV - seamH - pour - crack
      heights[y * size + x] = base
      const i = (y * size + x) * 4
      img.data[i] = Math.floor((0.48 + base * 0.38) * 255)
      img.data[i + 1] = Math.floor((0.51 + base * 0.35) * 255)
      img.data[i + 2] = Math.floor((0.55 + base * 0.32) * 255)
      img.data[i + 3] = 255
    }
  }
  ctx.putImageData(img, 0, 0)

  const normalCanvas = heightToNormal(heights, size, size, 3.5)
  const map = canvasTex(canvas, { repeat: new THREE.Vector2(2, 2) })
  const normalMap = canvasTex(normalCanvas, { repeat: new THREE.Vector2(2, 2) })
  normalMap.colorSpace = THREE.LinearSRGBColorSpace
  const roughCanvas = document.createElement('canvas')
  roughCanvas.width = roughCanvas.height = size
  const rctx = roughCanvas.getContext('2d')!
  const rimg = rctx.createImageData(size, size)
  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      const u = x / size
      const v = y / size
      const r = 0.82 + fbm(u * 18, v * 18, 4, 33) * 0.14
      const i = (y * size + x) * 4
      const rv = Math.floor(r * 255)
      rimg.data[i] = rimg.data[i + 1] = rimg.data[i + 2] = rv
      rimg.data[i + 3] = 255
    }
  }
  rctx.putImageData(rimg, 0, 0)
  const roughnessMap = canvasTex(roughCanvas, { repeat: new THREE.Vector2(2, 2) })
  roughnessMap.colorSpace = THREE.LinearSRGBColorSpace
  return { map, normalMap, roughnessMap, roughness: 0.88, metalness: 0.04 }
}

export function createMetalTextures(size = 512) {
  const canvas = document.createElement('canvas')
  canvas.width = canvas.height = size
  const ctx = canvas.getContext('2d')!
  const heights = new Float32Array(size * size)
  const img = ctx.createImageData(size, size)

  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      const u = x / size
      const v = y / size
      const brush = Math.sin(v * 320 + fbm(u * 35, v * 35, 4) * 8) * 0.05
      const scratch = fbm(u * 60, v * 8, 2, 44) * 0.03
      const rust = fbm(u * 8 + 3, v * 8, 4, 77)
      const rustMask = rust > 0.78 ? (rust - 0.78) * 2.2 : 0
      const base = 0.32 + brush + scratch
      heights[y * size + x] = base + rustMask * 0.18
      const i = (y * size + x) * 4
      if (rustMask > 0.04) {
        img.data[i] = Math.floor((0.42 + rustMask * 0.35) * 255)
        img.data[i + 1] = Math.floor((0.26 + rustMask * 0.12) * 255)
        img.data[i + 2] = Math.floor((0.14 + rustMask * 0.06) * 255)
      } else {
        img.data[i] = Math.floor((0.38 + base * 0.28) * 255)
        img.data[i + 1] = Math.floor((0.44 + base * 0.26) * 255)
        img.data[i + 2] = Math.floor((0.5 + base * 0.24) * 255)
      }
      img.data[i + 3] = 255
    }
  }
  ctx.putImageData(img, 0, 0)

  const normalCanvas = heightToNormal(heights, size, size, 4.5)
  const map = canvasTex(canvas, { repeat: new THREE.Vector2(1.5, 1.5) })
  const normalMap = canvasTex(normalCanvas, { repeat: new THREE.Vector2(1.5, 1.5) })
  normalMap.colorSpace = THREE.LinearSRGBColorSpace
  const roughCanvas = document.createElement('canvas')
  roughCanvas.width = roughCanvas.height = size
  const rctx = roughCanvas.getContext('2d')!
  const rimg = rctx.createImageData(size, size)
  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      const u = x / size
      const v = y / size
      const r = 0.25 + fbm(u * 22, v * 22, 3, 44) * 0.22
      const i = (y * size + x) * 4
      const rv = Math.floor(r * 255)
      rimg.data[i] = rimg.data[i + 1] = rimg.data[i + 2] = rv
      rimg.data[i + 3] = 255
    }
  }
  rctx.putImageData(rimg, 0, 0)
  const roughnessMap = canvasTex(roughCanvas, { repeat: new THREE.Vector2(1.5, 1.5) })
  roughnessMap.colorSpace = THREE.LinearSRGBColorSpace
  return { map, normalMap, roughnessMap, roughness: 0.32, metalness: 0.92 }
}

export function createTerrainTextures(size = 1024) {
  const canvas = document.createElement('canvas')
  canvas.width = canvas.height = size
  const ctx = canvas.getContext('2d')!
  const heights = new Float32Array(size * size)
  const img = ctx.createImageData(size, size)

  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      const u = x / size
      const v = y / size
      const elev = fbm(u * 5.5, v * 5.5, 6, 12)
      const detail = fbm(u * 28, v * 28, 4, 55) * 0.28
      const slope = fbm(u * 12 + 5, v * 12, 3, 33)
      const grass = fbm(u * 40 + 2, v * 40, 3, 88)
      const hVal = elev + detail
      heights[y * size + x] = hVal

      let r: number, g: number, b: number
      if (hVal > 0.78) {
        r = 72 + slope * 28
        g = 88 + slope * 32
        b = 68 + slope * 22
      } else if (hVal > 0.62) {
        r = 38 + hVal * 42
        g = 78 + hVal * 62
        b = 32 + hVal * 28
        g += grass * 22
        r += grass * 6
      } else if (hVal > 0.42) {
        r = 28 + hVal * 38
        g = 72 + hVal * 78
        b = 24 + hVal * 26
        g += grass * 32
        r += grass * 12
      } else {
        r = 42 + hVal * 40
        g = 78 + hVal * 52
        b = 30 + hVal * 22
      }
      const i = (y * size + x) * 4
      img.data[i] = Math.min(255, r)
      img.data[i + 1] = Math.min(255, g)
      img.data[i + 2] = Math.min(255, b)
      img.data[i + 3] = 255
    }
  }
  ctx.putImageData(img, 0, 0)

  const normalCanvas = heightToNormal(heights, size, size, 5.5)
  const map = canvasTex(canvas, { repeat: new THREE.Vector2(8, 8) })
  const normalMap = canvasTex(normalCanvas, { repeat: new THREE.Vector2(8, 8) })
  normalMap.colorSpace = THREE.LinearSRGBColorSpace
  const roughCanvas = document.createElement('canvas')
  roughCanvas.width = roughCanvas.height = size
  const rctx = roughCanvas.getContext('2d')!
  const rimg = rctx.createImageData(size, size)
  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      const u = x / size
      const v = y / size
      const r = 0.86 + fbm(u * 16, v * 16, 3, 66) * 0.1
      const i = (y * size + x) * 4
      const rv = Math.floor(r * 255)
      rimg.data[i] = rimg.data[i + 1] = rimg.data[i + 2] = rv
      rimg.data[i + 3] = 255
    }
  }
  rctx.putImageData(rimg, 0, 0)
  const roughnessMap = canvasTex(roughCanvas, { repeat: new THREE.Vector2(8, 8) })
  roughnessMap.colorSpace = THREE.LinearSRGBColorSpace
  return { map, normalMap, roughnessMap, roughness: 0.9, metalness: 0.015 }
}

/** 山林冠层纹理 — 用于丘陵位移面，非锥形树 */
export function createForestTextures(size = 1024) {
  const canvas = document.createElement('canvas')
  canvas.width = canvas.height = size
  const ctx = canvas.getContext('2d')!
  const heights = new Float32Array(size * size)
  const img = ctx.createImageData(size, size)

  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      const u = x / size
      const v = y / size
      const canopy = fbm(u * 8 + 2, v * 8, 7, 21)
      const detail = fbm(u * 32, v * 32, 4, 55) * 0.35
      const shadow = fbm(u * 4 + 10, v * 4, 3, 77) * 0.25
      const hVal = canopy + detail
      heights[y * size + x] = hVal

      const g = 58 + hVal * 75 + detail * 40 - shadow * 20
      const r = 32 + hVal * 38 + shadow * 15
      const b = 24 + hVal * 28
      const i = (y * size + x) * 4
      img.data[i] = Math.min(255, r)
      img.data[i + 1] = Math.min(255, g)
      img.data[i + 2] = Math.min(255, b)
      img.data[i + 3] = 255
    }
  }
  ctx.putImageData(img, 0, 0)

  const normalCanvas = heightToNormal(heights, size, size, 6.5)
  const map = canvasTex(canvas, { repeat: new THREE.Vector2(6, 6) })
  const normalMap = canvasTex(normalCanvas, { repeat: new THREE.Vector2(6, 6) })
  normalMap.colorSpace = THREE.LinearSRGBColorSpace
  const roughCanvas = document.createElement('canvas')
  roughCanvas.width = roughCanvas.height = size
  const rctx = roughCanvas.getContext('2d')!
  const rimg = rctx.createImageData(size, size)
  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      const u = x / size
      const v = y / size
      const r = 0.88 + fbm(u * 20, v * 20, 3, 88) * 0.1
      const i = (y * size + x) * 4
      const rv = Math.floor(r * 255)
      rimg.data[i] = rimg.data[i + 1] = rimg.data[i + 2] = rv
      rimg.data[i + 3] = 255
    }
  }
  rctx.putImageData(rimg, 0, 0)
  const roughnessMap = canvasTex(roughCanvas, { repeat: new THREE.Vector2(6, 6) })
  roughnessMap.colorSpace = THREE.LinearSRGBColorSpace
  return { map, normalMap, roughnessMap, roughness: 0.92, metalness: 0.01 }
}

/** @deprecated 使用 createCinematicSky */
export function createSkyEnvironment(renderer: THREE.WebGLRenderer): THREE.Texture {
  const pmrem = new THREE.PMREMGenerator(renderer)
  pmrem.compileEquirectangularShader()
  const skyScene = new THREE.Scene()
  const skyGeo = new THREE.SphereGeometry(500, 32, 16)
  const skyMat = new THREE.ShaderMaterial({
    side: THREE.BackSide,
    uniforms: {
      uTop: { value: new THREE.Color(0x0a1628) },
      uHorizon: { value: new THREE.Color(0x1a3050) },
      uBottom: { value: new THREE.Color(0x2a5080) },
    },
    vertexShader: `
      varying vec3 vPos;
      void main() {
        vPos = position;
        gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
      }
    `,
    fragmentShader: `
      uniform vec3 uTop;
      uniform vec3 uHorizon;
      uniform vec3 uBottom;
      varying vec3 vPos;
      void main() {
        float h = normalize(vPos).y * 0.5 + 0.5;
        vec3 col = h > 0.5 ? mix(uHorizon, uTop, (h - 0.5) * 2.0) : mix(uBottom, uHorizon, h * 2.0);
        gl_FragColor = vec4(col, 1.0);
      }
    `,
  })
  skyScene.add(new THREE.Mesh(skyGeo, skyMat))
  const rt = pmrem.fromScene(skyScene, 0.04)
  pmrem.dispose()
  skyGeo.dispose()
  skyMat.dispose()
  return rt.texture
}
