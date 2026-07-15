import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import { computed, reactive, ref } from 'vue'
import { createAffiliateWithdrawal, getAffiliateWithdrawals } from '../../api/affiliate'
import type { AffiliateWithdrawalsRes } from '../../types'
import { apiMessage } from '../../utils/apiError'
import { compareMoney, money, multiplyMoney } from '../../utils/money'

const moneyInput = /^\d+(?:\.\d{0,2})?$/

export function useAffiliateWithdrawals() {
  const loading = ref(false)
  const creating = ref(false)
  const error = ref('')
  const data = ref<AffiliateWithdrawalsRes | null>(null)
  const amount = ref('')
  const page = reactive({ current: 1, pageSize: 20, total: 0 })

  const metrics = computed(() => data.value ? [
    { label: '可提现余额', value: money(data.value.balance.available_amount), hint: '可发起提现' },
    { label: '冻结金额', value: money(data.value.balance.frozen_amount), hint: '审核或转入处理中', hintType: 'muted' as const },
    { label: '已提现', value: money(data.value.balance.withdrawn_amount), hint: '历史累计', hintType: 'muted' as const },
  ] : [])

  const expectedQuota = computed(() => {
    const value = amount.value.trim()
    if (!data.value || !moneyInput.test(value) || compareMoney(value, '0') <= 0) return money('0')
    return multiplyMoney(value, data.value.config.to_api_quota_rate)
  })

  async function load() {
    loading.value = true
    error.value = ''
    try {
      const res = await getAffiliateWithdrawals({ page: page.current, page_size: page.pageSize })
      data.value = res
      Object.assign(page, { current: res.page, pageSize: res.page_size, total: res.total })
    } catch (err) {
      data.value = null
      error.value = apiMessage(err, '读取提现信息失败')
    } finally {
      loading.value = false
    }
  }

  async function submit() {
    const current = data.value
    const value = amount.value.trim()
    if (!current) return
    if (!moneyInput.test(value) || compareMoney(value, '0') <= 0) {
      message.warning('请输入正确的提现金额')
      return
    }
    if (compareMoney(value, current.config.min_amount) < 0) {
      message.warning(`单次最低提现 ${money(current.config.min_amount)}`)
      return
    }
    if (compareMoney(value, current.balance.available_amount) > 0) {
      message.warning('可用返利余额不足')
      return
    }

    creating.value = true
    try {
      const res = await createAffiliateWithdrawal(value)
      message.success(res.message || '提现申请已提交')
      amount.value = ''
      page.current = 1
      await load()
    } catch (err) {
      message.error(apiMessage(err, '提交提现申请失败'))
    } finally {
      creating.value = false
    }
  }

  function tableChange(pager: TablePaginationConfig) {
    page.current = pager.current || 1
    page.pageSize = pager.pageSize || 20
    load()
  }

  return { amount, creating, data, error, expectedQuota, loading, metrics, page, load, submit, tableChange }
}
