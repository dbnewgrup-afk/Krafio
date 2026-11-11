import { useMemo } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  LayoutDashboard, Sparkles, LayoutTemplate, FolderOpen, Bot, Globe, SearchCheck, Share2, CreditCard, Wallet, HandCoins, Activity, Bell, LifeBuoy, Settings,
  Image, Video, Wand2, Scissors, Lightbulb, Film, Plus, User as UserIcon, HelpCircle, Images,
} from 'lucide-react'
import { AppShell, PanelFooter, type NavSection, type ShellConfig } from '@/shared/layout/AppShell'
import { FabStack } from '@/shared/layout/Fab'
import { TopBanner } from '@/shared/layout/Banners'
import { useAuth } from '@/shared/context/AuthContext'
import { LegalModal } from '@/shared/layout/Legal'
import { MessagesProvider } from '@/shared/messages/MessagesProvider'
import { MessagesMenu } from '@/shared/messages/MessagesMenu'
import { PasswordAlert } from '@/shared/account/PasswordAlert'
import { ContactVerifyGate } from './ContactVerifyGate'
import { http } from '@/api-client/http'
import { number, dateTime } from '@/shared/lib/format'
import { Link } from 'react-router-dom'
import { useState } from 'react'

export default function UserLayout() {
  const { t } = useTranslation()
  const { me, loading, refresh, logout } = useAuth()
  const loc = useLocation()
  const [rejectedTerms, setRejectedTerms] = useState(false)

  const ent = me?.entitlements ?? {}
  const lockedIf = (key: string) => (ent[key] && ent[key].enabled ? undefined : key)

  const sections: NavSection[] = useMemo(
    () => [
      {
        key: 'main',
        groups: [
          { key: 'dash', label: t('user.nav.dashboard'), icon: LayoutDashboard, to: '/app' },
          {
            key: 'studio',
            label: t('user.nav.studio'),
            icon: Sparkles,
            children: [
              { key: 'photo', label: t('features.photo_generate'), to: '/app/create/photo', icon: Image, lockedFeature: lockedIf('photo.generate') },
              { key: 'video', label: t('features.video_generate'), to: '/app/create/video', icon: Video, lockedFeature: lockedIf('video.generate') },
              { key: 'ephoto', label: t('features.photo_edit'), to: '/app/create/edit-photo', icon: Wand2, lockedFeature: lockedIf('photo.edit') },
              { key: 'evideo', label: t('features.video_edit'), to: '/app/create/edit-video', icon: Scissors, lockedFeature: lockedIf('video.edit') },
              { key: 'brain', label: t('features.content_brainstorm'), to: '/app/create/brainstorm', icon: Lightbulb, lockedFeature: lockedIf('content.brainstorm') },
              { key: 'clip', label: t('features.video_clipper'), to: '/app/create/clipper', icon: Film, lockedFeature: lockedIf('video.clipper') },
            ],
          },
          { key: 'tpl', label: t('user.nav.templates'), icon: LayoutTemplate, to: '/app/templates' },
          { key: 'proj', label: t('user.nav.projects'), icon: FolderOpen, to: '/app/projects' },
          { key: 'assets', label: t('user.nav.assets'), icon: Images, to: '/app/assets' },
          { key: 'assistant', label: t('features.chatbot_basic'), icon: Bot, to: '/app/assistant', lockedFeature: lockedIf('chatbot.basic') },
          { key: 'web', label: t('features.website_builder'), icon: Globe, to: '/app/websites', lockedFeature: lockedIf('website.builder') },
          { key: 'seo', label: t('features.seo_basic'), icon: SearchCheck, to: '/app/seo', lockedFeature: lockedIf('seo.basic') },
          { key: 'social', label: t('features.social_integrations'), icon: Share2, to: '/app/social', lockedFeature: lockedIf('social.integrations') },
        ],
      },
      {
        key: 'billing',
        title: t('user.nav.section_account'),
        groups: [
          {
            key: 'plan',
            label: t('user.nav.subscription'),
            icon: CreditCard,
            children: [
              { key: 'sub', label: t('user.nav.my_plan'), to: '/app/subscription' },
              { key: 'addons', label: t('user.nav.addons'), to: '/app/addons' },
              { key: 'custom', label: t('user.nav.custom_packages'), to: '/app/custom-packages' },
            ],
          },
          {
            key: 'wallet',
            label: t('user.nav.wallet'),
            icon: Wallet,
            children: [
              { key: 'balance', label: t('user.nav.balance'), to: '/app/wallet', end: true },
              { key: 'topup', label: t('user.nav.topup'), to: '/app/wallet/topup' },
              { key: 'wd', label: t('user.nav.withdrawals'), to: '/app/wallet/withdrawals' },
            ],
          },
          { key: 'aff', label: t('user.nav.affiliate'), icon: HandCoins, to: '/app/affiliate' },
          { key: 'usage', label: t('user.nav.usage'), icon: Activity, to: '/app/usage' },
        ],
      },
      {
        key: 'more',
        title: t('user.nav.section_more'),
        groups: [
          { key: 'notif', label: t('user.nav.notifications'), icon: Bell, to: '/app/notifications', badge: me?.unread_notifications },
          { key: 'help', label: t('user.nav.messages'), icon: LifeBuoy, to: '/app/support' },
          { key: 'settings', label: t('user.nav.settings'), icon: Settings, to: '/app/settings' },
        ],
      },
    ],
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [t, me],
  )

  if (loading) return null
  if (!me) return <Navigate to={`/login?next=${encodeURIComponent(loc.pathname + loc.search)}`} replace />
  if (me.role === 'admin') return <Navigate to="/admin" replace />
  if (me.status === 'pending_verification') return <Navigate to="/verify" replace />
  if (me.status !== 'active') return <Navigate to="/account-status" replace />
  // Akun buatan Admin: wajib verifikasi email ATAU nomor HP sebelum memakai panel
  if (me.needs_verification) return <ContactVerifyGate />

  const plan = me.plan
  const expSoon = plan?.ends_at && new Date(plan.ends_at).getTime() - Date.now() < 7 * 86400_000
  const subBanner = !plan
    ? null
    : plan.status === 'expired'
      ? { message: t('user.banner.expired', { plan: plan.name }), cta: t('locked.renew'), to: '/app/subscription' }
      : expSoon
        ? { message: t('user.banner.expiring', { plan: plan.name, date: dateTime(plan.ends_at, false) }), cta: t('locked.renew'), to: '/app/subscription' }
        : null

  const quotaPreview = Object.values(ent).filter((e) => e.enabled && e.quota).slice(0, 2)
  const config: ShellConfig = {
    area: 'user',
    home: '/app',
    sections,
    topBanner: (
      <>
        <TopBanner subscription={subBanner} />
        <PasswordAlert />
      </>
    ),
    navbarExtra: <MessagesMenu />,
    sidebarFooter: (
      <div className="grid gap-2">
        <div className="rounded-md border border-line bg-surface p-3">
          <p className="t-small text-muted">{t('user.sidebar.plan')}</p>
          <p className="font-semibold text-fg">{plan?.name ?? t('locked.no_plan')}</p>
          {quotaPreview.map((q) => (
            <p key={q.key} className="t-small mt-1 flex justify-between gap-2 text-muted">
              <span className="truncate">{t(`features.${q.key.replace('.', '_')}`)}</span>
              <span className="tabular-nums text-fg">
                {number(q.remaining ?? 0)}/{number(q.quota ?? 0)}
              </span>
            </p>
          ))}
          <Link to="/app/subscription" className="t-small mt-2 inline-block font-semibold text-primary hover:underline">
            {t('user.sidebar.upgrade')}
          </Link>
        </div>
        <Link to="/faq" className="t-small inline-flex items-center gap-1.5 px-1 text-muted hover:text-fg">
          <HelpCircle className="size-4" aria-hidden />
          {t('footer.help')}
        </Link>
      </div>
    ),
    bottomNav: [
      { key: 'home', label: t('user.nav.home'), to: '/app', icon: LayoutDashboard },
      { key: 'proj', label: t('user.nav.projects'), to: '/app/projects', icon: FolderOpen },
      { key: 'create', label: t('user.nav.create'), to: '/app/create/photo', icon: Plus, emphasis: true },
      { key: 'assets', label: t('user.nav.assets'), to: '/app/assets', icon: Images },
      { key: 'account', label: t('user.nav.account'), to: '/app/settings', icon: UserIcon },
    ],
    profileLinks: [
      { label: t('user.nav.profile'), to: '/app/settings', icon: UserIcon },
      { label: t('user.nav.subscription'), to: '/app/subscription', icon: CreditCard },
      { label: t('user.nav.settings'), to: '/app/settings?tab=preferences', icon: Settings },
      { label: t('footer.help'), to: '/app/support', icon: HelpCircle },
    ],
    footer: <PanelFooter version={`v${import.meta.env.VITE_APP_VERSION ?? '0.1.0'}`} />,
    fab: <div className="max-md:[--fab-base:64px]"><FabStack /></div>,
  }

  // Perubahan versi T&C saat login → modal wajib setujui (§11.2 butir 1)
  const pending = me.pending_terms?.[0]
  return (
    <>
      <MessagesProvider area="user">
        <AppShell config={config} />
      </MessagesProvider>
      {pending && !rejectedTerms && (
        <LegalModal
          type={pending.type}
          open
          mode="agree"
          onClose={() => undefined}
          onReject={() => setRejectedTerms(true)}
          onAgree={async (doc) => {
            await http.post('/me/consents', { document: doc.type, version: doc.version })
            await refresh()
          }}
        />
      )}
      {rejectedTerms && (
        <div className="fixed inset-0 z-modal grid place-items-center bg-[var(--overlay-dark)] p-6">
          <div className="grid max-w-sm gap-4 rounded-xl bg-surface p-6 text-center shadow-k-lg" role="alertdialog" aria-modal="true" aria-labelledby="rej-t">
            <h2 id="rej-t" className="t-h2">{t('legal.must_agree_title')}</h2>
            <p className="t-body text-muted">{t('legal.must_agree_body')}</p>
            <button type="button" className="h-11 rounded-md bg-primary font-semibold text-primary-contrast" onClick={() => setRejectedTerms(false)}>
              {t('legal.review_again')}
            </button>
            <Link to="/contact" className="t-label text-primary hover:underline">{t('account_status.contact_support')}</Link>
            <button type="button" className="t-label text-muted hover:text-fg" onClick={() => void logout()}>
              {t('nav.logout')}
            </button>
          </div>
        </div>
      )}
    </>
  )
}
