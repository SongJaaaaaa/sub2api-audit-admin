<script setup lang="ts">
import type { TablePaginationConfig } from 'ant-design-vue'
import type { RebateWithdrawal, WithdrawalStatus } from '../../../types'
import AsyncState from '../../AsyncState.vue'
import WithdrawalCards from './WithdrawalCards.vue'
import WithdrawalFilters from './WithdrawalFilters.vue'
import WithdrawalTable from './WithdrawalTable.vue'

defineProps<{
  loading: boolean
  error: string
  items: RebateWithdrawal[]
  actionIds: Set<number>
  page: { current: number; pageSize: number; total: number }
  pageAmount: string
}>()
const status = defineModel<WithdrawalStatus | ''>('status', { required: true })
const keyword = defineModel<string>('keyword', { required: true })
defineEmits<{
  retryLoad: []
  search: []
  changeStatus: [value: WithdrawalStatus | '']
  tableChange: [pager: TablePaginationConfig]
  pageChange: [page: number]
  approve: [row: RebateWithdrawal]
  reject: [row: RebateWithdrawal]
  retry: [row: RebateWithdrawal]
}>()
</script>

<template>
  <section class="withdrawPanel">
    <WithdrawalFilters
      v-model:status="status"
      v-model:keyword="keyword"
      :loading="loading"
      @search="$emit('search')"
      @change-status="$emit('changeStatus', $event)"
    />
    <div class="panelSummary"><span>共 {{ page.total }} 条申请</span><strong>本页合计 {{ pageAmount }}</strong></div>
    <AsyncState :loading="loading && items.length === 0" :error="error" :empty="!loading && items.length === 0" empty-text="暂无提现申请" @retry="$emit('retryLoad')">
      <WithdrawalTable
        class="desktopOnly"
        :items="items"
        :loading="loading"
        :action-ids="actionIds"
        :page="page"
        @change="$emit('tableChange', $event)"
        @approve="$emit('approve', $event)"
        @reject="$emit('reject', $event)"
        @retry="$emit('retry', $event)"
      />
      <WithdrawalCards
        class="mobileOnly"
        :items="items"
        :action-ids="actionIds"
        :page="page"
        @page-change="$emit('pageChange', $event)"
        @approve="$emit('approve', $event)"
        @reject="$emit('reject', $event)"
        @retry="$emit('retry', $event)"
      />
    </AsyncState>
  </section>
</template>

<style scoped>
.withdrawPanel { min-width: 0; padding: 0 24px 20px; border: 1px solid var(--rebate-border); border-radius: 16px; background: var(--rebate-card); box-shadow: 0 1px 3px rgb(15 23 42 / 8%), 0 1px 2px -1px rgb(15 23 42 / 8%); }
.panelSummary { display: flex; min-height: 42px; padding: 0 2px; align-items: center; justify-content: space-between; gap: 16px; border-top: 1px solid var(--rebate-border); color: var(--rebate-muted); font-size: 12px; }
.panelSummary strong { color: #334155; font-weight: 600; }
.mobileOnly { display: none; }

@media (max-width: 760px) {
  .withdrawPanel { padding: 0 16px 16px; border-radius: 16px; }
  .panelSummary { align-items: flex-start; flex-direction: column; justify-content: center; gap: 2px; }
  .desktopOnly { display: none; }
  .mobileOnly { display: flex; }
}
</style>
