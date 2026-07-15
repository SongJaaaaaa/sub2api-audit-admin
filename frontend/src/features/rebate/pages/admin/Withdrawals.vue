<script setup lang="ts">
import { CheckOutlined, ClockCircleOutlined, ReloadOutlined, SearchOutlined, StopOutlined } from '@ant-design/icons-vue'
import type { TablePaginationConfig } from 'ant-design-vue'
import { Modal, message } from 'ant-design-vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { approveWithdrawal, getAdminWithdrawals, rejectWithdrawal, retryWithdrawal } from '../../api/admin'
import AsyncState from '../../components/AsyncState.vue'
import StatusTag from '../../components/StatusTag.vue'
import type { RebateWithdrawal, WithdrawalStatus } from '../../types'

const loading = ref(false)
const error = ref('')
const items = ref<RebateWithdrawal[]>([])
const keyword = ref('')
const status = ref<WithdrawalStatus | ''>('pending')
const actionIds = ref<number[]>([])
const rejectOpen = ref(false)
const rejectTarget = ref<RebateWithdrawal | null>(null)
const rejectReason = ref('')
const rejecting = ref(false)
const page = reactive({ current: 1, pageSize: 20, total: 0 })
const statusTabs: { label: string; value: WithdrawalStatus | '' }[] = [
  { label: '待审核', value: 'pending' },
  { label: '处理中', value: 'processing' },
  { label: '已到账', value: 'succeeded' },
  { label: '已拒绝', value: 'rejected' },
  { label: '异常', value: 'exception' },
  { label: '全部', value: '' },
]
const pageAmount = computed(() => formatMoney(items.value.reduce((sum, item) => sum + toCents(item.amount), 0n)))

function money(value: string) {
  return formatMoney(toCents(value))
}

function toCents(value: string) {
  const raw = String(value || '0').trim()
  const negative = raw.startsWith('-')
  const [whole = '0', decimal = ''] = raw.replace(/^[+-]/, '').split('.')
  const cents = BigInt(whole || '0') * 100n + BigInt(`${decimal}00`.slice(0, 2))
  return negative ? -cents : cents
}

function formatMoney(value: bigint) {
  const negative = value < 0n
  const absolute = negative ? -value : value
  const whole = (absolute / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',')
  const decimal = (absolute % 100n).toString().padStart(2, '0')
  return `${negative ? '-' : ''}¥${whole}.${decimal}`
}

function apiMessage(err: unknown, fallback: string) {
  return (err as { response?: { data?: { message?: string } } }).response?.data?.message || fallback
}

function setAction(id: number, active: boolean) {
  actionIds.value = active ? [...actionIds.value, id] : actionIds.value.filter((item) => item !== id)
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
    page.total = res.total
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
  if (status.value === value) return
  status.value = value
  search()
}

function tableChange(pager: TablePaginationConfig) {
  page.current = pager.current || 1
  page.pageSize = pager.pageSize || 20
  load()
}

