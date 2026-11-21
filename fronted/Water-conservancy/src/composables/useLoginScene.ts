// ============================================================
// 登录页 Three.js — 真实水体渲染
// 大面积水面 + 顶点波动 + 法线贴图 + Sky 渐变 + 雾化地平线
// ============================================================
import { ref, onMounted, onUnmounted, type Ref } from 'vue'
import * as THREE from 'three'

// ── 时段 ──
type Period = 'dawn' | 'noon' | 'dusk' | 'night'

interface PeriodColors {
  skyTop: [number, number, number]
  skyHorizon: [number, number, number]
  waterDeep: [number, number, number]
  waterSurface: [number, number, number]
  specular: [number, number, number]
  sunDir: [number, number, number]
}

const PERIODS: Record<Period, PeriodColors> = {
  dawn: {
    skyTop: [0.22, 0.28, 0.5],
    skyHorizon: [0.65, 0.55, 0.58],
    waterDeep: [0.02, 0.06, 0.16],
    waterSurface: [0.06, 0.16, 0.3],
    specular: [0.9, 0.65, 0.4],
    sunDir: [0.5, 0.25, 0.7],
  },
  noon: {
    skyTop: [0.18, 0.4, 0.72],
    skyHorizon: [0.55, 0.68, 0.78],
    waterDeep: [0.02, 0.1, 0.24],
    waterSurface: [0.05, 0.19, 0.36],
    specular: [1.0, 0.92, 0.78],
    sunDir: [0.35, 0.65, 0.55],
  },
  dusk: {
    skyTop: [0.15, 0.08, 0.25],
    skyHorizon: [0.55, 0.28, 0.25],
    waterDeep: [0.02, 0.04, 0.14],
    waterSurface: [0.06, 0.09, 0.24],
    specular: [0.9, 0.5, 0.28],
    sunDir: [0.55, 0.2, 0.65],
  },
  night: {
    skyTop: [0.03, 0.04, 0.12],
    skyHorizon: [0.06, 0.07, 0.18],
    waterDeep: [0.01, 0.03, 0.1],
    waterSurface: [0.03, 0.06, 0.16],
    specular: [0.4, 0.5, 0.75],
    sunDir: [-0.2, 0.4, 0.7],
  },
}

// ============================================================
// 天空背景 Shader（渐变 + 太阳光晕）
// ============================================================
const skyVert = /* glsl */ `
  varying vec2 vUv;
  varying vec3 vWorldPos;
  void main() {
    vec4 wp = modelMatrix * vec4(position, 1.0);
    vWorldPos = wp.xyz;
    vUv = uv;
    gl_Position = projectionMatrix * viewMatrix * wp;
  }
`

const skyFrag = /* glsl */ `
  uniform vec3 uSkyTop;
  uniform vec3 uSkyHorizon;
  uniform vec3 uSunDir;
  varying vec2 vUv;
  varying vec3 vWorldPos;

  void main() {
    // 俯仰角 — 上方是天空，靠近地平线变亮
    float elevation = normalize(vWorldPos).y;
    float t = smoothstep(-0.15, 0.5, elevation);
    vec3 sky = mix(uSkyHorizon, uSkyTop, t);

    // 太阳光晕
    vec3 sunD = normalize(uSunDir);
    float sunGlow = pow(max(dot(normalize(vWorldPos), sunD), 0.0), 80.0) * 0.35;
    float sunHalo = pow(max(dot(normalize(vWorldPos), sunD), 0.0), 8.0) * 0.10;
    sky += vec3(1.0, 0.85, 0.6) * sunGlow;
    sky += vec3(0.9, 0.8, 0.7) * sunHalo;

    gl_FragColor = vec4(sky, 1.0);
  }
`

