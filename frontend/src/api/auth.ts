import { http } from './http'
import type { AffiliateUser } from '../features/rebate/types'

export interface AdminInfo {
  id: number
  sub2api_user_id: number | null
  name: string
  username: string | null
  email: string
  status: string
}

export type LoginRes =
  | { identity_type: 'admin'; token: string; admin: AdminInfo }
  | { identity_type: 'affiliate'; token: string; user: AffiliateUser }

export function login(account: string, password: string) {
  return http.post<unknown, LoginRes>('/auth/login', { account, password })
}
