// ============================================================
// 电影级动态天光 — 多天气预设
// ============================================================
import * as THREE from 'three'
import { WEATHER_PRESETS, lerpWeather, type WeatherType, type WeatherState } from './weatherSystem'

const SKY_VS = `
  varying vec3 vWorldPos;
  void main() {
    vec4 wp = modelMatrix * vec4(position, 1.0);
    vWorldPos = wp.xyz;
    gl_Position = projectionMatrix * viewMatrix * wp;
  }
`

const SKY_FS = `
  uniform float uTime;
  uniform vec3 uSunDir;
  uniform vec3 uSkyTop;
  uniform vec3 uSkyMid;
  uniform vec3 uSkyHorizon;
  uniform vec3 uSunColor;
  uniform float uSunStrength;
  uniform float uCloudCover;
  uniform float uRayStrength;

  varying vec3 vWorldPos;

  float hash(vec2 p) {
    return fract(sin(dot(p, vec2(127.1, 311.7))) * 43758.5453);
  }

  float noise(vec2 p) {
    vec2 i = floor(p);
    vec2 f = fract(p);
    f = f * f * (3.0 - 2.0 * f);
    float a = hash(i);
    float b = hash(i + vec2(1.0, 0.0));
    float c = hash(i + vec2(0.0, 1.0));
    float d = hash(i + vec2(1.0, 1.0));
    return mix(mix(a, b, f.x), mix(c, d, f.x), f.y);
  }

  float fbm(vec2 p) {
    float v = 0.0;
    float a = 0.5;
    for (int i = 0; i < 6; i++) {
      v += a * noise(p);
      p *= 2.15;
      a *= 0.47;
    }
    return v;
  }

  void main() {
    vec3 dir = normalize(vWorldPos);
    float h = dir.y * 0.5 + 0.5;

    vec3 sky = h > 0.5
      ? mix(uSkyMid, uSkyTop, (h - 0.5) * 2.0)
      : mix(uSkyHorizon, uSkyMid, h * 2.0);

    float clouds = fbm(dir.xz * 3.2 + uTime * 0.012);
    clouds += fbm(dir.xz * 6.8 - uTime * 0.007) * 0.5;
    clouds += fbm(dir.xz * 12.0 + uTime * 0.004) * 0.2;
    sky = mix(sky, uSkyTop * 0.75, smoothstep(0.25, 0.9, clouds) * uCloudCover);

    float sunDot = max(dot(dir, uSunDir), 0.0);
    float sun = pow(sunDot, 128.0) * 0.35 * uSunStrength;
    float sunHalo = pow(sunDot, 8.0) * 0.06 * uSunStrength;
    sky += uSunColor * sun;
    sky += uSunColor * sunHalo * 0.2;

    float ray = pow(sunDot, 2.8) * uRayStrength * (0.82 + 0.18 * sin(uTime * 0.28));
    ray *= smoothstep(-0.08, 0.52, dir.y);
    float rayBeam = fbm(dir.xz * 4.5 + uTime * 0.008) * 0.35 + 0.65;
    sky += uSunColor * ray * rayBeam * 0.48;

    gl_FragColor = vec4(sky, 1.0);
  }
`

export interface CinematicSky {
  mesh: THREE.Mesh
  envMap: THREE.Texture
  uniforms: Record<string, THREE.IUniform>
  currentWeather: WeatherState
  update: (t: number, dt: number) => void
  setWeather: (type: WeatherType) => void
  applyToScene: (
    scene: THREE.Scene,
    lights: {
      sun: THREE.DirectionalLight | null
      ambient: THREE.AmbientLight | null
      hemi: THREE.HemisphereLight | null
    },
    renderer: THREE.WebGLRenderer,
  ) => void
  dispose: () => void
}

