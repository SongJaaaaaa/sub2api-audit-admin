<script setup lang="ts">
defineProps<{
  title: string
  description?: string
}>()
</script>

<template>
  <header class="rebatePageHeader">
    <div class="rebatePageTitle">
      <h1>{{ title }}</h1>
      <p v-if="description">{{ description }}</p>
    </div>
    <div v-if="$slots.actions" class="rebatePageActions">
      <slot name="actions" />
    </div>
  </header>
</template>
