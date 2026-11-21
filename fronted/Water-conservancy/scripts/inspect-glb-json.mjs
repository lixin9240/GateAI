/** 只读 GLB 头部 JSON，不加载 mesh 二进制 */
import fs from 'fs'

const input = process.argv[2]
const buf = fs.readFileSync(input)
console.log('Size MB:', (buf.length / 1024 / 1024).toFixed(2))

const magic = buf.toString('utf8', 0, 4)
if (magic !== 'glTF') throw new Error('Not GLB')
const jsonLen = buf.readUInt32LE(12)
const jsonType = buf.toString('utf8', 16, 20)
if (jsonType !== 'JSON') throw new Error('Missing JSON chunk')
const json = JSON.parse(buf.toString('utf8', 20, 20 + jsonLen))

const nodes = json.nodes ?? []
const meshes = json.meshes ?? []
console.log('Nodes:', nodes.length, '| Meshes:', meshes.length)

let totalTris = 0
meshes.forEach((m, i) => {
  let tris = 0
  m.primitives?.forEach((p) => {
    const acc = json.accessors?.[p.indices ?? p.attributes?.POSITION]
    if (p.indices !== undefined) {
      tris += (json.accessors[p.indices].count ?? 0) / 3
    } else if (p.attributes?.POSITION !== undefined) {
      tris += (json.accessors[p.attributes.POSITION].count ?? 0) / 3
    }
  })
  totalTris += tris
  const name = m.name || `mesh_${i}`
  console.log(`${String(i + 1).padStart(2)}. ${name.padEnd(36)} ${Math.round(tris).toLocaleString()} tris`)
})

console.log('\nTotal tris:', Math.round(totalTris).toLocaleString())
nodes.slice(0, 20).forEach((n, i) => {
  console.log(`node ${i}: ${n.name ?? '(unnamed)'} mesh=${n.mesh ?? '-'} children=${n.children?.length ?? 0}`)
})
