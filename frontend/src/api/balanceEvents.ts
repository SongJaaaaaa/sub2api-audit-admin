import { http } from './http'

export type BalanceEventSource = 'admin_adjustment' | 'balance_redeem' | 'payment_order'
export type BalanceEventDirection = 'increment' | 'decrement'
export type BalanceEventLinkStatus = 'linked' | 'audit_orphan' | 'external'
export type BalanceEventPeriod = 'history' | 'current' | 'all'

export interface BalanceEvent {
  event_at: string
  source: BalanceEventSource
  remote_event_id: number
  sub2api_user_id: number
  user_email: string | null
  username: string | null
  direction: BalanceEventDirection
  amount: string
  notes: string | null
  link_status: BalanceEventLinkStatus
  ledger_adjustment_id: number | null
  ledger_no: string | null
}

export interface BalanceEventParams {
  start_date?: string
  end_date?: string
  user_id?: number | string
  keyword?: string
  source?: BalanceEventSource
  direction?: BalanceEventDirection
  link_status?: BalanceEventLinkStatus
  period?: BalanceEventPeriod
  page?: number
  page_size?: number
}

export interface BalanceEventSummary {
  record_count: number
  user_count: number
  increment_total: string
  decrement_total: string
  net_total: string
  linked_count: number
  external_count: number
  audit_orphan_count: number
  linked_rate: number
}

export interface BalanceEventRes {
  range: {
    start_date: string
    end_date: string
    timezone: string
  }
  cutover_at: string | null
  items: BalanceEvent[]
  total: number
  page: number
  page_size: number
  summary: BalanceEventSummary
}

export function getBalanceEvents(params: BalanceEventParams) {
  return http.get<unknown, BalanceEventRes>('/balance-events', { params })
}

export function exportBalanceEvents(params: BalanceEventParams) {
  return http.get<unknown, Blob>('/balance-events/export', {
    params,
    responseType: 'blob',
  })
}
