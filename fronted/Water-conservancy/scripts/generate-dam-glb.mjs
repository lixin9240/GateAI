/**
 * 导出向家坝大坝 GLB（含 gateLeaf_0…4 命名，供 GLTFLoader 阀门动画绑定）
 * 运行: npm run generate:dam
 */
import * as THREE from 'three'
import { GLTFExporter } from 'three/examples/jsm/exporters/GLTFExporter.js'
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

// Node 环境 polyfill（GLTFExporter 内部依赖 FileReader）
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
const outDir = path.join(__dirname, '../public/models')
const outFile = path.join(outDir, 'xiangjiaba-dam.glb')

function displace(geo, strength = 1) {
  const pos = geo.attributes.position
  for (let i = 0; i < pos.count; i++) {
    const x = pos.getX(i), y = pos.getY(i), z = pos.getZ(i)
    const n = Math.sin(x * 1.8 + z * 2.1) * 0.04
    pos.setXYZ(i, x + n * strength, y, z + n * 0.5 * strength)
  }
  geo.computeVertexNormals()
}

function buildDamScene() {
  const root = new THREE.Group()
  root.name = 'XiangjiabaDam'

  const concrete = new THREE.MeshStandardMaterial({ color: 0x8a9098, roughness: 0.88, metalness: 0.04 })
  const accent = new THREE.MeshStandardMaterial({ color: 0x7a828a, roughness: 0.9, metalness: 0.03 })
  const steel = new THREE.MeshStandardMaterial({ color: 0x6a7580, roughness: 0.35, metalness: 0.88 })
  const darkSteel = new THREE.MeshStandardMaterial({ color: 0x4a5058, roughness: 0.4, metalness: 0.85 })

  const bodyGeo = new THREE.BoxGeometry(14, 28, 52, 24, 48, 40)
  displace(bodyGeo, 1.2)
  const body = new THREE.Mesh(bodyGeo, concrete)
  body.position.set(-2, 14, 0)
  body.name = '向家坝大坝'
  root.add(body)

  const pierZs = [-18, -10.5, -3, 4.5, 12, 19.5]
  pierZs.forEach((z, i) => {
    const pierGeo = new THREE.BoxGeometry(2.2, 26, 3.8, 6, 16, 6)
    displace(pierGeo, 0.8)
    const pier = new THREE.Mesh(pierGeo, accent)
    pier.position.set(5.5, 13, z)
    pier.name = `pier_${i}`
    root.add(pier)

    if (i < pierZs.length - 1) {
      const midZ = (z + pierZs[i + 1]) / 2
      const bay = new THREE.Group()
      bay.name = `${i + 1}号阀门`

      const frame = new THREE.Mesh(new THREE.BoxGeometry(0.55, 11, 3.2, 1, 8, 3), steel)
      frame.position.set(6.2, 10, midZ)
      bay.add(frame)

      const gateLeaf = new THREE.Mesh(new THREE.BoxGeometry(0.42, 10, 2.8, 1, 6, 4), steel.clone())
      gateLeaf.name = `gateLeaf_${i}`
      gateLeaf.position.set(6.2, 6, midZ)
      bay.add(gateLeaf)

      const lintel = new THREE.Mesh(new THREE.BoxGeometry(1.0, 0.55, 3.4), darkSteel)
      lintel.position.set(6.2, 16.5, midZ)
      bay.add(lintel)

      root.add(bay)
    }
  })

  const ph = new THREE.Group()
  ph.name = '向家坝电站厂房'
  const phBody = new THREE.Mesh(new THREE.BoxGeometry(14, 9, 18, 8, 4, 6), accent)
  phBody.position.set(-12, 4.5, -14)
  ph.add(phBody)

  for (let row = 0; row < 2; row++) {
    for (let col = 0; col < 4; col++) {
      const win = new THREE.Mesh(
        new THREE.PlaneGeometry(1.8, 2.2),
        new THREE.MeshStandardMaterial({ color: 0xffdd99, emissive: 0xffaa44, emissiveIntensity: 1.4 }),
      )
      win.name = `phWindow_${row}_${col}`
      win.position.set(-12, 3 + row * 3.2, -20 + col * 4.5)
      win.rotation.y = Math.PI / 2
      ph.add(win)
    }
  }
  root.add(ph)

  const crest = new THREE.Mesh(new THREE.BoxGeometry(12, 1.2, 54, 8, 2, 24), accent)
  crest.position.set(-1, 28.5, 0)
  crest.name = '坝顶'
  root.add(crest)

  return root
}

fs.mkdirSync(outDir, { recursive: true })
const scene = buildDamScene()
const exporter = new GLTFExporter()

exporter.parse(
  scene,
  (buffer) => {
    fs.writeFileSync(outFile, Buffer.from(buffer))
    console.log(`✓ 已导出 ${outFile} (${(buffer.byteLength / 1024).toFixed(1)} KB)`)
    console.log('  阀门节点: gateLeaf_0 … gateLeaf_4')
    console.log('  替换素材: 下载 glb 覆盖此文件，保持 gateLeaf_* 命名即可')
  },
  (err) => {
    console.error('导出失败:', err)
    process.exit(1)
  },
  { binary: true },
)
