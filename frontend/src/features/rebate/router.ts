import type { RouteRecordRaw } from 'vue-router'
import './styles.css'

export const rebateAffiliateRoutes: RouteRecordRaw[] = [
  {
    path: '/affiliate/login',
    name: 'affiliate-login',
    component: () => import('./pages/affiliate/Login.vue'),
    meta: { guest: 'affiliate' },
  },
  {
    path: '/affiliate',
    component: () => import('./layouts/AffiliateLayout.vue'),
    meta: { auth: 'affiliate' },
    children: [
      { path: '', redirect: '/affiliate/dashboard' },
      { path: 'dashboard', name: 'affiliate-dashboard', component: () => import('./pages/affiliate/Dashboard.vue') },
      { path: 'team', name: 'affiliate-team', component: () => import('./pages/affiliate/Team.vue') },
      { path: 'promotion', name: 'affiliate-promotion', component: () => import('./pages/affiliate/Promotion.vue') },
      { path: 'rebates', name: 'affiliate-rebates', component: () => import('./pages/affiliate/RebateRecords.vue') },
      { path: 'withdrawals', name: 'affiliate-withdrawals', component: () => import('./pages/affiliate/Withdrawals.vue') },
    ],
  },
]

export const rebateAdminRoutes: RouteRecordRaw[] = [
  {
    path: '/rebate/dashboard',
    name: 'rebate-admin-dashboard',
    component: () => import('./pages/admin/Dashboard.vue'),
    meta: { auth: 'admin' },
  },
  {
    path: '/rebate/relationships',
    name: 'rebate-admin-relationships',
    component: () => import('./pages/admin/Relationships.vue'),
    meta: { auth: 'admin' },
  },
  {
    path: '/rebate/withdrawals',
    name: 'rebate-admin-withdrawals',
    component: () => import('./pages/admin/Withdrawals.vue'),
    meta: { auth: 'admin' },
  },
  {
    path: '/rebate/config',
    name: 'rebate-admin-config',
    component: () => import('./pages/admin/Config.vue'),
    meta: { auth: 'admin' },
  },
]

export const rebateRoutes: RouteRecordRaw[] = [...rebateAffiliateRoutes, ...rebateAdminRoutes]
