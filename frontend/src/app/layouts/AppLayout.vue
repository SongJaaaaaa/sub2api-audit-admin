<script setup lang="ts">
import { LogoutOutlined } from '@ant-design/icons-vue'
import { isAxiosError } from 'axios'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterView, useRoute, useRouter } from 'vue-router'
import AppPageHeader from '../components/AppPageHeader.vue'
import { useAppMode } from '../composables/useAppMode'
import { initNativeRuntime, networkOnline, syncNativeTheme } from '../services/nativeRuntime'
import { useAuthStore } from '../../stores/auth'
import { useThemeStore, type ThemeMode } from '../../stores/theme'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const themeStore = useThemeStore()
const { isAppMode } = useAppMode()
const backHint = ref(false)
const sessionError = ref('')
const appShell = ref<HTMLElement | null>(null)
const sessionReady = ref(false)
let hintTimer: number | undefined

const isHome = computed(() => route.name === 'app-home')
const title = computed(() => String(route.meta.title || 'Sub2API 审计后台'))
const subtitle = computed(() => String(route.meta.subtitle || ''))
const themeOptions: Array<{ value: ThemeMode; label: string }> = [
  { value: 'light', label: '浅色主题' },
  { value: 'dark', label: '深色主题' },
  { value: 'system', label: '跟随系统' },
]

function goBack() {
  if (window.history.length > 1 && route.path !== '/app') router.back()
  else router.replace('/app')
}

async function logout() {
  await auth.logout()
  await router.replace('/login')
}

function showBackHint() {
  backHint.value = true
  window.clearTimeout(hintTimer)
  hintTimer = window.setTimeout(() => { backHint.value = false }, 1800)
}

async function checkSession() {
  sessionError.value = ''
  try {
    await auth.fetchMe()
  } catch (err) {
    if (isAxiosError(err) && err.response?.status === 401) {
      await auth.logout()
      await router.replace('/login')
      return
    }
    sessionError.value = '登录状态暂时无法验证，请恢复网络后重试'
  }
}

watch(() => themeStore.themeName, (name) => { void syncNativeTheme(name === 'dark') })
watch(() => route.fullPath, () => { appShell.value?.scrollTo({ top: 0, behavior: 'auto' }) })

onMounted(async () => {
  window.addEventListener('app-back-hint', showBackHint)
  void initNativeRuntime(router, themeStore.themeName === 'dark')
  await checkSession()
  sessionReady.value = true
})

onBeforeUnmount(() => {
  window.removeEventListener('app-back-hint', showBackHint)
  window.clearTimeout(hintTimer)
})
</script>

<template>
  <main v-if="isAppMode" ref="appShell" class="appShell">
    <div v-if="!networkOnline" class="appOfflineBanner">当前无网络连接，请恢复网络后重试</div>
    <div v-if="sessionError" class="appOfflineBanner appSessionError" role="alert">
      <span>{{ sessionError }}</span>
      <button class="appSecondaryButton" type="button" @click="checkSession">重试</button>
    </div>
    <AppPageHeader v-if="!isHome" :title="title" :subtitle="subtitle" @back="goBack">
      <template #actions>
        <button
          v-for="item in themeOptions"
          :key="item.value"
          class="appThemeButton"
          :class="{ active: themeStore.mode === item.value }"
          type="button"
          :aria-label="item.label"
          :title="item.label"
          @click="themeStore.setMode(item.value)"
        >
          <span v-if="item.value === 'light'">☼</span>
          <span v-else-if="item.value === 'dark'">◐</span>
          <span v-else>◌</span>
        </button>
        <button class="appIconButton" type="button" aria-label="退出登录" title="退出登录" @click="logout"><LogoutOutlined /></button>
      </template>
    </AppPageHeader>
    <div v-if="!sessionReady" class="appSessionLoading" role="status">正在验证登录状态…</div>
    <section v-else class="appContent"><RouterView /></section>
    <div v-if="backHint" class="appToast" role="status">再次返回退出 App</div>
  </main>
</template>
