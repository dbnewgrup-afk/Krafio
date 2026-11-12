import { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Check, X, RotateCcw, Eye, Wallet, ShieldAlert, Banknote, Download, UserPlus } from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import { useApi } from '@/shared/hooks/useApi'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { useToast } from '@/shared/context/ToastContext'
import { Card, CardTitle, KpiCard, PageHeader, Tabs } from '@/shared/ui/Misc'
import { Button } from '@/shared/ui/Button'
import { StatusChip, Badge } from '@/shared/ui/Status'
import { DataTable, useServerList } from '@/shared/ui/DataTable'
import { ConfirmDialog } from '@/shared/ui/ConfirmDialog'
import { Modal } from '@/shared/ui/Modal'
import { SelectField, Switch, TextArea, TextField } from '@/shared/ui/Field'
import { EmptyState, Skeleton } from '@/shared/ui/States'
import { Carousel } from '@/shared/ui/Carousel'
import { dateTime, number, relative, rupiah } from '@/shared/lib/format'
import { useIsMobile } from '@/shared/hooks/useMediaQuery'
import { ResourcePage, InfoCard } from './ResourcePage'
import { useAdmin } from './AdminContext'
import { CreateAccountModal } from './CreateAccount'
import { UserFeaturesCard } from './UserFeatures'
import { cn } from '@/shared/lib/cn'
import { CountUp } from '@/shared/motion/CountUp'
import { RevealCard, GrowBar } from '@/shared/motion/Reveal'
import { DashboardHero } from '@/shared/ui/DashboardHero'
import { WalletShowcase } from '@/shared/ui/card-showcase/WalletShowcase'
import { InboxPeek } from '@/shared/messages/InboxPeek'

