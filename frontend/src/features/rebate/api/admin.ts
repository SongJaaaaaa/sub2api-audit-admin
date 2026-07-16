import { http } from '../../../api/http'
import type {
  AdminDashboard,
  MutationRes,
  PageRes,
  RebateConfig,
  RebateConfigInput,
  RebateWithdrawal,
  RelationshipRes,
  UserSearchItem,
  WithdrawalStatus,
} from '../types'

const base = '/rebate/admin'

export function getAdminDashboard() {
  return http.get<unknown, AdminDashboard>(`${base}/dashboard`)
}

export function searchSub2Users(keyword: string) {
  return http.get<unknown, PageRes<UserSearchItem>>('/sub2api/users/search', {
    params: { keyword },
  })
}

export function getRelationships(params: { user_id: number; page: number; page_size: number }) {
  return http.get<unknown, RelationshipRes>(`${base}/relationships`, { params })
}

export function getAdminWithdrawals(params: {
  page: number
  page_size: number
  status?: WithdrawalStatus | ''
  keyword?: string
}) {
  return http.get<unknown, PageRes<RebateWithdrawal>>(`${base}/withdrawals`, { params })
}

export function approveWithdrawal(id: number) {
  return http.post<unknown, MutationRes<RebateWithdrawal>>(`${base}/withdrawals/${id}/approve`)
}

export function rejectWithdrawal(id: number, reason: string) {
  return http.post<unknown, MutationRes<RebateWithdrawal>>(`${base}/withdrawals/${id}/reject`, { reason })
}

export function retryWithdrawal(id: number) {
  return http.post<unknown, MutationRes<RebateWithdrawal>>(`${base}/withdrawals/${id}/retry`)
}

export function getRebateConfig() {
  return http.get<unknown, RebateConfig>(`${base}/config`)
}

export function updateRebateConfig(data: RebateConfigInput) {
  return http.put<unknown, RebateConfig & { message: string }>(`${base}/config`, data)
}
