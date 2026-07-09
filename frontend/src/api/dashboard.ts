import { http } from './http'
import type { ModelRank, UsageSummary } from './sub2api'

export interface UserRank {
  sub2api_user_id: number
  sub2api_user_email: string | null
  /** 实收现金（本系统账本 cash_amount 之和） */
  cash_amount?: string
  /** 调额总额（现金 + 赠送） */
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
  /** 实收现金（本系统 cash_amount 之和） */
  cash_total?: string
  today_cash_total?: string
  /** 赠送额度（本系统 gift_quota_amount 之和） */
  gift_total?: string
  today_gift_total?: string
  /** 外部调整（直接在 sub2api 操作、无法分类的） */
  external_total?: string
  /** 到账总额 = 现金 + 赠送 + 外部，供趋势聚合使用 */
  recharge_total?: string
  today_recharge_total?: string
  sub2api_balance_total?: string
  quota_total?: string
  /** 入账榜：仅含本系统账本行，按 cash_amount 排序 */
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
