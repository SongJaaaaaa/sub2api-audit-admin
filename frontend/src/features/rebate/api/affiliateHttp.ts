import axios from 'axios'

export const affiliateHttp = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  timeout: 15000,
  headers: {
    Accept: 'application/json',
  },
})

affiliateHttp.interceptors.request.use((config) => {
  const token = localStorage.getItem('affiliateToken')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

affiliateHttp.interceptors.response.use(
  (res) => res.data,
  (err) => {
    if (err.response?.status === 401) {
      localStorage.removeItem('affiliateToken')
      localStorage.removeItem('affiliateInfo')
      if (window.location.pathname !== '/affiliate/login') {
        const redirect = encodeURIComponent(`${window.location.pathname}${window.location.search}`)
        window.location.assign(`/affiliate/login?redirect=${redirect}`)
      }
    }
    return Promise.reject(err)
  },
)
