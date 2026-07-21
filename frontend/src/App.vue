<script setup lang="ts">
import { App as AntApp, theme as antdTheme } from 'ant-design-vue'
import zhCN from 'ant-design-vue/es/locale/zh_CN'
import { NConfigProvider, darkTheme } from 'naive-ui'
import { computed } from 'vue'
import { RouterView } from 'vue-router'
import { useThemeStore } from './stores/theme'

const themeStore = useThemeStore()

const themeCfg = computed(() => {
  const dark = themeStore.themeName === 'dark'
  return {
    algorithm: dark ? antdTheme.darkAlgorithm : antdTheme.defaultAlgorithm,
    token: {
      borderRadius: 8,
      colorPrimary: dark ? '#60a5fa' : '#2563eb',
      // 让 antd 组件的容器/边框/文字色与 foundation.css 的自定义变量保持一致，
      // 避免暗色模式下输入框、卡片、弹窗出现发黑或与页面不协调的情况。
      colorBgContainer: dark ? '#111827' : '#ffffff',
      colorBgElevated: dark ? '#1f2937' : '#ffffff',
      colorBgLayout: dark ? '#0f172a' : '#f4f6f9',
      colorBorder: dark ? '#273449' : '#e5e7eb',
      colorBorderSecondary: dark ? '#1f2937' : '#f0f0f0',
      colorText: dark ? '#dbe4f0' : '#172033',
      colorTextSecondary: dark ? '#9ca3af' : '#667085',
      colorTextTertiary: dark ? '#6b7280' : '#98a2b3',
    },
  }
})

const naiveTheme = computed(() => (themeStore.themeName === 'dark' ? darkTheme : undefined))

themeStore.initTheme()
</script>

<template>
  <n-config-provider :theme="naiveTheme" class="appProvider">
    <a-config-provider :locale="zhCN" :theme="themeCfg">
      <AntApp class="antdApp">
        <RouterView />
      </AntApp>
    </a-config-provider>
  </n-config-provider>
</template>
