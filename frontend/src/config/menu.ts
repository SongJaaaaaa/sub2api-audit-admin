import {
  AuditOutlined,
  BarChartOutlined,
  DatabaseOutlined,
  DollarOutlined,
  ExceptionOutlined,
  FileDoneOutlined,
  GiftOutlined,
  HomeOutlined,
  ReconciliationOutlined,
  UserOutlined,
} from '@ant-design/icons-vue'
import type { Component } from 'vue'

export interface MenuItem {
  key: string
  label: string
  path: string
  icon: Component
}

export const menuItems: MenuItem[] = [
  { key: 'dashboard', label: '首页', path: '/', icon: HomeOutlined },
  { key: 'sub2-users', label: 'Sub2API 用户', path: '/sub2api/users', icon: DatabaseOutlined },
  { key: 'sub2-models', label: '模型消耗统计', path: '/sub2api/models', icon: BarChartOutlined },
  { key: 'users-quota', label: '用户充值', path: '/users-quota', icon: UserOutlined },
  { key: 'ledger', label: '额度调整记录', path: '/ledger', icon: FileDoneOutlined },
  { key: 'gift', label: '赠送额度', path: '/gift-quota', icon: GiftOutlined },
  { key: 'expense', label: '经营账', path: '/operation-expense', icon: DollarOutlined },
  { key: 'reconcile', label: '对账中心', path: '/reconcile', icon: ReconciliationOutlined },
  { key: 'exception', label: '异常中心', path: '/exceptions', icon: ExceptionOutlined },
  { key: 'audit', label: '操作审计', path: '/audit-log', icon: AuditOutlined },
]
