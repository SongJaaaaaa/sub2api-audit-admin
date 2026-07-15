<script setup lang="ts">
import { ref } from 'vue'

const items = [
  { id: 'milestone-config', label: '里程碑配置' },
  { id: 'stage-config', label: '后续台阶配置' },
  { id: 'withdraw-config', label: '提现配置' },
  { id: 'source-config', label: '返利来源' },
]
const active = ref(items[0].id)

function jump(id: string) {
  active.value = id
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
</script>

<template>
  <aside class="configRail">
    <nav aria-label="返利配置分区">
      <button
        v-for="item in items"
        :key="item.id"
        type="button"
        :class="{ active: active === item.id }"
        @click="jump(item.id)"
      >
        <span>{{ item.label }}</span><i aria-hidden="true">•</i>
      </button>
    </nav>
  </aside>
</template>

<style scoped>
.configRail { position: sticky; top: 16px; width: 224px; padding-top: 8px; }
.configRail nav { display: flex; flex-direction: column; }
.configRail button {
  display: flex;
  width: 100%;
  min-height: 48px;
  padding: 0 14px;
  align-items: center;
  justify-content: space-between;
  border: 0;
  border-left: 4px solid transparent;
  background: transparent;
  color: var(--rebate-muted);
  cursor: pointer;
  font: inherit;
  font-size: 14px;
  font-weight: 600;
  text-align: left;
}
.configRail button:hover { color: var(--rebate-text); }
.configRail button.active { border-left-color: var(--rebate-accent); color: var(--rebate-accent); }
.configRail i { font-style: normal; }
</style>
