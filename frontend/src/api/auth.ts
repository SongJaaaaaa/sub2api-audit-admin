import { http } from './http'

export interface AdminInfo {
  id: number
  sub2api_user_id: number | null
  name: string
  username: string | null
  email: string
  status: string
}

export interface LoginRes {
  identity_type: 'admin'
  token: string
  admin: AdminInfo
}

export function login(account: string, password: string) {
  return http.post<unknown, LoginRes>('/auth/login', { account, password })
}
