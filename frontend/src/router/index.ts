import { createRouter, createWebHistory } from 'vue-router'
import PlatformLayout from '../layouts/PlatformLayout.vue'
import { getMemoryToken } from '../app/services/tokenStorage'
import { isAppMode } from '../app/services/platform'

const loadSub2Users = () => import('../views/Sub2ApiUsersView.vue')
const loadSub2Models = () => import('../views/Sub2ApiModelStatsView.vue')
const loadUsersQuota = () => import('../views/UsersQuotaView.vue')
const loadLedger = () => import('../views/LedgerAdjustmentListView.vue')
const loadBalanceEvents = () => import('../views/BalanceEventsView.vue')
const loadExpenses = () => import('../views/OperationExpenseView.vue')
const loadProfit = () => import('../views/ProfitView.vue')
const loadExceptions = () => import('../views/ExceptionCenterView.vue')
const loadAudit = () => import('../views/AuditLogView.vue')
const loadAdmins = () => import('../views/AdminAccountsView.vue')

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
        { path: 'sub2api/users', name: 'sub2-users', meta: { title: 'Sub2API 用户' }, component: loadSub2Users },
        { path: 'sub2api/users/:userId', name: 'sub2-user-detail', meta: { title: '用户详情', appDetail: true, backTo: 'sub2-users' }, component: loadSub2Users },
        { path: 'sub2api/models', name: 'sub2-models', meta: { title: '消费排行' }, component: loadSub2Models },
        { path: 'sub2api/models/detail', name: 'sub2-model-detail', meta: { title: '模型用户排行', appDetail: true, backTo: 'sub2-models' }, component: loadSub2Models },
        { path: 'users-quota', name: 'users-quota', meta: { title: '用户充值' }, component: loadUsersQuota },
        { path: 'users-quota/:userId/history/:historyId', name: 'users-quota-history-detail', meta: { title: '充值记录详情', appDetail: true, backTo: 'users-quota-detail', backParams: ['userId'], desktopBackTo: 'users-quota' }, component: loadUsersQuota },
        { path: 'users-quota/:userId', name: 'users-quota-detail', meta: { title: '用户充值', appDetail: true, backTo: 'users-quota' }, component: loadUsersQuota },
        { path: 'ledger', name: 'ledger', meta: { title: '收入' }, component: loadLedger },
        { path: 'ledger/:entryId', name: 'income-detail', meta: { title: '收入详情', appDetail: true, backTo: 'ledger' }, component: loadLedger },
        { path: 'balance-events', name: 'balance-events', meta: { title: '历史账' }, component: loadBalanceEvents },
        { path: 'balance-events/:type/:sourceId', name: 'balance-event-detail', meta: { title: '账单详情', appDetail: true, backTo: 'balance-events' }, component: loadBalanceEvents },
        { path: 'operation-expense', name: 'expense', meta: { title: '支出' }, component: loadExpenses },
        { path: 'operation-expense/:expenseId', name: 'expense-detail', meta: { title: '支出详情', appDetail: true, backTo: 'expense' }, component: loadExpenses },
        { path: 'profit', name: 'profit', meta: { title: '利润统计' }, component: loadProfit },
        { path: 'profit/day/:date', name: 'profit-day-detail', meta: { title: '收支明细', appDetail: true, backTo: 'profit' }, component: loadProfit },
        { path: 'profit/settlements/:settlementId', name: 'profit-settlement-detail', meta: { title: '分账明细', appDetail: true, backTo: 'profit' }, component: loadProfit },
        { path: 'exceptions', name: 'exception', meta: { title: '异常中心' }, component: loadExceptions },
        { path: 'exceptions/:adjustmentId', name: 'exception-detail', meta: { title: '异常详情', appDetail: true, backTo: 'exception' }, component: loadExceptions },
        { path: 'audit-log', name: 'audit', meta: { title: '操作审计' }, component: loadAudit },
        { path: 'audit-log/:auditId', name: 'audit-detail', meta: { title: '审计详情', appDetail: true, backTo: 'audit' }, component: loadAudit },
        { path: 'admins', name: 'admins', meta: { title: '管理员账号' }, component: loadAdmins },
        { path: 'admins/:adminId', name: 'admin-detail', meta: { title: '管理员详情', appDetail: true, backTo: 'admins' }, component: loadAdmins },
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
  if (to.meta.appDetail && !isAppMode) {
    return { name: String(to.meta.desktopBackTo || to.meta.backTo || 'dashboard') }
  }

  return true
})
