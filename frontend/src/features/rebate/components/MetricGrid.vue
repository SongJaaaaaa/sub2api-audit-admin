<script setup lang="ts">
export interface MetricItem {
  label: string
  value: string | number
  tone?: 'blue' | 'green' | 'orange' | 'red'
}

defineProps<{ items: MetricItem[] }>()
</script>

<template>
  <div class="rebateMetrics">
    <article v-for="item in items" :key="item.label" class="rebateMetric" :data-tone="item.tone || 'blue'">
      <span>{{ item.label }}</span>
      <strong>{{ item.value }}</strong>
    </article>
  </div>
</template>
