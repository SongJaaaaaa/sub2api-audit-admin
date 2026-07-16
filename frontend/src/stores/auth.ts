import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { http } from '../api/http'
import type { AdminInfo } from '../api/auth'

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

  function save(nextToken: string, nextAdmin: AdminInfo) {
    token.value = nextToken
    admin.value = nextAdmin
    localStorage.setItem('adminToken', nextToken)
    localStorage.setItem('adminInfo', JSON.stringify(nextAdmin))
  }

  function clear() {
    token.value = ''
    admin.value = null
    localStorage.removeItem('adminToken')
    localStorage.removeItem('adminInfo')
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
    clear()
  }

  return {
    token,
    admin,
    authed,
    save,
    clear,
    fetchMe,
    logout,
  }
})
