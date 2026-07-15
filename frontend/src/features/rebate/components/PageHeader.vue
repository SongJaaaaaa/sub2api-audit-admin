<script setup lang="ts">
defineProps<{ title: string }>()
</script>

<template>
  <header class="rebatePageHeader">
    <h1>{{ title }}</h1>
    <div v-if="$slots.actions" class="rebatePageActions">
      <slot name="actions" />
    </div>
  </header>
</template>
