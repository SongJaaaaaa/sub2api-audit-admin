import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { getAffiliateMe, loginAffiliate, logoutAffiliate } from '../api/affiliate'
import type { AffiliateUser } from '../types'

const savedUser = () => {
  const raw = localStorage.getItem('affiliateInfo')
  return raw ? (JSON.parse(raw) as AffiliateUser) : null
}

export const useAffiliateAuthStore = defineStore('affiliateAuth', () => {
  const token = ref(localStorage.getItem('affiliateToken') || '')
  const user = ref<AffiliateUser | null>(savedUser())
  const authed = computed(() => token.value !== '')

  function save(nextToken: string, nextUser: AffiliateUser) {
    token.value = nextToken
    user.value = nextUser
    localStorage.setItem('affiliateToken', nextToken)
    localStorage.setItem('affiliateInfo', JSON.stringify(nextUser))
  }

  function clear() {
    token.value = ''
    user.value = null
    localStorage.removeItem('affiliateToken')
    localStorage.removeItem('affiliateInfo')
  }

  async function login(account: string, password: string) {
    const res = await loginAffiliate(account, password)
    save(res.token, res.user)
  }

  async function fetchMe() {
    const res = await getAffiliateMe()
    user.value = res.user
    localStorage.setItem('affiliateInfo', JSON.stringify(res.user))
  }

  async function logout() {
    if (token.value) {
      try {
        await logoutAffiliate()
      } catch {
        // 本地退出不能被已失效 token 阻断。
      }
    }
    clear()
  }

  return { token, user, authed, login, fetchMe, logout, clear }
})
