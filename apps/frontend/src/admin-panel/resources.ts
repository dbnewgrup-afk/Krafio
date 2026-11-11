// Definisi resource Admin (selaras backend app/Support/AdminResources.php).
// Label kolom/field memakai kunci i18n `admin.f.<name>`.

export type CellType = 'text' | 'money' | 'date' | 'status' | 'bool' | 'json' | 'user' | 'mono' | 'number' | 'palette'
export type FieldType = 'text' | 'textarea' | 'number' | 'money' | 'select' | 'bool' | 'json' | 'tags' | 'date' | 'palette' | 'secret' | 'features' | 'sections'

export interface ColDef {
  key: string
  type?: CellType
  sortable?: boolean
  primary?: boolean
  mobile?: boolean
}
export interface FieldDef {
  name: string
  type: FieldType
  options?: string[]
  required?: boolean
  hint?: string
  /** Hanya tampil saat membuat data baru (mis. jumlah kredit bonus) */
  createOnly?: boolean
  /** Nilai awal saat membuat data baru (switch default ON bila tidak diisi) */
  default?: boolean
}
export type Ability = 'create' | 'update' | 'delete'
export interface FilterDefLite {
  key: string
  options: string[]
}
export interface ResourceDef {
  endpoint: string
  /** Hanya lihat & ekspor (jejak uang/log). Selaras AdminResources::abilities() di backend. */
  readonly?: boolean
  /** Hak eksplisit; bila kosong: readonly → tidak ada, selain itu CRUD penuh */
  can?: Ability[]
  columns: ColDef[]
  fields?: FieldDef[]
  filters?: FilterDefLite[]
  bulk?: boolean
  /** Status draft/aktif & "Uji" (§7.2 butir 13) */
  testable?: boolean
}

const BOOL = ['true', 'false']
const ENT = ['photo.generate', 'video.generate', 'photo.edit', 'video.edit', 'content.brainstorm', 'templates.premium', 'website.builder', 'seo.basic', 'seo.advanced', 'social.integrations', 'chatbot.basic', 'chatbot.premium', 'video.clipper', 'exports.download']

