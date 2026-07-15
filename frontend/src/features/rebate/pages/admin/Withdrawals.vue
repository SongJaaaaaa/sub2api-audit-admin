<script setup lang="ts">
import { onMounted } from 'vue'
import RejectWithdrawalModal from '../../components/admin/withdrawals/RejectWithdrawalModal.vue'
import WithdrawalFlow from '../../components/admin/withdrawals/WithdrawalFlow.vue'
import WithdrawalHeader from '../../components/admin/withdrawals/WithdrawalHeader.vue'
import WithdrawalPanel from '../../components/admin/withdrawals/WithdrawalPanel.vue'
import { useAdminWithdrawals } from '../../composables/admin/useAdminWithdrawals'

const withdrawals = useAdminWithdrawals()

onMounted(withdrawals.load)
</script>

<template>
  <div class="rebatePage withdrawalPage">
    <WithdrawalHeader :loading="withdrawals.loading.value" @refresh="withdrawals.load" />
    <WithdrawalFlow />
    <WithdrawalPanel
      v-model:status="withdrawals.status.value"
      v-model:keyword="withdrawals.keyword.value"
      :loading="withdrawals.loading.value"
      :error="withdrawals.error.value"
      :items="withdrawals.items.value"
      :action-ids="withdrawals.actionIds.value"
      :page="withdrawals.page"
      :page-amount="withdrawals.pageAmount.value"
      @retry-load="withdrawals.load"
      @search="withdrawals.search"
      @change-status="withdrawals.changeStatus"
      @table-change="withdrawals.tableChange"
      @page-change="withdrawals.pageChange"
      @approve="withdrawals.approve"
      @reject="withdrawals.openReject"
      @retry="withdrawals.retry"
    />
    <RejectWithdrawalModal
      v-model:open="withdrawals.rejectOpen.value"
      v-model:reason="withdrawals.rejectReason.value"
      :loading="withdrawals.rejecting.value"
      @submit="withdrawals.submitReject"
    />
  </div>
</template>

<style scoped>
.withdrawalPage { gap: 24px; }
@media (max-width: 760px) { .withdrawalPage { gap: 16px; } }
</style>
