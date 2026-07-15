import { affiliateHttp } from './affiliateHttp'
import type {
  AffiliateDashboard,
  AffiliateUser,
  AffiliateWithdrawalsRes,
  MutationRes,
  PageRes,
  PromotionRes,
  RebateRecord,
  RebateWithdrawal,
  TeamMember,
} from '../types'

const base = '/affiliate'

export function loginAffiliate(account: string, password: string) {
  return affiliateHttp.post<unknown, { token: string; user: AffiliateUser }>(`${base}/auth/login`, {
    account,
    password,
  })
}

export function getAffiliateMe() {
  return affiliateHttp.get<unknown, { user: AffiliateUser }>(`${base}/auth/me`)
}

export function logoutAffiliate() {
  return affiliateHttp.post<unknown, { message: string }>(`${base}/auth/logout`)
}

export function getAffiliateDashboard() {
  return affiliateHttp.get<unknown, AffiliateDashboard>(`${base}/dashboard`)
}

export function getAffiliateTeam(params: { page: number; page_size: number }) {
  return affiliateHttp.get<unknown, PageRes<TeamMember>>(`${base}/team`, { params })
}

export function getAffiliatePromotion() {
  return affiliateHttp.get<unknown, PromotionRes>(`${base}/promotion`)
}

export function getAffiliateRebateRecords(params: {
  page: number
  page_size: number
  type?: '' | 'milestone' | 'stage'
}) {
  return affiliateHttp.get<unknown, PageRes<RebateRecord>>(`${base}/rebate-records`, { params })
}

export function getAffiliateWithdrawals(params: { page: number; page_size: number }) {
  return affiliateHttp.get<unknown, AffiliateWithdrawalsRes>(`${base}/withdrawals`, { params })
}

export function createAffiliateWithdrawal(amount: string) {
  return affiliateHttp.post<unknown, MutationRes<RebateWithdrawal>>(`${base}/withdrawals`, { amount })
}
