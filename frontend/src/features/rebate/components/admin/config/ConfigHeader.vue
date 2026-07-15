<script setup lang="ts">
import { SaveOutlined } from '@ant-design/icons-vue'

defineProps<{ saving: boolean; disabled: boolean }>()
defineEmits<{ save: [] }>()
</script>

<template>
  <header class="configHeader">
    <h1>配置中心</h1>
    <a-button type="primary" :loading="saving" :disabled="disabled" @click="$emit('save')">
      <template #icon><SaveOutlined /></template>
      保存更改
    </a-button>
  </header>
</template>

<style scoped>
.configHeader {
  display: flex;
  min-height: 56px;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.configHeader h1 { margin: 0; color: var(--rebate-text); font-size: 28px; line-height: 38px; letter-spacing: 0; }
.configHeader :deep(.ant-btn-primary) { min-width: 104px; background: var(--rebate-accent); box-shadow: none; }

@media (max-width: 760px) {
  .configHeader { min-height: 48px; }
  .configHeader h1 { font-size: 24px; line-height: 34px; }
}
</style>
