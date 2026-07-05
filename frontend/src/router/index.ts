import { createRouter, createWebHistory } from 'vue-router'
import AdminLayout from '../layouts/AdminLayout.vue'
import DashboardView from '../views/DashboardView.vue'
import LoginView from '../views/LoginView.vue'
import PlaceholderView from '../views/PlaceholderView.vue'
import Sub2ApiModelStatsView from '../views/Sub2ApiModelStatsView.vue'
import Sub2ApiUsersView from '../views/Sub2ApiUsersView.vue'

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
        { path: 'users-quota', name: 'users-quota', component: PlaceholderView },
        { path: 'ledger', name: 'ledger', component: PlaceholderView },
        { path: 'gift-quota', name: 'gift', component: PlaceholderView },
        { path: 'operation-expense', name: 'expense', component: PlaceholderView },
        { path: 'reconcile', name: 'reconcile', component: PlaceholderView },
        { path: 'exceptions', name: 'exception', component: PlaceholderView },
        { path: 'audit-log', name: 'audit', component: PlaceholderView },
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
