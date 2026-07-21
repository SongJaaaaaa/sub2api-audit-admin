import { Capacitor } from '@capacitor/core'

const envMode = String(import.meta.env.VITE_APP_MODE || '').toLowerCase()
const appApiUrl = 'https://audit.sjiaa.cc.cd/api/v1'
const nav = navigator as Navigator & { standalone?: boolean }

export const isNativeApp = Capacitor.isNativePlatform()
export const isPwaApp = window.matchMedia('(display-mode: standalone)').matches || nav.standalone === true
export const isAppMode = isNativeApp || isPwaApp || import.meta.env.MODE === 'app' || envMode === 'app'

export function getApiBaseUrl() {
  return isAppMode ? appApiUrl : '/api/v1'
}

export function assertAppApiConfig() {
  // App API 地址随安装包固定发布。
}

export function isExternalUrl(value: string) {
  return /^https?:\/\//i.test(value)
}
