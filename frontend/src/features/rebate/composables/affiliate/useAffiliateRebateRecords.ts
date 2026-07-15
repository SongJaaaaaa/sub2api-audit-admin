import type { TablePaginationConfig } from 'ant-design-vue'
import { computed, reactive, ref } from 'vue'
import { getAffiliateRebateRecords } from '../../api/affiliate'
import type { RebateRecord } from '../../types'
import { apiMessage } from '../../utils/apiError'
import { formatCents, sumMoney } from '../../utils/money'

export type RebateRecordType = '' | 'milestone' | 'stage'

function chinaMonth() {
  const parts = new Intl.DateTimeFormat('zh-CN', {
    timeZone: 'Asia/Shanghai',
    year: 'numeric',
    month: '2-digit',
  }).formatToParts(new Date())
  const part = (type: 'year' | 'month') => parts.find((item) => item.type === type)?.value || ''
  return `${part('year')}-${part('month')}`
}

export function useAffiliateRebateRecords() {
  const loading = ref(false)
  const error = ref('')
  const type = ref<RebateRecordType>('')
  const items = ref<RebateRecord[]>([])
  const page = reactive({ current: 1, pageSize: 20, total: 0 })

  const metrics = computed(() => {
    const month = chinaMonth()
    const monthItems = items.value.filter((item) => item.created_at?.startsWith(month))

    return [
      { label: '本页本月返利', value: formatCents(sumMoney(monthItems, (item) => item.rebate_amount)), hint: `本页本月 ${monthItems.length} 条` },
      { label: '本页累计返利', value: formatCents(sumMoney(items.value, (item) => item.rebate_amount)), hint: `当前页 ${items.value.length} 条合计`, hintType: 'muted' as const },
      { label: '本页下级充值', value: formatCents(sumMoney(items.value, (item) => item.source_amount)), hint: `当前页 ${items.value.length} 条来源合计`, hintType: 'muted' as const },
    ]
  })

  async function load() {
    loading.value = true
    error.value = ''
    try {
      const res = await getAffiliateRebateRecords({ page: page.current, page_size: page.pageSize, type: type.value })
      items.value = res.items
      Object.assign(page, { current: res.page, pageSize: res.page_size, total: res.total })
    } catch (err) {
      items.value = []
      error.value = apiMessage(err, '读取返利明细失败')
    } finally {
      loading.value = false
    }
  }

  function search() {
    page.current = 1
    load()
  }

  function tableChange(pager: TablePaginationConfig) {
    page.current = pager.current || 1
    page.pageSize = pager.pageSize || 20
    load()
  }

  return { loading, error, type, items, page, metrics, load, search, tableChange }
}