function applyWeatherToUniforms(uniforms: Record<string, THREE.IUniform>, w: WeatherState) {
  uniforms.uSkyTop.value.copy(w.skyTop)
  uniforms.uSkyMid.value.copy(w.skyMid)
  uniforms.uSkyHorizon.value.copy(w.skyHorizon)
  uniforms.uSunColor.value.copy(w.sunColor)
  uniforms.uSunStrength.value = w.sunStrength
  uniforms.uCloudCover.value = w.cloudCover
  uniforms.uRayStrength.value = w.rayStrength
  uniforms.uSunDir.value.copy(w.sunDir)
}

export function createCinematicSky(
  renderer: THREE.WebGLRenderer,
  initialType: WeatherType = 'cloudy_sunset',
): CinematicSky {
  let targetType = initialType
  let blend = 1
  let fromState = WEATHER_PRESETS[initialType]
  let current = lerpWeather(fromState, WEATHER_PRESETS[initialType], 1)

  const uniforms: Record<string, THREE.IUniform> = {
    uTime: { value: 0 },
    uSunDir: { value: current.sunDir.clone() },
    uSkyTop: { value: current.skyTop.clone() },
    uSkyMid: { value: current.skyMid.clone() },
    uSkyHorizon: { value: current.skyHorizon.clone() },
    uSunColor: { value: current.sunColor.clone() },
    uSunStrength: { value: current.sunStrength },
    uCloudCover: { value: current.cloudCover },
    uRayStrength: { value: current.rayStrength },
  }
  applyWeatherToUniforms(uniforms, current)

  const skyMat = new THREE.ShaderMaterial({
    side: THREE.BackSide,
    depthWrite: false,
    uniforms,
    vertexShader: SKY_VS,
    fragmentShader: SKY_FS,
  })

  const mesh = new THREE.Mesh(new THREE.SphereGeometry(420, 64, 32), skyMat)
  mesh.frustumCulled = false
  mesh.renderOrder = -1

  const pmrem = new THREE.PMREMGenerator(renderer)
  pmrem.compileEquirectangularShader()
  const envScene = new THREE.Scene()
  envScene.add(mesh.clone())
  const rt = pmrem.fromScene(envScene, 0.04)
  pmrem.dispose()

  return {
    mesh,
    envMap: rt.texture,
    uniforms,
    get currentWeather() {
      return current
    },
    update(t: number, dt: number) {
      uniforms.uTime.value = t
      if (blend < 1) {
        blend = Math.min(1, blend + dt * 0.35)
        current = lerpWeather(fromState, WEATHER_PRESETS[targetType], blend)
        applyWeatherToUniforms(uniforms, current)
      }
      const angle = t * 0.025
      uniforms.uSunDir.value
        .set(
          current.sunDir.x + Math.sin(angle) * 0.06,
          current.sunDir.y + Math.sin(t * 0.06) * 0.03,
          current.sunDir.z + Math.cos(angle) * 0.05,
        )
        .normalize()
    },
    setWeather(type: WeatherType) {
      if (type === targetType && blend >= 1) return
      fromState = current
      targetType = type
      blend = 0
    },
    applyToScene(scene, lights, renderer) {
      if (scene.fog instanceof THREE.FogExp2) {
        scene.fog.color.copy(current.fogColor)
        scene.fog.density = current.fogDensity
      }
      scene.background = current.skyHorizon.clone()
      if (lights.sun) {
        lights.sun.color.copy(current.sunColor)
        lights.sun.intensity = current.sunLightIntensity
        lights.sun.position.copy(uniforms.uSunDir.value.clone().multiplyScalar(120))
      }
      if (lights.ambient) {
        lights.ambient.color.copy(current.ambientColor)
        lights.ambient.intensity = current.ambientIntensity
      }
      if (lights.hemi) {
        lights.hemi.color.copy(current.hemiSky)
        lights.hemi.groundColor.copy(current.hemiGround)
        lights.hemi.intensity = current.hemiIntensity
      }
      renderer.toneMappingExposure = current.exposure
    },
    dispose() {
      rt.texture.dispose()
      skyMat.dispose()
      mesh.geometry.dispose()
    },
  }
}
