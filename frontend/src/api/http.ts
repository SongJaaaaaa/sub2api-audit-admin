import axios from 'axios'
import { clearTokenStorage, getMemoryToken } from '../app/services/tokenStorage'
import { getApiBaseUrl } from '../app/services/platform'

export const http = axios.create({
  baseURL: getApiBaseUrl(),
  timeout: 45000,
  headers: {
    Accept: 'application/json',
  },
})

http.interceptors.request.use((config) => {
  const token = getMemoryToken()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

http.interceptors.response.use(
  (res) => res.data,
  (err) => {
    const method = String(err.config?.method || 'GET').toUpperCase()
    const url = String(err.config?.url || '')
    const status = err.response?.status ?? 'NETWORK'
    const raw = typeof err.response?.data?.message === 'string' ? err.response.data.message : err.message
    const msg = String(raw || 'Request failed').split('\n')[0].slice(0, 300)
    console.error(`[API] ${method} ${url} -> ${status}: ${msg}`)

    if (err.response?.status === 401) {
      void clearTokenStorage()
      window.dispatchEvent(new CustomEvent('auth-expired'))
    }

    return Promise.reject(err)
  },
)
