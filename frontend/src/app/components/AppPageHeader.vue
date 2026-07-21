<script setup lang="ts">
import { ArrowLeftOutlined } from '@ant-design/icons-vue'

withDefaults(defineProps<{
  title: string
  subtitle?: string
  back?: boolean
}>(), { back: true })

const emit = defineEmits<{ back: [] }>()
</script>

<template>
  <header class="appPageHeader">
    <button v-if="back" class="appIconButton" type="button" data-app-close aria-label="返回" @click="emit('back')">
      <ArrowLeftOutlined />
    </button>
    <div class="appPageHeaderText">
      <h1>{{ title }}</h1>
      <p v-if="subtitle">{{ subtitle }}</p>
    </div>
    <div class="appPageHeaderActions"><slot name="actions" /></div>
  </header>
</template>
