import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const apiTarget = process.env.VITE_API_PROXY_TARGET || env.VITE_API_PROXY_TARGET || 'http://127.0.0.1:8001'
  const apiBase = env.VITE_API_BASE_URL || ''
  if (mode === 'app' && !/^https:\/\//i.test(apiBase)) {
    throw new Error('App 构建必须设置 HTTPS 的 VITE_API_BASE_URL，例如 https://example.com/api/v1')
  }

  return {
    plugins: [vue()],
    server: {
      proxy: {
        '/api': {
          target: apiTarget,
          changeOrigin: true,
        },
      },
    },
  }
})
