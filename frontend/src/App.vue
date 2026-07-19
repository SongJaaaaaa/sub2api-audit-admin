<script setup lang="ts">
import { theme as antdTheme } from 'ant-design-vue'
import zhCN from 'ant-design-vue/es/locale/zh_CN'
import { NConfigProvider, darkTheme } from 'naive-ui'
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

const naiveTheme = computed(() => (themeStore.themeName === 'dark' ? darkTheme : undefined))

onMounted(themeStore.initTheme)
</script>

<template>
  <n-config-provider :theme="naiveTheme" class="appProvider">
    <a-config-provider :locale="zhCN" :theme="themeCfg">
      <RouterView />
    </a-config-provider>
  </n-config-provider>
</template>
