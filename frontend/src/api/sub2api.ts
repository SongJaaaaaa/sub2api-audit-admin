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

export interface UsageSummary {
  request_count: number
  user_count: number
  model_count: number
  total_cost: string
  actual_cost: string
}

export interface ModelRank {
  model: string
  request_count: number
  user_count: number
  total_cost: string
  actual_cost: string
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

export function getModelStats(params: {
  from: string
  to: string
  limit?: number
  model?: string
}) {
  return http.get<unknown, ModelStatsRes>('/sub2api/model-stats', { params })
}
