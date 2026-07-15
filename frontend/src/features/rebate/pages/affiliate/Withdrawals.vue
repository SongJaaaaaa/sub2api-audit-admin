<script setup lang="ts">
import { ReloadOutlined } from '@ant-design/icons-vue'
import { computed, onMounted } from 'vue'
import AsyncState from '../../components/AsyncState.vue'
import MetricGrid from '../../components/MetricGrid.vue'
import PageHeader from '../../components/PageHeader.vue'
import WithdrawalApplyCard from '../../components/affiliate/withdrawals/WithdrawalApplyCard.vue'
import WithdrawalHistoryCard from '../../components/affiliate/withdrawals/WithdrawalHistoryCard.vue'
import { useAffiliateWithdrawals } from '../../composables/affiliate/useAffiliateWithdrawals'
import { money } from '../../utils/money'

const withdrawals = useAffiliateWithdrawals()
const description = computed(() => withdrawals.data.value
  ? `最低提现金额 ${money(withdrawals.data.value.config.min_amount)}，人工审核后转入 Sub2API API 额度。`
  : 'Sub2API API 额度提现与处理记录。')

onMounted(withdrawals.load)
</script>

<template>
  <div class="rebatePage">
    <PageHeader title="提现管理" :description="description">
      <template #actions>
        <a-button :loading="withdrawals.loading.value" @click="withdrawals.load"><template #icon><ReloadOutlined /></template>刷新</a-button>
      </template>
    </PageHeader>

    <AsyncState :loading="withdrawals.loading.value && !withdrawals.data.value" :error="withdrawals.error.value" @retry="withdrawals.load">
      <template v-if="withdrawals.data.value">
        <MetricGrid :items="withdrawals.metrics.value" />
        <div class="withdrawWorkspace">
          <WithdrawalApplyCard
            v-model="withdrawals.amount.value"
            :config="withdrawals.data.value.config"
            :expected-quota="withdrawals.expectedQuota.value"
            :creating="withdrawals.creating.value"
            @submit="withdrawals.submit"
          />
          <WithdrawalHistoryCard
            :items="withdrawals.data.value.items"
            :page="withdrawals.page"
            :loading="withdrawals.loading.value"
            :today-count="withdrawals.data.value.today_count"
            :today-amount="withdrawals.data.value.today_amount"
            @change="withdrawals.tableChange"
          />
        </div>
      </template>
    </AsyncState>
  </div>
</template>
