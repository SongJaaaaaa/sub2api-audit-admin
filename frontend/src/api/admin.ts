import { http } from './http'
import type { PageRes } from './finance'

export interface AdminOption {
  id: number
  name: string
  email: string
  status: string
}

export interface AdminAccount extends AdminOption {
  created_at: string | null
}

export interface AdminSummary {
  admin_count: number
  active_count: number
  disabled_count: number
}

export function getAdminOptions() {
  return http.get<unknown, { items: AdminOption[] }>('/auth/admin-options')
}

export function getAdmins(params: {
  page: number
  page_size: number
  keyword?: string
  status?: string
}) {
  return http.get<unknown, PageRes<AdminAccount, AdminSummary>>('/admins', { params })
}

export function createAdmin(data: {
  name: string
  email: string
  password: string
  password_confirmation: string
  status: 'active' | 'disabled'
}) {
  return http.post<unknown, { message: string; admin: AdminAccount }>('/admins', data)
}
