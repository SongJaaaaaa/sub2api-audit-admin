import { http } from './http'

export interface Sub2User {
  id: number
  email: string
  username: string | null
  role: string | null
  balance: string
  total_recharged: string
  status: string | null
  created_at: string | null
  updated_at: string | null
}

export interface UserListRes {
  items: Sub2User[]
  total: number
  page: number
  page_size: number
}

export interface Sub2BalanceHistoryItem {
  id: number
  ledger_adjustment_id: number | null
  ledger_no: string
  type: string
  value: string
  operation: 'increment' | 'decrement'
  operator_name: string
  operator_email: string | null
  adjusted_account: string
  adjusted_user_id: number
  before_balance: string | null
  after_balance: string | null
  adjust_reason: string | null
  admin_notes: string | null
  status: string | null
  used_at: string | null
  created_at: string | null
  notes: string | null
}

export interface Sub2BalanceHistoryRes {
  items: Sub2BalanceHistoryItem[]
  total: number
  page: number
  page_size: number
  total_recharged: string | number | null
}

export interface UsageSummary {
  request_count: number
  user_count: number
  model_count: number
  total_cost: string
  actual_cost: string
  token_total?: string
}

export interface ModelRank {
  model: string
  request_count: number
  user_count: number
  total_cost: string
  actual_cost: string
  token_total?: string
}

export interface RechargeSource {
  type: string
  count: number
}

export interface ModelStatsRes {
  summary: UsageSummary
  models: ModelRank[]
  sources: {
    payment_orders_completed: number
    redeem_codes_used: RechargeSource[]
  }
  range: {
    from: string
    to: string
  }
}

export function getSub2Users(params: {
  page: number
  page_size: number
  keyword?: string
}) {
  return http.get<unknown, UserListRes>('/sub2api/users', { params })
}

export function getSub2BalanceHistory(userId: number, params: {
  page: number
  page_size: number
}) {
  return http.get<unknown, Sub2BalanceHistoryRes>(`/sub2api/users/${userId}/balance-history`, { params })
}

export function getModelStats(params: {
  from: string
  to: string
  limit?: number
  model?: string
  user_id?: number | string
}) {
  return http.get<unknown, ModelStatsRes>('/sub2api/model-stats', { params })
}
