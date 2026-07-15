import { computed, reactive, ref } from 'vue'
import { getAffiliateTeam } from '../../api/affiliate'
import { useAffiliateAuthStore } from '../../stores/affiliateAuth'
import type { TeamMember } from '../../types'
import { apiMessage } from '../../utils/apiError'
import { formatCents, sumMoney } from '../../utils/money'

export function useAffiliateTeam() {
  const auth = useAffiliateAuthStore()
  const loading = ref(false)
  const error = ref('')
  const items = ref<TeamMember[]>([])
  const page = reactive({ current: 1, pageSize: 20, total: 0 })

  const rootName = computed(() => auth.user?.username || auth.user?.email || '当前用户')
  const rootMeta = computed(() => {
    if (auth.user?.username && auth.user.email) return auth.user.email
    return auth.user?.id ? `用户 ID ${auth.user.id}` : '推广账号'
  })
  const pageRecharge = computed(() => formatCents(sumMoney(items.value, (item) => item.total_recharge_amount)))

  async function load() {
    loading.value = true
    error.value = ''
    try {
      const res = await getAffiliateTeam({ page: page.current, page_size: page.pageSize })
      items.value = res.items
      Object.assign(page, { current: res.page, pageSize: res.page_size, total: res.total })
    } catch (err) {
      items.value = []
      error.value = apiMessage(err, '读取团队失败')
    } finally {
      loading.value = false
    }
  }

  function changePage(current: number, pageSize: number) {
    page.current = current
    page.pageSize = pageSize
    load()
  }

  return { error, items, loading, page, pageRecharge, rootMeta, rootName, changePage, load }
}
