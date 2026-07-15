<script setup lang="ts">
export interface MetricItem {
  label: string
  value: string | number
  hint?: string
  tone?: 'blue' | 'green' | 'orange' | 'red'
}

defineProps<{ items: MetricItem[] }>()
</script>

<template>
  <div class="rebateMetrics">
    <article v-for="item in items" :key="item.label" class="rebateMetric" :data-tone="item.tone || 'blue'">
      <span>{{ item.label }}</span>
      <strong>{{ item.value }}</strong>
      <small v-if="item.hint">{{ item.hint }}</small>
    </article>
  </div>
</template>
