import { http } from './http'
import type { ModelRank, UsageSummary } from './sub2api'

export interface UserRank {
  sub2api_user_id: number
  sub2api_user_email: string | null
  total_amount: string
  entry_count: number
}

export interface UserTokenRank {
  user_id: number
  user_email?: string | null
  request_count: number
  token_total: string
  total_cost: string
}

export interface FinanceTrendItem {
  date: string
  cash_amount: string
  gift_quota_amount: string
  sub2api_adjust_total: string
}

export interface DashboardStatsRes {
  summary: UsageSummary
  today_summary?: UsageSummary
  models: ModelRank[]
  recharge_total?: string
  today_recharge_total?: string
  sub2api_balance_total?: string
  quota_total?: string
  recharge_rank: UserRank[]
  quota_rank: UserRank[]
  user_token_rank?: UserTokenRank[]
  user_cost_rank?: UserTokenRank[]
  finance_trend?: FinanceTrendItem[]
  range: {
    from: string
    to: string
  }
}

export function getDashboardStats(params: {
  from: string
  to: string
  limit?: number
  model_group?: string
  mode?: 'full' | 'overview'
}) {
  return http.get<unknown, DashboardStatsRes>('/dashboard', { params })
}
