import { createRouter, createWebHistory } from 'vue-router'
import PlatformLayout from '../layouts/PlatformLayout.vue'
import { getMemoryToken } from '../app/services/tokenStorage'
import { isAppMode } from '../app/services/platform'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue'),
    },
    {
      path: '/',
      component: PlatformLayout,
      meta: { auth: 'admin' },
      children: [
        { path: 'app', name: 'app-home', meta: { title: '模块首页', appHome: true }, component: () => import('../app/views/AppHomeView.vue') },
        { path: '', name: 'dashboard', meta: { title: '数据总览', subtitle: '核心指标与趋势' }, component: () => import('../views/DashboardView.vue') },
        { path: 'sub2api/users', name: 'sub2-users', meta: { title: 'Sub2API 用户' }, component: () => import('../views/Sub2ApiUsersView.vue') },
        { path: 'sub2api/models', name: 'sub2-models', meta: { title: '消费排行' }, component: () => import('../views/Sub2ApiModelStatsView.vue') },
        { path: 'users-quota', name: 'users-quota', meta: { title: '用户充值' }, component: () => import('../views/UsersQuotaView.vue') },
        { path: 'ledger', name: 'ledger', meta: { title: '收入' }, component: () => import('../views/LedgerAdjustmentListView.vue') },
        { path: 'balance-events', name: 'balance-events', meta: { title: '历史账' }, component: () => import('../views/BalanceEventsView.vue') },
        { path: 'operation-expense', name: 'expense', meta: { title: '支出' }, component: () => import('../views/OperationExpenseView.vue') },
        { path: 'profit', name: 'profit', meta: { title: '利润统计' }, component: () => import('../views/ProfitView.vue') },
        { path: 'exceptions', name: 'exception', meta: { title: '异常中心' }, component: () => import('../views/ExceptionCenterView.vue') },
        { path: 'audit-log', name: 'audit', meta: { title: '操作审计' }, component: () => import('../views/AuditLogView.vue') },
        { path: 'admins', name: 'admins', meta: { title: '管理员账号' }, component: () => import('../views/AdminAccountsView.vue') },
      ],
    },
  ],
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition
    if (to.path !== from.path) return { top: 0 }
    return false
  },
})

router.beforeEach((to) => {
  const adminToken = getMemoryToken()
  if (to.meta.auth === 'admin' && !adminToken) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (to.name === 'login' && adminToken) {
    return { name: isAppMode ? 'app-home' : 'dashboard' }
  }
  if (to.name === 'app-home' && !isAppMode) {
    return { name: 'dashboard' }
  }

  return true
})
