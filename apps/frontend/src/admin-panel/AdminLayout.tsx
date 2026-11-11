import { useEffect, useMemo, useState } from 'react'
import { Navigate, useLocation, useNavigate, Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  LayoutDashboard, Users, ClipboardCheck, Package, Landmark, Cpu, LayoutTemplate, Images, Globe, SearchCheck, Share2, Mail, FileText, ShieldCheck, LifeBuoy, Settings, Activity,
} from 'lucide-react'
import { AppShell, PanelFooter, type NavSection, type ShellConfig } from '@/shared/layout/AppShell'
import { FabStack } from '@/shared/layout/Fab'
import { useAuth } from '@/shared/context/AuthContext'
import { http } from '@/api-client/http'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { StatusChip } from '@/shared/ui/Status'
import { AdminProvider } from './AdminContext'
import { ADMIN_NAV } from './adminRoutes'
import { MessagesProvider } from '@/shared/messages/MessagesProvider'
import { MessagesMenu } from '@/shared/messages/MessagesMenu'

const ICONS = [LayoutDashboard, Users, ClipboardCheck, Package, Landmark, Cpu, LayoutTemplate, Images, Globe, SearchCheck, Share2, Mail, FileText, ShieldCheck, LifeBuoy, Settings]

interface Badges {
  approvals: number
  withdrawals: number
  tickets: number
  failed_jobs: number
  account_approvals: number
}

export default function AdminLayout() {
  const { t } = useTranslation()
  const { me, loading, settings, logout } = useAuth()
  const loc = useLocation()
  const nav = useNavigate()
  const [badges, setBadges] = useState<Badges | null>(null)
  const [warn, setWarn] = useState<number | null>(null)

  const ok = !!me && me.role === 'admin' && me.mfa_passed

  useEffect(() => {
    if (!ok) return
    const load = () => http.get<{ data: Badges }>('/admin/badges').then((r) => setBadges(r.data)).catch(() => undefined)
    load()
    const id = window.setInterval(load, 60_000)
    return () => window.clearInterval(id)
  }, [ok, loc.pathname])

  // Peringatan sesi 60 dtk sebelum habis dengan tombol Perpanjang (§7.2 butir 15)
  useEffect(() => {
    if (!ok) return
    const id = window.setInterval(() => {
      http
        .get<{ data: { expires_in: number } }>('/admin/session/ping')
        .then((r) => setWarn(r.data.expires_in <= 60 ? r.data.expires_in : null))
        .catch(async () => {
          await logout()
          nav('/admin/login?expired=1', { replace: true })
        })
    }, 30_000)
    return () => window.clearInterval(id)
  }, [ok, logout, nav])

  const sections: NavSection[] = useMemo(
    () => [
      {
        key: 'all',
        groups: ADMIN_NAV.map((g, i) => ({
          key: g.key,
          label: t(`admin.nav.${g.key}`),
          icon: ICONS[i],
          badge: g.key === 'g_approval' ? badges?.approvals : g.key === 'g_support' ? badges?.tickets : g.key === 'g_media' ? badges?.failed_jobs : undefined,
          children: g.children.map((c) => ({
            key: c.path,
            label: t(`admin.nav.${c.key}`),
            to: c.path,
            // Cocok persis bila ada saudara dengan path lebih panjang (mis. /admin/templates vs /admin/templates/studio)
            end: c.path === '/admin' || g.children.some((x) => x.path !== c.path && x.path.startsWith(c.path + '/')),
            badge: c.key === 'pending_approvals' ? badges?.approvals : c.key === 'withdrawal_approval' ? badges?.withdrawals : c.key === 'support_tickets' ? badges?.tickets : c.key === 'media_jobs' ? badges?.failed_jobs : c.key === 'account_approval' ? badges?.account_approvals : undefined,
          })),
        })),
      },
    ],
    [t, badges],
  )

  if (loading) return null
  if (!ok) return <Navigate to={`/admin/login?next=${encodeURIComponent(loc.pathname)}`} replace />

  // Banner tipis bila environment ≠ production agar tidak tertukar (§7.2 butir 2)
  const env = settings?.environment ?? 'production'
  const config: ShellConfig = {
    area: 'admin',
    home: '/admin',
    sections,
    topBanner: env !== 'production' ? <div className="bg-warning px-4 py-1 text-center text-[12px] font-bold uppercase text-black">{env === 'local' ? 'LOCAL' : 'STAGING'} — {t('admin.not_production')}</div> : null,
    navbarExtra: <MessagesMenu />,
    sidebarFooter: (
      <div className="grid gap-1.5 rounded-md border border-line bg-surface p-3">
        <p className="t-small flex items-center gap-1.5 text-muted">
          <Activity className="size-4" aria-hidden />
          {t('admin.system_status')}
        </p>
        <StatusChip status={(badges?.failed_jobs ?? 0) > 10 ? 'degraded' : 'healthy'} label={(badges?.failed_jobs ?? 0) > 10 ? t('status.degraded') : t('status.healthy')} />
        <Link to="/admin/system/server" className="t-small text-primary hover:underline">{t('admin.nav.server')}</Link>
      </div>
    ),
    profileLinks: [
      { label: t('admin.nav.admin_security'), to: '/admin/settings/security', icon: ShieldCheck },
      { label: t('admin.nav.general_settings'), to: '/admin/settings/general', icon: Settings },
    ],
    footer: <PanelFooter version={`v${settings?.app_version ?? '0.1.0'}`} statusTo="/admin/system/server" />,
    fab: <FabStack showCs={false} />,
  }

  return (
    <AdminProvider>
      <MessagesProvider area="admin">
        <AppShell config={config} />
      </MessagesProvider>
      <Modal
        open={warn !== null}
        onClose={() => setWarn(null)}
        critical
        size="info"
        title={t('admin.session_warning_title')}
        footer={
          <>
            <Button variant="secondary" onClick={async () => { await logout(); nav('/admin/login', { replace: true }) }}>{t('nav.logout')}</Button>
            <Button onClick={async () => { await http.post('/admin/session/ping'); setWarn(null) }}>{t('admin.extend')}</Button>
          </>
        }
      >
        <p className="t-body text-muted">{t('admin.session_warning_body', { s: warn ?? 0 })}</p>
      </Modal>
    </AdminProvider>
  )
}
