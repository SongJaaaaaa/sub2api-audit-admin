<script setup lang="ts">
import { computed } from 'vue'
import type { RelationshipUser } from '../../../types'
import { money } from '../../../utils/money'

const props = defineProps<{
  open: boolean
  node: RelationshipUser | null
  rootId?: number
}>()

const emit = defineEmits<{ close: [] }>()
const name = computed(() => props.node?.username || props.node?.email || `用户 #${props.node?.user_id || ''}`)
const initial = computed(() => name.value.slice(0, 1).toUpperCase())
</script>

<template>
  <a-modal :open="open" title="用户详情" :footer="null" :width="480" @cancel="emit('close')">
    <template v-if="node">
      <div class="detailIdentity">
        <span>{{ initial }}</span>
        <div>
          <strong>{{ name }}</strong>
          <p>{{ node.email }} · ID {{ node.user_id }}</p>
        </div>
        <a-tag color="blue">{{ node.user_id === rootId ? '所选账号' : '一级下级' }}</a-tag>
      </div>

      <dl class="detailStats">
        <div><dt>累计充值</dt><dd>{{ money(node.total_recharge_amount) }}</dd></div>
        <div><dt>产生返利</dt><dd class="rebateValue">{{ money(node.total_rebate_amount) }}</dd></div>
        <div><dt>直接邀请</dt><dd>{{ node.direct_count }} 人</dd></div>
        <div><dt>邀请码</dt><dd>{{ node.invite_code || '--' }}</dd></div>
      </dl>

      <div class="parentInfo">
        <span>直接上级</span>
        <strong v-if="node.parent_user_id">{{ node.parent_email || '邮箱未同步' }} · ID {{ node.parent_user_id }}</strong>
        <strong v-else>无直接上级</strong>
      </div>
    </template>
  </a-modal>
</template>

<style scoped>
.detailIdentity {
  display: flex;
  min-width: 0;
  padding: 16px;
  align-items: center;
  gap: 14px;
  border-radius: 8px;
  background: var(--rebate-low, #f2f4f6);
}

.detailIdentity > span {
  display: inline-flex;
  width: 52px;
  height: 52px;
  flex: 0 0 52px;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: #eef0ff;
  color: #4648d4;
  font-size: 18px;
  font-weight: 700;
}

.detailIdentity > div {
  min-width: 0;
  flex: 1;
}

.detailIdentity strong,
.detailIdentity p {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.detailIdentity strong {
  color: var(--rebate-text, #0f172a);
  font-size: 17px;
}

.detailIdentity p {
  margin: 3px 0 0;
  color: var(--rebate-muted, #64748b);
  font-size: 12px;
}

.detailStats {
  display: grid;
  margin: 16px 0 0;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.detailStats > div {
  min-width: 0;
  padding: 12px;
  border: 1px solid var(--rebate-border, #e2e8f0);
  border-radius: 8px;
  text-align: center;
}

.detailStats dt {
  color: var(--rebate-muted, #64748b);
  font-size: 11px;
}

.detailStats dd {
  margin: 5px 0 0;
  overflow-wrap: anywhere;
  color: var(--rebate-text, #0f172a);
  font-size: 15px;
  font-weight: 700;
}

.detailStats .rebateValue {
  color: var(--rebate-success, #10b981);
}

.parentInfo {
  display: flex;
  margin-top: 16px;
  padding: 12px;
  align-items: baseline;
  justify-content: space-between;
  gap: 16px;
  border: 1px solid var(--rebate-border, #e2e8f0);
  border-radius: 8px;
}

.parentInfo span {
  flex: 0 0 auto;
  color: var(--rebate-muted, #64748b);
  font-size: 11px;
}

.parentInfo strong {
  overflow-wrap: anywhere;
  color: var(--rebate-text, #0f172a);
  font-size: 12px;
  text-align: right;
}

@media (max-width: 520px) {
  .detailStats {
    grid-template-columns: 1fr;
  }
}
</style>
