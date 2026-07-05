<script setup lang="ts">
import { LogoutOutlined, MenuFoldOutlined, MenuUnfoldOutlined } from '@ant-design/icons-vue'
import { computed, h, onMounted, ref } from 'vue'
import { RouterView, useRoute, useRouter } from 'vue-router'
import { menuItems } from '../config/menu'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const collapsed = ref(false)

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
  if (hit) router.push(hit.path)
}

async function logout() {
  await auth.logout()
  router.replace('/login')
}

onMounted(async () => {
  try {
    await auth.fetchMe()
  } catch {
    await auth.logout()
    router.replace('/login')
  }
})
</script>

<template>
  <a-layout class="admin">
    <a-layout-sider v-model:collapsed="collapsed" collapsible class="side" :trigger="null">
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
        <button class="iconBtn" type="button" @click="collapsed = !collapsed">
          <MenuUnfoldOutlined v-if="collapsed" />
          <MenuFoldOutlined v-else />
        </button>
        <div class="adminInfo">
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
  </a-layout>
</template>
