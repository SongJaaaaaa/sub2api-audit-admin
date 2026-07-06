import { http } from './http'
import type { ModelRank, UsageSummary } from './sub2api'

export interface UserRank {
  sub2api_user_id: number
  sub2api_user_email: string | null
  total_amount: string
  entry_count: number
}

export interface DashboardStatsRes {
  summary: UsageSummary
  models: ModelRank[]
  recharge_total?: string
  quota_total?: string
  recharge_rank: UserRank[]
  quota_rank: UserRank[]
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
}) {
  return http.get<unknown, DashboardStatsRes>('/dashboard', { params })
}
