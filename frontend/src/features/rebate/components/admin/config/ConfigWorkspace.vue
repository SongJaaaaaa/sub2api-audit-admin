<script setup lang="ts">
import type { RebateConfigInput } from '../../../types'
import ConfigAnchorRail from './ConfigAnchorRail.vue'
import ConfigHeader from './ConfigHeader.vue'
import MilestoneConfigCard from './MilestoneConfigCard.vue'
import RebateSourceCard from './RebateSourceCard.vue'
import StageConfigCard from './StageConfigCard.vue'
import WithdrawalConfigCard from './WithdrawalConfigCard.vue'

defineProps<{
  saving: boolean
  disabled: boolean
  cutoverAt: string | null
  updatedAt: string | null
}>()
defineEmits<{ save: [] }>()
const form = defineModel<RebateConfigInput>({ required: true })
</script>

<template>
  <div class="configWorkspace">
    <ConfigAnchorRail />
    <section class="configContent">
      <ConfigHeader :saving="saving" :disabled="disabled" @save="$emit('save')" />
      <MilestoneConfigCard v-model="form" />
      <StageConfigCard v-model="form" />
      <WithdrawalConfigCard v-model="form" />
      <RebateSourceCard v-model="form" :cutover-at="cutoverAt" />
      <footer class="configFooter">所有配置变更都会写入审计记录 · 最后更新 {{ updatedAt || '--' }}</footer>
    </section>
  </div>
</template>

<style scoped>
.configWorkspace { display: grid; min-width: 0; grid-template-columns: 168px minmax(0, 1fr); gap: 16px; align-items: start; }
.configContent { display: flex; min-width: 0; flex-direction: column; gap: 24px; }
.configFooter { padding: 8px 4px 0; color: var(--rebate-muted); font-size: 12px; line-height: 20px; }

@media (max-width: 900px) {
  .configWorkspace { grid-template-columns: 1fr; }
  .configWorkspace > :first-child { display: none; }
}

@media (max-width: 760px) {
  .configContent { gap: 16px; }
  .configFooter { padding: 4px 0 0; }
}
</style>
