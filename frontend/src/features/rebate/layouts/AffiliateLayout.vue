<script setup lang="ts">
import {
  BellOutlined,
  HomeOutlined,
  LinkOutlined,
  MenuOutlined,
  PoweroffOutlined,
  ProfileOutlined,
  SendOutlined,
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
  { path: '/affiliate/dashboard', label: '仪表盘', icon: HomeOutlined },
  { path: '/affiliate/promotion', label: '推广中心', icon: SendOutlined },
  { path: '/affiliate/team', label: '我的团队', icon: LinkOutlined },
  { path: '/affiliate/rebates', label: '返利明细', icon: ProfileOutlined },
  { path: '/affiliate/withdrawals', label: '提现管理', icon: WalletOutlined },
]

const selected = computed(() => nav.find((item) => route.path.startsWith(item.path))?.path || '/affiliate/dashboard')
const title = computed(() => nav.find((item) => item.path === selected.value)?.label || '推广中心')
const displayName = computed(() => auth.user?.username || auth.user?.email || '未登录')

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
          申请提现
        </a-button>
      </div>
    </aside>

    <div class="affiliateMain">
      <header class="affiliateHeader">
        <div class="affiliateHeaderStart">
          <a-button v-if="isMobile" class="affiliateMenuButton" type="text" shape="circle" aria-label="打开导航" @click="drawerOpen = true">
            <template #icon><MenuOutlined /></template>
          </a-button>
          <strong class="affiliateMobileBrand">Sub2Rebate</strong>
          <strong class="affiliateRouteTitle">{{ title }}</strong>
        </div>
        <div class="affiliateHeaderEnd">
          <a-button shape="circle" size="small" aria-label="通知">
            <template #icon><BellOutlined /></template>
          </a-button>
          <span class="affiliateHeaderDivider" aria-hidden="true"></span>
          <div class="affiliateHeaderIdentity">
            <span class="affiliateHeaderUser">{{ displayName }}</span>
            <small>推广员</small>
          </div>
          <a-button size="small" @click="logout">
            <template #icon><PoweroffOutlined /></template>
            退出
          </a-button>
        </div>
      </header>
      <main class="affiliateContent">
        <RouterView />
      </main>
    </div>

    <a-drawer v-model:open="drawerOpen" root-class-name="affiliateDrawer" placement="left" width="calc(100vw - 16px)" :closable="false">
      <div class="affiliateBrand affiliateDrawerBrand">
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
          申请提现
        </a-button>
      </template>
    </a-drawer>
  </div>
</template>
