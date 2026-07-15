<script setup lang="ts">
import { onMounted } from 'vue'
import AsyncState from '../../components/AsyncState.vue'
import DashboardShortcuts from '../../components/admin/dashboard/DashboardShortcuts.vue'
import RecentRebatesCard from '../../components/admin/dashboard/RecentRebatesCard.vue'
import RecentWithdrawalsCard from '../../components/admin/dashboard/RecentWithdrawalsCard.vue'
import RebateTrendChart from '../../components/dashboard/RebateTrendChart.vue'
import MetricGrid from '../../components/MetricGrid.vue'
import PageHeader from '../../components/PageHeader.vue'
import { useAdminDashboard } from '../../composables/admin/useAdminDashboard'

const { data, error, loading, metrics, load } = useAdminDashboard()

onMounted(load)
</script>

<template>
  <div class="rebatePage dashboardPage">
    <PageHeader title="数据看板" description="一级推广返利核心指标总览，展示当前服务端快照。" />

    <AsyncState :loading="loading && !data" :error="error" @retry="load">
      <template v-if="data">
        <MetricGrid :items="metrics" />
        <DashboardShortcuts />

        <div class="activityTitle">
          <h2>趋势分析</h2>
          <span>最近 7 日</span>
        </div>
        <RebateTrendChart :items="data.rebate_trend" summary />

        <div class="activityTitle">
          <h2>业务动态</h2>
          <span>最近返利与提现记录</span>
        </div>
        <div class="activityGrid">
          <RecentRebatesCard :items="data.recent_rebates" :today-amount="data.today_rebate_amount" />
          <RecentWithdrawalsCard :items="data.recent_withdrawals" :pending-amount="data.pending_withdrawal_amount" />
        </div>
      </template>
    </AsyncState>
  </div>
</template>

<style scoped>
.activityTitle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.activityTitle h2 {
  margin: 0;
  color: var(--rebate-text);
  font-size: 18px;
  line-height: 26px;
}

.activityTitle span {
  color: var(--rebate-muted);
  font-size: 12px;
}

.activityGrid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 24px;
}

@media (max-width: 1100px) {
  .activityGrid {
    grid-template-columns: 1fr;
  }
}
</style>
