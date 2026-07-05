<script setup lang="ts">
import { DesktopOutlined, LogoutOutlined, MenuFoldOutlined, MenuUnfoldOutlined } from '@ant-design/icons-vue'
import { computed, h, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterView, useRoute, useRouter } from 'vue-router'
import { menuItems } from '../config/menu'
import { useAuthStore } from '../stores/auth'
import { useThemeStore, type ThemeMode } from '../stores/theme'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const themeStore = useThemeStore()
const collapsed = ref(false)
const drawerOpen = ref(false)
const isMobile = ref(window.matchMedia('(max-width: 760px)').matches)
const mobileMedia = window.matchMedia('(max-width: 760px)')

const themeOptions: { label: string; value: ThemeMode }[] = [
  { label: '跟随系统', value: 'system' },
  { label: '浅色', value: 'light' },
  { label: '深色', value: 'dark' },
]

const selectedKeys = computed(() => {
  const hit = menuItems.find((item) => item.path === route.path)
  return [hit?.key || 'dashboard']
})

const menuData = computed(() =>
  menuItems.map((item) => ({
    key: item.key,
    icon: () => h(item.icon),
    label: item.label,
    title: item.label,
  })),
)

function goMenu({ key }: { key: string }) {
  const hit = menuItems.find((item) => item.key === key)
  if (hit) {
    router.push(hit.path)
    drawerOpen.value = false
  }
}

function setTheme(val: ThemeMode) {
  themeStore.setMode(val)
}

function updateMobile(event: MediaQueryListEvent) {
  isMobile.value = event.matches
  if (!event.matches) drawerOpen.value = false
}

async function logout() {
  await auth.logout()
  router.replace('/login')
}

onMounted(async () => {
  mobileMedia.addEventListener('change', updateMobile)

  try {
    await auth.fetchMe()
  } catch {
    await auth.logout()
    router.replace('/login')
  }
})

onBeforeUnmount(() => {
  mobileMedia.removeEventListener('change', updateMobile)
})
</script>

<template>
  <a-layout class="admin" :class="{ mobile: isMobile }">
    <a-layout-sider v-if="!isMobile" v-model:collapsed="collapsed" collapsible class="side" :trigger="null">
      <div class="brand">
        <span class="brandMark">S</span>
        <strong v-if="!collapsed">Sub2API 审计</strong>
      </div>
      <a-menu
        theme="dark"
        mode="inline"
        :items="menuData"
        :selected-keys="selectedKeys"
        @click="goMenu"
      />
    </a-layout-sider>

    <a-layout>
      <a-layout-header class="top">
        <button class="iconBtn" type="button" @click="isMobile ? (drawerOpen = true) : (collapsed = !collapsed)">
          <MenuUnfoldOutlined v-if="collapsed" />
          <MenuFoldOutlined v-else />
        </button>
        <div class="adminInfo">
          <div class="themeSwitch" role="group" aria-label="主题模式">
            <a-tooltip v-for="item in themeOptions" :key="item.value" :title="item.label">
              <button
                class="themeBtn"
                :class="{ active: themeStore.mode === item.value }"
                type="button"
                :aria-label="item.label"
                @click="setTheme(item.value)"
              >
                <DesktopOutlined v-if="item.value === 'system'" />
                <svg
                  v-else-if="item.value === 'light'"
                  class="themeSvg"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <circle cx="12" cy="12" r="4.2" />
                  <path d="M12 2.5v2.4M12 19.1v2.4M4.9 4.9l1.7 1.7M17.4 17.4l1.7 1.7M2.5 12h2.4M19.1 12h2.4M4.9 19.1l1.7-1.7M17.4 6.6l1.7-1.7" />
                </svg>
                <svg v-else class="themeSvg" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M20.5 14.6A8.5 8.5 0 0 1 9.4 3.5a8.7 8.7 0 1 0 11.1 11.1Z" />
                </svg>
              </button>
            </a-tooltip>
          </div>
          <span>{{ auth.admin?.name || '管理员' }}</span>
          <a-button type="text" @click="logout">
            <template #icon><LogoutOutlined /></template>
            退出
          </a-button>
        </div>
      </a-layout-header>

      <a-layout-content class="content">
        <RouterView />
      </a-layout-content>
    </a-layout>

    <a-drawer
      v-model:open="drawerOpen"
      class="mobileDrawer"
      placement="left"
      :width="280"
      :body-style="{ padding: 0 }"
      :closable="false"
    >
      <div class="brand drawerBrand">
        <span class="brandMark">S</span>
        <strong>Sub2API 审计</strong>
      </div>
      <a-menu mode="inline" :items="menuData" :selected-keys="selectedKeys" @click="goMenu" />
    </a-drawer>
  </a-layout>
</template>
