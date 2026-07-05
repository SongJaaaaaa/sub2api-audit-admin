import { http } from './http'

export interface PageRes<T> {
  items: T[]
  total: number
  page: number
  page_size: number
}

export interface CashEntry {
  id: number
  entry_no: string
  ledger_adjustment_id: number | null
  sub2api_user_id: number | null
  sub2api_user_email: string | null
  direction: 'in' | 'out'
  cash_amount: string
  source: string
  remark: string | null
  created_at: string | null
}

export interface GiftQuotaEntry {
  id: number
  entry_no: string
  ledger_adjustment_id: number | null
  sub2api_user_id: number | null
  sub2api_user_email: string | null
  quota_amount: string
  source: string
  remark: string | null
  created_at: string | null
}

export interface OperationExpense {
  id: number
  expense_no: string
  category: string
  amount: string
  paid_at: string
  remark: string | null
  content_html: string | null
  created_at: string | null
}

export function getCashEntries(params: { page: number; page_size: number; sub2api_user_id?: string | number }) {
  return http.get<unknown, PageRes<CashEntry>>('/finance/cash', { params })
}

export function getGiftEntries(params: { page: number; page_size: number; sub2api_user_id?: string | number }) {
  return http.get<unknown, PageRes<GiftQuotaEntry>>('/finance/gifts', { params })
}

export function getOperationExpenses(params: { page: number; page_size: number; category?: string }) {
  return http.get<unknown, PageRes<OperationExpense>>('/finance/expenses', { params })
}

export function createOperationExpense(data: {
  category: string
  amount: string
  paid_at: string
  remark?: string
  content_html?: string
}) {
  return http.post<unknown, { expense: OperationExpense; message: string }>('/finance/expenses', data)
}
