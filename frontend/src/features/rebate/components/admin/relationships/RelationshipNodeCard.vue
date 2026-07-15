<script setup lang="ts">
import { computed } from 'vue'
import type { RelationshipUser } from '../../../types'
import { money } from '../../../utils/money'

const props = defineProps<{
  node: RelationshipUser
  root?: boolean
}>()

defineEmits<{ select: [node: RelationshipUser] }>()

const name = computed(() => props.node.username || props.node.email || `用户 #${props.node.user_id}`)
</script>

<template>
  <button type="button" class="relationshipNode" :class="{ rootNode: root }" @click="$emit('select', node)">
    <i class="nodeDot" />
    <strong :title="name">{{ name }}</strong>
    <span :title="node.email">{{ node.email }}</span>
    <small>ID: {{ node.user_id }}</small>
    <b>{{ root ? '所选账号' : '一级下级' }}</b>
    <em>{{ money(node.total_recharge_amount) }}</em>
    <span>直邀 {{ node.direct_count }} 人</span>
    <span v-if="root" class="parentLine">
      直接上级：{{ node.parent_user_id ? `${node.parent_email || '邮箱未同步'} · ID ${node.parent_user_id}` : '无直接上级' }}
    </span>
  </button>
</template>

<style scoped>
.relationshipNode {
  display: flex;
  width: 220px;
  min-height: 184px;
  padding: 14px 16px;
  align-items: center;
  flex-direction: column;
  border: 2px solid var(--rebate-border);
  border-radius: 8px;
  background: var(--rebate-card);
  box-shadow: 0 1px 3px rgb(15 23 42 / 8%);
  color: var(--rebate-text);
  cursor: pointer;
  font: inherit;
  text-align: center;
  transition: transform 150ms ease, box-shadow 150ms ease;
}

.relationshipNode:hover {
  box-shadow: 0 5px 14px rgb(15 23 42 / 10%);
  transform: translateY(-2px);
}

.relationshipNode.rootNode {
  width: 250px;
  min-height: 214px;
  border-color: var(--rebate-accent);
}

.nodeDot {
  width: 11px;
  height: 11px;
  margin-bottom: 8px;
  border-radius: 50%;
  background: var(--rebate-success);
}

.rootNode .nodeDot {
  background: var(--rebate-accent);
}

.relationshipNode strong,
.relationshipNode > span:not(.parentLine) {
  width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.relationshipNode strong {
  font-size: 14px;
  line-height: 22px;
}

.relationshipNode > span,
.relationshipNode small {
  color: var(--rebate-muted);
  font-size: 11px;
  line-height: 18px;
}

.relationshipNode b {
  margin-top: 6px;
  color: var(--rebate-muted);
  font-size: 11px;
  font-weight: 500;
}

.relationshipNode em {
  margin-top: 4px;
  color: var(--rebate-accent);
  font-size: 13px;
  font-style: normal;
  font-weight: 700;
}

.relationshipNode .parentLine {
  width: 100%;
  margin-top: 8px;
  padding-top: 7px;
  overflow-wrap: anywhere;
  border-top: 1px solid var(--rebate-border);
  white-space: normal;
}
</style>