// ============================================================
// 水体 Shader — 大面积水面 + 顶点波动 + 法线贴图 + 真实光照
// ============================================================
const waterVert = /* glsl */ `
  uniform float uTime;
  varying vec3 vWorldPos;
  varying vec3 vWorldNormal;
  varying vec2 vUv0;
  varying vec2 vUv1;
  varying float vWaveHeight;

  // ── Gerstner 波：更自然的波浪形态 ──
  float gerstner(vec2 xz, vec2 dir, float freq, float amp, float speed, float sharpness) {
    float phase = dot(xz, dir) * freq + uTime * speed;
    // sharpness > 1.0 → 波峰更尖、波谷更平
    float wave = (pow(0.5 + 0.5 * sin(phase), sharpness) * 2.0 - 1.0);
    return wave * amp;
  }

  float waveHeight(vec2 xz) {
    float h = 0.0;
    // 大型涌浪（不同方向交叉传播）
    h += gerstner(xz, normalize(vec2(0.7, 0.7)),  0.06, 0.70, 0.08, 1.6);
    h += gerstner(xz, normalize(vec2(0.3, 0.9)),  0.08, 0.55, 0.10, 1.4);
    h += gerstner(xz, normalize(vec2(-0.5, 0.85)), 0.07, 0.50, 0.07, 1.5);
    // 中型波浪
    h += gerstner(xz, normalize(vec2(0.6, -0.3)), 0.14, 0.28, 0.14, 1.3);
    h += gerstner(xz, normalize(vec2(-0.4, 0.6)), 0.16, 0.22, 0.12, 1.2);
    h += gerstner(xz, normalize(vec2(0.8, 0.2)),  0.18, 0.18, 0.16, 1.1);
    // 细碎浪花
    h += gerstner(xz, normalize(vec2(0.4, -0.7)), 0.28, 0.10, 0.20, 1.0);
    h += gerstner(xz, normalize(vec2(-0.6, -0.4)),0.32, 0.08, 0.22, 1.0);
    h += gerstner(xz, normalize(vec2(0.2, 0.5)),  0.38, 0.05, 0.25, 1.0);
    h += gerstner(xz, normalize(vec2(-0.3, 0.7)), 0.44, 0.04, 0.28, 1.0);
    return h;
  }

  void main() {
    vec3 pos = position;

    float h = waveHeight(pos.xz);
    pos.y += h;
    vWaveHeight = h;

    // 近似法线：采样周围点的高度
    float eps = 0.4;
    float hR = waveHeight(pos.xz + vec2(eps, 0.0));
    float hL = waveHeight(pos.xz + vec2(-eps, 0.0));
    float hF = waveHeight(pos.xz + vec2(0.0, eps));
    float hB = waveHeight(pos.xz + vec2(0.0, -eps));
    vec3 approxN = normalize(vec3((hL - hR) / (2.0 * eps), 1.0, (hB - hF) / (2.0 * eps)));
    vWorldNormal = normalize(mat3(modelMatrix) * approxN);

    vec4 worldPos = modelMatrix * vec4(pos, 1.0);
    vWorldPos = worldPos.xyz;
    vUv0 = pos.xz * 0.5;
    vUv1 = pos.xz * 0.2;

    gl_Position = projectionMatrix * viewMatrix * worldPos;
  }
`

