<script setup lang="ts">
import { CheckOutlined, ReloadOutlined, SearchOutlined, StopOutlined } from '@ant-design/icons-vue'
import type { TablePaginationConfig } from 'ant-design-vue'
import { Modal, message } from 'ant-design-vue'
import { onMounted, reactive, ref } from 'vue'
import { approveWithdrawal, getAdminWithdrawals, rejectWithdrawal, retryWithdrawal } from '../../api/admin'
import AsyncState from '../../components/AsyncState.vue'
import PageHeader from '../../components/PageHeader.vue'
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

function money(value: string) {
  return `¥${Number(value || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
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

onMounted(load)
</script>

<template>
  <div class="rebatePage">
    <PageHeader title="提现审核">
      <template #actions>
        <a-button :loading="loading" @click="load">
          <template #icon><ReloadOutlined /></template>
          刷新
        </a-button>
      </template>
    </PageHeader>

    <section class="rebateSection">
      <div class="rebateFilters">
        <div class="rebateActions">
          <a-select v-model:value="status" style="width: 150px" @change="search">
            <a-select-option value="">全部状态</a-select-option>
            <a-select-option value="pending">待审核</a-select-option>
            <a-select-option value="processing">处理中</a-select-option>
            <a-select-option value="succeeded">已到账</a-select-option>
            <a-select-option value="rejected">已拒绝</a-select-option>
            <a-select-option value="exception">异常</a-select-option>
          </a-select>
          <a-input-search v-model:value="keyword" allow-clear placeholder="申请单号、邮箱或用户 ID" style="width: min(100%, 320px)" @search="search">
            <template #enterButton><SearchOutlined /></template>
          </a-input-search>
        </div>
        <span class="rebateMuted">共 {{ page.total }} 条</span>
      </div>
    </section>

    <section class="rebateSection">
      <AsyncState :loading="loading && items.length === 0" :error="error" :empty="!loading && items.length === 0" @retry="load">
        <div class="rebateTable">
          <a-table
            row-key="id"
            size="middle"
            :loading="loading"
            :data-source="items"
            :pagination="{ current: page.current, pageSize: page.pageSize, total: page.total, showSizeChanger: true }"
            :scroll="{ x: 1280 }"
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
