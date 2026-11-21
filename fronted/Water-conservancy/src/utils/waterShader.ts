// ============================================================
// 物理级动态水面 — 多层波浪 / 菲涅尔 / 天光反射 / 焦散
// ============================================================
import * as THREE from 'three'

export function createWaterMaterial(options?: {
  color?: number
  deepColor?: number
  opacity?: number
  envMap?: THREE.Texture | null
  waveScale?: number
  /** 水面镜面高光强度，孪生场景宜 0.1~0.2 */
  specIntensity?: number
  /** 蓝色流体网格强度 */
  gridStrength?: number
  reflectivity?: number
}) {
  const color = options?.color ?? 0x1a5080
  const deepColor = options?.deepColor ?? 0x0a2540
  const opacity = options?.opacity ?? 0.9
  const waveScale = options?.waveScale ?? 0.38
  const specIntensity = options?.specIntensity ?? 1.0
  const reflectivity = options?.reflectivity ?? 0.72
  const gridStrength = options?.gridStrength ?? 0

  const mat = new THREE.ShaderMaterial({
    uniforms: {
      uTime: { value: 0 },
      uColor: { value: new THREE.Color(color) },
      uDeepColor: { value: new THREE.Color(deepColor) },
      uOpacity: { value: opacity },
      uWaveScale: { value: waveScale },
      uFlowSpeed: { value: 1.0 },
      uSunDirection: { value: new THREE.Vector3(0.55, 0.35, 0.42).normalize() },
      uSunColor: { value: new THREE.Color(0xffcc77) },
      uEnvMap: { value: options?.envMap ?? null },
      uReflectivity: { value: reflectivity },
      uSpecIntensity: { value: specIntensity },
      uGridStrength: { value: gridStrength },
    },
    vertexShader: `
      uniform float uTime;
      uniform float uWaveScale;
      uniform float uFlowSpeed;
      varying vec3 vWorldPos;
      varying vec3 vNormal;
      varying vec3 vViewDir;
      varying float vWave;
      varying vec2 vUv;

      void main() {
        vec3 pos = position;
        float t = uTime * uFlowSpeed;
        float w1 = sin(pos.x * 0.32 + t * 1.05) * uWaveScale;
        float w2 = cos(pos.z * 0.26 + t * 0.78) * uWaveScale * 0.72;
        float w3 = sin((pos.x * 0.55 + pos.z * 0.38) + t * 1.35) * uWaveScale * 0.38;
        float w4 = cos(pos.x * 0.18 - pos.z * 0.22 + t * 0.55) * uWaveScale * 0.22;
        float ripple = sin(length(pos.xz) * 0.65 - t * 1.8) * uWaveScale * 0.12;
        pos.y += w1 + w2 + w3 + w4 + ripple;
        vWave = w1 + w2 + w3 + ripple;

        float dx = cos(pos.x * 0.32 + t * 1.05) * 0.32 * uWaveScale
                 + cos((pos.x * 0.55 + pos.z * 0.38) + t * 1.35) * 0.55 * uWaveScale * 0.38
                 - sin(pos.x * 0.18 - pos.z * 0.22 + t * 0.55) * 0.18 * uWaveScale * 0.22;
        float dz = -sin(pos.z * 0.26 + t * 0.78) * 0.26 * uWaveScale * 0.72
                 + sin((pos.x * 0.55 + pos.z * 0.38) + t * 1.35) * 0.38 * uWaveScale * 0.38
                 + sin(pos.x * 0.18 - pos.z * 0.22 + t * 0.55) * 0.22 * uWaveScale * 0.22;
        vec3 waveNormal = normalize(vec3(-dx, 1.0, -dz));

        vNormal = normalize(normalMatrix * waveNormal);
        vec4 wp = modelMatrix * vec4(pos, 1.0);
        vWorldPos = wp.xyz;
        vViewDir = normalize(cameraPosition - vWorldPos);
        vUv = uv;
        gl_Position = projectionMatrix * viewMatrix * wp;
      }
    `,
    fragmentShader: `
      uniform vec3 uColor;
      uniform vec3 uDeepColor;
      uniform float uOpacity;
      uniform vec3 uSunDirection;
      uniform vec3 uSunColor;
      uniform float uTime;
      uniform samplerCube uEnvMap;
      uniform float uReflectivity;
      uniform float uSpecIntensity;
      uniform float uGridStrength;
      varying vec3 vWorldPos;
      varying vec3 vNormal;
      varying vec3 vViewDir;
      varying float vWave;
      varying vec2 vUv;

      void main() {
        vec3 N = normalize(vNormal);
        vec3 V = normalize(vViewDir);
        vec3 L = normalize(uSunDirection);

        float fresnel = pow(1.0 - max(dot(V, N), 0.0), 3.8);
        vec3 R = reflect(-V, N);
        vec3 envRef = textureCube(uEnvMap, R).rgb;
        float hasEnv = step(0.01, length(envRef));

        vec3 refractDir = refract(-V, N, 0.75);
        vec3 refrCol = textureCube(uEnvMap, refractDir).rgb * vec3(0.35, 0.55, 0.72);

        float spec = pow(max(dot(reflect(-L, N), V), 0.0), 220.0);
        float spec2 = pow(max(dot(reflect(-L, N), V), 0.0), 42.0);
        float sunBand = pow(max(dot(reflect(-L, N), V), 0.0), 8.0);

        float caustic = sin(vWorldPos.x * 2.2 + uTime * 1.1) * sin(vWorldPos.z * 2.2 + uTime * 0.9);
        caustic = caustic * 0.5 + 0.5;

        vec3 col = mix(uDeepColor, uColor, 0.42 + vWave * 0.14 + fresnel * 0.22);
        col = mix(col, refrCol, (1.0 - fresnel) * 0.28 * hasEnv);
        col = mix(col, envRef * vec3(0.55, 0.72, 0.95), fresnel * uReflectivity * hasEnv);
        col += uSunColor * spec * 2.2 * uSpecIntensity;
        col += uSunColor * spec2 * 0.55 * uSpecIntensity;
        col += uSunColor * sunBand * 0.18 * uSpecIntensity * (0.85 + 0.15 * sin(uTime * 0.8 + vWorldPos.x * 0.3));

        float sunStreak = pow(max(dot(reflect(-L, N), V), 0.0), 3.2);
        col += uSunColor * sunStreak * 0.42 * uSpecIntensity * fresnel;

        float sparkle = pow(max(
          sin(vWorldPos.x * 9.0 + uTime * 1.8) *
          sin(vWorldPos.z * 8.0 - uTime * 1.4), 0.0), 14.0);
        col += uSunColor * sparkle * 0.32 * fresnel * uSpecIntensity;

        vec2 sunXZ = normalize(vec2(L.x + 0.001, L.z + 0.001));
        float longStripe = sin(dot(vWorldPos.xz, sunXZ) * 1.8 + uTime * 0.45) * 0.5 + 0.5;
        longStripe = pow(longStripe, 5.0) * sunBand;
        col += uSunColor * longStripe * 0.62 * uSpecIntensity;

        float gridX = abs(fract(vWorldPos.x * 0.35) - 0.5);
        float gridZ = abs(fract(vWorldPos.z * 0.35) - 0.5);
        float grid = (1.0 - smoothstep(0.0, 0.06, gridX)) + (1.0 - smoothstep(0.0, 0.06, gridZ));
        grid = min(grid, 1.0) * uGridStrength;
        col += vec3(0.15, 0.45, 0.95) * grid * (0.6 + fresnel * 0.4);

        col += vec3(0.05, 0.25, 0.45) * caustic * 0.08;

        float foam = smoothstep(0.28, 0.52, vWave) * 0.12;
        col += vec3(foam);

        gl_FragColor = vec4(col, uOpacity);
      }
    `,
    transparent: true,
    side: THREE.DoubleSide,
  })

  return mat
}
