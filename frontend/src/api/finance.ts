import { http } from './http'

export interface PageRes<T, S = unknown> {
  items: T[]
  total: number
  page: number
  page_size: number
  summary?: S
}

export interface FinanceSummary {
  record_count: number
  user_count: number
  amount_total: string
  linked_count: number
  unlinked_count: number
  related_cash_count?: number
  missing_cash_count?: number
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
  created_by: number | null
  operator_name: string | null
  operator_email: string | null
  has_related_cash: boolean
  created_at: string | null
}

export interface ExpenseSummary {
  record_count: number
  category_count: number
  amount_total: string
  max_amount: string
  daily_average: string | null
}

export interface ExpenseCategory {
  category: string
  record_count: number
  amount_total: string
}

export interface OperationExpense {
  id: number
  expense_no: string
  category: string
  amount: string
  paid_at: string
  remark: string | null
  content_html: string | null
  created_by: number | null
  operator_name: string | null
  operator_email: string | null
  created_at: string | null
}

export type FinanceHistoryType = 'income' | 'expense' | 'gift'

export interface FinanceHistoryItem {
  type: FinanceHistoryType
  source_id: number
  bill_no: string
  biz_date: string
  sub2api_user_id: number | null
  sub2api_user_email: string | null
  category: string | null
  amount: string
  created_by: number | null
  operator_name: string | null
  operator_email: string | null
  remark: string | null
  created_at: string | null
}

export interface FinanceHistorySummary {
  record_count: number
  income_count: number
  expense_count: number
  gift_count: number
  income_total: string
  expense_total: string
  gift_total: string
}

export interface FinanceHistoryParams {
  page?: number
  page_size?: number
  type?: FinanceHistoryType
  start_date?: string
  end_date?: string
  sub2api_user_id?: number | string
  created_by?: number
  keyword?: string
}

export interface FinanceParams {
  page: number
  page_size: number
  sub2api_user_id?: string | number
  sub2api_user_email?: string
  start_date?: string
  end_date?: string
  created_by?: number
  business_no?: string
  link_status?: 'linked' | 'unlinked' | ''
}

export function getGiftEntries(params: FinanceParams) {
  return http.get<unknown, PageRes<GiftQuotaEntry, FinanceSummary>>('/finance/gifts', { params })
}

export function getOperationExpenses(params: {
  page: number
  page_size: number
  category?: string
  from?: string
  to?: string
  created_by?: number
  min_amount?: string
  max_amount?: string
  keyword?: string
}) {
  return http.get<unknown, PageRes<OperationExpense, ExpenseSummary> & { categories: ExpenseCategory[] }>('/finance/expenses', { params })
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

export function getFinanceHistory(params: FinanceHistoryParams) {
  return http.get<unknown, PageRes<FinanceHistoryItem, FinanceHistorySummary> & { summary: FinanceHistorySummary }>('/finance/history', { params })
}

export function exportFinanceHistory(params: FinanceHistoryParams) {
  return http.get<unknown, Blob>('/finance/history/export', {
    params,
    responseType: 'blob',
  })
}
