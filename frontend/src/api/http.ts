import axios from 'axios'
import { clearTokenStorage, getMemoryToken } from '../app/services/tokenStorage'
import { getApiBaseUrl } from '../app/services/platform'

export const http = axios.create({
  baseURL: getApiBaseUrl(),
  timeout: 15000,
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
    if (err.response?.status === 401) {
      void clearTokenStorage()
      window.dispatchEvent(new CustomEvent('auth-expired'))
    }

    return Promise.reject(err)
  },
)
