import { http } from './http'
import type { PageRes } from './finance'

export interface ReconcileBatch {
  id: number
  batch_no: string
  biz_date: string
  cash_total: string
  quota_total: string
  gift_total: string
  sub2api_delta_total: string
  diff_amount: string
  status: 'balanced' | 'diff'
  created_at: string | null
}

export interface ReconcileDiff {
  id: number
  type: string
  title: string
  amount: string
  reason: string | null
}

export function getReconciliations(params: { page: number; page_size: number }) {
  return http.get<unknown, PageRes<ReconcileBatch>>('/reconciliations', { params })
}

export function createReconciliation(data: { biz_date: string }) {
  return http.post<unknown, { batch: ReconcileBatch; message: string }>('/reconciliations', data)
}

export function getReconciliationDiffs(id: number) {
  return http.get<unknown, { items: ReconcileDiff[] }>(`/reconciliations/${id}/diffs`)
}
