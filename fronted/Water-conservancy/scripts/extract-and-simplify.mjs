/**
 * 从混元 GLB 提取纯几何并减面（跳过 90MB 贴图，降低内存）
 * 运行: node --max-old-space-size=4096 scripts/extract-and-simplify.mjs
 */
import * as THREE from 'three'
import { GLTFExporter } from 'three/examples/jsm/exporters/GLTFExporter.js'
import { SimplifyModifier } from 'three/examples/jsm/modifiers/SimplifyModifier.js'
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

if (typeof globalThis.FileReader === 'undefined') {
  globalThis.FileReader = class FileReader {
    result = null
    onloadend = null
    readAsArrayBuffer(blob) {
      blob.arrayBuffer().then((buf) => {
        this.result = buf
        this.onloadend?.()
      })
    }
  }
}

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const INPUT = path.join(__dirname, '../public/models/hunyuan-raw.glb')
const OUTPUT = path.join(__dirname, '../public/models/xiangjiaba-dam.glb')
const TARGET_TRIS = 30000
const TARGET_HEIGHT = 28

function parseGlbMesh(filePath) {
  const buf = fs.readFileSync(filePath)
  const jsonLen = buf.readUInt32LE(12)
  const json = JSON.parse(buf.toString('utf8', 20, 20 + jsonLen))

  const binStart = 20 + jsonLen
  const binLen = buf.readUInt32LE(binStart + 4)
  const bin = buf.subarray(binStart + 8, binStart + 8 + binLen)

  const mesh = json.meshes[0]
  const prim = mesh.primitives[0]
  const posAcc = json.accessors[prim.attributes.POSITION]
  const idxAcc = json.accessors[prim.indices]

  const posView = json.bufferViews[posAcc.bufferView]
  const idxView = json.bufferViews[idxAcc.bufferView]

  const posArr = new Float32Array(
    bin.buffer,
    bin.byteOffset + posView.byteOffset + (posAcc.byteOffset ?? 0),
    posAcc.count * 3,
  )

  let indices
  if (idxAcc.componentType === 5125) {
    indices = new Uint32Array(
      bin.buffer,
      bin.byteOffset + idxView.byteOffset + (idxAcc.byteOffset ?? 0),
      idxAcc.count,
    )
  } else if (idxAcc.componentType === 5123) {
    const u16 = new Uint16Array(
      bin.buffer,
      bin.byteOffset + idxView.byteOffset + (idxAcc.byteOffset ?? 0),
      idxAcc.count,
    )
    indices = Uint32Array.from(u16)
  } else {
    throw new Error(`Unsupported index type ${idxAcc.componentType}`)
  }

  console.log('Vertices:', posAcc.count.toLocaleString())
  console.log('Triangles:', (idxAcc.count / 3).toLocaleString())

  const geometry = new THREE.BufferGeometry()
  geometry.setAttribute('position', new THREE.BufferAttribute(posArr.slice(), 3))
  geometry.setIndex(new THREE.BufferAttribute(indices.slice(), 1))
  geometry.computeVertexNormals()
  return geometry
}

function normalizeToScene(geometry) {
  geometry.computeBoundingBox()
  const box = geometry.boundingBox
  const size = new THREE.Vector3()
  box.getSize(size)
  const center = new THREE.Vector3()
  box.getCenter(center)

  geometry.translate(-center.x, -box.min.y, -center.z)

  const scale = TARGET_HEIGHT / (size.y || 1)
  geometry.scale(scale, scale, scale)
  geometry.computeBoundingBox()
  console.log('Scaled size:', box.getSize(new THREE.Vector3()).multiplyScalar(scale).toArray().map((v) => v.toFixed(2)))
  return scale
}

function exportGlb(object) {
  return new Promise((resolve, reject) => {
    const exporter = new GLTFExporter()
    exporter.parse(
      object,
      (data) => {
        if (data instanceof ArrayBuffer) {
          fs.writeFileSync(OUTPUT, Buffer.from(data))
          resolve(fs.statSync(OUTPUT).size)
        } else {
          reject(new Error('Expected binary GLB'))
        }
      },
      reject,
      { binary: true },
    )
  })
}

console.log('Reading mesh geometry from', INPUT)
const geometry = parseGlbMesh(INPUT)
const triCount = geometry.index ? geometry.index.count / 3 : geometry.attributes.position.count / 3
console.log('Loaded tris:', Math.round(triCount).toLocaleString())

console.log('Simplifying to', TARGET_TRIS.toLocaleString(), 'tris ...')
const modifier = new SimplifyModifier()
let current = geometry
const steps = [
  Math.min(Math.round(triCount * 0.15), 200000),
  80000,
  TARGET_TRIS,
]
for (const target of steps) {
  if ((current.index?.count ?? current.attributes.position.count) / 3 <= target) continue
  console.log('  step ->', target.toLocaleString())
  const next = modifier.modify(current, target)
  if (current !== geometry) current.dispose()
  current = next
}
const simplified = current
if (geometry !== simplified) geometry.dispose()

const finalTris = simplified.index ? simplified.index.count / 3 : simplified.attributes.position.count / 3
console.log('Simplified tris:', Math.round(finalTris).toLocaleString())

normalizeToScene(simplified)

const mesh = new THREE.Mesh(
  simplified,
  new THREE.MeshStandardMaterial({ color: 0x8a9098, roughness: 0.88, metalness: 0.04 }),
)
mesh.name = '向家坝大坝'

const root = new THREE.Group()
root.name = 'XiangjiabaDam'
root.add(mesh)

console.log('Exporting', OUTPUT)
const bytes = await exportGlb(root)
console.log('Done. Output size MB:', (bytes / 1024 / 1024).toFixed(2))
