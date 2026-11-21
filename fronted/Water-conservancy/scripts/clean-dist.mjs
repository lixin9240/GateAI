import { rmSync, existsSync } from 'node:fs'
import { resolve } from 'node:path'

const distDir = resolve(process.cwd(), 'dist')

if (existsSync(distDir)) {
  rmSync(distDir, { recursive: true, force: true })
  console.log('[clean-dist] 已删除 dist/')
} else {
  console.log('[clean-dist] dist/ 不存在，跳过')
}
