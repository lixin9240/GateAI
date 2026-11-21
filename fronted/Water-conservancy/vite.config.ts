import { fileURLToPath, URL } from 'node:url'
import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const apiProxyTarget =
    env.VITE_DEV_PROXY_TARGET ||
    env.VITE_DEV_API_PROXY ||
    'http://47.108.169.152:8089'

  return {
    plugins: [vue()],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    server: {
      port: 5174,
      strictPort: true,
      proxy: {
        '/api': {
          target: apiProxyTarget,
          changeOrigin: true,
          secure: false,
          ws: true,
        },
        '/ws': {
          target: apiProxyTarget,
          changeOrigin: true,
          secure: false,
          ws: true,
        },
      },
    },
    css: {
      preprocessorOptions: {
        scss: {
          additionalData: `@use "@/assets/styles/design-tokens.scss" as *;`,
        },
      },
    },
    build: {
      outDir: 'dist',
      emptyOutDir: true,
      assetsDir: 'assets',
      chunkSizeWarningLimit: 800,
      rollupOptions: {
        output: {
          entryFileNames: 'assets/js/[name]-[hash].js',
          chunkFileNames: 'assets/js/[name]-[hash].js',
          assetFileNames: (info) => {
            const ext = info.name?.split('.').pop()?.toLowerCase() ?? ''
            if (ext === 'css') return 'assets/css/[name]-[hash][extname]'
            if (['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico'].includes(ext)) {
              return 'assets/img/[name]-[hash][extname]'
            }
            if (['woff', 'woff2', 'eot', 'ttf', 'otf'].includes(ext)) {
              return 'assets/fonts/[name]-[hash][extname]'
            }
            return 'assets/[name]-[hash][extname]'
          },
          manualChunks(id) {
            if (!id.includes('node_modules')) return
            if (id.includes('three')) return 'vendor-three'
            if (id.includes('echarts') || id.includes('zrender')) return 'vendor-echarts'
            if (id.includes('element-plus')) return 'vendor-element-plus'
            if (id.includes('vue') || id.includes('pinia') || id.includes('vue-router')) {
              return 'vendor-vue'
            }
          },
        },
      },
    },
  }
})
