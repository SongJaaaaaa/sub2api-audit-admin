import { createRouter, createWebHistory } from 'vue-router'
import AdminLayout from '../layouts/AdminLayout.vue'

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
      component: AdminLayout,
      meta: { auth: true },
      children: [
        { path: '', name: 'dashboard', component: () => import('../views/DashboardView.vue') },
        { path: 'sub2api/users', name: 'sub2-users', component: () => import('../views/Sub2ApiUsersView.vue') },
        { path: 'sub2api/models', name: 'sub2-models', component: () => import('../views/Sub2ApiModelStatsView.vue') },
        { path: 'users-quota', name: 'users-quota', component: () => import('../views/UsersQuotaView.vue') },
        { path: 'ledger', name: 'ledger', component: () => import('../views/LedgerAdjustmentListView.vue') },
        { path: 'balance-events', name: 'balance-events', component: () => import('../views/BalanceEventsView.vue') },
        { path: 'gift-quota', name: 'gift', component: () => import('../views/GiftQuotaListView.vue') },
        { path: 'operation-expense', name: 'expense', component: () => import('../views/OperationExpenseView.vue') },
        { path: 'reconcile', name: 'reconcile', component: () => import('../views/ReconcileView.vue') },
        { path: 'exceptions', name: 'exception', component: () => import('../views/ExceptionCenterView.vue') },
        { path: 'audit-log', name: 'audit', component: () => import('../views/AuditLogView.vue') },
        { path: 'admins', name: 'admins', component: () => import('../views/AdminAccountsView.vue') },
      ],
    },
  ],
})

router.beforeEach((to) => {
  const token = localStorage.getItem('adminToken')
  if (to.meta.auth && !token) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (to.name === 'login' && token) {
    return { name: 'dashboard' }
  }

  return true
})