const waterFrag = /* glsl */ `
  uniform sampler2D uNormalMap;
  uniform vec3 uCameraPos;
  uniform vec3 uWaterDeep;
  uniform vec3 uWaterSurface;
  uniform vec3 uSpecular;
  uniform vec3 uSunDir;
  uniform vec3 uSkyTop;
  uniform vec3 uSkyHorizon;
  uniform float uTime;
  uniform float uNormalStrength;

  varying vec3 vWorldPos;
  varying vec3 vWorldNormal;
  varying vec2 vUv0;
  varying vec2 vUv1;
  varying float vWaveHeight;

  void main() {
    // ── 法线贴图微扰动 ──
    vec2 flow0 = vUv0 + vec2(uTime * 0.02, uTime * 0.025);
    vec2 flow1 = vUv1 + vec2(-uTime * 0.03, uTime * 0.018);
    vec3 n0 = texture2D(uNormalMap, flow0).rgb * 2.0 - 1.0;
    vec3 n1 = texture2D(uNormalMap, flow1).rgb * 2.0 - 1.0;
    vec3 bump = normalize(mix(n0, n1, 0.4));

    // 将贴图法线叠加到顶点法线上（切线空间 → 世界）
    vec3 N_vert = normalize(vWorldNormal);
    // 避免 N_vert 与 up 平行时 cross 为零
    vec3 up = abs(N_vert.y) > 0.999 ? vec3(0.0, 0.0, 1.0) : vec3(0.0, 1.0, 0.0);
    vec3 T = normalize(cross(N_vert, up));
    vec3 B = cross(N_vert, T);
    vec3 N = normalize(N_vert + (T * bump.x + B * bump.y) * uNormalStrength);

    vec3 V = normalize(uCameraPos - vWorldPos);
    vec3 L = normalize(uSunDir);
    vec3 H = normalize(L + V);

    float NdotV = abs(dot(N, V));
    float NdotH = max(dot(N, H), 0.0);

    // ── 距离 ──
    float dist = length(vWorldPos.xz);
    float depthFactor = smoothstep(5.0, 45.0, dist);

    // ── 水体颜色 ──
    vec3 waterColor = mix(uWaterSurface, uWaterDeep, depthFactor);
    waterColor = mix(waterColor * 0.82, waterColor * 1.06, smoothstep(-0.3, 0.3, vWaveHeight));

    // ── Fresnel ──
    float f0 = 0.02;
    float fresnel = f0 + (1.0 - f0) * pow(1.0 - NdotV, 5.0);
    vec3 skyRefl = mix(uSkyHorizon * 0.7, uSkyTop * 0.85, smoothstep(0.0, 0.6, N.y));
    waterColor = mix(waterColor, skyRefl, fresnel * 0.65);

    // ── 镜面高光 ──
    float roughness = 0.06;
    float a2 = roughness * roughness;
    float d = NdotH * NdotH * (a2 - 1.0) + 1.0;
    float spec = (a2 / (3.14159 * d * d)) * 0.5;
    float specWide = pow(NdotH, 80.0) * 0.12;
    waterColor += uSpecular * (spec * smoothstep(0.25, 0.55, NdotH) + specWide);
    waterColor -= uSpecular * spec * depthFactor * 0.4;

    // ── 太阳倒影 ──
    float sunRefl = pow(max(dot(reflect(-L, N), V), 0.0), 500.0) * 0.18;
    waterColor += uSpecular * sunRefl;

    // ── 泡沫 ──
    float foam = smoothstep(0.32, 0.58, vWaveHeight) * smoothstep(0.4, 0.75, NdotV);
    foam *= (1.0 - depthFactor * 0.6);
    waterColor = mix(waterColor, vec3(0.78, 0.84, 0.92), foam * 0.25);

    // ── 波谷散射 ──
    waterColor += uWaterSurface * smoothstep(-0.5, 0.0, vWaveHeight) * (1.0 - depthFactor) * 0.06;

    gl_FragColor = vec4(waterColor, 1.0);
  }
`

