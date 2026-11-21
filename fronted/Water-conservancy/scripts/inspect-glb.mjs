/**
 * 快速检查 GLB 网格结构（不整文件解析材质贴图）
 * 运行: node --max-old-space-size=8192 scripts/inspect-glb.mjs [path]
 */
import * as THREE from 'three'
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js'
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const input = process.argv[2] ?? path.join(__dirname, '../public/models/hunyuan-raw.glb')

const buf = fs.readFileSync(input)
console.log('File:', input)
console.log('Size MB:', (buf.length / 1024 / 1024).toFixed(2))

const loader = new GLTFLoader()
loader.parse(buf.buffer.slice(buf.byteOffset, buf.byteOffset + buf.byteLength), '', (gltf) => {
  const root = gltf.scene
  const box = new THREE.Box3().setFromObject(root)
  const size = box.getCenter(new THREE.Vector3())
  const dim = box.getSize(new THREE.Vector3())
  console.log('\nBounds min:', box.min.toArray().map((v) => v.toFixed(3)))
  console.log('Bounds max:', box.max.toArray().map((v) => v.toFixed(3)))
  console.log('Size:', dim.toArray().map((v) => v.toFixed(3)))
  console.log('Center:', size.toArray().map((v) => v.toFixed(3)))

  let totalTris = 0
  const meshes = []
  root.traverse((obj) => {
    if (!(obj instanceof THREE.Mesh)) return
    const geo = obj.geometry
    const tris = geo.index ? geo.index.count / 3 : geo.attributes.position.count / 3
    totalTris += tris
    const mat = obj.material
    const color = mat?.color ? `#${mat.color.getHexString()}` : 'n/a'
    meshes.push({ name: obj.name || '(unnamed)', tris: Math.round(tris), color })
  })

  meshes.sort((a, b) => b.tris - a.tris)
  console.log('\nMeshes:', meshes.length, '| Total tris:', Math.round(totalTris))
  meshes.slice(0, 30).forEach((m, i) => {
    console.log(`${String(i + 1).padStart(2)}. ${m.name.padEnd(40)} ${String(m.tris).padStart(10)}  ${m.color}`)
  })
  if (meshes.length > 30) console.log(`... and ${meshes.length - 30} more`)
}, undefined, (err) => {
  console.error('Load failed:', err)
  process.exit(1)
})
