/**
 * 混元 GLB 处理：去掉底部水体 mesh → 减面 → 导出
 * 运行: node scripts/fast-decimate-glb.mjs
 */
import * as THREE from 'three'
import { GLTFExporter } from 'three/examples/jsm/exporters/GLTFExporter.js'
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
/** 去掉模型底部该比例以下的三角面（混元水体/消力池） */
const DROP_BOTTOM_RATIO = 0.22

function parseGlbMesh(filePath) {
  const buf = fs.readFileSync(filePath)
  const jsonLen = buf.readUInt32LE(12)
  const json = JSON.parse(buf.toString('utf8', 20, 20 + jsonLen))
  const binStart = 20 + jsonLen
  const binLen = buf.readUInt32LE(binStart + 4)
  const bin = buf.subarray(binStart + 8, binStart + 8 + binLen)

  const prim = json.meshes[0].primitives[0]
  const posAcc = json.accessors[prim.attributes.POSITION]
  const idxAcc = json.accessors[prim.indices]
  const posView = json.bufferViews[posAcc.bufferView]
  const idxView = json.bufferViews[idxAcc.bufferView]

  const positions = new Float32Array(posAcc.count * 3)
  positions.set(new Float32Array(
    bin.buffer,
    bin.byteOffset + posView.byteOffset + (posAcc.byteOffset ?? 0),
    posAcc.count * 3,
  ))

  let srcIdx
  if (idxAcc.componentType === 5125) {
    srcIdx = new Uint32Array(
      bin.buffer,
      bin.byteOffset + idxView.byteOffset + (idxAcc.byteOffset ?? 0),
      idxAcc.count,
    )
  } else {
    const u16 = new Uint16Array(
      bin.buffer,
      bin.byteOffset + idxView.byteOffset + (idxAcc.byteOffset ?? 0),
      idxAcc.count,
    )
    srcIdx = Uint32Array.from(u16)
  }

  return { positions, srcIdx, triTotal: idxAcc.count / 3 }
}

function filterDamTriangles(positions, srcIdx, triTotal) {
  let minY = Infinity
  let maxY = -Infinity
  for (let i = 0; i < positions.length; i += 3) {
    const y = positions[i + 1]
    if (y < minY) minY = y
    if (y > maxY) maxY = y
  }
  const yCut = minY + (maxY - minY) * DROP_BOTTOM_RATIO
  console.log(`Y range: ${minY.toFixed(4)} ~ ${maxY.toFixed(4)}, cut below ${yCut.toFixed(4)}`)

  const kept = []
  for (let t = 0; t < triTotal; t++) {
    const i = t * 3
    const a = srcIdx[i] * 3
    const b = srcIdx[i + 1] * 3
    const c = srcIdx[i + 2] * 3
    const cy = (positions[a + 1] + positions[b + 1] + positions[c + 1]) / 3
    if (cy >= yCut) kept.push(srcIdx[i], srcIdx[i + 1], srcIdx[i + 2])
  }
  console.log('After water filter tris:', (kept.length / 3).toLocaleString())
  return kept
}

function decimate(positions, indices, triTotal) {
  const step = Math.max(1, Math.floor(triTotal / TARGET_TRIS))
  const newIndices = []
  for (let t = 0; t < triTotal; t += step) {
    const i = t * 3
    newIndices.push(indices[i], indices[i + 1], indices[i + 2])
    if (newIndices.length / 3 >= TARGET_TRIS) break
  }

  const used = new Set(newIndices)
  const remap = new Map()
  const newPos = []
  for (const vi of used) {
    remap.set(vi, remap.size)
    newPos.push(positions[vi * 3], positions[vi * 3 + 1], positions[vi * 3 + 2])
  }
  return {
    positions: new Float32Array(newPos),
    indices: newIndices.map((vi) => remap.get(vi)),
  }
}

function normalize(geometry) {
  geometry.computeBoundingBox()
  const box = geometry.boundingBox
  const size = new THREE.Vector3()
  const center = new THREE.Vector3()
  box.getSize(size)
  box.getCenter(center)
  geometry.translate(-center.x, -box.min.y, -center.z)
  const s = TARGET_HEIGHT / (size.y || 1)
  geometry.scale(s, s, s)
  return s
}

function exportGlb(object) {
  return new Promise((resolve, reject) => {
    new GLTFExporter().parse(
      object,
      (data) => {
        fs.writeFileSync(OUTPUT, Buffer.from(data))
        resolve(fs.statSync(OUTPUT).size)
      },
      reject,
      { binary: true },
    )
  })
}

console.log('Input:', INPUT)
const { positions, srcIdx, triTotal } = parseGlbMesh(INPUT)
console.log('Original tris:', Math.round(triTotal).toLocaleString())

const filtered = filterDamTriangles(positions, srcIdx, triTotal)
const filteredTriTotal = filtered.length / 3

const { positions: p2, indices } = decimate(positions, filtered, filteredTriTotal)
console.log('Decimated tris:', (indices.length / 3).toLocaleString())

const geo = new THREE.BufferGeometry()
geo.setAttribute('position', new THREE.BufferAttribute(p2, 3))
geo.setIndex(Array.from(indices))
geo.computeVertexNormals()
const scale = normalize(geo)
console.log('Scale applied:', scale.toFixed(3))

const mesh = new THREE.Mesh(
  geo,
  new THREE.MeshStandardMaterial({ color: 0x7a828a, roughness: 0.82, metalness: 0.06 }),
)
mesh.name = '向家坝大坝'
const root = new THREE.Group()
root.name = 'XiangjiabaDam'
root.add(mesh)

const bytes = await exportGlb(root)
console.log('Output:', OUTPUT)
console.log('Size KB:', (bytes / 1024).toFixed(1))
