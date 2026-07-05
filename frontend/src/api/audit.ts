import { http } from './http'
import type { PageRes } from './finance'

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
}) {
  return http.get<unknown, PageRes<AuditLog>>('/audit-logs', { params })
}