export const RESOURCES: Record<string, ResourceDef> = {
  plans: {
    endpoint: 'plans',
    bulk: true,
    columns: [{ key: 'name_id', primary: true }, { key: 'code', type: 'mono', mobile: true }, { key: 'price_monthly', type: 'money', sortable: true, mobile: true }, { key: 'price_yearly', type: 'money' }, { key: 'is_recommended', type: 'bool' }, { key: 'is_active', type: 'bool' }, { key: 'sort', type: 'number', sortable: true }],
    filters: [{ key: 'is_active', options: BOOL }],
    fields: [
      { name: 'code', type: 'text', required: true }, { name: 'name_id', type: 'text', required: true }, { name: 'name_en', type: 'text' }, { name: 'description_id', type: 'textarea' }, { name: 'description_en', type: 'textarea' },
      { name: 'price_monthly', type: 'money', required: true }, { name: 'price_yearly', type: 'money', required: true }, { name: 'features', type: 'features', options: ENT },
      { name: 'quotas', type: 'json', hint: 'quotas_hint' }, { name: 'limits', type: 'json', hint: 'limits_hint' }, { name: 'is_recommended', type: 'bool' }, { name: 'is_active', type: 'bool' }, { name: 'sort', type: 'number' },
    ],
  },
  addons: {
    endpoint: 'addons',
    bulk: true,
    columns: [{ key: 'name_id', primary: true }, { key: 'code', type: 'mono' }, { key: 'price', type: 'money', sortable: true, mobile: true }, { key: 'billing', mobile: true }, { key: 'duration_days', type: 'number' }, { key: 'is_active', type: 'bool' }],
    filters: [{ key: 'is_active', options: BOOL }, { key: 'billing', options: ['one_time', 'recurring', 'follow_subscription'] }],
    fields: [
      { name: 'code', type: 'text', required: true }, { name: 'name_id', type: 'text', required: true }, { name: 'name_en', type: 'text' }, { name: 'description_id', type: 'textarea' }, { name: 'description_en', type: 'textarea' },
      { name: 'price', type: 'money', required: true }, { name: 'billing', type: 'select', options: ['one_time', 'recurring', 'follow_subscription'], required: true }, { name: 'duration_days', type: 'number' },
      { name: 'grants', type: 'json', hint: 'grants_hint' }, { name: 'plan_codes', type: 'tags', hint: 'plan_codes_hint' }, { name: 'is_active', type: 'bool' },
    ],
  },
  'custom-packages': {
    endpoint: 'custom-packages',
    columns: [{ key: 'name', primary: true }, { key: 'code', type: 'mono' }, { key: 'price', type: 'money', sortable: true, mobile: true }, { key: 'duration_days', type: 'number' }, { key: 'status', type: 'status', mobile: true }],
    filters: [{ key: 'status', options: ['draft', 'published', 'archived'] }],
    fields: [
      { name: 'code', type: 'text', required: true }, { name: 'name', type: 'text', required: true }, { name: 'description', type: 'textarea' }, { name: 'price', type: 'money', required: true }, { name: 'duration_days', type: 'number', required: true },
      { name: 'features', type: 'features', options: ENT }, { name: 'quotas', type: 'json', hint: 'quotas_hint' }, { name: 'limits', type: 'json', hint: 'limits_hint' }, { name: 'offered_to', type: 'number', hint: 'offered_to_hint' }, { name: 'status', type: 'select', options: ['draft', 'published', 'archived'], required: true },
    ],
  },
  coupons: {
    endpoint: 'coupons',
    bulk: true,
    columns: [{ key: 'code', type: 'mono', primary: true }, { key: 'name' }, { key: 'type', mobile: true }, { key: 'value', type: 'number' }, { key: 'auto_apply', type: 'bool' }, { key: 'is_public', type: 'bool', mobile: true }, { key: 'used', type: 'number', sortable: true, mobile: true }, { key: 'reserved', type: 'number' }, { key: 'ends_at', type: 'date', sortable: true }, { key: 'is_active', type: 'bool' }],
    filters: [{ key: 'type', options: ['percent', 'fixed', 'bonus_credit', 'bonus_days'] }, { key: 'auto_apply', options: BOOL }, { key: 'is_public', options: BOOL }, { key: 'is_active', options: BOOL }, { key: 'campaign', options: ['early_bird', 'flash_sale', 'annual', 'referral', 'winback', 'upgrade'] }],
    fields: [
      { name: 'code', type: 'text', required: true }, { name: 'name', type: 'text' }, { name: 'type', type: 'select', options: ['percent', 'fixed', 'bonus_credit', 'bonus_days'], required: true }, { name: 'value', type: 'number', required: true },
      { name: 'max_discount', type: 'money' }, { name: 'min_purchase', type: 'money' }, { name: 'plan_codes', type: 'tags' }, { name: 'cycles', type: 'tags', hint: 'cycles_hint' }, { name: 'applies_to_addons', type: 'bool', default: false }, { name: 'applies_to_custom', type: 'bool', default: false },
      { name: 'first_purchase_only', type: 'bool', default: false }, { name: 'max_uses', type: 'number' }, { name: 'max_uses_per_user', type: 'number' }, { name: 'duration', type: 'select', options: ['once', 'first_n_months', 'forever'], required: true }, { name: 'duration_months', type: 'number' },
      { name: 'auto_apply', type: 'bool', default: false }, { name: 'campaign', type: 'select', options: ['', 'early_bird', 'flash_sale', 'annual', 'referral', 'winback', 'upgrade'] }, { name: 'starts_at', type: 'date' }, { name: 'ends_at', type: 'date' }, { name: 'is_active', type: 'bool' },
      // Etalase publik: halaman /promo, strip beranda & chip "Promo tersedia" di checkout
      { name: 'is_public', type: 'bool', default: false, hint: 'promo_public_hint' }, { name: 'headline', type: 'text', hint: 'promo_headline_hint' }, { name: 'description', type: 'textarea' },
      { name: 'image', type: 'text', hint: 'promo_image_hint' }, { name: 'sort', type: 'number' },
    ],
  },
  'coupon-redemptions': { endpoint: 'coupon-redemptions', readonly: true, columns: [{ key: 'coupon_id', type: 'number', primary: true }, { key: 'user_id', type: 'number', mobile: true }, { key: 'discount', type: 'money', sortable: true }, { key: 'status', type: 'status', mobile: true }, { key: 'created_at', type: 'date', sortable: true }], filters: [{ key: 'status', options: ['reserved', 'used', 'released'] }] },
  subscriptions: { endpoint: 'subscriptions', can: ['update'], fields: [{ name: 'status', type: 'select', options: ['active', 'past_due', 'cancelled', 'expired'], required: true }, { name: 'ends_at', type: 'date' }], columns: [{ key: 'plan_name', primary: true }, { key: 'user', type: 'user', mobile: true }, { key: 'status', type: 'status', mobile: true }, { key: 'price', type: 'money', sortable: true }, { key: 'cycle' }, { key: 'ends_at', type: 'date', sortable: true }], filters: [{ key: 'status', options: ['active', 'past_due', 'expired', 'cancelled', 'pending_payment'] }] },
  usage: { endpoint: 'usage', can: ['update', 'delete'], fields: [{ name: 'used', type: 'number', required: true }], columns: [{ key: 'entitlement', primary: true }, { key: 'user_id', type: 'number', mobile: true }, { key: 'period', mobile: true }, { key: 'used', type: 'number', sortable: true }] },
  'credit-packs': { endpoint: 'credit-packs', can: ['create', 'update'], fields: [{ name: 'user_id', type: 'number', required: true, createOnly: true }, { name: 'credits_total', type: 'number', required: true, createOnly: true }, { name: 'label', type: 'text', required: true }, { name: 'expires_at', type: 'date' }], columns: [{ key: 'label', primary: true }, { key: 'user_id', type: 'number' }, { key: 'source', mobile: true }, { key: 'credits_total', type: 'number' }, { key: 'credits_used', type: 'number', mobile: true }, { key: 'expires_at', type: 'date', sortable: true }], filters: [{ key: 'source', options: ['plan', 'addon', 'bonus', 'trial'] }] },
  'credit-ledger': { endpoint: 'credit-ledger', readonly: true, columns: [{ key: 'description', primary: true }, { key: 'user_id', type: 'number' }, { key: 'direction', mobile: true }, { key: 'amount', type: 'number', sortable: true, mobile: true }, { key: 'created_at', type: 'date', sortable: true }], filters: [{ key: 'direction', options: ['credit', 'debit'] }] },
  payments: { endpoint: 'payments', readonly: true, columns: [{ key: 'reference', type: 'mono', primary: true }, { key: 'user', type: 'user' }, { key: 'purpose', mobile: true }, { key: 'amount', type: 'money', sortable: true, mobile: true }, { key: 'status', type: 'status', mobile: true }, { key: 'gateway' }, { key: 'created_at', type: 'date', sortable: true }], filters: [{ key: 'status', options: ['pending', 'paid', 'failed', 'expired', 'refunded'] }, { key: 'purpose', options: ['topup', 'subscription', 'addon', 'custom'] }] },
  topups: { endpoint: 'topups', readonly: true, columns: [{ key: 'reference', type: 'mono', primary: true }, { key: 'user', type: 'user' }, { key: 'amount', type: 'money', sortable: true, mobile: true }, { key: 'status', type: 'status', mobile: true }, { key: 'method' }, { key: 'created_at', type: 'date', sortable: true }], filters: [{ key: 'status', options: ['pending', 'paid', 'failed', 'expired'] }] },
  ledger: { endpoint: 'ledger', readonly: true, columns: [{ key: 'description', primary: true }, { key: 'user', type: 'user' }, { key: 'wallet', mobile: true }, { key: 'direction' }, { key: 'amount', type: 'money', sortable: true, mobile: true }, { key: 'balance_after', type: 'money' }, { key: 'reason' }, { key: 'created_at', type: 'date', sortable: true }], filters: [{ key: 'wallet', options: ['topup', 'commission'] }, { key: 'direction', options: ['credit', 'debit'] }] },
  commissions: { endpoint: 'commissions', readonly: true, columns: [{ key: 'referrer', type: 'user', primary: true }, { key: 'referred', type: 'user' }, { key: 'base_amount', type: 'money' }, { key: 'amount', type: 'money', sortable: true, mobile: true }, { key: 'status', type: 'status', mobile: true }, { key: 'available_at', type: 'date' }], filters: [{ key: 'status', options: ['pending', 'approved', 'available', 'reversed', 'paid'] }] },
  'ai-providers': {
    endpoint: 'ai-providers',
    testable: true,
    columns: [{ key: 'name', primary: true }, { key: 'driver', mobile: true }, { key: 'status', type: 'status', mobile: true }, { key: 'health', type: 'status' }, { key: 'priority', type: 'number', sortable: true }, { key: 'api_key_set', type: 'bool' }, { key: 'tokens_used', type: 'number', sortable: true }, { key: 'monthly_token_limit', type: 'number' }],
    filters: [{ key: 'status', options: ['draft', 'active', 'disabled'] }, { key: 'health', options: ['unknown', 'healthy', 'degraded', 'down'] }],
    fields: [
      { name: 'name', type: 'text', required: true }, { name: 'driver', type: 'select', options: ['openai_compatible', 'comfyui', 'custom_http'], required: true }, { name: 'base_url', type: 'text', hint: 'base_url_hint' },
      { name: 'api_key', type: 'secret', hint: 'secret_hint' }, { name: 'capabilities', type: 'features', options: ['text', 'image', 'video'] }, { name: 'status', type: 'select', options: ['draft', 'active', 'disabled'], required: true },
      { name: 'priority', type: 'number' }, { name: 'monthly_token_limit', type: 'number' },
    ],
  },
  'ai-models': {
    endpoint: 'ai-models',
    bulk: true,
    columns: [{ key: 'name', primary: true }, { key: 'model_id', type: 'mono' }, { key: 'provider', type: 'user' }, { key: 'capability', mobile: true }, { key: 'credit_cost', type: 'number', sortable: true, mobile: true }, { key: 'is_fallback', type: 'bool' }, { key: 'is_active', type: 'bool' }],
    filters: [{ key: 'capability', options: ['text', 'image', 'video'] }, { key: 'is_active', options: BOOL }],
    fields: [{ name: 'provider_id', type: 'number', required: true }, { name: 'name', type: 'text', required: true }, { name: 'model_id', type: 'text', required: true }, { name: 'capability', type: 'select', options: ['text', 'image', 'video'], required: true }, { name: 'credit_cost', type: 'number', required: true }, { name: 'is_active', type: 'bool' }, { name: 'is_fallback', type: 'bool' }],
  },
  'ai-prompts': { endpoint: 'ai-prompts', columns: [{ key: 'name', primary: true }, { key: 'key', type: 'mono', mobile: true }, { key: 'version' }, { key: 'status', type: 'status', mobile: true }, { key: 'updated_at', type: 'date', sortable: true }], filters: [{ key: 'status', options: ['draft', 'active', 'archived'] }], fields: [{ name: 'key', type: 'text', required: true }, { name: 'name', type: 'text', required: true }, { name: 'template', type: 'textarea', required: true }, { name: 'version', type: 'text', required: true }, { name: 'status', type: 'select', options: ['draft', 'active', 'archived'], required: true }] },
  'ai-agents': { endpoint: 'ai-agents', columns: [{ key: 'name', primary: true }, { key: 'purpose', mobile: true }, { key: 'status', type: 'status', mobile: true }, { key: 'updated_at', type: 'date', sortable: true }], filters: [{ key: 'status', options: ['draft', 'active', 'disabled'] }], fields: [{ name: 'name', type: 'text', required: true }, { name: 'purpose', type: 'text', required: true }, { name: 'model_id', type: 'number' }, { name: 'instructions', type: 'textarea' }, { name: 'status', type: 'select', options: ['draft', 'active', 'disabled'], required: true }] },
  'ai-jobs': { endpoint: 'ai-jobs', can: ['update'], fields: [{ name: 'status', type: 'select', options: ['cancelled', 'failed'], required: true, hint: 'job_stop_hint' }, { name: 'error', type: 'text' }], columns: [{ key: 'prompt', primary: true }, { key: 'user', type: 'user' }, { key: 'type', mobile: true }, { key: 'status', type: 'status', mobile: true }, { key: 'cost', type: 'number', sortable: true }, { key: 'error' }, { key: 'created_at', type: 'date', sortable: true }], filters: [{ key: 'status', options: ['queued', 'processing', 'completed', 'failed', 'cancelled'] }, { key: 'type', options: ['photo_generate', 'video_generate', 'photo_edit', 'video_edit', 'content_brainstorm', 'video_clipper'] }] },
  uploads: { endpoint: 'uploads', can: ['delete'], columns: [{ key: 'original_name', primary: true }, { key: 'user_id', type: 'number' }, { key: 'mime', mobile: true }, { key: 'size', type: 'number', sortable: true, mobile: true }, { key: 'created_at', type: 'date', sortable: true }] },
  templates: {
    endpoint: 'templates',
    bulk: true,
    columns: [{ key: 'title_id', primary: true }, { key: 'palette', type: 'palette' }, { key: 'type', mobile: true }, { key: 'category' }, { key: 'ratio' }, { key: 'is_premium', type: 'bool' }, { key: 'is_featured', type: 'bool' }, { key: 'status', type: 'status', mobile: true }, { key: 'usage_count', type: 'number', sortable: true }],
    filters: [{ key: 'type', options: ['photo', 'video', 'website', 'social', 'business'] }, { key: 'status', options: ['draft', 'pending', 'active', 'archived'] }, { key: 'is_premium', options: BOOL }, { key: 'is_featured', options: BOOL }],
    fields: [
      { name: 'title_id', type: 'text', required: true }, { name: 'title_en', type: 'text' }, { name: 'description_id', type: 'textarea' }, { name: 'description_en', type: 'textarea' },
      { name: 'type', type: 'select', options: ['photo', 'video', 'website', 'social', 'business'], required: true }, { name: 'category', type: 'text' }, { name: 'ratio', type: 'select', options: ['1:1', '4:5', '9:16', '16:9', '4:3'], required: true },
      { name: 'palette', type: 'palette', required: true }, { name: 'is_premium', type: 'bool' }, { name: 'is_featured', type: 'bool' }, { name: 'status', type: 'select', options: ['draft', 'pending', 'active', 'archived'], required: true },
    ],
  },
  'template-categories': { endpoint: 'template-categories', columns: [{ key: 'name_id', primary: true }, { key: 'slug', type: 'mono', mobile: true }, { key: 'type', mobile: true }, { key: 'sort', type: 'number', sortable: true }], filters: [{ key: 'type', options: ['photo', 'video', 'website', 'social', 'business'] }], fields: [{ name: 'slug', type: 'text', required: true }, { name: 'name_id', type: 'text', required: true }, { name: 'name_en', type: 'text' }, { name: 'type', type: 'select', options: ['photo', 'video', 'website', 'social', 'business'], required: true }, { name: 'sort', type: 'number' }] },
  projects: { endpoint: 'projects', can: ['update', 'delete'], fields: [{ name: 'name', type: 'text', required: true }, { name: 'status', type: 'select', options: ['active', 'archived'], required: true }], columns: [{ key: 'name', primary: true }, { key: 'user_id', type: 'number' }, { key: 'type', mobile: true }, { key: 'status', type: 'status', mobile: true }, { key: 'updated_at', type: 'date', sortable: true }] },
  websites: { endpoint: 'websites', can: ['update', 'delete'], fields: [{ name: 'name', type: 'text', required: true }, { name: 'status', type: 'select', options: ['draft', 'published', 'archived'], required: true }, { name: 'custom_domain', type: 'text' }], columns: [{ key: 'name', primary: true }, { key: 'user', type: 'user' }, { key: 'subdomain', type: 'mono', mobile: true }, { key: 'custom_domain' }, { key: 'status', type: 'status', mobile: true }, { key: 'published_at', type: 'date', sortable: true }], filters: [{ key: 'status', options: ['draft', 'published', 'archived'] }] },
  'seo-rules': { endpoint: 'seo-rules', bulk: true, columns: [{ key: 'name', primary: true }, { key: 'code', type: 'mono' }, { key: 'severity', type: 'status', mobile: true }, { key: 'weight', type: 'number', sortable: true, mobile: true }, { key: 'ruleset_version' }, { key: 'is_active', type: 'bool' }], filters: [{ key: 'severity', options: ['critical', 'warning', 'info'] }, { key: 'is_active', options: BOOL }], fields: [{ name: 'code', type: 'text', required: true }, { name: 'name', type: 'text', required: true }, { name: 'severity', type: 'select', options: ['critical', 'warning', 'info'], required: true }, { name: 'weight', type: 'number', required: true }, { name: 'ruleset_version', type: 'text', required: true }, { name: 'is_active', type: 'bool' }] },
  'seo-audits': { endpoint: 'seo-audits', can: ['delete'], columns: [{ key: 'url', primary: true }, { key: 'user_id', type: 'number' }, { key: 'score', type: 'number', sortable: true, mobile: true }, { key: 'ruleset_version' }, { key: 'status', type: 'status', mobile: true }, { key: 'created_at', type: 'date', sortable: true }] },
  'social-accounts': { endpoint: 'social-accounts', can: ['delete'], columns: [{ key: 'platform', primary: true }, { key: 'user_id', type: 'number' }, { key: 'account_name', mobile: true }, { key: 'status', type: 'status', mobile: true }, { key: 'expires_at', type: 'date' }], filters: [{ key: 'platform', options: ['whatsapp', 'facebook', 'instagram', 'tiktok', 'threads'] }] },
  'webhook-logs': { endpoint: 'webhook-logs', readonly: true, columns: [{ key: 'event', primary: true }, { key: 'source', mobile: true }, { key: 'signature_valid', type: 'bool', mobile: true }, { key: 'status', type: 'status' }, { key: 'created_at', type: 'date', sortable: true }], filters: [{ key: 'signature_valid', options: BOOL }] },
  'email-templates': { endpoint: 'email-templates', columns: [{ key: 'key', type: 'mono', primary: true }, { key: 'subject_id', mobile: true }, { key: 'is_active', type: 'bool', mobile: true }], fields: [{ name: 'key', type: 'text', required: true }, { name: 'subject_id', type: 'text', required: true }, { name: 'body_id', type: 'textarea', required: true }, { name: 'subject_en', type: 'text' }, { name: 'body_en', type: 'textarea' }, { name: 'is_active', type: 'bool' }] },
  faqs: { endpoint: 'faqs', bulk: true, columns: [{ key: 'question_id', primary: true }, { key: 'category', mobile: true }, { key: 'sort', type: 'number', sortable: true }, { key: 'is_active', type: 'bool', mobile: true }], filters: [{ key: 'category', options: ['general', 'billing', 'ai', 'account', 'affiliate'] }, { key: 'is_active', options: BOOL }], fields: [{ name: 'category', type: 'select', options: ['general', 'billing', 'ai', 'account', 'affiliate'], required: true }, { name: 'question_id', type: 'text', required: true }, { name: 'answer_id', type: 'textarea', required: true }, { name: 'question_en', type: 'text' }, { name: 'answer_en', type: 'textarea' }, { name: 'sort', type: 'number' }, { name: 'is_active', type: 'bool' }] },
  banners: { endpoint: 'banners', columns: [{ key: 'title', primary: true }, { key: 'level', type: 'status', mobile: true }, { key: 'placement' }, { key: 'is_active', type: 'bool', mobile: true }, { key: 'ends_at', type: 'date' }], filters: [{ key: 'level', options: ['info', 'warning', 'danger'] }, { key: 'is_active', options: BOOL }], fields: [{ name: 'title', type: 'text', required: true }, { name: 'level', type: 'select', options: ['info', 'warning', 'danger'], required: true }, { name: 'message', type: 'textarea', required: true }, { name: 'link', type: 'text' }, { name: 'placement', type: 'select', options: ['global', 'frontpage', 'user', 'admin'], required: true }, { name: 'is_active', type: 'bool' }, { name: 'starts_at', type: 'date' }, { name: 'ends_at', type: 'date' }] },
  'blog-posts': { endpoint: 'blog-posts', columns: [{ key: 'title_id', primary: true }, { key: 'slug', type: 'mono' }, { key: 'status', type: 'status', mobile: true }, { key: 'published_at', type: 'date', sortable: true, mobile: true }], filters: [{ key: 'status', options: ['draft', 'published', 'archived'] }], fields: [{ name: 'slug', type: 'text', required: true }, { name: 'title_id', type: 'text', required: true }, { name: 'title_en', type: 'text' }, { name: 'body_id', type: 'textarea' }, { name: 'body_en', type: 'textarea' }, { name: 'status', type: 'select', options: ['draft', 'published', 'archived'], required: true }, { name: 'published_at', type: 'date' }] },
  'legal-documents': { endpoint: 'legal-documents', columns: [{ key: 'title_id', primary: true }, { key: 'type', mobile: true }, { key: 'version', type: 'mono', mobile: true }, { key: 'effective_at', type: 'date', sortable: true }, { key: 'requires_reconsent', type: 'bool' }, { key: 'is_active', type: 'bool' }], filters: [{ key: 'type', options: ['terms', 'privacy', 'refund', 'affiliate_terms', 'cookies'] }], fields: [{ name: 'type', type: 'select', options: ['terms', 'privacy', 'refund', 'affiliate_terms', 'cookies'], required: true }, { name: 'version', type: 'text', required: true }, { name: 'effective_at', type: 'date', required: true }, { name: 'title_id', type: 'text', required: true }, { name: 'title_en', type: 'text' }, { name: 'sections_id', type: 'sections', required: true }, { name: 'summary_id', type: 'textarea' }, { name: 'requires_reconsent', type: 'bool' }, { name: 'is_active', type: 'bool' }] },
  'feature-flags': { endpoint: 'feature-flags', bulk: true, columns: [{ key: 'key', type: 'mono', primary: true }, { key: 'description', mobile: true }, { key: 'enabled', type: 'bool', mobile: true }], fields: [{ name: 'key', type: 'text', required: true }, { name: 'description', type: 'text', required: true }, { name: 'enabled', type: 'bool' }] },
  'audit-logs': { endpoint: 'audit-logs', readonly: true, columns: [{ key: 'action', type: 'mono', primary: true }, { key: 'actor', type: 'user', mobile: true }, { key: 'subject_type' }, { key: 'subject_id', type: 'number' }, { key: 'ip', type: 'mono' }, { key: 'meta', type: 'json' }, { key: 'created_at', type: 'date', sortable: true, mobile: true }] },
  'security-events': { endpoint: 'security-events', readonly: true, columns: [{ key: 'type', type: 'mono', primary: true }, { key: 'severity', type: 'status', mobile: true }, { key: 'user', type: 'user' }, { key: 'ip', type: 'mono' }, { key: 'created_at', type: 'date', sortable: true, mobile: true }], filters: [{ key: 'severity', options: ['info', 'warning', 'critical'] }] },
  'mail-logs': { endpoint: 'mail-logs', readonly: true, columns: [{ key: 'subject', primary: true }, { key: 'to', mobile: true }, { key: 'template', type: 'mono' }, { key: 'mailer' }, { key: 'status', type: 'status', mobile: true }, { key: 'error' }, { key: 'created_at', type: 'date', sortable: true }], filters: [{ key: 'status', options: ['sent', 'failed', 'skipped'] }] },
  'wa-logs': { endpoint: 'wa-logs', readonly: true, columns: [{ key: 'event', type: 'mono', primary: true }, { key: 'to', mobile: true }, { key: 'template', type: 'mono' }, { key: 'status', type: 'status', mobile: true }, { key: 'error' }, { key: 'created_at', type: 'date', sortable: true }], filters: [{ key: 'status', options: ['sent', 'failed', 'skipped'] }] },
  'wa-templates': {
    endpoint: 'wa-templates',
    columns: [{ key: 'event', type: 'mono', primary: true }, { key: 'meta_name', type: 'mono', mobile: true }, { key: 'language' }, { key: 'meta_status', type: 'status' }, { key: 'is_active', type: 'bool', mobile: true }],
    fields: [{ name: 'event', type: 'select', options: ['otp', 'welcome', 'password_changed', 'topup_success', 'subscription_active', 'subscription_expiring', 'withdrawal_requested', 'withdrawal_paid', 'withdrawal_rejected', 'test'], required: true }, { name: 'meta_name', type: 'text', required: true, hint: 'meta_name_hint' }, { name: 'language', type: 'text', required: true }, { name: 'body_preview', type: 'textarea', required: true }, { name: 'variables', type: 'tags', hint: 'variables_hint' }, { name: 'is_active', type: 'bool' }],
  },
  tickets: { endpoint: 'tickets', can: ['delete'], columns: [{ key: 'subject', primary: true }, { key: 'number', type: 'mono', mobile: true }, { key: 'user', type: 'user' }, { key: 'guest_email' }, { key: 'status', type: 'status', mobile: true }, { key: 'updated_at', type: 'date', sortable: true }], filters: [{ key: 'status', options: ['open', 'pending', 'resolved', 'closed'] }] },
}

export function abilities(def: ResourceDef): Ability[] {
  if (def.can) return def.can
  return def.readonly || !def.fields ? [] : ['create', 'update', 'delete']
}
