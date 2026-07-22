import { Capacitor } from '@capacitor/core'

const envMode = String(import.meta.env.VITE_APP_MODE || '').toLowerCase()
const appApiUrl = 'https://autsub2.hyojooapi.top/api/v1'
const nav = navigator as Navigator & { standalone?: boolean }

export const isNativeApp = Capacitor.isNativePlatform()
export const isPwaApp = window.matchMedia('(display-mode: standalone)').matches || nav.standalone === true
export const isAppMode = isNativeApp || isPwaApp || import.meta.env.MODE === 'app' || envMode === 'app'

// 开发专用开关（仅在 .env.local 中启用，不影响真机打包/生产）：
// 开启后 app 模式在浏览器里改走相对路径 /api/v1，由 Vite dev 代理转发到线上后端，
// 避免浏览器直连生产地址被 CORS 拦截。真机（Capacitor）始终直连线上地址。
const useDevProxy =
  import.meta.env.DEV &&
  !isNativeApp &&
  String(import.meta.env.VITE_APP_USE_PROXY || '').toLowerCase() === 'true'

export function getApiBaseUrl() {
  if (useDevProxy) return '/api/v1'
  return isAppMode ? appApiUrl : '/api/v1'
}

export function assertAppApiConfig() {
  // App API 地址随安装包固定发布。
}

export function isExternalUrl(value: string) {
  return /^https?:\/\//i.test(value)
}
