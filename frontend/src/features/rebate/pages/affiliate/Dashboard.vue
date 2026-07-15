<script setup lang="ts">
import { ReloadOutlined, WalletOutlined } from '@ant-design/icons-vue'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { getAffiliateDashboard } from '../../api/affiliate'
import AsyncState from '../../components/AsyncState.vue'
import MetricGrid, { type MetricItem } from '../../components/MetricGrid.vue'
import PageHeader from '../../components/PageHeader.vue'
import { useAffiliateAuthStore } from '../../stores/affiliateAuth'
import type { AffiliateDashboard } from '../../types'

const router = useRouter()
const auth = useAffiliateAuthStore()
const loading = ref(false)
const error = ref('')
const data = ref<AffiliateDashboard | null>(null)

const metrics = computed<MetricItem[]>(() => data.value ? [
  { label: '可用返利', value: money(data.value.balance.available_amount), hint: '可发起提现', tone: 'green' },
  { label: '冻结返利', value: money(data.value.balance.frozen_amount), hint: '提现处理中', tone: 'orange' },
  { label: '累计返利', value: money(data.value.balance.total_rebate_amount), hint: '历史累计', tone: 'blue' },
  { label: '直接邀请', value: data.value.direct_count, hint: `已充值 ${data.value.converted_count} 人`, tone: 'blue' },
] : [])

const conversionRate = computed(() => {
  if (!data.value?.direct_count) return '0.0%'
  return `${(data.value.converted_count / data.value.direct_count * 100).toFixed(1)}%`
})

const displayName = computed(() => auth.user?.username || auth.user?.email?.split('@')[0] || '用户')

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
    <PageHeader :title="`欢迎回来，${displayName}`" description="返利余额、邀请表现与最近动态">
      <template #actions>
        <a-button type="primary" @click="router.push('/affiliate/withdrawals')">
          <template #icon><WalletOutlined /></template>
          申请提现
        </a-button>
        <a-button :loading="loading" @click="load">
          <template #icon><ReloadOutlined /></template>
          刷新
        </a-button>
      </template>
    </PageHeader>

    <AsyncState :loading="loading" :error="error" @retry="load">
      <template v-if="data">
        <MetricGrid :items="metrics" />
        <div class="affiliateDashboardGrid">
          <section class="rebateSection">
            <div class="rebateSectionHeader">
              <h2>最近返利</h2>
              <a-button type="link" @click="router.push('/affiliate/rebates')">查看全部</a-button>
            </div>
            <AsyncState :empty="data.recent_rebates.length === 0" empty-text="暂无返利明细">
              <div class="rebateTable">
                <a-table row-key="id" size="middle" :data-source="data.recent_rebates" :pagination="false" :scroll="{ x: 680 }">
                  <a-table-column title="时间" data-index="created_at" :width="175" />
                  <a-table-column title="下级" key="payer" :width="230">
                    <template #default="{ record }">{{ record.payer_email || `用户 #${record.payer_user_id}` }}</template>
                  </a-table-column>
                  <a-table-column title="类型" key="type" :width="120">
                    <template #default="{ record }">{{ record.type === 'milestone' ? '初始里程碑' : '后续台阶' }}</template>
                  </a-table-column>
                  <a-table-column title="返利金额" key="amount" align="right" :width="140">
                    <template #default="{ record }"><span class="rebateAmount">+{{ money(record.rebate_amount) }}</span></template>
                  </a-table-column>
                </a-table>
              </div>
            </AsyncState>
          </section>

          <aside class="rebateSection affiliateInsights">
            <div class="rebateSectionHeader"><h2>推广概览</h2></div>
            <dl>
              <div>
                <dt>充值转化率</dt>
                <dd>{{ conversionRate }}</dd>
              </div>
              <div>
                <dt>已充值下级</dt>
                <dd>{{ data.converted_count }} 人</dd>
              </div>
              <div>
                <dt>下级累计充值</dt>
                <dd>{{ money(data.total_direct_recharge_amount) }}</dd>
              </div>
              <div>
                <dt>待处理提现</dt>
                <dd>{{ money(data.pending_withdrawal_amount) }}</dd>
              </div>
            </dl>
          </aside>
        </div>
      </template>
    </AsyncState>
  </div>
</template>
