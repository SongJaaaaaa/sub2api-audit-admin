<script setup lang="ts">
import { RightOutlined, LogoutOutlined } from '@ant-design/icons-vue'
import { useRouter } from 'vue-router'
import { menuItems } from '../../config/menu'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const auth = useAuthStore()

const hints: Record<string, string> = {
  dashboard: '核心指标与趋势',
  'sub2-models': '模型与用户消耗',
  'sub2-users': '余额与使用状态',
  'users-quota': '充值与赠送',
  ledger: '收入调整流水',
  expense: '运营支出记录',
  'balance-events': '余额变动历史',
  profit: '周期利润与分账',
  exception: '待处理风险记录',
  audit: '管理员操作记录',
  admins: '账号与权限状态',
}

async function openModule(path: string) {
  await router.push(path)
}

async function logout() {
  await auth.logout()
  await router.replace('/login')
}
</script>

<template>
  <section class="appHome" aria-labelledby="appHomeTitle">
    <header class="appHomeHead">
      <div class="appHomeBrand">
        <img class="appHomeMark" src="/favicon.svg" alt="" />
        <div>
          <h1 id="appHomeTitle">Sub2API 审计后台</h1>
          <p>{{ auth.admin?.name || auth.admin?.email || '管理员' }}</p>
        </div>
      </div>
      <button class="appIconButton" type="button" aria-label="退出登录" title="退出登录" @click="logout"><LogoutOutlined /></button>
    </header>

    <div class="appModuleGrid">
      <button v-for="item in menuItems" :key="item.key" class="appModuleCard" type="button" @click="openModule(item.path)">
        <span class="appModuleIcon" aria-hidden="true"><component :is="item.icon" /></span>
        <span class="appModuleTitle"><strong>{{ item.label }}</strong><RightOutlined class="appCardArrow" /></span>
        <span class="appModuleHint">{{ hints[item.key] || '打开模块' }}</span>
      </button>
    </div>
  </section>
</template>
