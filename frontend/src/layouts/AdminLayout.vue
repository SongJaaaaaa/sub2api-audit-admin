<script setup lang="ts">
import {
  DesktopOutlined,
  LogoutOutlined,
  MenuFoldOutlined,
  MenuUnfoldOutlined,
} from '@ant-design/icons-vue'
import type { MenuOption } from 'naive-ui'
import {
  NButton,
  NDrawer,
  NDrawerContent,
  NLayout,
  NLayoutContent,
  NLayoutHeader,
  NLayoutSider,
  NMenu,
  NTooltip,
} from 'naive-ui'
import { computed, h, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterView, useRoute, useRouter } from 'vue-router'
import { menuItems, type MenuItem } from '../config/menu'
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
const defaultExpandedKeys = ['rebate']

const themeOptions: { label: string; value: ThemeMode }[] = [
  { label: '跟随系统', value: 'system' },
  { label: '浅色', value: 'light' },
  { label: '深色', value: 'dark' },
]

const selectedKey = computed(() => {
  const hit = findMenu((item) => item.path === route.path)
  return hit?.key || 'dashboard'
})

const selectedItem = computed(() => findMenu((item) => item.key === selectedKey.value))

const menuData = computed<MenuOption[]>(() =>
  menuItems.map(toMenuOption),
)

function toMenuOption(item: MenuItem): MenuOption {
  return {
    key: item.key,
    label: item.label,
    icon: () => h(item.icon),
    children: item.children?.map(toMenuOption),
  }
}

function findMenu(match: (item: MenuItem) => boolean, items = menuItems): MenuItem | undefined {
  for (const item of items) {
    if (match(item)) return item
    const hit = item.children && findMenu(match, item.children)
    if (hit) return hit
  }
}

function goMenu(key: string) {
  const hit = findMenu((item) => item.key === key)
  if (hit?.path) {
    router.push(hit.path)
    drawerOpen.value = false
  }
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
  <NLayout class="soyAdmin" has-sider>
    <NLayoutSider
      v-if="!isMobile"
      class="soySider"
      bordered
      collapse-mode="width"
      :collapsed="collapsed"
      :collapsed-width="64"
      :width="224"
      :native-scrollbar="false"
    >
      <div class="soyLogo">
        <span class="brandMark">S</span>
        <strong v-if="!collapsed">Sub2API 审计</strong>
      </div>
      <NMenu
        class="soyMenu"
        :collapsed="collapsed"
        :collapsed-width="64"
        :collapsed-icon-size="20"
        :options="menuData"
        :value="selectedKey"
        :default-expanded-keys="defaultExpandedKeys"
        @update:value="goMenu"
      />
    </NLayoutSider>

    <NLayout class="soyMain">
      <NLayoutHeader class="soyHeader" bordered>
        <div class="soyHeaderLeft">
          <NButton quaternary circle @click="isMobile ? (drawerOpen = true) : (collapsed = !collapsed)">
            <template #icon>
              <MenuUnfoldOutlined v-if="collapsed || isMobile" />
              <MenuFoldOutlined v-else />
            </template>
          </NButton>
          <span class="soyBreadcrumb">{{ selectedItem?.label || '首页' }}</span>
        </div>

        <div class="soyHeaderRight">
          <div class="themeSwitch" role="group" aria-label="主题模式">
            <NTooltip v-for="item in themeOptions" :key="item.value" trigger="hover">
              <template #trigger>
                <button
                  class="themeBtn"
                  :class="{ active: themeStore.mode === item.value }"
                  type="button"
                  :aria-label="item.label"
                  @click="themeStore.setMode(item.value)"
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
              </template>
              {{ item.label }}
            </NTooltip>
          </div>
          <span class="adminName">{{ auth.admin?.name || '管理员' }}</span>
          <NButton quaternary @click="logout">
            <template #icon><LogoutOutlined /></template>
            退出
          </NButton>
        </div>
      </NLayoutHeader>

      <NLayoutContent class="soyContent" :native-scrollbar="false">
        <RouterView />
      </NLayoutContent>
    </NLayout>

    <NDrawer v-model:show="drawerOpen" placement="left" :width="280">
      <NDrawerContent class="mobileDrawerContent" body-content-class="mobileDrawerBody" :native-scrollbar="false">
        <div class="soyLogo mobileLogo">
          <span class="brandMark">S</span>
          <strong>Sub2API 审计</strong>
        </div>
        <NMenu
          :options="menuData"
          :value="selectedKey"
          :default-expanded-keys="defaultExpandedKeys"
          @update:value="goMenu"
        />
      </NDrawerContent>
    </NDrawer>
  </NLayout>
</template>
