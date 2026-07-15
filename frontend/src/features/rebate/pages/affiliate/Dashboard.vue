<script setup lang="ts">
import { ReloadOutlined } from '@ant-design/icons-vue'
import { computed, onMounted, ref } from 'vue'
import { getAffiliateDashboard } from '../../api/affiliate'
import AsyncState from '../../components/AsyncState.vue'
import MetricGrid, { type MetricItem } from '../../components/MetricGrid.vue'
import PageHeader from '../../components/PageHeader.vue'
import type { AffiliateDashboard } from '../../types'

const loading = ref(false)
const error = ref('')
const data = ref<AffiliateDashboard | null>(null)

const metrics = computed<MetricItem[]>(() => data.value ? [
  { label: '可用返利', value: money(data.value.balance.available_amount), tone: 'green' },
  { label: '冻结返利', value: money(data.value.balance.frozen_amount), tone: 'orange' },
  { label: '累计返利', value: money(data.value.balance.total_rebate_amount), tone: 'blue' },
  { label: '直接邀请', value: data.value.direct_count, tone: 'blue' },
  { label: '已充值下级', value: data.value.converted_count, tone: 'green' },
  { label: '下级累计充值', value: money(data.value.total_direct_recharge_amount), tone: 'green' },
] : [])

function money(value: string) {
  return `¥${Number(value || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function apiMessage(err: unknown) {
  return (err as { response?: { data?: { message?: string } } }).response?.data?.message || '读取仪表盘失败'
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await getAffiliateDashboard()
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
    <PageHeader title="仪表盘">
      <template #actions>
        <a-button :loading="loading" @click="load">
          <template #icon><ReloadOutlined /></template>
          刷新
        </a-button>
      </template>
    </PageHeader>

    <AsyncState :loading="loading" :error="error" @retry="load">
      <template v-if="data">
        <MetricGrid :items="metrics" />
        <section class="rebateSection">
          <div class="rebateSectionHeader">
            <h2>最近返利</h2>
            <span class="rebateMuted">待处理提现 {{ money(data.pending_withdrawal_amount) }}</span>
          </div>
          <AsyncState :empty="data.recent_rebates.length === 0" empty-text="暂无返利明细">
            <div class="rebateTable">
              <a-table row-key="id" size="middle" :data-source="data.recent_rebates" :pagination="false" :scroll="{ x: 760 }">
                <a-table-column title="时间" data-index="created_at" :width="175" />
                <a-table-column title="下级" key="payer" :width="230">
                  <template #default="{ record }">{{ record.payer_email || `用户 #${record.payer_user_id}` }}</template>
                </a-table-column>
                <a-table-column title="类型" key="type" :width="120">
                  <template #default="{ record }">{{ record.type === 'milestone' ? '初始里程碑' : '后续台阶' }}</template>
                </a-table-column>
                <a-table-column title="返利金额" key="amount" align="right" :width="140">
                  <template #default="{ record }"><span class="rebateAmount">{{ money(record.rebate_amount) }}</span></template>
                </a-table-column>
              </a-table>
            </div>
          </AsyncState>
        </section>
      </template>
    </AsyncState>
  </div>
</template>
