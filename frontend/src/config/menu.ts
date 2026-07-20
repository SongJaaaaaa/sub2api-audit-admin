import {
  AuditOutlined,
  BarChartOutlined,
  DatabaseOutlined,
  DollarOutlined,
  ExceptionOutlined,
  FileDoneOutlined,
  FundOutlined,
  HistoryOutlined,
  HomeOutlined,
  TeamOutlined,
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
  { key: 'sub2-models', label: '消费排行', path: '/sub2api/models', icon: BarChartOutlined },
  { key: 'sub2-users', label: 'Sub2API 用户', path: '/sub2api/users', icon: DatabaseOutlined },
  { key: 'users-quota', label: '用户充值', path: '/users-quota', icon: UserOutlined },
  { key: 'ledger', label: '收入', path: '/ledger', icon: FileDoneOutlined },
  { key: 'expense', label: '支出', path: '/operation-expense', icon: DollarOutlined },
  { key: 'balance-events', label: '历史账', path: '/balance-events', icon: HistoryOutlined },
  { key: 'profit', label: '利润统计', path: '/profit', icon: FundOutlined },
  { key: 'exception', label: '异常中心', path: '/exceptions', icon: ExceptionOutlined },
  { key: 'audit', label: '操作审计', path: '/audit-log', icon: AuditOutlined },
  { key: 'admins', label: '管理员账号', path: '/admins', icon: TeamOutlined },
]
