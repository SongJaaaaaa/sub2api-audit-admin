import { http } from './http'
import type { PageRes } from './finance'

export type ReconcileStatus = 'ok' | 'warning' | 'error'

export interface ReconcileBatch {
  id: number
  batch_no: string
  biz_date: string
  period_start: string | null
  period_end: string | null
  cash_total: string
  quota_total: string
  gift_total: string
  sub2api_delta_total: string
  diff_amount: string
  local_success_count: number
  local_adjustment_net: string
  remote_matched_count: number
  remote_matched_net: string
  external_count: number
  external_net: string
  audit_orphan_count: number
  audit_orphan_net: string
  issue_count: number
  status: ReconcileStatus
  created_at: string | null
  updated_at: string | null
}

export interface ReconcileSummary {
  batch_count: number
  ok_count: number
  warning_count: number
  error_count: number
  diff_count: number
  diff_amount: string
  healthy_rate: number
  last_success_date: string | null
  unreconciled_days: number | null
}

export interface ReconcileDiff {
  id: number
  type: string
  title: string
  amount: string
  reason: string | null
  local_adjustment_id: number | null
  remote_event_id: number | null
  sub2api_user_id: number | null
  direction: 'increment' | 'decrement' | null
  local_amount: string | null
  remote_amount: string | null
}

export function getReconciliations(params: {
  page: number
  page_size: number
  start_date?: string
  end_date?: string
  status?: ReconcileStatus | ''
  has_external?: '' | '0' | '1'
  has_orphan?: '' | '0' | '1'
  created_by?: number
}) {
  return http.get<unknown, PageRes<ReconcileBatch, ReconcileSummary>>('/reconciliations', { params })
}

export function createReconciliation(data: { biz_date: string }) {
  return http.post<unknown, { batch: ReconcileBatch; message: string }>('/reconciliations', data)
}

export function getReconciliationDiffs(id: number) {
  return http.get<unknown, { items: ReconcileDiff[] }>(`/reconciliations/${id}/diffs`)
}
