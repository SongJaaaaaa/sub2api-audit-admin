import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { http } from '../api/http'

export interface AdminInfo {
  id: number
  name: string
  email: string
  status: string
}

interface LoginRes {
  token: string
  admin: AdminInfo
}

interface MeRes {
  admin: AdminInfo
}

const savedAdmin = () => {
  const raw = localStorage.getItem('adminInfo')
  return raw ? (JSON.parse(raw) as AdminInfo) : null
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('adminToken') || '')
  const admin = ref<AdminInfo | null>(savedAdmin())
  const authed = computed(() => token.value !== '')

  async function login(email: string, password: string) {
    const res = await http.post<unknown, LoginRes>('/auth/login', { email, password })
    token.value = res.token
    admin.value = res.admin
    localStorage.setItem('adminToken', res.token)
    localStorage.setItem('adminInfo', JSON.stringify(res.admin))
  }

  async function fetchMe() {
    const res = await http.get<unknown, MeRes>('/auth/me')
    admin.value = res.admin
    localStorage.setItem('adminInfo', JSON.stringify(res.admin))
  }

  async function logout() {
    if (token.value) {
      try {
        await http.post('/auth/logout')
      } catch {
        // 本地退出不能被已失效 token 阻断。
      }
    }
    token.value = ''
    admin.value = null
    localStorage.removeItem('adminToken')
    localStorage.removeItem('adminInfo')
  }

  return {
    token,
    admin,
    authed,
    login,
    fetchMe,
    logout,
  }
})
