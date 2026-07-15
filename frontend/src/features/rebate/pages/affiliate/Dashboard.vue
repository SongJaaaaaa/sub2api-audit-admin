<script setup lang="ts">
import { ReloadOutlined } from '@ant-design/icons-vue'
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { getAffiliateDashboard } from '../../api/affiliate'
import AsyncState from '../../components/AsyncState.vue'
import RecentRebatesCard from '../../components/affiliate/dashboard/RecentRebatesCard.vue'
import RebateTrendChart from '../../components/dashboard/RebateTrendChart.vue'
import MetricGrid, { type MetricItem } from '../../components/MetricGrid.vue'
import PageHeader from '../../components/PageHeader.vue'
import { useAffiliateAuthStore } from '../../stores/affiliateAuth'
import type { AffiliateDashboard } from '../../types'
import { apiMessage } from '../../utils/apiError'
import { money } from '../../utils/money'

const router = useRouter()
const auth = useAffiliateAuthStore()
const loading = ref(false)
const error = ref('')
const data = ref<AffiliateDashboard | null>(null)

const metrics = computed<MetricItem[]>(() => data.value ? [
  { label: '可提现余额', value: money(data.value.balance.available_amount), hint: '可发起提现' },
  { label: '下级累计充值', value: money(data.value.total_direct_recharge_amount), hint: '一级下级贡献', hintType: 'muted' },
  { label: '累计返利', value: money(data.value.balance.total_rebate_amount), hint: '历史累计', hintType: 'muted' },
  { label: '直接邀请', value: data.value.direct_count, hint: `已充值 ${data.value.converted_count} 人` },
  { label: '待处理提现', value: money(data.value.pending_withdrawal_amount), hint: '审核或到账处理中', hintType: 'muted' },
] : [])

const displayName = computed(() => auth.user?.username || auth.user?.email?.split('@')[0] || '用户')

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await getAffiliateDashboard()
  } catch (err) {
    data.value = null
    error.value = apiMessage(err, '读取仪表盘失败')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="rebatePage">
    <PageHeader :title="`欢迎回来，${displayName}`" description="查看返利余额、邀请表现和最近动态。">
      <template #actions>
        <a-button :loading="loading" @click="load">
          <template #icon><ReloadOutlined /></template>
          刷新
        </a-button>
        <a-button type="primary" @click="router.push('/affiliate/withdrawals')">
          申请提现
        </a-button>
      </template>
    </PageHeader>

    <AsyncState :loading="loading" :error="error" @retry="load">
      <template v-if="data">
        <MetricGrid :items="metrics" />
        <RebateTrendChart :items="data.rebate_trend" :height="370" />
        <RecentRebatesCard :items="data.recent_rebates" />
      </template>
    </AsyncState>
  </div>
</template>
