import type { TablePaginationConfig } from 'ant-design-vue'
import { Modal, message } from 'ant-design-vue'
import { computed, reactive, ref } from 'vue'
import { approveWithdrawal, getAdminWithdrawals, rejectWithdrawal, retryWithdrawal } from '../../api/admin'
import type { RebateWithdrawal, WithdrawalStatus } from '../../types'
import { apiMessage } from '../../utils/apiError'
import { formatCents, sumMoney } from '../../utils/money'

export function useAdminWithdrawals() {
  const loading = ref(false)
  const error = ref('')
  const items = ref<RebateWithdrawal[]>([])
  const keyword = ref('')
  const status = ref<WithdrawalStatus | ''>('')
  const actionIds = ref(new Set<number>())
  const rejectOpen = ref(false)
  const rejectTarget = ref<RebateWithdrawal | null>(null)
  const rejectReason = ref('')
  const rejecting = ref(false)
  const page = reactive({ current: 1, pageSize: 20, total: 0 })
  const pageAmount = computed(() => formatCents(sumMoney(items.value, (item) => item.amount)))

  function setAction(id: number, active: boolean) {
    const next = new Set(actionIds.value)
    active ? next.add(id) : next.delete(id)
    actionIds.value = next
  }

  async function load() {
    loading.value = true
    error.value = ''
    try {
      const res = await getAdminWithdrawals({
        page: page.current,
        page_size: page.pageSize,
        status: status.value,
        keyword: keyword.value.trim(),
      })
      items.value = res.items
      Object.assign(page, { current: res.page, pageSize: res.page_size, total: res.total })
    } catch (err) {
      items.value = []
      error.value = apiMessage(err, '读取提现申请失败')
    } finally {
      loading.value = false
    }
  }

  function search() {
    page.current = 1
    load()
  }

  function changeStatus(value: WithdrawalStatus | '') {
    status.value = value
    search()
  }

  function tableChange(pager: TablePaginationConfig) {
    page.current = pager.current || 1
    page.pageSize = pager.pageSize || 20
    load()
  }

  function pageChange(current: number) {
    page.current = current
    load()
  }

  function approve(row: RebateWithdrawal) {
    Modal.confirm({
      title: '确认通过提现申请？',
      content: `${row.user_email || `用户 #${row.user_id}`} 将转入 Sub2API API 额度。`,
      okText: '通过并处理',
      cancelText: '取消',
      async onOk() {
        setAction(row.id, true)
        try {
          const res = await approveWithdrawal(row.id)
          message.success(res.message || '提现已进入处理队列')
          await load()
        } catch (err) {
          message.error(apiMessage(err, '审核提现失败'))
          await load()
          throw err
        } finally {
          setAction(row.id, false)
        }
      },
    })
  }

  function openReject(row: RebateWithdrawal) {
    rejectTarget.value = row
    rejectReason.value = ''
    rejectOpen.value = true
  }

  async function submitReject() {
    const row = rejectTarget.value
    if (!row || !rejectReason.value.trim()) {
      message.warning('请输入拒绝原因')
      return
    }
    rejecting.value = true
    setAction(row.id, true)
    try {
      const res = await rejectWithdrawal(row.id, rejectReason.value.trim())
      message.success(res.message || '提现申请已拒绝')
      rejectOpen.value = false
      await load()
    } catch (err) {
      message.error(apiMessage(err, '拒绝提现失败'))
      await load()
    } finally {
      rejecting.value = false
      setAction(row.id, false)
    }
  }

  function retry(row: RebateWithdrawal) {
    Modal.confirm({
      title: '重新处理异常提现？',
      content: `系统将复用申请单号 ${row.request_no}，不会重复加额。`,
      okText: '重新处理',
      cancelText: '取消',
      async onOk() {
        setAction(row.id, true)
        try {
          const res = await retryWithdrawal(row.id)
          message.success(res.message || '提现已重新进入处理队列')
          await load()
        } catch (err) {
          message.error(apiMessage(err, '重试提现失败'))
          await load()
          throw err
        } finally {
          setAction(row.id, false)
        }
      },
    })
  }

  return {
    loading, error, items, keyword, status, actionIds, rejectOpen, rejectReason,
    rejecting, page, pageAmount, load, search, changeStatus, tableChange,
    pageChange, approve, openReject, submitReject, retry,
  }
}
