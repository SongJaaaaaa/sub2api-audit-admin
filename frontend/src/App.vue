<script setup lang="ts">
import { theme as antdTheme } from 'ant-design-vue'
import { computed, onMounted } from 'vue'
import { RouterView } from 'vue-router'
import { useThemeStore } from './stores/theme'

const themeStore = useThemeStore()

const themeCfg = computed(() => ({
  algorithm:
    themeStore.themeName === 'dark' ? antdTheme.darkAlgorithm : antdTheme.defaultAlgorithm,
  token: {
    borderRadius: 8,
    colorPrimary: '#2563eb',
  },
}))

onMounted(themeStore.initTheme)
</script>

<template>
  <a-config-provider :theme="themeCfg">
    <RouterView />
  </a-config-provider>
</template>
