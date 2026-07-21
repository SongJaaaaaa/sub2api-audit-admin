import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { http } from '../api/http'
import type { AdminInfo } from '../api/auth'
import {
  clearTokenStorage,
  getMemoryToken,
  hydrateTokenStorage,
  saveAdminInfo,
  saveToken,
} from '../app/services/tokenStorage'

interface MeRes {
  admin: AdminInfo
}

function parseAdmin(raw: string | null) {
  if (!raw) return null
  try {
    return JSON.parse(raw) as AdminInfo
  } catch {
    return null
  }
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref(getMemoryToken())
  const admin = ref<AdminInfo | null>(null)
  const authed = computed(() => token.value !== '')

  async function hydrate() {
    const saved = await hydrateTokenStorage()
    token.value = saved.token
    admin.value = parseAdmin(saved.adminInfo)
  }

  async function save(nextToken: string, nextAdmin: AdminInfo) {
    token.value = nextToken
    admin.value = nextAdmin
    await saveToken(nextToken, JSON.stringify(nextAdmin))
  }

  async function clear() {
    token.value = ''
    admin.value = null
    await clearTokenStorage()
  }

  async function fetchMe() {
    const res = await http.get<unknown, MeRes>('/auth/me')
    admin.value = res.admin
    await saveAdminInfo(JSON.stringify(res.admin))
  }

  async function logout() {
    if (token.value) {
      try {
        await http.post('/auth/logout')
      } catch {
        // 本地退出不能被已失效 token 阻断。
      }
    }
    await clear()
  }

  return {
    token,
    admin,
    authed,
    hydrate,
    save,
    clear,
    fetchMe,
    logout,
  }
})