/* ===================== Dashboard (Spesifikasi Admin: pusat monitoring) ===================== */
interface Dash {
  users: Record<string, number>
  finance: Record<string, number>
  ai: Record<string, number>
  system: Record<string, number>
  signups_14d: { date: string; count: number }[]
  recent_approvals: { id: number; type: string; title: string; created_at: string }[]
}
/** Monitoring "Perlu tindakan" (Ops::attention) — kategori kosong dirapatkan di belakang. */
function AttentionPanel() {
  const { t } = useTranslation()
  const toast = useToast()
  const a = useApi<{ key: string; severity: 'critical' | 'warning' | 'info'; count: number; to: string; value?: number | null; threshold?: number }[]>('/admin/ops/attention')
  const [busy, setBusy] = useState(false)
  if (!a.data) return null
  const open = a.data.filter((x) => x.count > 0)
  const tone = { critical: 'bg-danger/12 text-danger', warning: 'bg-warning/14 text-warning', info: 'bg-info/12 text-info' }
  const stuck = a.data.find((x) => x.key === 'stuck_jobs')?.count ?? 0
  return (
    <Card className="mt-6">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <CardTitle>{t('admin.ops.title')}</CardTitle>
        {stuck > 0 && (
          <Button
            size="sm"
            variant="secondary"
            loading={busy}
            onClick={async () => {
              setBusy(true)
              try {
                const r = await http.post<{ data: { recovered: number } }>('/admin/ops/recover-stuck-jobs')
                toast.push('success', t('admin.ops.recovered', { n: r.data.recovered }))
                a.reload()
              } finally {
                setBusy(false)
              }
            }}
          >
            {t('admin.ops.recover')}
          </Button>
        )}
      </div>
      {open.length === 0 ? (
        <p className="t-small text-muted">{t('admin.ops.all_clear')}</p>
      ) : (
        <ul className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
          {open.map((x) => (
            <li key={x.key}>
              <Link to={x.to} className="flex items-center justify-between gap-3 rounded-md border border-line px-3 py-2.5 hover:bg-surface-2">
                <span className="text-sm">{t(`admin.ops.k.${x.key}`, { value: x.value ?? '—', threshold: x.threshold })}</span>
                <span className={cn('inline-flex h-6 min-w-6 items-center justify-center rounded-full px-2 text-[12px] font-semibold tabular-nums', tone[x.severity])}>{number(x.count)}</span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </Card>
  )
}

export function AdminDashboard() {
  const { t } = useTranslation()
  useDocTitle(t('admin.nav.dashboard'))
  const isMobile = useIsMobile()
  const { data, error, reload } = useApi<Dash>('/admin/dashboard')
  if (error) return <EmptyState kind="error" title={t('errors.server_title')} action={<Button onClick={reload}>{t('common.retry')}</Button>} />
  if (!data)
    return (
      <>
        <PageHeader title={t('admin.nav.dashboard')} subtitle={t('admin.dash_sub')} />
        <DashboardHero area="admin" />
        <Skeleton className="h-96" />
      </>
    )
  const max = Math.max(1, ...data.signups_14d.map((d) => d.count))
  const kpis = [
    <KpiCard key="u" label={t('admin.kpi.users')} value={<CountUp to={data.users.total} overshoot />} hint={t('admin.kpi.new_today', { n: data.users.new_today })} to="/admin/users" />,
    <KpiCard key="p" label={t('admin.kpi.pending')} value={<CountUp to={data.users.pending} delay={0.05} />} tone="warning" to="/admin/users/pending" />,
    <KpiCard key="r" label={t('admin.kpi.revenue_month')} value={<CountUp to={data.finance.revenue_month} format={rupiah} delay={0.1} />} tone="commission" to="/admin/finance/payments" />,
    <KpiCard key="w" label={t('admin.kpi.withdraw_pending')} value={<CountUp to={data.finance.withdraw_pending} format={rupiah} delay={0.15} />} tone="warning" to="/admin/approvals/withdrawals" />,
  ]
  return (
    <>
      <PageHeader title={t('admin.nav.dashboard')} subtitle={t('admin.dash_sub')} />
      <DashboardHero area="admin" />
      {isMobile ? <Carousel label={t('admin.kpi.label')} itemClass="basis-[78%]" variant="strip">{kpis}</Carousel> : <div className="grid grid-cols-4 gap-4">{kpis.map((k, i) => <RevealCard key={k.key} order={i + 1}>{k}</RevealCard>)}</div>}
      <AttentionPanel />
      <div className="mt-6 grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <RevealCard order={5}>
        <Card>
          <CardTitle>{t('admin.signups_14d')}</CardTitle>
          <div className="flex h-40 items-end gap-1.5" role="img" aria-label={t('admin.signups_14d')}>
            {data.signups_14d.map((d, di) => (
              <div key={d.date} className="group relative flex h-full flex-1 flex-col justify-end">
                <GrowBar index={di} pct={Math.max(3, (d.count / max) * 100)} className="rounded-t-sm bg-primary/80" />
                <span className="pointer-events-none absolute -top-6 left-1/2 hidden -translate-x-1/2 rounded bg-fg px-1.5 text-[11px] text-bg group-hover:block">{d.count}</span>
              </div>
            ))}
          </div>
          <div className="t-small mt-2 flex justify-between text-muted">
            <span>{dateTime(data.signups_14d[0]?.date, false)}</span>
            <span>{dateTime(data.signups_14d.at(-1)?.date, false)}</span>
          </div>
        </Card>
        </RevealCard>
        <RevealCard order={6}>
        <Card>
          <CardTitle action={<Link to="/admin/approvals" className="t-label text-primary hover:underline">{t('common.see_all')}</Link>}>{t('admin.nav.pending_approvals')}</CardTitle>
          {data.recent_approvals.length === 0 ? (
            <p className="t-small text-muted">{t('admin.no_pending')}</p>
          ) : (
            <ul className="divide-y divide-line">
              {data.recent_approvals.map((a) => (
                <li key={a.id} className="flex items-center justify-between gap-3 py-2.5">
                  <div className="min-w-0">
                    <p className="truncate text-sm font-medium">{a.title}</p>
                    <p className="t-small text-muted">{t(`admin.approval_types.${a.type}`)} — {relative(a.created_at)}</p>
                  </div>
                  <StatusChip status="pending" />
                </li>
              ))}
            </ul>
          )}
        </Card>
        </RevealCard>
      </div>
      <div className="mt-4 grid gap-4 md:grid-cols-3">
        <RevealCard order={7}>
        <Card>
          <CardTitle>{t('admin.ai_overview')}</CardTitle>
          <dl className="grid gap-2 text-sm">
            <Row k={t('admin.kpi.jobs_today')} v={number(data.ai.jobs_today)} />
            <Row k={t('admin.kpi.failed_today')} v={number(data.ai.failed_today)} />
            <Row k={t('admin.kpi.active_providers')} v={number(data.ai.active_providers)} />
            <Row k={t('admin.kpi.tokens_month')} v={number(data.ai.tokens_month)} />
          </dl>
        </Card>
        </RevealCard>
        <RevealCard order={8}>
        <Card>
          <CardTitle>{t('admin.finance_overview')}</CardTitle>
          <dl className="grid gap-2 text-sm">
            <Row k={t('admin.kpi.topup_today')} v={rupiah(data.finance.topup_today)} />
            <Row k={t('admin.kpi.pending_payments')} v={number(data.finance.pending_payments)} />
            <Row k={t('admin.kpi.suspended')} v={number(data.users.suspended)} />
          </dl>
        </Card>
        </RevealCard>
        <RevealCard order={9}>
        <Card>
          <CardTitle>{t('admin.system_overview')}</CardTitle>
          <dl className="grid gap-2 text-sm">
            <Row k={t('admin.kpi.queue_backlog')} v={number(data.system.queue_backlog)} />
            <Row k={t('admin.kpi.failed_queue')} v={number(data.system.failed_queue)} />
            <Row k={t('admin.kpi.security_today')} v={number(data.system.security_events_today)} />
          </dl>
          <Link to="/admin/system/server" className="t-label mt-3 inline-block text-primary hover:underline">{t('admin.nav.server')}</Link>
        </Card>
        </RevealCard>
      </div>
      <div className="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <WalletShowcase area="admin" order={10} title={t('cards.platform_title')} />
        <RevealCard order={11}>
          <Card>
            <CardTitle action={<Link to="/admin/support/inbox" className="t-label text-primary hover:underline">{t('common.see_all')}</Link>}>{t('inbox.admin_title')}</CardTitle>
            <InboxPeek />
          </Card>
        </RevealCard>
      </div>
    </>
  )
}
function Row({ k, v }: { k: string; v: React.ReactNode }) {
  return (
    <div className="flex justify-between gap-3">
      <dt className="text-muted">{k}</dt>
      <dd className="font-semibold tabular-nums text-fg">{v}</dd>
    </div>
  )
}

/* ===================== Users ===================== */
interface UserRow {
  id: number
  name: string
  email: string
  phone: string | null
  status: string
  plan: string | null
  created_at: string
  last_login_at: string | null
  must_change_password?: boolean
  needs_verification?: boolean
  created_by_admin?: boolean
  email_verified?: boolean
  phone_verified?: boolean
}
export function AdminUsers({ status, title, role }: { status?: string; title?: string; role?: 'user' | 'admin' }) {
  const { t } = useTranslation()
  const ttl = title ?? t('admin.nav.all_users')
  useDocTitle(ttl)
  const nav = useNavigate()
  const list = useServerList<UserRow>('/admin/users', { status: status ?? '', role: role ?? '' })
  const [creating, setCreating] = useState(false)
  return (
    <>
      <PageHeader title={ttl} crumbs={[{ label: t('admin.nav.g_users') }, { label: ttl }]} actions={<Button icon={<UserPlus className="size-4" aria-hidden />} onClick={() => setCreating(true)}>{t('account_new.button')}</Button>} />
      <CreateAccountModal open={creating} defaultType={role === 'admin' ? 'admin' : 'user'} onClose={() => setCreating(false)} onCreated={() => list.reload()} />
      <DataTable<UserRow>
        list={list}
        exportName="users"
        onRowClick={(r) => nav(`/admin/users/${r.id}`)}
        filters={status ? [] : [{ key: 'status', label: t('common.status'), options: ['pending_verification', 'pending_approval', 'request_revision', 'active', 'rejected', 'suspended', 'banned', 'deactivated'].map((s) => ({ value: s, label: t(`status.${s}`) })) }]}
        columns={[
          {
            key: 'name',
            header: t('fields.name'),
            primary: true,
            sortable: true,
            render: (r) => (
              <span className="inline-flex flex-wrap items-center gap-1.5">
                {r.must_change_password && <span className="size-2 shrink-0 rounded-full bg-danger" aria-hidden />}
                <span>{r.name}</span>
                {r.must_change_password && <span className="inline-flex h-5 items-center rounded-full bg-danger/12 px-2 text-[11px] font-semibold text-danger">{t('account_new.flag_password')}</span>}
                {r.needs_verification && <span className="inline-flex h-5 items-center rounded-full bg-warning/14 px-2 text-[11px] font-semibold text-warning">{t('account_new.flag_verify')}</span>}
              </span>
            ),
          },
          { key: 'email', header: t('fields.email'), mobile: true },
          { key: 'plan', header: t('admin.f.plan'), render: (r) => r.plan ?? '—' },
          { key: 'status', header: t('common.status'), mobile: true, render: (r) => <StatusChip status={r.status} /> },
          { key: 'created_at', header: t('admin.f.created_at'), sortable: true, render: (r) => dateTime(r.created_at, false) },
          { key: 'last_login_at', header: t('admin.f.last_login_at'), sortable: true, render: (r) => (r.last_login_at ? relative(r.last_login_at) : '—') },
        ]}
      />
    </>
  )
}

export function AdminUserDetail() {
  const { id } = useParams()
  const { t } = useTranslation()
  const toast = useToast()
  const { withReauth } = useAdmin()
  const u = useApi<any>(`/admin/users/${id}`)
  useDocTitle(u.data?.name ?? t('admin.nav.all_users'))
  const [tab, setTab] = useState('overview')
  const [statusOpen, setStatusOpen] = useState(false)
  const [newStatus, setNewStatus] = useState('active')
  const [bal, setBal] = useState(false)
  const [balForm, setBalForm] = useState({ wallet: 'topup', direction: 'credit', amount: '', reason: '' })
  const [revealed, setRevealed] = useState<Record<string, string>>({})
  if (!u.data) return <Skeleton className="h-96" />
  const d = u.data
  const reveal = async (field: 'email' | 'phone') => {
    const r = await http.post<{ data: { value: string } }>(`/admin/users/${id}/reveal`, { field })
    setRevealed((x) => ({ ...x, [field]: r.data.value }))
  }
  // Halaman detail memakai tab (§7.2 butir 10)
  const tabs = ['overview', 'subscription', 'wallet', 'ai_usage', 'media_jobs', 'websites', 'affiliate', 'security', 'activity']
  return (
    <>
      <PageHeader
        title={d.name}
        subtitle={<span className="inline-flex items-center gap-2"><StatusChip status={d.status} />{d.plan?.name && <Badge>{d.plan.name}</Badge>}</span>}
        crumbs={[{ label: t('admin.nav.all_users'), to: '/admin/users' }, { label: d.name }]}
        actions={
          <>
            <Button variant="secondary" icon={<Wallet className="size-4" aria-hidden />} onClick={() => setBal(true)}>{t('admin.adjust_balance')}</Button>
            <Button icon={<ShieldAlert className="size-4" aria-hidden />} onClick={() => { setNewStatus(d.status); setStatusOpen(true) }}>{t('admin.change_status')}</Button>
          </>
        }
      />
      <Tabs label={d.name} value={tab} onChange={setTab} items={tabs.map((x) => ({ value: x, label: t(`admin.user_tabs.${x}`) }))} />
      <div className="mt-5">
        {tab === 'overview' && (
          <div className="grid gap-4 lg:grid-cols-2">
            <Card>
              <CardTitle>{t('admin.user_tabs.profile')}</CardTitle>
              <dl className="grid gap-2 text-sm">
                {(['email', 'phone'] as const).map((f) => (
                  <div key={f} className="flex items-center justify-between gap-3">
                    <dt className="text-muted">{t(`fields.${f}`)}</dt>
                    <dd className="flex items-center gap-2">
                      <span className="t-mono">{revealed[f] ?? d[f] ?? '—'}</span>
                      {d[f] && !revealed[f] && (
                        <button type="button" onClick={() => reveal(f)} className="t-small inline-flex items-center gap-1 text-primary hover:underline">
                          <Eye className="size-3.5" aria-hidden />
                          {t('admin.reveal')}
                        </button>
                      )}
                    </dd>
                  </div>
                ))}
                <Row k={t('admin.f.created_at')} v={dateTime(d.created_at)} />
                <Row k={t('admin.f.last_login_at')} v={d.last_login_at ? dateTime(d.last_login_at) : '—'} />
                <Row k="IP" v={d.last_login_ip ?? '—'} />
                <Row k={t('admin.f.referred_by')} v={d.referred_by ?? '—'} />
              </dl>
              <p className="t-small mt-3 text-muted">{t('admin.reveal_note')}</p>
            </Card>
            <Card>
              <CardTitle>{t('admin.balances')}</CardTitle>
              <dl className="grid gap-2 text-sm">
                <Row k={t('wallet.topup')} v={rupiah(d.balances.topup)} />
                <Row k={t('wallet.commission')} v={rupiah(d.balances.commission)} />
                <Row k={t('wallet.reserved_label')} v={rupiah(d.balances.commission_reserved)} />
                <Row k={t('credits.title')} v={`${number(d.entitlements.credits?.remaining ?? 0)} / ${number(d.entitlements.credits?.quota ?? 0)}`} />
                <Row k={t('admin.f.jobs')} v={number(d.counts.jobs)} />
                <Row k={t('admin.f.referrals')} v={number(d.counts.referrals)} />
              </dl>
            </Card>
          </div>
        )}
        {tab === 'subscription' && (
          <>
          <UserFeaturesCard userId={id!} isAdmin={d.role === 'admin'} onChanged={u.reload} />
          <ul className="divide-y divide-line rounded-lg border border-line bg-surface">
            {d.subscriptions.map((s: any) => (
              <li key={s.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <span className="font-medium">{s.plan_name}</span>
                <span className="t-small text-muted">{dateTime(s.starts_at, false)} — {s.ends_at ? dateTime(s.ends_at, false) : '—'}</span>
                <span className="tabular-nums">{rupiah(s.price)}</span>
                <StatusChip status={s.status} />
              </li>
            ))}
            {d.subscriptions.length === 0 && <li className="t-small px-4 py-6 text-muted">{t('billing.no_history')}</li>}
          </ul>
          </>
        )}
        {tab === 'wallet' && <ResourcePage resource="ledger" title={t('admin.nav.wallet_ledger')} preset={{ user_id: Number(id) }} hideCreate />}
        {(tab === 'ai_usage' || tab === 'media_jobs') && <ResourcePage resource="ai-jobs" title={t(`admin.user_tabs.${tab}`)} preset={{ user_id: Number(id) }} />}
        {tab === 'websites' && <ResourcePage resource="websites" title={t('admin.user_tabs.websites')} preset={{ user_id: Number(id) }} />}
        {tab === 'affiliate' && <ResourcePage resource="credit-ledger" title={t('credits.history')} preset={{ user_id: Number(id) }} />}
        {tab === 'security' && <ResourcePage resource="security-events" title={t('admin.user_tabs.security')} preset={{ user_id: Number(id) }} />}
        {tab === 'activity' && <ResourcePage resource="audit-logs" title={t('admin.user_tabs.activity')} />}
      </div>

      <ConfirmDialog
        open={statusOpen}
        onClose={() => setStatusOpen(false)}
        title={t('admin.change_status')}
        confirmLabel={t('admin.save_status')}
        destructive={['suspended', 'banned', 'rejected'].includes(newStatus)}
        requireReason
        extra={
          <SelectField label={t('common.status')} value={newStatus} onChange={(e) => setNewStatus(e.target.value)}>
            {['active', 'pending_approval', 'request_revision', 'rejected', 'suspended', 'banned', 'deactivated'].map((s) => <option key={s} value={s}>{t(`status.${s}`)}</option>)}
          </SelectField>
        }
        onConfirm={async (reason) => {
          await http.post(`/admin/users/${id}/status`, { status: newStatus, reason })
          toast.push('success', t('common.saved'))
          u.reload()
        }}
      />
      <Modal
        open={bal}
        onClose={() => setBal(false)}
        critical
        size="form"
        dirty={!!balForm.amount}
        title={t('admin.adjust_balance')}
        footer={
          <>
            <Button variant="secondary" onClick={() => setBal(false)}>{t('common.cancel')}</Button>
            <Button
              disabled={!balForm.amount || balForm.reason.length < 5}
              onClick={async () => {
                try {
                  await withReauth(() => http.post(`/admin/users/${id}/balance`, { ...balForm, amount: Number(balForm.amount) }))
                  toast.push('success', t('common.saved'))
                  setBal(false)
                  setBalForm({ wallet: 'topup', direction: 'credit', amount: '', reason: '' })
                  u.reload()
                } catch (e) {
                  toast.push('error', e instanceof ApiError ? e.message : (e as Error).message)
                }
              }}
            >
              {balForm.amount ? t('admin.adjust_amount', { amount: rupiah(Number(balForm.amount)) }) : t('admin.adjust_balance')}
            </Button>
          </>
        }
      >
        <div className="grid gap-4">
          <p className="t-small rounded-md bg-warning/14 p-3 text-fg">{t('admin.adjust_note')}</p>
          <div className="grid grid-cols-2 gap-3">
            <SelectField label={t('wallet.col_wallet')} value={balForm.wallet} onChange={(e) => setBalForm({ ...balForm, wallet: e.target.value })}>
              <option value="topup">{t('wallet.topup')}</option>
              <option value="commission">{t('wallet.commission')}</option>
            </SelectField>
            <SelectField label={t('admin.direction')} value={balForm.direction} onChange={(e) => setBalForm({ ...balForm, direction: e.target.value })}>
              <option value="credit">{t('wallet.credit')}</option>
              <option value="debit">{t('wallet.debit')}</option>
            </SelectField>
          </div>
          <TextField label={t('wallet.amount')} required inputMode="numeric" prefix="Rp" value={balForm.amount ? new Intl.NumberFormat('id-ID').format(Number(balForm.amount)) : ''} onChange={(e) => setBalForm({ ...balForm, amount: e.target.value.replace(/\D/g, '') })} />
          <TextArea label={t('common.reason')} required rows={3} value={balForm.reason} onChange={(e) => setBalForm({ ...balForm, reason: e.target.value })} hint={t('common.reason_hint')} />
        </div>
      </Modal>
    </>
  )
}

/* ===================== Approvals ===================== */
interface ApprovalRow {
  id: number
  type: string
  title: string
  status: string
  reason: string | null
  payload: Record<string, any> | null
  user: { id: number; name: string; email: string } | null
  decider: string | null
  decided_at: string | null
  created_at: string
  subject_id: number
}
export function AdminApprovals({ type, history, title }: { type?: string; history?: boolean; title: string }) {
  const { t } = useTranslation()
  useDocTitle(title)
  const toast = useToast()
  const { withReauth } = useAdmin()
  const list = useServerList<ApprovalRow>('/admin/approvals', { type: type ?? '', status: history ? 'approved' : 'pending' })
  const [act, setAct] = useState<{ row: ApprovalRow; decision: 'approve' | 'reject' | 'revision' } | null>(null)
  const [histStatus, setHistStatus] = useState('approved')
  const [refundMethod, setRefundMethod] = useState<'balance' | 'transfer'>('balance')
  const [refundRef, setRefundRef] = useState('')
  const isRefundApprove = act?.row.type === 'refund' && act.decision === 'approve'
  const topupRefund = act?.row.payload?.purpose === 'topup'
  return (
    <>
      <PageHeader title={title} subtitle={t('admin.approvals_sub')} crumbs={[{ label: t('admin.nav.g_approval') }, { label: title }]} />
      {history && (
        <div className="mb-3">
          <Tabs label={title} value={histStatus} onChange={(v) => { setHistStatus(v); list.update({ status: v }) }} items={['approved', 'rejected', 'revision'].map((s) => ({ value: s, label: t(`status.${s}`, { defaultValue: s }) }))} />
        </div>
      )}
      <DataTable<ApprovalRow>
        list={list}
        emptyTitle={t('admin.no_pending')}
        columns={[
          { key: 'title', header: t('admin.f.title'), primary: true },
          { key: 'type', header: t('admin.f.type'), mobile: true, render: (r) => t(`admin.approval_types.${r.type}`) },
          { key: 'user', header: t('admin.f.user'), render: (r) => (r.user ? <Link to={`/admin/users/${r.user.id}`} className="text-primary hover:underline">{r.user.name}</Link> : '—') },
          { key: 'payload', header: t('admin.f.detail'), render: (r) => (
            <span className="t-small line-clamp-2 text-muted">
              {r.payload?.amount ? rupiah(r.payload.amount) : r.payload?.note ?? r.payload?.email ?? '—'}
              {r.type === 'refund' && r.payload?.reason && <span className="block">“{r.payload.reason}”</span>}
              {r.type === 'payment' && r.payload?.gateway_status && (
                <span className={cn('ml-2 font-semibold', r.payload.gateway_status === 'paid' ? 'text-success' : r.payload.gateway_status === 'amount_mismatch' ? 'text-danger' : 'text-muted')}>
                  {t(`admin.gateway_status.${r.payload.gateway_status}`, { defaultValue: r.payload.gateway_status })}
                </span>
              )}
            </span>
          ) },
          { key: 'status', header: t('common.status'), mobile: true, render: (r) => <StatusChip status={r.status} /> },
          { key: 'created_at', header: t('admin.f.created_at'), render: (r) => relative(r.created_at) },
          ...(history ? [{ key: 'decider', header: t('admin.f.decider'), render: (r: ApprovalRow) => `${r.decider ?? '—'}${r.reason ? ` — ${r.reason}` : ''}` }] : []),
        ]}
        rowActions={
          history
            ? undefined
            : (r) => (
                <div className="flex gap-1">
                  <Button size="sm" icon={<Check className="size-4" aria-hidden />} onClick={() => setAct({ row: r, decision: 'approve' })}>{t('admin.approve')}</Button>
                  {r.type === 'account' && <Button size="sm" variant="ghost" aria-label={t('admin.revision')} icon={<RotateCcw className="size-4" aria-hidden />} onClick={() => setAct({ row: r, decision: 'revision' })} />}
                  <Button size="sm" variant="ghost" aria-label={t('admin.reject')} icon={<X className="size-4" aria-hidden />} onClick={() => setAct({ row: r, decision: 'reject' })} />
                </div>
              )
        }
      />
      <ConfirmDialog
        open={!!act}
        onClose={() => setAct(null)}
        destructive={act?.decision === 'reject'}
        requireReason={act?.decision !== 'approve'}
        title={act ? t(`admin.decide_title_${act.decision}`, { title: act.row.title }) : ''}
        body={act?.row.type === 'withdrawal' ? t('admin.withdraw_decide_note') : isRefundApprove ? t('admin.refund.note') : undefined}
        extra={
          isRefundApprove ? (
            <div className="grid gap-3">
              {!topupRefund && (
                <SelectField label={t('admin.refund.method')} value={refundMethod} onChange={(e) => setRefundMethod(e.target.value as 'balance' | 'transfer')}>
                  <option value="balance">{t('admin.refund.to_balance')}</option>
                  <option value="transfer">{t('admin.refund.to_transfer')}</option>
                </SelectField>
              )}
              {(topupRefund || refundMethod === 'transfer') && <TextField label={t('admin.f.reference')} required value={refundRef} onChange={(e) => setRefundRef(e.target.value)} hint={t('admin.refund.reference_hint')} />}
            </div>
          ) : undefined
        }
        confirmLabel={act ? t(`admin.${act.decision}`) : ''}
        onConfirm={async (reason) => {
          await withReauth(() => http.post(`/admin/approvals/${act!.row.id}/decide`, { decision: act!.decision, reason, ...(isRefundApprove ? { refund_method: topupRefund ? 'transfer' : refundMethod, reference: refundRef || undefined } : {}) }))
          setRefundRef('')
          setRefundMethod('balance')
          toast.push('success', t('common.saved'))
          list.reload()
        }}
      />
    </>
  )
}

/* ===================== Withdrawals (status lanjutan) ===================== */
export function AdminWithdrawals() {
  const { t } = useTranslation()
  useDocTitle(t('admin.nav.withdrawals'))
  const toast = useToast()
  const { withReauth } = useAdmin()
  const list = useServerList<any>('/admin/r/withdrawals', {})
  const [pay, setPay] = useState<any | null>(null)
  const [ref, setRef] = useState('')
  const next = async (row: any, status: string, reference?: string) => {
    await withReauth(() => http.post(`/admin/withdrawals/${row.id}/status`, { status, reference }))
    toast.push('success', t('common.saved'))
    list.reload()
  }
  return (
    <>
      <PageHeader title={t('admin.nav.withdrawals')} subtitle={t('admin.withdrawals_sub')} crumbs={[{ label: t('admin.nav.g_financial') }, { label: t('admin.nav.withdrawals') }]} />
      <DataTable<any>
        list={list}
        exportName="withdrawals"
        filters={[{ key: 'status', label: t('common.status'), options: ['requested', 'under_review', 'approved', 'processing', 'paid', 'rejected', 'cancelled'].map((s) => ({ value: s, label: t(`status.${s}`) })) }]}
        columns={[
          { key: 'user', header: t('admin.f.user'), primary: true, render: (r) => r.user?.name ?? '—' },
          { key: 'amount', header: t('wallet.amount'), align: 'right', sortable: true, mobile: true, render: (r) => rupiah(r.amount) },
          { key: 'destination_snapshot', header: t('wallet.destination'), render: (r) => `${r.destination_snapshot?.provider ?? ''} ${r.destination_snapshot?.account_masked ?? ''}` },
          { key: 'status', header: t('common.status'), mobile: true, render: (r) => <StatusChip status={r.status} /> },
          { key: 'reference', header: t('admin.f.reference'), render: (r) => r.reference ?? '—' },
          { key: 'created_at', header: t('admin.f.created_at'), sortable: true, render: (r) => dateTime(r.created_at) },
        ]}
        rowActions={(r) =>
          r.status === 'requested' ? (
            <Link to="/admin/approvals/withdrawals" className="t-label text-primary hover:underline">{t('admin.review')}</Link>
          ) : r.status === 'approved' ? (
            <Button size="sm" variant="secondary" onClick={() => next(r, 'processing')}>{t('admin.mark_processing')}</Button>
          ) : r.status === 'processing' ? (
            <Button size="sm" icon={<Banknote className="size-4" aria-hidden />} onClick={() => { setRef(''); setPay(r) }}>{t('admin.mark_paid')}</Button>
          ) : null
        }
      />
      <Modal
        open={!!pay}
        onClose={() => setPay(null)}
        critical
        size="confirm"
        title={pay ? t('admin.mark_paid_title', { amount: rupiah(pay.amount) }) : ''}
        footer={
          <>
            <Button variant="secondary" onClick={() => setPay(null)}>{t('common.cancel')}</Button>
            <Button disabled={ref.trim().length < 3} onClick={async () => { await next(pay, 'paid', ref); setPay(null) }}>{t('admin.mark_paid')}</Button>
          </>
        }
      >
        <TextField label={t('admin.f.reference')} required value={ref} onChange={(e) => setRef(e.target.value)} hint={t('admin.reference_hint')} />
      </Modal>
    </>
  )
}

/* ===================== Settings per grup ===================== */
export interface SettingField {
  key: string
  type: 'text' | 'number' | 'bool' | 'textarea' | 'secret' | 'select' | 'json'
  options?: string[]
}
export function AdminSettings({ title, fields, info, group }: { title: string; fields: SettingField[]; info?: string; group?: string }) {
  const { t } = useTranslation()
  useDocTitle(title)
  const toast = useToast()
  const s = useApi<Record<string, any>>('/admin/settings')
  const [vals, setVals] = useState<Record<string, any> | null>(null)
  const [busy, setBusy] = useState(false)
  useEffect(() => {
    if (s.data) setVals(Object.fromEntries(fields.map((f) => [f.key, f.type === 'json' ? JSON.stringify(s.data![f.key] ?? null, null, 2) : s.data![f.key] ?? (f.type === 'bool' ? false : '')])))
  }, [s.data, fields])
  const init = useMemo(() => (s.data ? JSON.stringify(Object.fromEntries(fields.map((f) => [f.key, f.type === 'json' ? JSON.stringify(s.data![f.key] ?? null, null, 2) : s.data![f.key] ?? (f.type === 'bool' ? false : '')]))) : ''), [s.data, fields])
  if (!vals) return <Skeleton className="h-80" />
  const dirty = JSON.stringify(vals) !== init
  return (
    <>
      <PageHeader title={title} crumbs={group ? [{ label: group }, { label: title }] : undefined} />
      {info && <InfoCard title={t('admin.note')}>{info}</InfoCard>}
      <Card className="max-w-3xl">
        <div className="grid gap-5">
          {fields.map((f) => {
            const label = t(`admin.s.${f.key.replace(/\./g, '_')}`, { defaultValue: f.key })
            const v = vals[f.key]
            const set = (x: any) => setVals({ ...vals, [f.key]: x })
            if (f.type === 'bool') return <Switch key={f.key} label={label} checked={!!v} onChange={set} />
            if (f.type === 'textarea' || f.type === 'json') return <TextArea key={f.key} label={label} rows={f.type === 'json' ? 6 : 3} className={f.type === 'json' ? 'font-mono text-[13px]' : ''} value={v ?? ''} onChange={(e) => set(e.target.value)} />
            if (f.type === 'select')
              return (
                <SelectField key={f.key} label={label} value={v ?? ''} onChange={(e) => set(e.target.value)}>
                  {f.options!.map((o) => <option key={o} value={o}>{t(`status.${o}`, { defaultValue: o })}</option>)}
                </SelectField>
              )
            if (f.type === 'secret') return <TextField key={f.key} label={label} type="password" autoComplete="new-password" value={v === '__set__' ? '' : v ?? ''} placeholder={v === '__set__' ? '••••••••' : ''} onChange={(e) => set(e.target.value || (s.data![f.key] === '__set__' ? '__set__' : ''))} hint={s.data![f.key] === '__set__' ? t('admin.secret_set') : t('admin.secret_never_shown')} />
            return <TextField key={f.key} label={label} inputMode={f.type === 'number' ? 'numeric' : undefined} value={v ?? ''} onChange={(e) => set(f.type === 'number' ? e.target.value.replace(/[^\d.]/g, '') : e.target.value)} />
          })}
          <div className="flex gap-2">
            <Button
              loading={busy}
              disabled={!dirty}
              onClick={async () => {
                setBusy(true)
                try {
                  const values: Record<string, any> = {}
                  for (const f of fields) {
                    const v = vals[f.key]
                    values[f.key] = f.type === 'number' ? (v === '' ? null : Number(v)) : f.type === 'json' ? JSON.parse(v || 'null') : v === '' ? null : v
                  }
                  await http.put('/admin/settings', { values })
                  toast.push('success', t('common.saved'))
                  s.reload()
                } catch (e) {
                  toast.push('error', e instanceof SyntaxError ? t('admin.invalid_json') : (e as Error).message)
                } finally {
                  setBusy(false)
                }
              }}
            >
              {t('common.save')}
            </Button>
          </div>
        </div>
      </Card>
    </>
  )
}

/* ===================== System monitoring ===================== */
export function AdminSystem({ tab: initial = 'server' }: { tab?: string }) {
  const { t } = useTranslation()
  useDocTitle(t(`admin.nav.${initial}`))
  const { data, reload, loading } = useApi<any>('/admin/system')
  if (!data) return <Skeleton className="h-80" />
  const blocks: Record<string, [string, React.ReactNode][]> = {
    server: [['App', `Krafio ${data.app.version}`], ['PHP', data.app.php], ['Laravel', data.app.laravel], ['Environment', data.app.environment], ['Debug', data.app.debug ? 'ON' : 'OFF'], [t('admin.disk'), `${data.storage.disk_free_gb} / ${data.storage.disk_total_gb} GB`], ['Mail', data.mail.mailer], ['Session', `${data.session.driver} (${data.session.lifetime}m)`]],
    database: [[t('common.status'), <StatusChip key="d" status={data.database.status} />], ['Version', data.database.version], [t('admin.size'), `${data.database.size_mb} MB`]],
    redis: [[t('common.status'), <StatusChip key="r" status={data.redis.status === 'not_configured' ? 'disabled' : data.redis.status} label={data.redis.status === 'not_configured' ? t('admin.not_configured') : undefined} />], ['Cache driver', data.cache.driver], ['Cache', <StatusChip key="c" status={data.cache.status} />]],
    queue: [['Driver', data.queue.driver], [t('admin.kpi.queue_backlog'), number(data.queue.backlog)], [t('admin.kpi.failed_queue'), number(data.queue.failed)], [t('admin.inline_mode'), data.queue.inline_mode ? 'ON' : 'OFF']],
  }
  return (
    <>
      <PageHeader title={t(`admin.nav.${initial}`)} subtitle={t('admin.checked_at', { at: dateTime(data.checked_at) })} actions={<Button variant="secondary" loading={loading} onClick={reload}>{t('common.refresh')}</Button>} />
      {initial === 'redis' && data.redis.status === 'not_configured' && <InfoCard title={t('admin.note')}>{t('admin.redis_note')}</InfoCard>}
      {initial === 'queue' && data.queue.inline_mode && <InfoCard title={t('admin.note')}>{t('admin.inline_note')}</InfoCard>}
      {initial === 'backup' ? (
        <InfoCard title={t('admin.nav.backup')}>{t('admin.backup_note')}</InfoCard>
      ) : (
        <Card className="max-w-2xl">
          <dl className="grid gap-3 text-sm">
            {(blocks[initial] ?? blocks.server).map(([k, v]) => <Row key={String(k)} k={String(k)} v={v} />)}
          </dl>
        </Card>
      )}
    </>
  )
}

/* ===================== AI usage, token, biaya & margin (PRD 7 D, 55.2.4) ===================== */
export function AdminAiUsage({ title }: { title: string }) {
  const { t } = useTranslation()
  useDocTitle(title)
  const { data } = useApi<any>('/admin/ai/usage')
  const plans = useApi<any[]>('/public/plans')
  if (!data) return <Skeleton className="h-80" />
  return (
    <>
      <PageHeader title={title} />
      <div className="mb-4 grid gap-3 md:grid-cols-3">
        {(['text', 'image', 'video'] as const).map((c) => (
          <Card key={c}>
            <p className="t-small text-muted">{t(`admin.capability.${c}`)}</p>
            <StatusChip status={data[`${c}_configured`] ? 'active' : 'disabled'} label={data[`${c}_configured`] ? t('admin.configured') : t('admin.simulation')} />
          </Card>
        ))}
      </div>
      <Card className="mb-4">
        <CardTitle>{t('admin.usage_by_type')}</CardTitle>
        {data.by_type.length === 0 ? (
          <p className="t-small text-muted">{t('table.empty')}</p>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line text-left text-muted">
                <th className="t-label py-2">{t('usage.type')}</th>
                <th className="t-label py-2 text-right">{t('admin.f.jobs')}</th>
                <th className="t-label py-2 text-right">{t('credits.title')}</th>
                <th className="t-label py-2 text-right">{t('status.failed')}</th>
              </tr>
            </thead>
            <tbody>
              {data.by_type.map((r: any) => (
                <tr key={r.type} className="border-b border-line last:border-0">
                  <td className="py-2">{t(`job_types.${r.type}`)}</td>
                  <td className="py-2 text-right tabular-nums">{number(r.jobs)}</td>
                  <td className="py-2 text-right tabular-nums">{number(r.credits)}</td>
                  <td className="py-2 text-right tabular-nums">{number(r.failed)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
      <Card>
        <CardTitle>{t('admin.margin_title')}</CardTitle>
        <p className="t-small mb-3 text-muted">{t('admin.margin_formula')}</p>
        {plans.data && (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-line text-left text-muted">
                <th className="t-label py-2">{t('admin.f.plan')}</th>
                <th className="t-label py-2 text-right">{t('admin.f.price_monthly')}</th>
                <th className="t-label py-2 text-right">{t('credits.per_month')}</th>
                <th className="t-label py-2 text-right">{t('admin.max_cost')}</th>
                <th className="t-label py-2 text-right">{t('admin.cost_ratio')}</th>
              </tr>
            </thead>
            <tbody>
              {plans.data.filter((p) => p.price_monthly > 0).map((p) => {
                const credits = Number(p.quotas.credits ?? 0)
                const cost = credits * 90
                const net = p.price_monthly / 1.11
                const ratio = Math.round((cost / net) * 100)
                return (
                  <tr key={p.code} className="border-b border-line last:border-0">
                    <td className="py-2">{p.name}</td>
                    <td className="py-2 text-right tabular-nums">{rupiah(p.price_monthly)}</td>
                    <td className="py-2 text-right tabular-nums">{number(credits)}</td>
                    <td className="py-2 text-right tabular-nums">{rupiah(cost)}</td>
                    <td className={cn('py-2 text-right font-semibold tabular-nums', ratio > 55 ? 'text-danger' : 'text-success')}>{ratio}%</td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        )}
        <p className="t-small mt-3 text-muted">{t('admin.margin_note')}</p>
      </Card>
    </>
  )
}

/* ===================== Matriks entitlement (PRD 4.5, 55.2.1) ===================== */
export function AdminEntitlements() {
  const { t } = useTranslation()
  useDocTitle(t('admin.nav.entitlements'))
  const plans = useApi<any[]>('/public/plans')
  const keys = ['photo.generate', 'photo.edit', 'video.generate', 'video.edit', 'video.clipper', 'content.brainstorm', 'templates.premium', 'website.builder', 'seo.basic', 'seo.advanced', 'social.integrations', 'chatbot.basic', 'chatbot.premium', 'exports.download']
  return (
    <>
      <PageHeader title={t('admin.nav.entitlements')} subtitle={t('admin.entitlements_sub')} actions={<Link to="/admin/billing/plans" className="t-label text-primary hover:underline">{t('admin.nav.plans')}</Link>} />
      {!plans.data ? (
        <Skeleton className="h-80" />
      ) : (
        <div className="overflow-x-auto rounded-lg border border-line bg-surface">
          <table className="w-full min-w-[640px] text-sm">
            <thead className="bg-bg-subtle">
              <tr>
                <th className="t-label sticky left-0 bg-bg-subtle px-3 py-2 text-left">{t('admin.f.entitlement')}</th>
                {plans.data.map((p) => <th key={p.code} className="t-label px-3 py-2 text-center">{p.name}</th>)}
              </tr>
            </thead>
            <tbody>
              {['credits', ...keys].map((k) => (
                <tr key={k} className="border-t border-line">
                  <td className="t-mono sticky left-0 bg-surface px-3 py-2">{k}</td>
                  {plans.data!.map((p) => {
                    const on = k === 'credits' ? Number(p.quotas.credits ?? 0) > 0 : p.features.includes(k)
                    const q = p.quotas[k]
                    return (
                      <td key={p.code} className="px-3 py-2 text-center">
                        {on ? <span className="inline-flex items-center gap-1 text-success"><Check className="size-4" aria-label={t('pricing.included')} />{q ? <span className="t-small tabular-nums text-fg">{number(q)}</span> : null}</span> : <span className="text-muted" aria-label={t('pricing.not_included')}>—</span>}
                      </td>
                    )
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  )
}

/* ===================== Ekspor & info generik ===================== */
export function AdminInfoPage({ title, body, links }: { title: string; body: string; links?: { to: string; label: string }[] }) {
  useDocTitle(title)
  return (
    <>
      <PageHeader title={title} />
      <InfoCard title={title}>{body}</InfoCard>
      {links && (
        <div className="flex flex-wrap gap-2">
          {links.map((l) => (
            <Link key={l.to} to={l.to} className="t-label inline-flex h-10 items-center gap-2 rounded-md border border-line px-3 hover:bg-surface-2">
              <Download className="size-4 rotate-[-90deg]" aria-hidden />
              {l.label}
            </Link>
          ))}
        </div>
      )}
    </>
  )
}
