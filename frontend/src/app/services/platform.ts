import { Capacitor } from '@capacitor/core'

const envMode = String(import.meta.env.VITE_APP_MODE || '').toLowerCase()

export const isNativeApp = Capacitor.isNativePlatform()
export const isAppMode = isNativeApp || import.meta.env.MODE === 'app' || envMode === 'app'

export function getApiBaseUrl() {
  return String(import.meta.env.VITE_API_BASE_URL || '/api/v1').replace(/\/$/, '')
}

export function assertAppApiConfig() {
  if (isAppMode && import.meta.env.PROD && !/^https:\/\//i.test(getApiBaseUrl())) {
    throw new Error('App 生产构建必须配置 HTTPS 的 VITE_API_BASE_URL')
  }
}

export function isExternalUrl(value: string) {
  return /^https?:\/\//i.test(value)
}
