<script setup lang="ts">
export interface MetricItem {
  label: string
  value: string | number
  hint?: string
  hintType?: 'success' | 'danger' | 'muted'
}

defineProps<{ items: MetricItem[] }>()
</script>

<template>
  <div class="rebateMetrics">
    <article v-for="item in items" :key="item.label" class="rebateMetric">
      <span>{{ item.label }}</span>
      <strong>{{ item.value }}</strong>
      <small v-if="item.hint" :data-type="item.hintType || 'success'">{{ item.hint }}</small>
    </article>
  </div>
</template>
