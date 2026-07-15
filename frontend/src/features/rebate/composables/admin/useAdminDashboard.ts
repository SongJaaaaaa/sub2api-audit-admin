import { computed, ref } from 'vue'
import { getAdminDashboard } from '../../api/admin'
import type { MetricItem } from '../../components/MetricGrid.vue'
import type { AdminDashboard } from '../../types'
import { apiMessage } from '../../utils/apiError'
import { money } from '../../utils/money'

export function useAdminDashboard() {
  const loading = ref(false)
  const error = ref('')
  const data = ref<AdminDashboard | null>(null)

  const metrics = computed<MetricItem[]>(() => {
    if (!data.value) return []

    return [
      {
        label: '返利用户',
        value: data.value.total_users.toLocaleString('zh-CN'),
        hint: '已进入一级推广体系',
        hintType: 'muted',
      },
      {
        label: '一级推荐关系',
        value: data.value.direct_referral_count.toLocaleString('zh-CN'),
        hint: '仅统计直接推荐',
        hintType: 'muted',
      },
      {
        label: '累计发放返利',
        value: money(data.value.total_rebate_amount),
        hint: `本月 ${money(data.value.month_rebate_amount)}`,
      },
      {
        label: '累计转入额度',
        value: money(data.value.withdrawn_amount),
        hint: '已成功转入 Sub2API',
        hintType: 'muted',
      },
      {
        label: '待审提现',
        value: money(data.value.pending_withdrawal_amount),
        hint: `${data.value.pending_withdrawal_count} 笔待处理`,
        hintType: data.value.pending_withdrawal_count ? 'danger' : 'muted',
      },
      {
        label: '可用返利余额',
        value: money(data.value.available_rebate_amount),
        hint: '用户当前可申请提现',
        hintType: 'muted',
      },
      {
        label: '冻结返利余额',
        value: money(data.value.frozen_rebate_amount),
        hint: '提现处理中的资金',
        hintType: 'muted',
      },
      {
        label: '今日返利',
        value: money(data.value.today_rebate_amount),
        hint: '今日已确认发放',
        hintType: 'muted',
      },
    ]
  })

  async function load() {
    loading.value = true
    error.value = ''
    try {
      data.value = await getAdminDashboard()
    } catch (err) {
      data.value = null
      error.value = apiMessage(err, '读取返利看板失败')
    } finally {
      loading.value = false
    }
  }

  return { data, error, loading, metrics, load }
}
