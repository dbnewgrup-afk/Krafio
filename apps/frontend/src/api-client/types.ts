export type AccountStatus =
  | 'pending_verification'
  | 'pending_approval'
  | 'request_revision'
  | 'active'
  | 'rejected'
  | 'suspended'
  | 'banned'
  | 'deactivated'

export interface Me {
  id: number
  name: string
  email: string
  phone: string | null
  role: 'user' | 'admin'
  status: AccountStatus
  status_note: string | null
  locale: 'id' | 'en'
  theme: 'system' | 'light' | 'dark'
  loader: 'auto' | 'transparent' | 'dark'
  referral_code: string
  email_verified_at: string | null
  mfa_enabled: boolean
  mfa_passed?: boolean
  avatar_color?: string
  created_at: string
  plan: { code: string; name: string; ends_at: string | null; status: string } | null
  entitlements: Record<string, Entitlement>
  balances: { topup: number; commission: number; commission_reserved: number }
  unread_notifications: number
  pending_terms: LegalDoc[]
  ai_consent: boolean
  auto_renew: boolean
  trial_available: boolean
  scheduled_change: { plan_code: string; effective_at: string | null } | null
  limits: Record<string, number | string | boolean>
}

export interface Entitlement {
  key: string
  enabled: boolean
  unit: 'credit' | 'day' | 'month' | 'access'
  quota: number | null
  used: number
  remaining: number | null
  reset_at: string | null
}

export interface Plan {
  id: number
  code: string
  name: string
  description: string
  price_monthly: number
  price_yearly: number
  is_recommended: boolean
  is_active: boolean
  sort: number
  features: string[]
  quotas: Record<string, number>
  limits: Record<string, number>
}

export interface Addon {
  id: number
  code: string
  name: string
  description: string
  price: number
  billing: 'one_time' | 'recurring' | 'follow_subscription'
  duration_days: number | null
  grants: Record<string, number | boolean>
  is_active: boolean
}

export interface TemplateItem {
  id: number
  title: string
  type: 'photo' | 'video' | 'website' | 'social' | 'business'
  category: string
  ratio: string
  is_premium: boolean
  status: string
  is_featured: boolean
  palette: string[]
  usage_count: number
  description: string
  /** Slug gambar pratinjau di /assets/img/templates/<art>.svg (opsional) */
  art?: string
}

export interface LegalDoc {
  id: number
  type: 'terms' | 'privacy' | 'refund' | 'affiliate_terms' | 'cookies'
  title: string
  version: string
  effective_at: string
  sections: { id: string; heading: string; body: string }[]
  summary_of_changes: string | null
  is_translated: boolean
}

export interface FaqItem {
  id: number
  question: string
  answer: string
  category: string
}

export interface AiJob {
  id: number
  type: string
  prompt: string
  status: 'queued' | 'processing' | 'completed' | 'failed' | 'cancelled'
  progress: number
  cost: number
  result: { preview_palette?: string[]; preview_images?: string[]; text?: string; items?: string[]; note?: string } | null
  error: string | null
  created_at: string
  completed_at: string | null
}

export interface LedgerEntry {
  id: number
  wallet: 'topup' | 'commission'
  direction: 'credit' | 'debit'
  amount: number
  balance_after: number
  description: string
  created_at: string
}

export interface Withdrawal {
  id: number
  amount: number
  status: 'requested' | 'under_review' | 'approved' | 'processing' | 'paid' | 'rejected' | 'cancelled'
  destination: { type: string; provider: string; holder: string; account_masked: string }
  admin_note: string | null
  reference: string | null
  created_at: string
  updated_at: string
}

export interface NotificationItem {
  id: string
  title: string
  body: string
  category: string
  read_at: string | null
  created_at: string
  link: string | null
}

export interface PublicSettings {
  app_name: string
  environment: string
  registration_mode: 'auto' | 'manual'
  topup_min: number
  topup_max: number
  withdraw_min: number
  cs: { hours: string; whatsapp: string | null; reply_estimate: string }
  maintenance: { active: boolean; message: string | null }
  announcement: { level: 'info' | 'warning' | 'danger'; message: string; link: string | null } | null
  turnstile_site_key: string | null
  sso_google: boolean
  social: { platform: string; url: string }[]
  app_version: string
}

export interface Quote {
  normal: number
  prorata: number
  discount: number
  final: number
  coupon: { id: number; code: string | null; name: string; type: string; value: number; auto: boolean } | null
  coupon_error: string | null
  ppn: number
  ppn_inclusive: boolean
  is_upgrade: boolean
  is_downgrade: boolean
  downgrade_at: string | null
}
