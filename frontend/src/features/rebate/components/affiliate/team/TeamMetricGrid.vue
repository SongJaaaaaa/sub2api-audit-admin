<script setup lang="ts">
defineProps<{
  directCount: number
  teamCount: number
  visibleCount: number
}>()

const items = [
  { key: 'direct', label: '直邀人数' },
  { key: 'team', label: '团队总人数' },
  { key: 'visible', label: '本页下级' },
] as const
</script>

<template>
  <div class="teamMetrics">
    <article v-for="item in items" :key="item.key" :data-tone="item.key">
      <strong>{{ item.key === 'direct' ? directCount : item.key === 'team' ? teamCount : visibleCount }}</strong>
      <span>{{ item.label }}</span>
    </article>
  </div>
</template>

<style scoped>
.teamMetrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
.teamMetrics article { display: flex; min-width: 0; min-height: 98px; padding: 14px; align-items: center; justify-content: center; flex-direction: column; border: 1px solid var(--rebate-border); border-radius: 16px; background: var(--rebate-card); box-shadow: 0 1px 3px rgb(15 23 42 / 8%), 0 1px 2px -1px rgb(15 23 42 / 8%); text-align: center; }
.teamMetrics strong { color: var(--rebate-accent); font-size: 24px; line-height: 30px; }
.teamMetrics article[data-tone='team'] strong { color: #3157e8; }
.teamMetrics article[data-tone='visible'] strong { color: var(--rebate-success); }
.teamMetrics span { margin-top: 2px; color: var(--rebate-muted); font-size: 12px; line-height: 18px; }
@media (max-width: 760px) {
  .teamMetrics { gap: 16px; }
  .teamMetrics article { min-height: 84px; padding: 8px 4px; }
  .teamMetrics strong { font-size: 23px; }
  .teamMetrics span { overflow-wrap: anywhere; }
}
</style>
