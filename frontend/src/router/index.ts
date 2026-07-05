import { createRouter, createWebHistory } from 'vue-router'
import AdminLayout from '../layouts/AdminLayout.vue'
import AuditLogView from '../views/AuditLogView.vue'
import DashboardView from '../views/DashboardView.vue'
import LoginView from '../views/LoginView.vue'
import ExceptionCenterView from '../views/ExceptionCenterView.vue'
import GiftQuotaListView from '../views/GiftQuotaListView.vue'
import LedgerAdjustmentListView from '../views/LedgerAdjustmentListView.vue'
import OperationExpenseView from '../views/OperationExpenseView.vue'
import ReconcileView from '../views/ReconcileView.vue'
import Sub2ApiModelStatsView from '../views/Sub2ApiModelStatsView.vue'
import Sub2ApiUsersView from '../views/Sub2ApiUsersView.vue'
import UsersQuotaView from '../views/UsersQuotaView.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
    },
    {
      path: '/',
      component: AdminLayout,
      meta: { auth: true },
      children: [
        { path: '', name: 'dashboard', component: DashboardView },
        { path: 'sub2api/users', name: 'sub2-users', component: Sub2ApiUsersView },
        { path: 'sub2api/models', name: 'sub2-models', component: Sub2ApiModelStatsView },
        { path: 'users-quota', name: 'users-quota', component: UsersQuotaView },
        { path: 'ledger', name: 'ledger', component: LedgerAdjustmentListView },
        { path: 'gift-quota', name: 'gift', component: GiftQuotaListView },
        { path: 'operation-expense', name: 'expense', component: OperationExpenseView },
        { path: 'reconcile', name: 'reconcile', component: ReconcileView },
        { path: 'exceptions', name: 'exception', component: ExceptionCenterView },
        { path: 'audit-log', name: 'audit', component: AuditLogView },
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
