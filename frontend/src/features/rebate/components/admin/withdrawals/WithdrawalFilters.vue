<script setup lang="ts">
import { SearchOutlined } from '@ant-design/icons-vue'
import type { WithdrawalStatus } from '../../../types'

defineProps<{ loading: boolean }>()
const status = defineModel<WithdrawalStatus | ''>('status', { required: true })
const keyword = defineModel<string>('keyword', { required: true })
defineEmits<{ search: []; changeStatus: [value: WithdrawalStatus | ''] }>()

const options: { label: string; value: WithdrawalStatus | '' }[] = [
  { label: '全部状态', value: '' },
  { label: '待审核', value: 'pending' },
  { label: '处理中', value: 'processing' },
  { label: '已到账', value: 'succeeded' },
  { label: '已拒绝', value: 'rejected' },
  { label: '异常', value: 'exception' },
]
</script>

<template>
  <div class="withdrawFilters">
    <div class="filterControls">
      <a-select
        aria-label="筛选提现状态"
        v-model:value="status"
        :options="options"
        class="statusSelect"
        :disabled="loading"
        @change="$emit('changeStatus', $event as WithdrawalStatus | '')"
      />
      <a-input-search
        v-model:value="keyword"
        allow-clear
        placeholder="申请单号或用户邮箱"
        class="keywordSearch"
        :loading="loading"
        @search="$emit('search')"
      >
        <template #enterButton><SearchOutlined /></template>
      </a-input-search>
    </div>
    <p>通过后由队列转入 Sub2API API 额度，处理中请勿重复操作。</p>
  </div>
</template>

<style scoped>
.withdrawFilters { display: flex; min-height: 72px; align-items: center; justify-content: space-between; gap: 20px; }
.filterControls { display: flex; min-width: 0; align-items: center; gap: 10px; }
.statusSelect { width: 150px; }
.keywordSearch { width: 280px; }
.withdrawFilters p { margin: 0; color: var(--rebate-muted); font-size: 12px; line-height: 19px; text-align: right; }

@media (max-width: 900px) {
  .withdrawFilters { align-items: stretch; flex-direction: column; justify-content: center; gap: 10px; }
  .filterControls { width: 100%; }
  .keywordSearch { width: min(100%, 340px); }
  .withdrawFilters p { text-align: left; }
}

@media (max-width: 560px) {
  .filterControls { align-items: stretch; flex-direction: column; }
  .statusSelect,
  .keywordSearch { width: 100%; }
}
</style>
