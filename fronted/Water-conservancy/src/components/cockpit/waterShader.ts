// ============================================================
// 向家坝水电站 — 水面着色器（波浪 + 菲涅尔反射）
// ============================================================
import * as THREE from 'three'

export const waterVertexShader = /* glsl */ `
uniform float uTime;
uniform float uWaveScale;
varying vec2 vUv;
varying vec3 vWorldPos;
varying vec3 vNormal;
varying float vWave;

void main() {
  vUv = uv;
  vec3 pos = position;

  float wave1 = sin(pos.x * 1.2 + uTime * 1.4) * 0.12;
  float wave2 = sin(pos.z * 0.9 + uTime * 1.1) * 0.08;
  float wave3 = sin((pos.x + pos.z) * 0.6 + uTime * 0.8) * 0.06;
  float wave = (wave1 + wave2 + wave3) * uWaveScale;
  pos.y += wave;
  vWave = wave;

  vec4 worldPos = modelMatrix * vec4(pos, 1.0);
  vWorldPos = worldPos.xyz;
  vNormal = normalize(normalMatrix * normal);

  gl_Position = projectionMatrix * viewMatrix * worldPos;
}
`

export const waterFragmentShader = /* glsl */ `
uniform float uTime;
uniform vec3 uDeepColor;
uniform vec3 uShallowColor;
uniform vec3 uSunColor;
uniform vec3 uSunDirection;
uniform float uOpacity;

varying vec2 vUv;
varying vec3 vWorldPos;
varying vec3 vNormal;
varying float vWave;

void main() {
  vec3 normal = normalize(vNormal + vec3(
    sin(vWorldPos.x * 2.0 + uTime) * 0.08,
    0.0,
    cos(vWorldPos.z * 2.0 + uTime * 0.9) * 0.08
  ));

  vec3 viewDir = normalize(cameraPosition - vWorldPos);
  float fresnel = pow(1.0 - max(dot(viewDir, normal), 0.0), 3.0);

  float depthMix = smoothstep(-0.2, 0.35, vWave + vUv.y * 0.5);
  vec3 waterColor = mix(uDeepColor, uShallowColor, depthMix);

  float spec = pow(max(dot(reflect(-uSunDirection, normal), viewDir), 0.0), 64.0);
  vec3 specular = uSunColor * spec * 1.5;

  vec3 skyReflect = mix(vec3(0.02, 0.08, 0.18), vec3(0.0, 0.55, 0.75), fresnel);
  vec3 finalColor = mix(waterColor, skyReflect, fresnel * 0.65) + specular;

  float alpha = uOpacity + fresnel * 0.15;
  gl_FragColor = vec4(finalColor, alpha);
}
`

export function createWaterMaterial(): THREE.ShaderMaterial {
  return new THREE.ShaderMaterial({
    uniforms: {
      uTime: { value: 0 },
      uWaveScale: { value: 1.0 },
      uDeepColor: { value: new THREE.Color(0x003355) },
      uShallowColor: { value: new THREE.Color(0x0088bb) },
      uSunColor: { value: new THREE.Color(0xaaddff) },
      uSunDirection: { value: new THREE.Vector3(0.6, 0.8, 0.3).normalize() },
      uOpacity: { value: 0.82 },
    },
    vertexShader: waterVertexShader,
    fragmentShader: waterFragmentShader,
    transparent: true,
    side: THREE.DoubleSide,
  })
}

export function createWaterMesh(width: number, depth: number, segments = 128): THREE.Mesh {
  const geo = new THREE.PlaneGeometry(width, depth, segments, segments)
  geo.rotateX(-Math.PI / 2)
  return new THREE.Mesh(geo, createWaterMaterial())
}
