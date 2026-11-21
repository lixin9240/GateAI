// ============================================================
// 数字孪生 — 动态天气预设（随仿真场景同步）
// ============================================================
import * as THREE from 'three'
import type { SimulationScene } from '@/types/simulation'

export type WeatherType =
  'clear' | 'cloudy_sunset' | 'rain' | 'storm' | 'fog_morning' | 'night' | 'twin' | 'panorama'

export interface WeatherState {
  skyTop: THREE.Color
  skyMid: THREE.Color
  skyHorizon: THREE.Color
  sunColor: THREE.Color
  sunStrength: number
  cloudCover: number
  rayStrength: number
  fogColor: THREE.Color
  fogDensity: number
  ambientColor: THREE.Color
  ambientIntensity: number
  hemiSky: THREE.Color
  hemiGround: THREE.Color
  hemiIntensity: number
  sunLightIntensity: number
  exposure: number
  mistMultiplier: number
  sunDir: THREE.Vector3
}

function w(
  partial: Partial<WeatherState> &
    Pick<WeatherState, 'skyTop' | 'skyHorizon' | 'sunColor' | 'fogColor'>,
): WeatherState {
  return {
    skyMid: partial.skyMid ?? partial.skyTop.clone().lerp(partial.skyHorizon, 0.5),
    sunStrength: 1,
    cloudCover: 0.5,
    rayStrength: 0,
    fogDensity: 0.003,
    ambientColor: new THREE.Color(0x1a3050),
    ambientIntensity: 0.38,
    hemiSky: new THREE.Color(0x4488bb),
    hemiGround: new THREE.Color(0x1a2838),
    hemiIntensity: 0.52,
    sunLightIntensity: 0.75,
    exposure: 0.92,
    mistMultiplier: 1,
    sunDir: new THREE.Vector3(0.55, 0.28, 0.42),
    ...partial,
  }
}

export const WEATHER_PRESETS: Record<WeatherType, WeatherState> = {
  /** 黄昏多云（默认 cinematic） */
  cloudy_sunset: w({
    skyTop: new THREE.Color(0x0a0e14),
    skyMid: new THREE.Color(0x1a2438),
    skyHorizon: new THREE.Color(0x3d4a5c),
    sunColor: new THREE.Color(0xffcc77),
    sunStrength: 0.22,
    cloudCover: 0.72,
    fogColor: new THREE.Color(0x1a2838),
    fogDensity: 0.0028,
    ambientColor: new THREE.Color(0x1a2838),
    ambientIntensity: 0.38,
    hemiSky: new THREE.Color(0x556688),
    hemiGround: new THREE.Color(0x1a2028),
    hemiIntensity: 0.48,
    sunLightIntensity: 0.65,
    exposure: 0.9,
    mistMultiplier: 1.2,
    sunDir: new THREE.Vector3(0.62, 0.22, 0.38),
  }),
  clear: w({
    skyTop: new THREE.Color(0x1a5080),
    skyMid: new THREE.Color(0x4a90c8),
    skyHorizon: new THREE.Color(0x87ceeb),
    sunColor: new THREE.Color(0xffffee),
    sunStrength: 1.8,
    cloudCover: 0.15,
    fogColor: new THREE.Color(0xc8dff5),
    fogDensity: 0.0018,
    ambientColor: new THREE.Color(0x4488aa),
    ambientIntensity: 0.45,
    hemiSky: new THREE.Color(0x87ceeb),
    hemiGround: new THREE.Color(0x3a5a40),
    hemiIntensity: 0.55,
    sunLightIntensity: 2.6,
    exposure: 1.05,
    mistMultiplier: 0.6,
    sunDir: new THREE.Vector3(0.4, 0.65, 0.3),
  }),
  rain: w({
    skyTop: new THREE.Color(0x0a1018),
    skyMid: new THREE.Color(0x1a2430),
    skyHorizon: new THREE.Color(0x2a3848),
    sunColor: new THREE.Color(0x8899aa),
    sunStrength: 0.35,
    cloudCover: 0.88,
    fogColor: new THREE.Color(0x2a3848),
    fogDensity: 0.0045,
    ambientColor: new THREE.Color(0x2a3848),
    ambientIntensity: 0.38,
    hemiSky: new THREE.Color(0x556677),
    hemiGround: new THREE.Color(0x1a2028),
    hemiIntensity: 0.35,
    sunLightIntensity: 0.8,
    exposure: 0.95,
    mistMultiplier: 1.5,
    sunDir: new THREE.Vector3(0.3, 0.5, 0.4),
  }),
  storm: w({
    skyTop: new THREE.Color(0x050810),
    skyMid: new THREE.Color(0x0f1824),
    skyHorizon: new THREE.Color(0x1a2838),
    sunColor: new THREE.Color(0x668899),
    sunStrength: 0.2,
    cloudCover: 0.95,
    fogColor: new THREE.Color(0x0f1824),
    fogDensity: 0.0055,
    ambientColor: new THREE.Color(0x0f1824),
    ambientIntensity: 0.25,
    hemiSky: new THREE.Color(0x334455),
    hemiGround: new THREE.Color(0x0a1018),
    hemiIntensity: 0.28,
    sunLightIntensity: 0.6,
    exposure: 0.88,
    mistMultiplier: 2.0,
    sunDir: new THREE.Vector3(0.45, 0.15, 0.35),
  }),
  fog_morning: w({
    skyTop: new THREE.Color(0xc8d8e8),
    skyMid: new THREE.Color(0xd8e8f0),
    skyHorizon: new THREE.Color(0xe8f0f8),
    sunColor: new THREE.Color(0xffeedd),
    sunStrength: 0.9,
    cloudCover: 0.55,
    fogColor: new THREE.Color(0xd0dce8),
    fogDensity: 0.006,
    ambientColor: new THREE.Color(0xb0c0d0),
    ambientIntensity: 0.5,
    hemiSky: new THREE.Color(0xd0e0f0),
    hemiGround: new THREE.Color(0x607060),
    hemiIntensity: 0.5,
    sunLightIntensity: 1.6,
    exposure: 1.0,
    mistMultiplier: 2.5,
    sunDir: new THREE.Vector3(0.5, 0.18, 0.45),
  }),
  night: w({
    skyTop: new THREE.Color(0x020408),
    skyMid: new THREE.Color(0x0a1020),
    skyHorizon: new THREE.Color(0x101828),
    sunColor: new THREE.Color(0x4466aa),
    sunStrength: 0.08,
    cloudCover: 0.4,
    fogColor: new THREE.Color(0x0a1020),
    fogDensity: 0.0035,
    ambientColor: new THREE.Color(0x0a1020),
    ambientIntensity: 0.18,
    hemiSky: new THREE.Color(0x1a2848),
    hemiGround: new THREE.Color(0x050810),
    hemiIntensity: 0.22,
    sunLightIntensity: 0.35,
    exposure: 0.82,
    mistMultiplier: 0.8,
    sunDir: new THREE.Vector3(0.2, 0.08, 0.5),
  }),
  /** 数字孪生 — 白色简洁背景 */
  twin: w({
    skyTop: new THREE.Color(0xffffff),
    skyMid: new THREE.Color(0xf7fbff),
    skyHorizon: new THREE.Color(0xf0f6fc),
    sunColor: new THREE.Color(0xfff5eb),
    sunStrength: 0.85,
    cloudCover: 0.06,
    fogColor: new THREE.Color(0xf5f8fc),
    fogDensity: 0,
    ambientColor: new THREE.Color(0xe8eef4),
    ambientIntensity: 0.72,
    hemiSky: new THREE.Color(0xffffff),
    hemiGround: new THREE.Color(0xe2e8f0),
    hemiIntensity: 0.58,
    sunLightIntensity: 0.92,
    exposure: 1.06,
    mistMultiplier: 0,
    sunDir: new THREE.Vector3(-0.35, 0.55, 0.38).normalize(),
  }),
  /** 全景弹窗 — 电影级冷暖对冲 + 丁达尔体积光 */
  panorama: w({
    skyTop: new THREE.Color(0x8899aa),
    skyMid: new THREE.Color(0xb8c4d0),
    skyHorizon: new THREE.Color(0xd8e0e8),
    sunColor: new THREE.Color(0xffd899),
    sunStrength: 0.55,
    cloudCover: 0.38,
    rayStrength: 0.42,
    fogColor: new THREE.Color(0xc8d4dc),
    fogDensity: 0.0018,
    ambientColor: new THREE.Color(0x8898a8),
    ambientIntensity: 0.52,
    hemiSky: new THREE.Color(0xb0c0d0),
    hemiGround: new THREE.Color(0x5a7060),
    hemiIntensity: 0.48,
    sunLightIntensity: 1.05,
    exposure: 0.94,
    mistMultiplier: 1.4,
    sunDir: new THREE.Vector3(-0.62, 0.28, 0.42).normalize(),
  }),
}

