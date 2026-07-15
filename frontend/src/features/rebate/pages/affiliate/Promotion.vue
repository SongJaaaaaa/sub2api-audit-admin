<script setup lang="ts">
import { ReloadOutlined } from '@ant-design/icons-vue'
import { computed, onMounted, ref } from 'vue'
import { getAffiliatePromotion } from '../../api/affiliate'
import AsyncState from '../../components/AsyncState.vue'
import InviteLinkCard from '../../components/affiliate/promotion/InviteLinkCard.vue'
import RecentInvitesCard from '../../components/affiliate/promotion/RecentInvitesCard.vue'
import MetricGrid, { type MetricItem } from '../../components/MetricGrid.vue'
import PageHeader from '../../components/PageHeader.vue'
import type { PromotionRes } from '../../types'
import { apiMessage } from '../../utils/apiError'
import { money } from '../../utils/money'

const loading = ref(false)
const error = ref('')
const data = ref<PromotionRes | null>(null)

const metrics = computed<MetricItem[]>(() => data.value ? [
  { label: '可提现余额', value: money(data.value.balance.available_amount), hint: '可发起提现' },
  { label: '直邀人数', value: data.value.direct_count, hint: '直接推广', hintType: 'muted' },
  { label: '团队人数', value: data.value.direct_count, hint: '仅含一级下级', hintType: 'muted' },
  { label: '充值转化率', value: `${data.value.conversion_rate}%`, hint: '付费用户占比' },
  { label: '累计返利', value: money(data.value.balance.total_rebate_amount), hint: '全部已获返利' },
] : [])

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await getAffiliatePromotion()
  } catch (err) {
    data.value = null
    error.value = apiMessage(err, '读取推广数据失败')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="rebatePage">
    <PageHeader title="推广中心" description="分享推广链接，邀请用户，获得一级返利。">
      <template #actions>
        <a-button :loading="loading" @click="load">
          <template #icon><ReloadOutlined /></template>
          刷新
        </a-button>
      </template>
    </PageHeader>

    <AsyncState :loading="loading" :error="error" @retry="load">
      <template v-if="data">
        <InviteLinkCard :invite-url="data.invite_url" :invite-code="data.invite_code" />
        <MetricGrid :items="metrics" />
        <RecentInvitesCard :items="data.items" :total="data.direct_count" />
      </template>
    </AsyncState>
  </div>
</template>
