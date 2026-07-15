<script setup lang="ts">
import type { RebateRecordType } from '../../../composables/affiliate/useAffiliateRebateRecords'

defineProps<{ total: number }>()
const type = defineModel<RebateRecordType>({ required: true })
defineEmits<{ change: [] }>()
</script>

<template>
  <div class="recordFilters">
    <a-select
      aria-label="筛选返利类型"
      v-model:value="type"
      class="recordTypeFilter"
      :options="[
        { label: '全部类型', value: '' },
        { label: '初始里程碑', value: 'milestone' },
        { label: '后续台阶', value: 'stage' },
      ]"
      @change="$emit('change')"
    />
    <span>共 {{ total }} 条</span>
  </div>
</template>

<style scoped>
.recordFilters { display: flex; margin-bottom: 16px; align-items: center; justify-content: space-between; gap: 12px; }
.recordTypeFilter { width: 150px; }
.recordFilters > span { color: var(--rebate-muted); font-size: 12px; }
@media (max-width: 760px) {
  .recordFilters { align-items: stretch; flex-direction: column; }
  .recordTypeFilter { width: 100%; }
}
</style>