export function simulationSceneToWeather(scene: SimulationScene): WeatherType {
  switch (scene) {
    case 'flood':
      return 'storm'
    case 'rainstorm':
      return 'rain'
    case 'dry':
      return 'clear'
    case 'normal':
    case 'custom':
      return 'cloudy_sunset'
    default:
      return 'cloudy_sunset'
  }
}

export function lerpWeather(a: WeatherState, b: WeatherState, t: number): WeatherState {
  const k = THREE.MathUtils.clamp(t, 0, 1)
  return {
    skyTop: a.skyTop.clone().lerp(b.skyTop, k),
    skyMid: a.skyMid.clone().lerp(b.skyMid, k),
    skyHorizon: a.skyHorizon.clone().lerp(b.skyHorizon, k),
    sunColor: a.sunColor.clone().lerp(b.sunColor, k),
    sunStrength: THREE.MathUtils.lerp(a.sunStrength, b.sunStrength, k),
    cloudCover: THREE.MathUtils.lerp(a.cloudCover, b.cloudCover, k),
    rayStrength: THREE.MathUtils.lerp(a.rayStrength, b.rayStrength, k),
    fogColor: a.fogColor.clone().lerp(b.fogColor, k),
    fogDensity: THREE.MathUtils.lerp(a.fogDensity, b.fogDensity, k),
    ambientColor: a.ambientColor.clone().lerp(b.ambientColor, k),
    ambientIntensity: THREE.MathUtils.lerp(a.ambientIntensity, b.ambientIntensity, k),
    hemiSky: a.hemiSky.clone().lerp(b.hemiSky, k),
    hemiGround: a.hemiGround.clone().lerp(b.hemiGround, k),
    hemiIntensity: THREE.MathUtils.lerp(a.hemiIntensity, b.hemiIntensity, k),
    sunLightIntensity: THREE.MathUtils.lerp(a.sunLightIntensity, b.sunLightIntensity, k),
    exposure: THREE.MathUtils.lerp(a.exposure, b.exposure, k),
    mistMultiplier: THREE.MathUtils.lerp(a.mistMultiplier, b.mistMultiplier, k),
    sunDir: a.sunDir.clone().lerp(b.sunDir, k).normalize(),
  }
}
