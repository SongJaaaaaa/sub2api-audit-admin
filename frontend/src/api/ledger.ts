import { http } from './http'

export interface LedgerAdjustment {
  id: number
  ledger_no: string
  idempotency_key: string
  sub2api_user_id: number
  sub2api_user_email: string | null
  operation: 'increment' | 'decrement'
  amount: string
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
}

export interface AdjustmentListRes {
  items: LedgerAdjustment[]
  total: number
  page: number
  page_size: number
}

export interface AdjustmentRes {
  adjustment: LedgerAdjustment
  message: string
}

export function getLedgerAdjustments(params: {
  page: number
  page_size: number
  status?: 'succeeded' | 'voided' | 'exception' | 'abnormal' | 'all'
  sub2api_user_id?: number | string
}) {
  return http.get<unknown, AdjustmentListRes>('/ledger-adjustments', { params })
}

export function createLedgerAdjustment(data: {
  sub2api_user_id: number
  operation: 'increment' | 'decrement'
  amount: string
  adjust_reason: string
  admin_notes?: string
}) {
  return http.post<unknown, AdjustmentRes>('/ledger-adjustments', data)
}