// ============================================================
// 主 Composable
// ============================================================
export function useLoginScene(containerRef: Ref<HTMLElement | null>) {
  const webglSupported = ref(true)

  let scene: THREE.Scene
  let camera: THREE.PerspectiveCamera
  let renderer: THREE.WebGLRenderer
  let waterMat: THREE.ShaderMaterial
  let skyMat: THREE.ShaderMaterial
  let normalMap: THREE.Texture
  let animationId = 0
  let clock: THREE.Clock

  function onResize() {
    if (!camera || !renderer) return
    camera.aspect = window.innerWidth / window.innerHeight
    camera.updateProjectionMatrix()
    renderer.setSize(window.innerWidth, window.innerHeight)
  }

  function animate() {
    animationId = requestAnimationFrame(animate)
    clock.getDelta() // 必须调用以触发 Clock 的 autoStart，否则 elapsedTime 异常
    const elapsed = clock.elapsedTime

    // 相机固定
    camera.position.set(0, 2.2, 5)
    camera.lookAt(0, 1.2, -5)

    waterMat.uniforms.uTime.value = elapsed
    waterMat.uniforms.uCameraPos.value.copy(camera.position)
    skyMat.uniforms.uSunDir.value.copy(waterMat.uniforms.uSunDir.value)

    renderer.render(scene, camera)
  }

  function init() {
    const container = containerRef.value
    if (!container) return
    try {
      const c = document.createElement('canvas')
      if (!(c.getContext('webgl') || c.getContext('experimental-webgl'))) throw new Error()
    } catch {
      webglSupported.value = false
      return
    }

    const cl = PERIODS.noon

    scene = new THREE.Scene()

    camera = new THREE.PerspectiveCamera(58, window.innerWidth / window.innerHeight, 0.5, 120)
    camera.position.set(0, 2.2, 5)
    camera.lookAt(0, 1.5, -3)

    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false })
    renderer.setSize(window.innerWidth, window.innerHeight)
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2))
    renderer.toneMapping = THREE.ACESFilmicToneMapping
    renderer.toneMappingExposure = 1.1
    container.appendChild(renderer.domElement)

    // ── 法线贴图 ──
    const texLoader = new THREE.TextureLoader()
    normalMap = texLoader.load('/textures/Water_1_M_Normal.jpg')
    normalMap.wrapS = normalMap.wrapT = THREE.RepeatWrapping
    normalMap.colorSpace = THREE.NoColorSpace

    // ── 天空球（大球壳罩住整个场景） ──
    const skyGeo = new THREE.SphereGeometry(50, 64, 32)
    skyMat = new THREE.ShaderMaterial({
      vertexShader: skyVert,
      fragmentShader: skyFrag,
      uniforms: {
        uSkyTop: { value: new THREE.Color(cl.skyTop[0], cl.skyTop[1], cl.skyTop[2]) },
        uSkyHorizon: {
          value: new THREE.Color(cl.skyHorizon[0], cl.skyHorizon[1], cl.skyHorizon[2]),
        },
        uSunDir: { value: new THREE.Vector3(cl.sunDir[0], cl.sunDir[1], cl.sunDir[2]) },
      },
      side: THREE.BackSide,
      depthWrite: false,
    })
    const skyDome = new THREE.Mesh(skyGeo, skyMat)
    scene.add(skyDome)

    // ── 水面（延伸到远方的大平面） ──
    // 高密度网格 — 顶点波动才能细腻
    const waterGeo = new THREE.PlaneGeometry(100, 80, 150, 120)
    waterGeo.rotateX(-Math.PI / 2)
    waterMat = new THREE.ShaderMaterial({
      vertexShader: waterVert,
      fragmentShader: waterFrag,
      uniforms: {
        uNormalMap: { value: normalMap },
        uTime: { value: 0 },
        uCameraPos: { value: new THREE.Vector3() },
        uWaterDeep: { value: new THREE.Color(cl.waterDeep[0], cl.waterDeep[1], cl.waterDeep[2]) },
        uWaterSurface: {
          value: new THREE.Color(cl.waterSurface[0], cl.waterSurface[1], cl.waterSurface[2]),
        },
        uSpecular: { value: new THREE.Color(cl.specular[0], cl.specular[1], cl.specular[2]) },
        uSunDir: { value: new THREE.Vector3(cl.sunDir[0], cl.sunDir[1], cl.sunDir[2]) },
        uSkyTop: { value: new THREE.Color(cl.skyTop[0], cl.skyTop[1], cl.skyTop[2]) },
        uSkyHorizon: {
          value: new THREE.Color(cl.skyHorizon[0], cl.skyHorizon[1], cl.skyHorizon[2]),
        },
        uNormalStrength: { value: 0.4 },
      },
      transparent: false,
      depthWrite: true,
    })
    const waterMesh = new THREE.Mesh(waterGeo, waterMat)
    waterMesh.position.y = -1.0
    waterMesh.position.z = -5
    scene.add(waterMesh)

    // ── 远景雾层（地平线处的薄雾带） ──
    const mistGeo = new THREE.PlaneGeometry(120, 8)
    const mistMat = new THREE.ShaderMaterial({
      vertexShader: /* glsl */ `
        varying vec2 vUv;
        void main() {
          vUv = uv;
          gl_Position = projectionMatrix * viewMatrix * modelMatrix * vec4(position, 1.0);
        }
      `,
      fragmentShader: /* glsl */ `
        varying vec2 vUv;
        uniform vec3 uColor;
        void main() {
          float a = smoothstep(0.0, 0.25, vUv.y) * smoothstep(1.0, 0.75, vUv.y);
          a *= 0.35;
          gl_FragColor = vec4(uColor, a);
        }
      `,
      uniforms: {
        uColor: { value: new THREE.Color(cl.skyHorizon[0], cl.skyHorizon[1], cl.skyHorizon[2]) },
      },
      transparent: true,
      depthWrite: false,
    })
    const mist = new THREE.Mesh(mistGeo, mistMat)
    mist.position.set(0, -0.3, -20)
    scene.add(mist)

    window.addEventListener('resize', onResize)
    clock = new THREE.Clock()
    animate()
  }

  function dispose() {
    cancelAnimationFrame(animationId)
    window.removeEventListener('resize', onResize)
    if (renderer) {
      renderer.dispose()
      const dom = renderer.domElement
      if (dom.parentNode) dom.parentNode.removeChild(dom)
    }
    if (normalMap) normalMap.dispose()
    if (scene) {
      scene.traverse((obj) => {
        if (obj instanceof THREE.Mesh) {
          obj.geometry?.dispose()
          if (Array.isArray(obj.material)) obj.material.forEach((m) => m.dispose())
          else obj.material?.dispose()
        }
      })
    }
  }

  onMounted(() => {
    if (window.innerWidth >= 768) init()
  })
  onUnmounted(() => dispose())
  return { webglSupported }
}
