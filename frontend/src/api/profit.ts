import { http } from './http'

export interface ProfitOwner {
  id: number
  name: string
  email: string | null
}

export interface ProfitDay {
  biz_date: string
  income_by_owner: Record<number, string>
  expense_by_owner: Record<number, string>
  income_total: string
  expense_total: string
  profit_total: string
  income_count: number
  expense_count: number
}

export interface ProfitSummary {
  income_total: string
  expense_total: string
  profit_total: string
  income_count: number
  expense_count: number
}

export interface ProfitIncomeDetail {
  id: number
  entry_no: string
  sub2api_user_id: number | null
  sub2api_user_email: string | null
  amount: string
  owner_admin_id: number | null
  owner_name: string
  remark: string | null
  biz_date: string
  created_at: string | null
}

export interface ProfitExpenseDetail {
  id: number
  expense_no: string
  category: string
  amount: string
  owner_admin_id: number | null
  owner_name: string
  remark: string | null
  biz_date: string
  created_at: string | null
}

export interface ProfitSettlement {
  id: number
  batch_no: string
  start_date: string
  end_date: string
  income_total: string
  expense_total: string
  profit_total: string
  income_count: number
  expense_count: number
  status: 'confirmed' | 'reversed'
  created_by: number | null
  operator_name: string | null
  reversed_by: number | null
  reverser_name: string | null
  reversed_at: string | null
  created_at: string | null
}

export interface ProfitSettlementItem {
  id: number
  item_type: 'cash_entry' | 'operation_expense'
  item_id: number
  biz_date: string
  owner_admin_id: number | null
  owner_name: string | null
  reference_no: string
  description: string | null
  amount: string
}

export function getProfitSummary(params: { start_date: string; end_date: string }) {
  return http.get<unknown, { owners: ProfitOwner[]; days: ProfitDay[]; summary: ProfitSummary }>('/profit/summary', { params })
}

export function getProfitDetails(bizDate: string) {
  return http.get<unknown, { income: ProfitIncomeDetail[]; expenses: ProfitExpenseDetail[] }>('/profit/details', { params: { biz_date: bizDate } })
}

export function createProfitSettlement(data: { start_date: string; end_date: string }) {
  return http.post<unknown, { settlement: ProfitSettlement; message: string }>('/profit/settlements', data)
}

export function getProfitSettlements(params: { page: number; page_size: number; status?: string }) {
  return http.get<unknown, { items: ProfitSettlement[]; total: number; page: number; page_size: number }>('/profit/settlements', { params })
}

export function getProfitSettlementItems(id: number) {
  return http.get<unknown, { items: ProfitSettlementItem[] }>(`/profit/settlements/${id}/items`)
}

export function reverseProfitSettlement(id: number) {
  return http.post<unknown, { settlement: ProfitSettlement; message: string }>(`/profit/settlements/${id}/reverse`)
}