function approve(row: RebateWithdrawal) {
  Modal.confirm({
    title: '确认通过提现申请？',
    content: `${row.user_email || `用户 #${row.user_id}`} 将转入 ${money(row.quota_amount)} Sub2API 额度。`,
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
    content: `系统将使用原请求号 ${row.request_no} 继续处理。`,
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

function rowClass(row: RebateWithdrawal) {
  return row.status === 'exception' ? 'withdrawRowException' : row.status === 'pending' ? 'withdrawRowPending' : ''
}

onMounted(load)
</script>

<template>
  <div class="rebatePage withdrawalPage">
    <header class="adminPageHead">
      <div>
        <span class="pageEyebrow">资金审核</span>
        <h1>提现审核</h1>
        <p>审核返利转入 Sub2API 额度的申请</p>
      </div>
      <a-button :loading="loading" @click="load">
        <template #icon><ReloadOutlined /></template>
        刷新队列
      </a-button>
    </header>

    <section class="auditOverview">
      <div class="auditIntro">
        <span>审核队列</span>
        <h2>提现申请处理</h2>
        <p>通过、拒绝或重新处理异常申请</p>
      </div>
      <article class="queueMetric">
        <div class="queueTopline">
          <ClockCircleOutlined />
          <span>{{ statusTabs.find((item) => item.value === status)?.label || '全部' }}</span>
        </div>
        <strong>{{ page.total }}</strong>
        <small>条申请 · 本页 {{ pageAmount }}</small>
      </article>
    </section>

    <section class="filterPanel">
      <nav class="statusTabs" aria-label="提现状态">
        <button
          v-for="item in statusTabs"
          :key="item.value || 'all'"
          type="button"
          :class="{ active: status === item.value }"
          @click="changeStatus(item.value)"
        >
          {{ item.label }}
          <span v-if="status === item.value">{{ page.total }}</span>
        </button>
      </nav>
      <a-input-search
        v-model:value="keyword"
        allow-clear
        placeholder="申请单号、邮箱或用户 ID"
        class="withdrawSearch"
        @search="search"
      >
        <template #enterButton><SearchOutlined /></template>
      </a-input-search>
    </section>

    <section class="adminPanel">
      <div class="panelHeader">
        <div>
          <h2>申请列表</h2>
          <p>共 {{ page.total }} 条符合当前条件的记录</p>
        </div>
        <span class="liveState">当前状态</span>
      </div>
      <AsyncState :loading="loading && items.length === 0" :error="error" :empty="!loading && items.length === 0" @retry="load">
        <div class="rebateTable">
          <a-table
            row-key="id"
            size="small"
            :loading="loading"
            :data-source="items"
            :pagination="{ current: page.current, pageSize: page.pageSize, total: page.total, showSizeChanger: true }"
            :scroll="{ x: 1280 }"
            :row-class-name="rowClass"
            @change="tableChange"
          >
            <a-table-column title="申请时间" data-index="created_at" :width="175" />
            <a-table-column title="申请单号" data-index="request_no" :width="220" />
            <a-table-column title="用户" key="user" :width="240">
              <template #default="{ record }">
                <div class="rebateUserCell">
                  <strong>{{ record.user_email || `用户 #${record.user_id}` }}</strong>
                  <span>ID {{ record.user_id }}</span>
                </div>
              </template>
            </a-table-column>
            <a-table-column title="扣除返利" key="amount" align="right" :width="130">
              <template #default="{ record }"><span class="rebateAmount">{{ money(record.amount) }}</span></template>
            </a-table-column>
            <a-table-column title="转入额度" key="quota_amount" align="right" :width="130">
              <template #default="{ record }">{{ money(record.quota_amount) }}</template>
            </a-table-column>
            <a-table-column title="状态" key="status" :width="105">
              <template #default="{ record }"><StatusTag :status="record.status" /></template>
            </a-table-column>
            <a-table-column title="结果" key="result" :width="240">
              <template #default="{ record }">
                <span class="rebateWrapCell">{{ record.reject_reason || record.error_message || '--' }}</span>
              </template>
            </a-table-column>
            <a-table-column title="操作" key="action" fixed="right" :width="190">
              <template #default="{ record }">
                <div class="rebateActions">
                  <template v-if="record.status === 'pending'">
                    <a-button type="primary" size="small" :loading="actionIds.includes(record.id)" @click="approve(record)">
                      <template #icon><CheckOutlined /></template>
                      通过
                    </a-button>
                    <a-button danger size="small" :disabled="actionIds.includes(record.id)" @click="openReject(record)">
                      <template #icon><StopOutlined /></template>
                      拒绝
                    </a-button>
                  </template>
                  <a-button v-else-if="record.status === 'exception'" size="small" :loading="actionIds.includes(record.id)" @click="retry(record)">
                    <template #icon><ReloadOutlined /></template>
                    重试
                  </a-button>
                  <span v-else class="rebateMuted">--</span>
                </div>
              </template>
            </a-table-column>
          </a-table>
        </div>
      </AsyncState>
    </section>

    <a-modal
      v-model:open="rejectOpen"
      title="拒绝提现"
      ok-text="确认拒绝"
      cancel-text="取消"
      :confirm-loading="rejecting"
      @ok="submitReject"
    >
      <a-form layout="vertical">
        <a-form-item label="拒绝原因" required>
          <a-textarea v-model:value="rejectReason" :rows="4" :maxlength="500" show-count placeholder="请输入拒绝原因" />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<style scoped>
.withdrawalPage {
  gap: 24px;
}

.adminPageHead {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
}

.pageEyebrow {
  display: block;
  margin-bottom: 4px;
  color: #4648d4;
  font-size: 12px;
  font-weight: 700;
  line-height: 18px;
}

.adminPageHead h1 {
  margin: 0;
  color: var(--heading);
  font-size: 28px;
  line-height: 38px;
  letter-spacing: 0;
}

.adminPageHead p,
.auditIntro p,
.panelHeader p {
  margin: 3px 0 0;
  color: var(--muted);
  font-size: 13px;
  line-height: 20px;
}

.auditOverview {
  display: grid;
  min-width: 0;
  grid-template-columns: minmax(0, 1fr) 300px;
  gap: 20px;
}

.auditIntro {
  display: flex;
  min-height: 154px;
  padding: 28px 30px;
  justify-content: center;
  flex-direction: column;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface);
  box-shadow: 0 1px 3px rgb(15 23 42 / 4%);
}

.auditIntro > span {
  color: #64748b;
  font-size: 11px;
  font-weight: 700;
  line-height: 18px;
}

.auditIntro h2 {
  margin: 4px 0 0;
  color: var(--heading);
  font-size: 24px;
  line-height: 34px;
  letter-spacing: 0;
}

.queueMetric {
  display: flex;
  min-width: 0;
  min-height: 154px;
  padding: 24px;
  justify-content: center;
  flex-direction: column;
  border-radius: 8px;
  background: #4648d4;
  color: #fff;
  box-shadow: 0 8px 20px rgb(70 72 212 / 18%);
}

.queueTopline {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  font-size: 18px;
}

.queueTopline span {
  padding: 3px 10px;
  border-radius: 999px;
  background: rgb(255 255 255 / 18%);
  font-size: 11px;
  font-weight: 700;
}

.queueMetric strong {
  margin-top: 6px;
  overflow-wrap: anywhere;
  font-size: 34px;
  font-variant-numeric: tabular-nums;
  line-height: 42px;
  letter-spacing: 0;
}

.queueMetric small {
  margin-top: 2px;
  color: rgb(255 255 255 / 78%);
  font-size: 12px;
}

.filterPanel {
  display: flex;
  min-width: 0;
  align-items: flex-end;
  justify-content: space-between;
  gap: 18px;
  border-bottom: 1px solid var(--border);
}

.statusTabs {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 4px;
  overflow-x: auto;
}

.statusTabs button {
  position: relative;
  display: inline-flex;
  min-height: 42px;
  padding: 0 12px;
  align-items: center;
  gap: 6px;
  border: 0;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  font: inherit;
  font-size: 13px;
  white-space: nowrap;
}

.statusTabs button::after {
  position: absolute;
  right: 10px;
  bottom: -1px;
  left: 10px;
  height: 2px;
  background: transparent;
  content: '';
}

.statusTabs button.active {
  color: #4648d4;
  font-weight: 700;
}

.statusTabs button.active::after {
  background: #4648d4;
}

.statusTabs button span {
  display: inline-flex;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  background: #eeedff;
  color: #4648d4;
  font-size: 10px;
}

.withdrawSearch {
  width: min(100%, 340px);
  padding-bottom: 8px;
}

.adminPanel {
  min-width: 0;
  padding: 0 20px 16px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface);
  box-shadow: 0 1px 3px rgb(15 23 42 / 4%);
}

.panelHeader {
  display: flex;
  min-height: 76px;
  padding: 16px 0;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  border-bottom: 1px solid var(--border);
}

.panelHeader h2 {
  margin: 0;
  color: var(--heading);
  font-size: 17px;
  line-height: 25px;
  letter-spacing: 0;
}

.liveState {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  color: #059669;
  font-size: 11px;
  font-weight: 700;
}

.liveState i {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #10b981;
}

:deep(.ant-table-wrapper .ant-table-thead > tr > th) {
  padding: 11px 14px;
  background: #f2f4f6;
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
}

:deep(.ant-table-wrapper .ant-table-tbody > tr > td) {
  padding: 12px 14px;
  color: var(--text);
  font-size: 13px;
}

:deep(.ant-table-wrapper .withdrawRowException > td:first-child) {
  box-shadow: inset 3px 0 #ef4444;
}

:deep(.ant-table-wrapper .withdrawRowPending > td:first-child) {
  box-shadow: inset 3px 0 #f59e0b;
}

:deep(.ant-tag) {
  margin-inline-end: 0;
  border: 0;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
}

:deep(.ant-table-pagination.ant-pagination) {
  margin: 16px 0 0;
}

@media (max-width: 900px) {
  .auditOverview {
    grid-template-columns: minmax(0, 1fr) 240px;
  }

  .filterPanel {
    align-items: stretch;
    flex-direction: column-reverse;
  }

  .withdrawSearch {
    width: 100%;
    padding-bottom: 0;
  }
}

@media (max-width: 760px) {
  .withdrawalPage {
    gap: 16px;
  }

  .adminPageHead {
    align-items: stretch;
    flex-direction: column;
  }

  .adminPageHead h1 {
    font-size: 24px;
    line-height: 34px;
  }

  .adminPageHead .ant-btn {
    width: 100%;
  }

  .auditOverview {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .auditIntro,
  .queueMetric {
    min-height: 130px;
    padding: 20px;
  }

  .adminPanel {
    padding: 0 12px 12px;
    border-radius: 8px;
  }

  .panelHeader {
    min-height: 68px;
  }
}
</style>
