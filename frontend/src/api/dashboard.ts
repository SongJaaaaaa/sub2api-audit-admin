import { http } from './http'

export interface DashboardRange {
  start_date: string
  end_date: string
  timezone: string
}

export interface FinanceTrendItem {
  date: string
  cash_total: string
  gift_total: string
  adjustment_in_total: string
  adjustment_out_total: string
  adjustment_net_total: string
}

export interface UsageTrendItem {
  date: string
  request_count: number
  input_tokens: number
  output_tokens: number
  cache_creation_tokens: number
  cache_read_tokens: number
  total_tokens: number
  standard_cost: string
  actual_cost: string
}

export interface RechargeUserRank {
  user_id: number
  email: string | null
  cash_total: string
  entry_count: number
}

export interface UserTokenRank {
  user_id: number
  email: string | null
  request_count: number
  input_tokens: number
  output_tokens: number
  cache_tokens: number
  total_tokens: number
  standard_cost: string
  actual_cost: string
}

export interface UserActualCostRank {
  user_id: number
  email: string | null
  actual_cost: string
  request_count: number
  total_tokens: number
}

export interface ModelTokenRank {
  model: string
  request_count: number
  input_tokens: number
  output_tokens: number
  cache_creation_tokens: number
  cache_read_tokens: number
  total_tokens: number
  standard_cost: string
  actual_cost: string
}

export interface RecentAdjustment {
  id: number
  ledger_no: string
  sub2api_source_id: number | null
  sub2api_user_id: number
  sub2api_user_email: string | null
  operation: 'increment' | 'decrement'
  amount: string
  cash_amount: string
  gift_quota_amount: string
  status: 'succeeded' | 'exception' | 'voided'
  adjust_reason: string
  exception_reason: string | null
  event_at: string | null
}

export interface DashboardStatsRes {
  range: DashboardRange
  cutover_at: string | null
  finance: {
    cash_total: string
    gift_total: string
    adjustment_in_total: string
    adjustment_out_total: string
    adjustment_net_total: string
    trend: FinanceTrendItem[]
  }
  usage: {
    request_count: number
    total_tokens: number
    standard_cost: string
    actual_cost: string
    trend: UsageTrendItem[]
  }
  balance: {
    active_user_count: number
    active_user_balance: string
    total_recharged: string
    as_of: string
  }
  rankings: {
    recharge_users: RechargeUserRank[]
    user_tokens: UserTokenRank[]
    user_actual_cost: UserActualCostRank[]
    models: ModelTokenRank[]
  }
  recent_adjustments: RecentAdjustment[]
  alerts: {
    unlinked_adjustment_count: number
    reconcile_issue_count: number
    external_adjustment_count: number
    audit_orphan_count: number
    last_reconciled_date: string | null
  }
}

export function getDashboardStats(params: {
  start_date?: string
  end_date?: string
  limit?: number
}) {
  return http.get<unknown, DashboardStatsRes>('/dashboard', { params, timeout: 30000 })
}
