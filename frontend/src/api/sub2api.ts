import { http } from './http'

export interface Sub2User {
  id: number
  email: string
  username: string | null
  role: string | null
  balance: string
  total_recharged: string
  status: string | null
  last_used_at: string | null
  created_at: string | null
  updated_at: string | null
}

export interface UserSummary {
  user_count: number
  active_count: number
  disabled_count: number
  balance_total: string
  average_balance: string
  negative_balance_count: number
  zero_balance_count: number
}

export interface UserListRes {
  items: Sub2User[]
  total: number
  page: number
  page_size: number
  summary: UserSummary
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

export interface ModelStat {
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

export interface ModelUserRank {
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

export interface ModelStatsSummary {
  model_count: number
  request_count: number
  total_tokens: number
  cache_tokens: number
  cache_rate: number
  standard_cost: string
  actual_cost: string
  top3_token_rate: number
}

export interface ModelStatsRes {
  range: {
    start_date: string
    end_date: string
    timezone: string
  }
  model_source: 'requested'
  selected_model: string | null
  summary: ModelStatsSummary
  models: ModelStat[]
  users: ModelUserRank[]
}

export function getSub2Users(params: {
  page: number
  page_size: number
  keyword?: string
  user_filter?: 'zero_balance' | 'negative_balance' | 'disabled' | ''
  last_used_start?: string
  last_used_end?: string
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
  start_date?: string
  end_date?: string
  model?: string
  limit?: number
}) {
  return http.get<unknown, ModelStatsRes>('/sub2api/model-stats', { params })
}
