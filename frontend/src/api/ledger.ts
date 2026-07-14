import { http } from './http'

export interface LedgerAdjustment {
  id: number
  ledger_no: string
  idempotency_key: string
  sub2api_user_id: number
  sub2api_source_id: number | null
  sub2api_user_email: string | null
  operation: 'increment' | 'decrement'
  amount: string
  cash_amount: string
  gift_quota_amount: string
  before_balance: string | null
  after_balance: string | null
  status: 'pending' | 'succeeded' | 'voided' | 'exception'
  adjust_reason: string
  admin_notes: string | null
  sub2api_notes: string | null
  exception_reason: string | null
  called_at: string | null
  confirmed_at: string | null
  created_at: string | null
  created_by: number | null
  operator_name: string | null
  operator_email: string | null
}

export interface LedgerSummary {
  record_count: number
  user_count: number
  increment_total: string
  decrement_total: string
  net_total: string
  cash_total: string
  gift_total: string
  amount_total?: string
  oldest_created_at?: string | null
  over_24h_count?: number
  types?: Array<{ type: string; record_count: number; user_count: number; amount_total: string }>
}

export interface AdjustmentListRes {
  items: LedgerAdjustment[]
  total: number
  page: number
  page_size: number
  summary: LedgerSummary
}

export interface AdjustmentRes {
  adjustment: LedgerAdjustment
  message: string
}

export interface BatchGiftRes {
  items: Array<{
    user_id: number
    status: LedgerAdjustment['status']
    adjustment: LedgerAdjustment
    message: string
  }>
  success_count: number
  failed_count: number
  message: string
}

export function getLedgerAdjustments(params: {
  page: number
  page_size: number
  status?: 'succeeded' | 'voided' | 'exception' | 'abnormal' | 'all'
  sub2api_user_id?: number | string
  sub2api_user_email?: string
  created_by?: number
  start_date?: string
  end_date?: string
  min_amount?: string
  max_amount?: string
}) {
  return http.get<unknown, AdjustmentListRes>('/ledger-adjustments', { params })
}

export function createLedgerAdjustment(data: {
  sub2api_user_id: number
  operation: 'increment' | 'decrement'
  amount: string
  cash_amount?: string
  gift_quota_amount?: string
  adjust_reason: string
  admin_notes?: string
}) {
  return http.post<unknown, AdjustmentRes>('/ledger-adjustments', data)
}

export function createBatchGift(data: {
  user_ids: number[]
  amount: string
  admin_notes?: string
}) {
  return http.post<unknown, BatchGiftRes>('/ledger-adjustments/batch-gift', data, { timeout: 120000 })
}
