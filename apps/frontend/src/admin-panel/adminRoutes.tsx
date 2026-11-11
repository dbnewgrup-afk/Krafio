import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ResourcePage } from './ResourcePage'
import { InboxPage } from '@/shared/messages/InboxPage'
import { TemplateStudio } from './TemplateStudio'
import { HeroEditor } from './HeroEditor'
import { IntegrationPage, FinanceReportPage, BackupPage } from './OpsPages'
import { AdminApprovals, AdminDashboard, AdminEntitlements, AdminInfoPage, AdminSettings, AdminSystem, AdminUsers, AdminWithdrawals, AdminAiUsage, type SettingField } from './AdminPages'

// Struktur Sidebar Admin Panel Final (PRD Bab 13) — 16 grup, setiap grup dengan sub-sidebar.
type Child = { key: string; path: string; el: () => ReactNode }
type Group = { key: string; children: Child[] }

/* Helper elemen agar judul ikut terjemahan saat render */
function R({ resource, k, g, preset, hideCreate }: { resource: string; k: string; g: string; preset?: Record<string, string | number>; hideCreate?: boolean }) {
  const { t, i18n } = useTranslation()
  return <ResourcePage key={k} resource={resource} title={t(`admin.nav.${k}`)} subtitle={i18n.exists(`admin.sub.${k}`) ? t(`admin.sub.${k}`) : undefined} group={t(`admin.nav.${g}`)} preset={preset} hideCreate={hideCreate} />
}
function S({ k, g, fields, info }: { k: string; g: string; fields: SettingField[]; info?: string }) {
  const { t } = useTranslation()
  return <AdminSettings key={k} title={t(`admin.nav.${k}`)} group={t(`admin.nav.${g}`)} fields={fields} info={info ? t(info) : undefined} />
}
function U({ k, status, role }: { k: string; status?: string; role?: 'user' | 'admin' }) {
  const { t } = useTranslation()
  return <AdminUsers key={k} title={t(`admin.nav.${k}`)} status={status} role={role} />
}
function A({ k, type, history }: { k: string; type?: string; history?: boolean }) {
  const { t } = useTranslation()
  return <AdminApprovals key={k} title={t(`admin.nav.${k}`)} type={type} history={history} />
}
function I({ k, body, links }: { k: string; body: string; links?: { to: string; label: string }[] }) {
  const { t } = useTranslation()
  return <AdminInfoPage title={t(`admin.nav.${k}`)} body={t(body)} links={links?.map((l) => ({ ...l, label: t(l.label) }))} />
}
function AI({ k }: { k: string }) {
  const { t } = useTranslation()
  return <AdminAiUsage title={t(`admin.nav.${k}`)} />
}

const social = (p: string): SettingField[] => [
  { key: `social.${p}.enabled`, type: 'bool' },
  { key: `social.${p}.app_id`, type: 'text' },
  { key: `social.${p}.oauth_url`, type: 'text' },
]

