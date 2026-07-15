export type Money = string

export interface PageRes<T> {
  items: T[]
  total: number
  page: number
  page_size: number
}

export interface AffiliateUser {
  id: number
  email: string
  username: string | null
  status: string | null
  invite_code: string | null
  created_at: string | null
}

export interface RebateBalance {
  available_amount: Money
  frozen_amount: Money
  withdrawn_amount: Money
  total_rebate_amount: Money
}

export interface RebateTrendPoint {
  date: string
  amount: Money
}

export interface RebateRecord {
  id: number
  event_id: number | null
  payer_user_id: number
  payer_email: string | null
  receiver_user_id: number
  receiver_email: string | null
  type: 'milestone' | 'stage' | 'legacy_opening'
  level: 1
  source_amount: Money
  rebate_amount: Money
  status: string
  created_at: string | null
}

export type WithdrawalStatus = 'pending' | 'processing' | 'succeeded' | 'rejected' | 'exception'

export interface RebateWithdrawal {
  id: number
  request_no: string
  user_id: number
  user_email: string | null
  amount: Money
  quota_amount: Money
  status: WithdrawalStatus
  reject_reason: string | null
  error_message: string | null
  reviewed_at: string | null
  completed_at: string | null
  created_at: string | null
}

export interface TeamMember {
  user_id: number
  email: string
  username: string | null
  total_recharge_amount: Money
  total_rebate_amount: Money
  milestone_times: number
  joined_at: string | null
}

export interface RelationshipUser {
  user_id: number
  email: string
  username: string | null
  invite_code: string | null
  parent_user_id: number | null
  parent_email: string | null
  direct_count: number
  total_recharge_amount: Money
  total_rebate_amount: Money
  created_at: string | null
}

export interface RelationshipRes extends PageRes<RelationshipUser> {
  user: RelationshipUser
}

export interface AdminDashboard {
  total_users: number
  direct_referral_count: number
  total_rebate_amount: Money
  available_rebate_amount: Money
  frozen_rebate_amount: Money
  withdrawn_amount: Money
  today_rebate_amount: Money
  month_rebate_amount: Money
  pending_withdrawal_count: number
  pending_withdrawal_amount: Money
  rebate_trend: RebateTrendPoint[]
  recent_rebates: RebateRecord[]
  recent_withdrawals: RebateWithdrawal[]
}

export interface AffiliateDashboard {
  user: AffiliateUser
  balance: RebateBalance
  direct_count: number
  converted_count: number
  total_direct_recharge_amount: Money
  pending_withdrawal_amount: Money
  rebate_trend: RebateTrendPoint[]
  recent_rebates: RebateRecord[]
}

export interface PromotionRes {
  invite_code: string
  invite_url: string
  balance: RebateBalance
  direct_count: number
  converted_count: number
  conversion_rate: string
  total_direct_recharge_amount: Money
  items: TeamMember[]
}

export interface RebateConfig {
  milestone_amount: Money
  milestone_reward_amount: Money
  milestone_max_times: number
  stage_amount: Money
  stage_reward_amount: Money
  withdraw_min_amount: Money
  withdraw_daily_limit: number
  withdraw_daily_amount_limit: Money
  withdraw_to_api_quota_rate: Money
  native_recharge_enabled: boolean
  redeem_enabled: boolean
  admin_adjust_enabled: boolean
  rebate_cutover_at: string | null
  updated_at: string | null
}

export type RebateConfigInput = Omit<RebateConfig, 'rebate_cutover_at' | 'updated_at'>

export interface WithdrawConfig {
  min_amount: Money
  daily_limit: number
  daily_amount_limit: Money
  to_api_quota_rate: Money
}

export interface AffiliateWithdrawalsRes extends PageRes<RebateWithdrawal> {
  balance: RebateBalance
  config: WithdrawConfig
  today_count: number
  today_amount: Money
}

export interface UserSearchItem {
  id: number
  email: string
  username: string | null
  status: string | null
}

export interface MutationRes<T = never> {
  message: string
  withdrawal?: T
}
