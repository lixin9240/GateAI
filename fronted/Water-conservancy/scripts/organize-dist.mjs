import { readdirSync, rmSync, statSync, writeFileSync, existsSync } from 'node:fs'
import { join, resolve } from 'node:path'

const distDir = resolve(process.cwd(), 'dist')

function walkRemoveBak(dir) {
  if (!existsSync(dir)) return 0
  let removed = 0
  for (const name of readdirSync(dir)) {
    const path = join(dir, name)
    if (statSync(path).isDirectory()) {
      removed += walkRemoveBak(path)
    } else if (name.endsWith('.bak') || name.endsWith('.bak.glb')) {
      rmSync(path, { force: true })
      removed += 1
      console.log(`[organize-dist] 删除备份: ${path.replace(distDir, 'dist')}`)
    }
  }
  return removed
}

function countFiles(dir) {
  if (!existsSync(dir)) return { files: 0, bytes: 0 }
  let files = 0
  let bytes = 0
  for (const name of readdirSync(dir)) {
    const path = join(dir, name)
    const st = statSync(path)
    if (st.isDirectory()) {
      const sub = countFiles(path)
      files += sub.files
      bytes += sub.bytes
    } else {
      files += 1
      bytes += st.size
    }
  }
  return { files, bytes }
}

const removed = walkRemoveBak(distDir)
const stats = countFiles(distDir)
const mb = (stats.bytes / 1024 / 1024).toFixed(2)

const readme = `# Water-conservancy 生产构建产物

生成时间: ${new Date().toLocaleString('zh-CN')}

## 目录结构

dist/
├── index.html              # SPA 入口
├── favicon.svg / icons.svg
├── dam-generator.html      # 大坝生成工具页（静态）
├── smart-dam-twin.html     # 数字孪生独立页（静态）
├── assets/
│   ├── js/                 # 脚本（含 vendor 分包）
│   ├── css/                # 样式
│   └── img/                # 图片资源
├── models/                 # 3D 模型（GLB）
└── textures/               # 纹理贴图

## 部署

将 dist 目录整体上传至静态服务器根目录，配置 SPA 回退到 index.html。

## 本地预览

npm run preview

---
文件数: ${stats.files}  |  总体积: ${mb} MB  |  清理备份: ${removed} 个
`

writeFileSync(join(distDir, 'README.txt'), readme, 'utf8')
console.log(`[organize-dist] 完成 · ${stats.files} 个文件 · ${mb} MB · 已写入 dist/README.txt`)
