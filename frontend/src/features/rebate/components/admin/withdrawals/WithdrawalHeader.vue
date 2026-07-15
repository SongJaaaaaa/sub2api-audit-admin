<script setup lang="ts">
import { ReloadOutlined } from '@ant-design/icons-vue'

defineProps<{ loading: boolean }>()
defineEmits<{ refresh: [] }>()
</script>

<template>
  <header class="withdrawHeader">
    <div>
      <h1>提现审核</h1>
      <p>审批用户提现申请、确认转入 Sub2API API 额度。</p>
    </div>
    <a-button :loading="loading" @click="$emit('refresh')">
      <template #icon><ReloadOutlined /></template>
      刷新
    </a-button>
  </header>
</template>

<style scoped>
.withdrawHeader { display: flex; min-height: 64px; align-items: flex-start; justify-content: space-between; gap: 16px; }
.withdrawHeader h1,
.withdrawHeader p { margin: 0; }
.withdrawHeader h1 { color: var(--rebate-text); font-size: 30px; line-height: 38px; letter-spacing: 0; }
.withdrawHeader p { margin-top: 4px; color: var(--rebate-muted); font-size: 14px; line-height: 22px; }

@media (max-width: 760px) {
  .withdrawHeader { min-height: 58px; align-items: flex-start; }
  .withdrawHeader h1 { font-size: 24px; line-height: 34px; }
  .withdrawHeader p { font-size: 13px; }
  .withdrawHeader :deep(.ant-btn) { width: 36px; padding-inline: 0; font-size: 0; }
  .withdrawHeader :deep(.ant-btn .anticon) { margin: 0; font-size: 14px; }
}
</style>
