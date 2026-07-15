<script setup lang="ts">
import { ReloadOutlined } from '@ant-design/icons-vue'
import { computed, onMounted, ref } from 'vue'
import { getAdminDashboard } from '../../api/admin'
import AsyncState from '../../components/AsyncState.vue'
import MetricGrid, { type MetricItem } from '../../components/MetricGrid.vue'
import PageHeader from '../../components/PageHeader.vue'
import StatusTag from '../../components/StatusTag.vue'
import type { AdminDashboard } from '../../types'

const loading = ref(false)
const error = ref('')
const data = ref<AdminDashboard | null>(null)

const metrics = computed<MetricItem[]>(() => {
  if (!data.value) return []
  return [
    { label: '返利用户', value: data.value.total_users.toLocaleString('zh-CN'), tone: 'blue' },
    { label: '一级推荐关系', value: data.value.direct_referral_count.toLocaleString('zh-CN'), tone: 'green' },
    { label: '累计发放返利', value: money(data.value.total_rebate_amount), tone: 'green' },
    { label: '待审核提现', value: `${data.value.pending_withdrawal_count} 笔`, tone: 'orange' },
    { label: '可用返利余额', value: money(data.value.available_rebate_amount), tone: 'blue' },
    { label: '冻结返利余额', value: money(data.value.frozen_rebate_amount), tone: 'orange' },
    { label: '累计转入额度', value: money(data.value.withdrawn_amount), tone: 'green' },
    { label: '本月返利', value: money(data.value.month_rebate_amount), tone: 'blue' },
  ]
})

function money(value: string) {
  return `¥${Number(value || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function apiMessage(err: unknown) {
  return (err as { response?: { data?: { message?: string } } }).response?.data?.message || '读取返利看板失败'
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await getAdminDashboard()
  } catch (err) {
    data.value = null
    error.value = apiMessage(err)
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="rebatePage">
    <PageHeader title="推广返利看板">
      <template #actions>
        <a-button :loading="loading" @click="load">
          <template #icon><ReloadOutlined /></template>
          刷新
        </a-button>
      </template>
    </PageHeader>

    <AsyncState :loading="loading && !data" :error="error" @retry="load">
      <template v-if="data">
        <MetricGrid :items="metrics" />

        <section class="rebateSection">
          <div class="rebateSectionHeader">
            <h2>最近返利</h2>
            <span class="rebateMuted">今日 {{ money(data.today_rebate_amount) }}</span>
          </div>
          <div class="rebateTable">
            <a-table
              row-key="id"
              size="middle"
              :data-source="data.recent_rebates"
              :pagination="false"
              :scroll="{ x: 850 }"
            >
              <a-table-column title="时间" data-index="created_at" key="created_at" :width="175" />
              <a-table-column title="充值用户" key="payer" :width="230">
                <template #default="{ record }">
                  <div class="rebateUserCell">
                    <strong>{{ record.payer_email || `用户 #${record.payer_user_id}` }}</strong>
                    <span>ID {{ record.payer_user_id }}</span>
                  </div>
                </template>
              </a-table-column>
              <a-table-column title="返利类型" key="type" :width="120">
                <template #default="{ record }">{{ record.type === 'milestone' ? '初始里程碑' : '后续台阶' }}</template>
              </a-table-column>
              <a-table-column title="充值金额" key="source_amount" align="right" :width="130">
                <template #default="{ record }">{{ money(record.source_amount) }}</template>
              </a-table-column>
              <a-table-column title="返利金额" key="rebate_amount" align="right" :width="130">
                <template #default="{ record }"><span class="rebateAmount">{{ money(record.rebate_amount) }}</span></template>
              </a-table-column>
            </a-table>
          </div>
        </section>

        <section class="rebateSection">
          <div class="rebateSectionHeader">
            <h2>最近提现</h2>
            <span class="rebateMuted">待审核 {{ money(data.pending_withdrawal_amount) }}</span>
          </div>
          <div class="rebateTable">
            <a-table
              row-key="id"
              size="middle"
              :data-source="data.recent_withdrawals"
              :pagination="false"
              :scroll="{ x: 800 }"
            >
              <a-table-column title="申请时间" data-index="created_at" :width="175" />
              <a-table-column title="申请单号" data-index="request_no" :width="210" />
              <a-table-column title="用户" key="user" :width="230">
                <template #default="{ record }">{{ record.user_email || `用户 #${record.user_id}` }}</template>
              </a-table-column>
              <a-table-column title="金额" key="amount" align="right" :width="130">
                <template #default="{ record }"><span class="rebateAmount">{{ money(record.amount) }}</span></template>
              </a-table-column>
              <a-table-column title="状态" key="status" :width="100">
                <template #default="{ record }"><StatusTag :status="record.status" /></template>
              </a-table-column>
            </a-table>
          </div>
        </section>
      </template>
    </AsyncState>
  </div>
</template>
