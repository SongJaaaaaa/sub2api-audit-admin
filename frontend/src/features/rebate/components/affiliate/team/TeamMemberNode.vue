<script setup lang="ts">
import { computed } from 'vue'
import type { TeamMember } from '../../../types'
import { money } from '../../../utils/money'

const props = defineProps<{ member: TeamMember }>()
const name = computed(() => props.member.username || props.member.email || `用户 #${props.member.user_id}`)
const mark = computed(() => name.value.slice(0, 1).toUpperCase())
</script>

<template>
  <article class="teamMemberNode">
    <span class="memberMark">{{ mark }}</span>
    <div class="memberCopy">
      <strong :title="name">{{ name }}</strong>
      <span :title="member.email">{{ member.email }}</span>
    </div>
    <dl>
      <div><dt>累计充值</dt><dd>{{ money(member.total_recharge_amount) }}</dd></div>
      <div><dt>产生返利</dt><dd>{{ money(member.total_rebate_amount) }}</dd></div>
    </dl>
  </article>
</template>

<style scoped>
.teamMemberNode { position: relative; display: flex; width: 230px; padding: 14px; align-items: flex-start; flex-wrap: wrap; gap: 10px; border: 1px solid var(--rebate-border); border-radius: 8px; background: var(--rebate-card); box-shadow: 0 2px 8px rgb(15 23 42 / 7%); }
.teamMemberNode::before { position: absolute; bottom: 100%; left: 50%; width: 1px; height: 30px; background: #cbd5e1; content: ''; }
.memberMark { display: inline-flex; width: 34px; height: 34px; flex: 0 0 34px; align-items: center; justify-content: center; border-radius: 50%; background: #eef2ff; color: var(--rebate-accent); font-size: 13px; font-weight: 700; }
.memberCopy { display: flex; min-width: 0; flex: 1; flex-direction: column; gap: 2px; }
.memberCopy strong,
.memberCopy span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.memberCopy strong { color: var(--rebate-text); font-size: 13px; }
.memberCopy span { color: var(--rebate-muted); font-size: 11px; }
.teamMemberNode dl { display: grid; width: 100%; margin: 4px 0 0; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.teamMemberNode dl div { min-width: 0; padding-top: 9px; border-top: 1px solid var(--rebate-border); }
.teamMemberNode dt { color: var(--rebate-muted); font-size: 10px; }
.teamMemberNode dd { margin: 2px 0 0; overflow-wrap: anywhere; color: var(--rebate-text); font-size: 12px; font-weight: 600; }
</style>
