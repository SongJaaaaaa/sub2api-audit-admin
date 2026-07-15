<script setup lang="ts">
import {
  DashboardOutlined,
  GiftOutlined,
  LogoutOutlined,
  MenuFoldOutlined,
  ShareAltOutlined,
  TeamOutlined,
  WalletOutlined,
} from '@ant-design/icons-vue'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterView, useRoute, useRouter } from 'vue-router'
import { useAffiliateAuthStore } from '../stores/affiliateAuth'

const route = useRoute()
const router = useRouter()
const auth = useAffiliateAuthStore()
const drawerOpen = ref(false)
const media = window.matchMedia('(max-width: 760px)')
const isMobile = ref(media.matches)

const nav = [
  { path: '/affiliate/dashboard', label: '仪表盘', icon: DashboardOutlined },
  { path: '/affiliate/team', label: '我的团队', icon: TeamOutlined },
  { path: '/affiliate/promotion', label: '推广中心', icon: ShareAltOutlined },
  { path: '/affiliate/rebates', label: '返利明细', icon: GiftOutlined },
  { path: '/affiliate/withdrawals', label: '提现管理', icon: WalletOutlined },
]

const selected = computed(() => nav.find((item) => route.path.startsWith(item.path))?.path || '/affiliate/dashboard')
const title = computed(() => nav.find((item) => item.path === selected.value)?.label || '推广中心')

function updateMobile(event: MediaQueryListEvent) {
  isMobile.value = event.matches
  if (!event.matches) drawerOpen.value = false
}

function go(path: string) {
  router.push(path)
  drawerOpen.value = false
}

async function logout() {
  await auth.logout()
  router.replace('/affiliate/login')
}

onMounted(async () => {
  media.addEventListener('change', updateMobile)
  try {
    await auth.fetchMe()
  } catch {
    auth.clear()
    router.replace('/affiliate/login')
  }
})

onBeforeUnmount(() => media.removeEventListener('change', updateMobile))
</script>

<template>
  <div class="affiliateApp">
    <aside v-if="!isMobile" class="affiliateSider">
      <div class="affiliateBrand">
        <span class="affiliateBrandMark">S</span>
        <div class="affiliateBrandCopy">
          <strong>Sub2Rebate</strong>
          <span>返利推广中心</span>
        </div>
      </div>
      <nav class="affiliateNav" aria-label="推广用户导航">
        <button
          v-for="item in nav"
          :key="item.path"
          type="button"
          :class="{ active: selected === item.path }"
          @click="go(item.path)"
        >
          <component :is="item.icon" />
          <span>{{ item.label }}</span>
        </button>
      </nav>
      <div class="affiliateSidebarFooter">
        <a-button class="affiliateWithdrawCta" type="primary" block @click="go('/affiliate/withdrawals')">
          <template #icon><WalletOutlined /></template>
          申请提现
        </a-button>
        <div class="affiliateAccount">
          <span>{{ auth.user?.username || auth.user?.email || '推广用户' }}</span>
          <a-button type="text" size="small" @click="logout">
            <template #icon><LogoutOutlined /></template>
            退出
          </a-button>
        </div>
      </div>
    </aside>

    <div class="affiliateMain">
      <header class="affiliateHeader">
        <a-button v-if="isMobile" type="text" shape="circle" aria-label="打开导航" @click="drawerOpen = true">
          <template #icon><MenuFoldOutlined /></template>
        </a-button>
        <strong>{{ title }}</strong>
        <div class="affiliateHeaderIdentity">
          <span class="affiliateHeaderUser">{{ auth.user?.email || '' }}</span>
          <small>推广用户</small>
        </div>
      </header>
      <main class="affiliateContent">
        <RouterView />
      </main>
    </div>

    <a-drawer v-model:open="drawerOpen" root-class-name="affiliateDrawer" placement="left" :width="280" :closable="false">
      <div class="affiliateBrand affiliateDrawerBrand">
        <span class="affiliateBrandMark">S</span>
        <div class="affiliateBrandCopy">
          <strong>Sub2Rebate</strong>
          <span>返利推广中心</span>
        </div>
      </div>
      <nav class="affiliateNav" aria-label="推广用户导航">
        <button
          v-for="item in nav"
          :key="item.path"
          type="button"
          :class="{ active: selected === item.path }"
          @click="go(item.path)"
        >
          <component :is="item.icon" />
          <span>{{ item.label }}</span>
        </button>
      </nav>
      <template #footer>
        <a-button class="affiliateWithdrawCta" type="primary" block @click="go('/affiliate/withdrawals')">
          <template #icon><WalletOutlined /></template>
          申请提现
        </a-button>
        <a-button class="affiliateDrawerLogout" block @click="logout">
          <template #icon><LogoutOutlined /></template>
          退出登录
        </a-button>
      </template>
    </a-drawer>
  </div>
</template>
