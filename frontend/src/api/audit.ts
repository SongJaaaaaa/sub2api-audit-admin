import { http } from './http'
import type { PageRes } from './finance'

export interface AuditSummary {
  record_count: number
  operator_count: number
  action_count: number
  target_count: number
  high_risk_count: number
  actions: Array<{ action: string; record_count: number }>
}

export interface AuditLog {
  id: number
  admin_id: number | null
  admin_name: string | null
  action: string
  target_type: string
  target_id: number | null
  before_value: unknown
  after_value: unknown
  ip: string | null
  user_agent: string | null
  created_at: string | null
}

export function getAuditLogs(params: {
  page: number
  page_size: number
  action?: string
  admin_id?: string | number
  from?: string
  to?: string
  target_type?: string
  target_id?: string | number
  ip?: string
  keyword?: string
  risk?: '' | 'high'
}) {
  return http.get<unknown, PageRes<AuditLog, AuditSummary>>('/audit-logs', { params })
}
