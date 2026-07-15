<script setup lang="ts">
import { CopyOutlined } from '@ant-design/icons-vue'
import { message } from 'ant-design-vue'
import AppCard from '../../core/AppCard.vue'

const props = defineProps<{
  inviteUrl: string
  inviteCode: string
}>()

async function copy(value: string, label: string) {
  try {
    await navigator.clipboard.writeText(value)
    message.success(`${label}已复制`)
  } catch {
    message.error('复制失败')
  }
}
</script>

<template>
  <AppCard>
    <div class="sourceInviteGrid">
      <div class="sourceInviteMain">
        <span class="sourceFieldLabel">Sub2API 邀请链接</span>
        <div class="sourceInviteUrl">{{ inviteUrl || '暂无 Sub2API 邀请链接' }}</div>
      </div>
      <a-button type="primary" aria-label="复制邀请链接" :disabled="!inviteUrl" @click="copy(inviteUrl, '邀请链接')">
        <template #icon><CopyOutlined /></template>
        复制链接
      </a-button>
    </div>
    <p class="sourceInviteCode">
      Sub2API 邀请码：
      <button type="button" :disabled="!inviteCode" @click="copy(inviteCode, '邀请码')">{{ inviteCode || '-' }}</button>
    </p>
  </AppCard>
</template>

<style scoped>
.sourceInviteGrid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: end;
  gap: 16px;
}

.sourceInviteMain { min-width: 0; }

.sourceFieldLabel {
  color: var(--rebate-muted);
  font-size: 12px;
  font-weight: 600;
}

.sourceInviteUrl {
  min-height: 52px;
  margin-top: 12px;
  padding: 16px;
  overflow-wrap: anywhere;
  border-radius: 8px;
  background: var(--rebate-low);
  color: var(--rebate-text);
  font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
  font-size: 14px;
  line-height: 20px;
}

.sourceInviteCode {
  margin: 16px 0 0;
  color: var(--rebate-muted);
  font-size: 14px;
}

.sourceInviteCode button {
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--rebate-text);
  cursor: pointer;
  font-weight: 700;
}

@media (max-width: 760px) {
  .sourceInviteGrid { grid-template-columns: 1fr; }
  .sourceInviteGrid :deep(.ant-btn) { width: 100%; }
}
</style>