export const ADMIN_NAV: Group[] = [
  { key: 'g_overview', children: [
    { key: 'dashboard', path: '/admin', el: () => <AdminDashboard /> },
    { key: 'platform_analytics', path: '/admin/analytics', el: () => <AI k="platform_analytics" /> },
  ] },
  { key: 'g_users', children: [
    { key: 'all_users', path: '/admin/users', el: () => <U k="all_users" /> },
    { key: 'admin_accounts', path: '/admin/users/admins', el: () => <U k="admin_accounts" role="admin" /> },
    { key: 'pending_accounts', path: '/admin/users/pending', el: () => <U k="pending_accounts" status="pending_approval,request_revision" /> },
    { key: 'user_verification', path: '/admin/users/verification', el: () => <U k="user_verification" status="pending_verification" /> },
    { key: 'user_activity', path: '/admin/users/activity', el: () => <R resource="audit-logs" k="user_activity" g="g_users" /> },
    { key: 'suspended_accounts', path: '/admin/users/suspended', el: () => <U k="suspended_accounts" status="suspended" /> },
    { key: 'banned_accounts', path: '/admin/users/banned', el: () => <U k="banned_accounts" status="banned" /> },
  ] },
  { key: 'g_approval', children: [
    { key: 'pending_approvals', path: '/admin/approvals', el: () => <A k="pending_approvals" /> },
    { key: 'account_approval', path: '/admin/approvals/accounts', el: () => <A k="account_approval" type="account" /> },
    { key: 'custom_package_approval', path: '/admin/approvals/custom-packages', el: () => <A k="custom_package_approval" type="custom_package" /> },
    { key: 'withdrawal_approval', path: '/admin/approvals/withdrawals', el: () => <A k="withdrawal_approval" type="withdrawal" /> },
    { key: 'refund_approval', path: '/admin/approvals/refunds', el: () => <A k="refund_approval" type="refund" /> },
    { key: 'integration_approval', path: '/admin/approvals/integrations', el: () => <A k="integration_approval" type="integration" /> },
    { key: 'template_approval', path: '/admin/approvals/templates', el: () => <A k="template_approval" type="template" /> },
    { key: 'approval_history', path: '/admin/approvals/history', el: () => <A k="approval_history" history /> },
  ] },
  { key: 'g_billing', children: [
    { key: 'plans', path: '/admin/billing/plans', el: () => <R resource="plans" k="plans" g="g_billing" /> },
    { key: 'addons', path: '/admin/billing/addons', el: () => <R resource="addons" k="addons" g="g_billing" /> },
    { key: 'custom_packages', path: '/admin/billing/custom-packages', el: () => <R resource="custom-packages" k="custom_packages" g="g_billing" /> },
    { key: 'entitlements', path: '/admin/billing/entitlements', el: () => <AdminEntitlements /> },
    { key: 'coupons', path: '/admin/billing/coupons', el: () => <R resource="coupons" k="coupons" g="g_billing" /> },
    { key: 'usage_quotas', path: '/admin/billing/usage', el: () => <R resource="credit-packs" k="usage_quotas" g="g_billing" /> },
  ] },
  { key: 'g_financial', children: [
    { key: 'payments', path: '/admin/finance/payments', el: () => <R resource="payments" k="payments" g="g_financial" /> },
    { key: 'topups', path: '/admin/finance/topups', el: () => <R resource="topups" k="topups" g="g_financial" /> },
    { key: 'wallet_ledger', path: '/admin/finance/ledger', el: () => <R resource="ledger" k="wallet_ledger" g="g_financial" /> },
    { key: 'withdrawals', path: '/admin/finance/withdrawals', el: () => <AdminWithdrawals /> },
    { key: 'affiliate', path: '/admin/finance/affiliate', el: () => <R resource="commissions" k="affiliate" g="g_financial" /> },
    { key: 'finance_reports', path: '/admin/finance/reports', el: () => <FinanceReportPage /> },
    { key: 'payment_gateway', path: '/admin/finance/gateway', el: () => <IntegrationPage kind="payment" /> },
    { key: 'reconciliation', path: '/admin/finance/reconciliation', el: () => <R resource="webhook-logs" k="reconciliation" g="g_financial" /> },
  ] },
  { key: 'g_ai', children: [
    { key: 'ai_providers', path: '/admin/ai/providers', el: () => <R resource="ai-providers" k="ai_providers" g="g_ai" /> },
    { key: 'ai_models', path: '/admin/ai/models', el: () => <R resource="ai-models" k="ai_models" g="g_ai" /> },
    { key: 'ai_gateway', path: '/admin/ai/gateway', el: () => <S k="ai_gateway" g="g_ai" info="admin.info.credit_costs" fields={[{ key: 'credits.costs', type: 'json' }, { key: 'trial.plan_code', type: 'text' }, { key: 'trial.days', type: 'number' }, { key: 'trial.credits', type: 'number' }]} /> },
    { key: 'ai_agents', path: '/admin/ai/agents', el: () => <R resource="ai-agents" k="ai_agents" g="g_ai" /> },
    { key: 'prompt_library', path: '/admin/ai/prompts', el: () => <R resource="ai-prompts" k="prompt_library" g="g_ai" /> },
    { key: 'ai_usage', path: '/admin/ai/usage', el: () => <R resource="ai-jobs" k="ai_usage" g="g_ai" /> },
    { key: 'token_monitoring', path: '/admin/ai/tokens', el: () => <AI k="token_monitoring" /> },
    { key: 'provider_limits', path: '/admin/ai/limits', el: () => <R resource="ai-providers" k="provider_limits" g="g_ai" /> },
    { key: 'ai_cost', path: '/admin/ai/cost', el: () => <AI k="ai_cost" /> },
    { key: 'provider_health', path: '/admin/ai/health', el: () => <R resource="ai-providers" k="provider_health" g="g_ai" /> },
    { key: 'fallback_settings', path: '/admin/ai/fallback', el: () => <R resource="ai-models" k="fallback_settings" g="g_ai" preset={{ is_fallback: 'true' }} /> },
  ] },
  { key: 'g_templates', children: [
    { key: 'template_studio', path: '/admin/templates/studio', el: () => <TemplateStudio /> },
    { key: 'template_dashboard', path: '/admin/templates', el: () => <R resource="templates" k="template_dashboard" g="g_templates" preset={{ sort: '-usage_count' }} /> },
    { key: 'all_templates', path: '/admin/templates/all', el: () => <R resource="templates" k="all_templates" g="g_templates" /> },
    { key: 'create_template', path: '/admin/templates/create', el: () => <R resource="templates" k="create_template" g="g_templates" preset={{ status: 'draft' }} /> },
    { key: 'ai_template_generator', path: '/admin/templates/ai-generator', el: () => <I k="ai_template_generator" body="admin.info.ai_template_generator" links={[{ to: '/admin/ai/providers', label: 'admin.nav.ai_providers' }, { to: '/admin/templates/create', label: 'admin.nav.create_template' }]} /> },
    { key: 'template_editor', path: '/admin/templates/editor', el: () => <R resource="templates" k="template_editor" g="g_templates" /> },
    { key: 'photo_templates', path: '/admin/templates/photo', el: () => <R resource="templates" k="photo_templates" g="g_templates" preset={{ type: 'photo' }} /> },
    { key: 'video_templates', path: '/admin/templates/video', el: () => <R resource="templates" k="video_templates" g="g_templates" preset={{ type: 'video' }} /> },
    { key: 'website_templates', path: '/admin/templates/website', el: () => <R resource="templates" k="website_templates" g="g_templates" preset={{ type: 'website' }} /> },
    { key: 'social_templates', path: '/admin/templates/social', el: () => <R resource="templates" k="social_templates" g="g_templates" preset={{ type: 'social' }} /> },
    { key: 'business_templates', path: '/admin/templates/business', el: () => <R resource="templates" k="business_templates" g="g_templates" preset={{ type: 'business' }} /> },
    { key: 'template_categories', path: '/admin/templates/categories', el: () => <R resource="template-categories" k="template_categories" g="g_templates" /> },
    { key: 'template_assets', path: '/admin/templates/assets', el: () => <R resource="uploads" k="template_assets" g="g_templates" /> },
    { key: 'template_access', path: '/admin/templates/access', el: () => <R resource="templates" k="template_access" g="g_templates" preset={{ is_premium: 'true' }} /> },
    { key: 'featured_templates', path: '/admin/templates/featured', el: () => <R resource="templates" k="featured_templates" g="g_templates" preset={{ is_featured: 'true' }} /> },
    { key: 'draft_templates', path: '/admin/templates/drafts', el: () => <R resource="templates" k="draft_templates" g="g_templates" preset={{ status: 'draft' }} /> },
    { key: 'archived_templates', path: '/admin/templates/archived', el: () => <R resource="templates" k="archived_templates" g="g_templates" preset={{ status: 'archived' }} /> },
    { key: 'template_analytics', path: '/admin/templates/analytics', el: () => <R resource="templates" k="template_analytics" g="g_templates" preset={{ sort: '-usage_count' }} hideCreate /> },
  ] },
  { key: 'g_media', children: [
    { key: 'image_generation', path: '/admin/media/images', el: () => <R resource="ai-jobs" k="image_generation" g="g_media" preset={{ type: 'photo_generate' }} /> },
    { key: 'video_generation', path: '/admin/media/videos', el: () => <R resource="ai-jobs" k="video_generation" g="g_media" preset={{ type: 'video_generate' }} /> },
    { key: 'ai_editing', path: '/admin/media/editing', el: () => <R resource="ai-jobs" k="ai_editing" g="g_media" preset={{ type: 'photo_edit' }} /> },
    { key: 'video_clipper', path: '/admin/media/clipper', el: () => <R resource="ai-jobs" k="video_clipper" g="g_media" preset={{ type: 'video_clipper' }} /> },
    { key: 'media_jobs', path: '/admin/media/jobs', el: () => <R resource="ai-jobs" k="media_jobs" g="g_media" /> },
    { key: 'asset_storage', path: '/admin/media/storage', el: () => <R resource="uploads" k="asset_storage" g="g_media" /> },
  ] },
  { key: 'g_website', children: [
    { key: 'website_templates_wb', path: '/admin/websites/templates', el: () => <R resource="templates" k="website_templates_wb" g="g_website" preset={{ type: 'website' }} /> },
    { key: 'user_websites', path: '/admin/websites', el: () => <R resource="websites" k="user_websites" g="g_website" /> },
    { key: 'website_projects', path: '/admin/websites/projects', el: () => <R resource="projects" k="website_projects" g="g_website" preset={{ type: 'website' }} /> },
    { key: 'domains', path: '/admin/websites/domains', el: () => <R resource="websites" k="domains" g="g_website" /> },
    { key: 'deployments', path: '/admin/websites/deployments', el: () => <R resource="websites" k="deployments" g="g_website" preset={{ status: 'published' }} /> },
  ] },
  { key: 'g_seo', children: [
    { key: 'seo_dashboard', path: '/admin/seo', el: () => <R resource="seo-audits" k="seo_dashboard" g="g_seo" /> },
    { key: 'seo_rules', path: '/admin/seo/rules', el: () => <R resource="seo-rules" k="seo_rules" g="g_seo" /> },
    { key: 'algorithm_settings', path: '/admin/seo/algorithm', el: () => <S k="algorithm_settings" g="g_seo" fields={[{ key: 'seo.ruleset_version', type: 'text' }]} info="admin.info.seo_algorithm" /> },
    { key: 'crawl_jobs', path: '/admin/seo/crawl', el: () => <R resource="seo-audits" k="crawl_jobs" g="g_seo" /> },
    { key: 'seo_reports', path: '/admin/seo/reports', el: () => <R resource="seo-audits" k="seo_reports" g="g_seo" /> },
  ] },
  { key: 'g_social', children: [
    { key: 'whatsapp', path: '/admin/social/whatsapp', el: () => <IntegrationPage kind="whatsapp" /> },
    { key: 'facebook', path: '/admin/social/facebook', el: () => <S k="facebook" g="g_social" info="admin.info.social_official" fields={social('facebook')} /> },
    { key: 'instagram', path: '/admin/social/instagram', el: () => <S k="instagram" g="g_social" info="admin.info.social_official" fields={social('instagram')} /> },
    { key: 'tiktok', path: '/admin/social/tiktok', el: () => <S k="tiktok" g="g_social" info="admin.info.social_official" fields={social('tiktok')} /> },
    { key: 'threads', path: '/admin/social/threads', el: () => <S k="threads" g="g_social" info="admin.info.social_official" fields={social('threads')} /> },
    { key: 'webhook_logs', path: '/admin/social/webhooks', el: () => <R resource="webhook-logs" k="webhook_logs" g="g_social" /> },
  ] },
  { key: 'g_communication', children: [
    { key: 'smtp_settings', path: '/admin/communication/smtp', el: () => <IntegrationPage kind="smtp" /> },
    { key: 'email_templates', path: '/admin/communication/email-templates', el: () => <R resource="email-templates" k="email_templates" g="g_communication" /> },
    { key: 'otp_settings', path: '/admin/communication/otp', el: () => <S k="otp_settings" g="g_communication" fields={[{ key: 'otp.ttl_minutes', type: 'number' }, { key: 'otp.resend_seconds', type: 'number' }, { key: 'otp.max_attempts', type: 'number' }, { key: 'otp.hourly_limit', type: 'number' }, { key: 'registration.mode', type: 'select', options: ['auto', 'manual'] }]} /> },
    { key: 'whatsapp_templates', path: '/admin/communication/whatsapp-templates', el: () => <R resource="wa-templates" k="whatsapp_templates" g="g_communication" /> },
    { key: 'mail_logs', path: '/admin/communication/mail-logs', el: () => <R resource="mail-logs" k="mail_logs" g="g_communication" /> },
    { key: 'whatsapp_logs', path: '/admin/communication/whatsapp-logs', el: () => <R resource="wa-logs" k="whatsapp_logs" g="g_communication" /> },
    { key: 'notification_center', path: '/admin/communication/notifications', el: () => <R resource="banners" k="notification_center" g="g_communication" /> },
  ] },
  { key: 'g_content', children: [
    { key: 'dashboard_hero', path: '/admin/content/hero', el: () => <HeroEditor /> },
    { key: 'frontpage', path: '/admin/content/frontpage', el: () => <S k="frontpage" g="g_content" fields={[{ key: 'general.app_name', type: 'text' }, { key: 'cs.hours', type: 'text' }, { key: 'cs.reply_estimate', type: 'text' }, { key: 'social.links', type: 'json' }, { key: 'maintenance.active', type: 'bool' }, { key: 'maintenance.message', type: 'textarea' }]} /> },
    { key: 'banners', path: '/admin/content/banners', el: () => <R resource="banners" k="banners" g="g_content" /> },
    { key: 'blog', path: '/admin/content/blog', el: () => <R resource="blog-posts" k="blog" g="g_content" /> },
    { key: 'faq', path: '/admin/content/faq', el: () => <R resource="faqs" k="faq" g="g_content" /> },
    { key: 'legal_documents', path: '/admin/content/legal', el: () => <R resource="legal-documents" k="legal_documents" g="g_content" /> },
    { key: 'seo_metadata', path: '/admin/content/seo-metadata', el: () => <S k="seo_metadata" g="g_content" fields={[{ key: 'seo.meta_title', type: 'text' }, { key: 'seo.meta_description', type: 'textarea' }]} /> },
  ] },
  { key: 'g_system', children: [
    { key: 'server', path: '/admin/system/server', el: () => <AdminSystem tab="server" /> },
    { key: 'database', path: '/admin/system/database', el: () => <AdminSystem tab="database" /> },
    { key: 'redis', path: '/admin/system/redis', el: () => <AdminSystem tab="redis" /> },
    { key: 'queue', path: '/admin/system/queue', el: () => <AdminSystem tab="queue" /> },
    { key: 'cloudflare', path: '/admin/system/cloudflare', el: () => <S k="cloudflare" g="g_system" info="admin.info.cloudflare" fields={[{ key: 'security.turnstile_site_key', type: 'text' }, { key: 'security.turnstile_secret', type: 'secret' }]} /> },
    { key: 'security_events', path: '/admin/system/security-events', el: () => <R resource="security-events" k="security_events" g="g_system" /> },
    { key: 'backup', path: '/admin/system/backup', el: () => <BackupPage /> },
    { key: 'audit_logs', path: '/admin/system/audit-logs', el: () => <R resource="audit-logs" k="audit_logs" g="g_system" /> },
  ] },
  { key: 'g_support', children: [
    { key: 'inbox', path: '/admin/support/inbox', el: () => <InboxPage area="admin" /> },
    { key: 'support_tickets', path: '/admin/support/tickets', el: () => <R resource="tickets" k="support_tickets" g="g_support" /> },
    { key: 'help_center', path: '/admin/support/help-center', el: () => <R resource="faqs" k="help_center" g="g_support" /> },
  ] },
  { key: 'g_settings', children: [
    { key: 'general_settings', path: '/admin/settings/general', el: () => <S k="general_settings" g="g_settings" fields={[{ key: 'general.app_name', type: 'text' }, { key: 'general.environment_label', type: 'text' }, { key: 'wallet.topup_min', type: 'number' }, { key: 'wallet.topup_max', type: 'number' }, { key: 'wallet.withdraw_min', type: 'number' }, { key: 'affiliate.active', type: 'bool' }, { key: 'affiliate.rate_percent', type: 'number' }, { key: 'affiliate.hold_days', type: 'number' }, { key: 'subscription.grace_days', type: 'number' }, { key: 'billing.ppn_percent', type: 'number' }, { key: 'billing.price_includes_ppn', type: 'bool' }, { key: 'payment.gateway', type: 'select', options: ['manual', 'midtrans', 'xendit', 'tripay'] }]} /> },
    { key: 'admin_security', path: '/admin/settings/security', el: () => <S k="admin_security" g="g_settings" fields={[{ key: 'security.admin_mfa_required', type: 'bool' }, { key: 'security.login_max_attempts', type: 'number' }, { key: 'security.admin_session_minutes', type: 'number' }, { key: 'sso.google', type: 'bool' }]} info="admin.info.admin_security" /> },
    { key: 'feature_flags', path: '/admin/settings/feature-flags', el: () => <R resource="feature-flags" k="feature_flags" g="g_settings" /> },
    { key: 'system_configuration', path: '/admin/settings/system', el: () => <S k="system_configuration" g="g_settings" fields={[{ key: 'billing.margin_alert_percent', type: 'number' }, { key: 'payment.webhook_secret.manual', type: 'secret' }]} /> },
  ] },
]

export const ADMIN_ROUTES = ADMIN_NAV.flatMap((g) => g.children)
