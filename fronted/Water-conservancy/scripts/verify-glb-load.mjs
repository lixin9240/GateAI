/** 验证 xiangjiaba-dam.glb 能否被 GLTFLoader 正常解析 */
import * as THREE from 'three'
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js'
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const file = path.join(path.dirname(fileURLToPath(import.meta.url)), '../public/models/xiangjiaba-dam.glb')
const buf = fs.readFileSync(file)
console.log('File KB:', (buf.length / 1024).toFixed(1))

const loader = new GLTFLoader()
loader.parse(
  buf.buffer.slice(buf.byteOffset, buf.byteOffset + buf.byteLength),
  '',
  (gltf) => {
    const root = gltf.scene
    let meshes = 0
    let tris = 0
    root.traverse((o) => {
      if (o instanceof THREE.Mesh) {
        meshes++
        const g = o.geometry
        tris += g.index ? g.index.count / 3 : g.attributes.position.count / 3
        console.log('Mesh:', o.name, 'tris:', Math.round(g.index ? g.index.count / 3 : 0))
      }
    })
    const box = new THREE.Box3().setFromObject(root)
    console.log('Meshes:', meshes, 'Total tris:', Math.round(tris))
    console.log('Bounds min:', box.min.toArray().map((v) => v.toFixed(2)))
    console.log('Bounds max:', box.max.toArray().map((v) => v.toFixed(2)))
    console.log('OK: GLB loads successfully')
  },
  (err) => {
    console.error('FAIL:', err)
    process.exit(1)
  },
)
