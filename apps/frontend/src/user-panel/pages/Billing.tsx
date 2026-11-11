import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Copy, Link2, Check } from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import type { Addon, Plan } from '@/api-client/types'
import { useAuth } from '@/shared/context/AuthContext'
import { useToast } from '@/shared/context/ToastContext'
import { useApi } from '@/shared/hooks/useApi'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { Card, CardTitle, KpiCard, PageHeader, Segmented } from '@/shared/ui/Misc'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Switch } from '@/shared/ui/Field'
import { StatusChip } from '@/shared/ui/Status'
import { DataTable, useServerList } from '@/shared/ui/DataTable'
import { EmptyState, Skeleton } from '@/shared/ui/States'
import { Carousel } from '@/shared/ui/Carousel'
import { PlanCard } from '@/public-site/components/PlanCard'
import { CheckoutModal, type CheckoutItem } from './Checkout'
import { PendingInvoices } from '@/shared/billing/PendingInvoices'
import { dateTime, number, rupiah } from '@/shared/lib/format'

/** Kredit, trial, perpanjangan otomatis, perubahan terjadwal (PRD 55.1, 55.4.1, 55.5). */
function SubscriptionExtras({ onChange }: { onChange: () => void }) {
  const { t } = useTranslation()
  const { me, refresh } = useAuth()
  const toast = useToast()
  const [busy, setBusy] = useState(false)
  const history = useServerList<{ id: number; direction: string; amount: number; description: string; created_at: string }>('/app/credits/history')
  if (!me) return null
  const c = me.entitlements.credits
  return (
    <div className="mt-6 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
      <Card>
        <CardTitle>{t('credits.title')}</CardTitle>
        <p className="text-[28px] font-bold tabular-nums text-fg">
          {number(c?.remaining ?? 0)} <span className="t-small font-normal text-muted">/ {number(c?.quota ?? 0)}</span>
        </p>
        {c?.reset_at && <p className="t-small text-muted">{t('credits.reset_at', { date: dateTime(c.reset_at, false) })}</p>}
        <p className="t-small mt-2 text-muted">{t('credits.rules')}</p>
        <div className="mt-3 flex flex-wrap gap-2">
          <a href="/app/addons" className="t-label inline-flex h-9 items-center rounded-md border border-line px-3 hover:bg-surface-2">{t('credits.buy_more')}</a>
        </div>
      </Card>
      <Card>
        <div className="grid gap-4">
          <Switch
            label={t('billing.auto_renew')}
            description={t('billing.auto_renew_desc')}
            checked={me.auto_renew}
            onChange={async (v) => {
              await http.patch('/app/subscription/auto-renew', { enabled: v })
              await refresh()
              toast.push('success', t('common.saved'))
            }}
          />
          {me.scheduled_change && (
            <div className="flex flex-wrap items-center justify-between gap-2 rounded-md bg-info/10 p-3">
              <p className="t-small text-info">{t('billing.scheduled_change', { plan: me.scheduled_change.plan_code, date: dateTime(me.scheduled_change.effective_at, false) })}</p>
              <Button size="sm" variant="ghost" onClick={async () => { await http.del('/app/subscription/scheduled-change'); await refresh(); onChange() }}>{t('billing.cancel_change')}</Button>
            </div>
          )}
          {me.trial_available && (
            <div className="grid gap-2 rounded-md border border-primary/40 bg-primary-soft p-3">
              <p className="font-semibold text-fg">{t('billing.trial_title')}</p>
              <p className="t-small text-muted">{t('billing.trial_body')}</p>
              <Button
                size="sm"
                loading={busy}
                className="justify-self-start"
                onClick={async () => {
                  setBusy(true)
                  try {
                    await http.post('/app/trial')
                    await refresh()
                    onChange()
                    toast.push('success', t('billing.trial_started'))
                  } catch (e) {
                    toast.push('error', e instanceof ApiError ? e.message : t('errors.server_body'))
                  } finally {
                    setBusy(false)
                  }
                }}
              >
                {t('billing.start_trial')}
              </Button>
            </div>
          )}
        </div>
      </Card>
      <section className="grid gap-3 lg:col-span-2">
        <h2 className="t-h2 text-fg">{t('credits.history')}</h2>
        <DataTable
          list={history}
          emptyTitle={t('credits.history_empty')}
          columns={[
            { key: 'description', header: t('wallet.col_desc'), primary: true },
            { key: 'amount', header: t('credits.title'), align: 'right', mobile: true, render: (r) => <span className={r.direction === 'credit' ? 'text-success' : 'text-fg'}>{r.direction === 'credit' ? '+' : '−'}{number(r.amount)}</span> },
            { key: 'created_at', header: t('wallet.col_date'), mobile: true, render: (r) => dateTime(r.created_at) },
          ]}
        />
      </section>
    </div>
  )
}

interface SubInfo {
  current: { plan: Plan; status: string; starts_at: string; ends_at: string | null; paid_until?: string | null; cycle: string } | null
  history: { id: number; plan_name: string; status: string; starts_at: string; ends_at: string | null; price: number }[]
}

export function SubscriptionPage() {
  const { t } = useTranslation()
  useDocTitle(t('user.nav.my_plan'))
  const plans = useApi<Plan[]>('/public/plans')
  const sub = useApi<SubInfo>('/app/subscription')
  const [cycle, setCycle] = useState<'monthly' | 'yearly'>('monthly')
  const [co, setCo] = useState<CheckoutItem | null>(null)
  const cur = sub.data?.current
  return (
    <>
      <PageHeader title={t('user.nav.my_plan')} subtitle={t('billing.sub_subtitle')} crumbs={[{ label: t('user.nav.subscription') }, { label: t('user.nav.my_plan') }]} />
      <PendingInvoices onChange={sub.reload} />
      {!sub.data ? (
        <Skeleton className="h-28" />
      ) : cur ? (
        <div className="grid gap-3 sm:grid-cols-3">
          <KpiCard label={t('billing.current_plan')} value={cur.plan.name} hint={<StatusChip status={cur.status} />} />
          <KpiCard label={t('billing.started')} value={dateTime(cur.starts_at, false)} />
          <KpiCard
            label={t('billing.ends')}
            value={cur.paid_until ?? cur.ends_at ? dateTime((cur.paid_until ?? cur.ends_at)!, false) : '—'}
            hint={cur.paid_until && cur.ends_at && cur.paid_until !== cur.ends_at ? t('billing.renewed_hint', { date: dateTime(cur.ends_at, false) }) : undefined}
            tone="warning"
          />
        </div>
      ) : (
        <EmptyState title={t('billing.no_sub')} body={t('billing.no_sub_body')} />
      )}

      <SubscriptionExtras onChange={sub.reload} />

      <div className="mb-3 mt-8 flex flex-wrap items-center justify-between gap-3">
        <h2 className="t-h2 text-fg">{t('billing.choose_plan')}</h2>
        <Segmented
          label={t('pricing.cycle')}
          value={cycle}
          onChange={setCycle}
          items={[
            { value: 'monthly', label: t('pricing.monthly') },
            { value: 'yearly', label: t('pricing.yearly') },
          ]}
        />
      </div>
      {!plans.data ? (
        <Skeleton className="h-80" />
      ) : (
        <Carousel label={t('billing.choose_plan')} itemClass="basis-[86%] sm:basis-[48%] lg:basis-[calc((100%-3rem)/4)]">
          {plans.data.map((p) => (
            <PlanCard key={p.id} plan={p} cycle={cycle} current={cur?.plan.code === p.code && cur.status === 'active'} onChoose={(pl) => setCo({ kind: 'plan', code: pl.code, name: pl.name, price: cycle === 'monthly' ? pl.price_monthly : pl.price_yearly, cycle })} />
          ))}
        </Carousel>
      )}

      <section className="mt-8 grid gap-3">
        <h2 className="t-h2 text-fg">{t('billing.history')}</h2>
        {!sub.data ? (
          <Skeleton className="h-24" />
        ) : sub.data.history.length === 0 ? (
          <p className="t-small text-muted">{t('billing.no_history')}</p>
        ) : (
          <ul className="divide-y divide-line rounded-lg border border-line bg-surface">
            {sub.data.history.map((h) => (
              <li key={h.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <span className="font-medium text-fg">{h.plan_name}</span>
                <span className="t-small text-muted">
                  {dateTime(h.starts_at, false)} — {h.ends_at ? dateTime(h.ends_at, false) : '—'}
                </span>
                <span className="tabular-nums text-fg">{rupiah(h.price)}</span>
                <StatusChip status={h.status} />
              </li>
            ))}
          </ul>
        )}
      </section>
      <CheckoutModal item={co} onClose={() => setCo(null)} onDone={sub.reload} />
    </>
  )
}

export function AddonsUserPage() {
  const { t } = useTranslation()
  useDocTitle(t('user.nav.addons'))
  const addons = useApi<Addon[]>('/public/addons')
  const mine = useApi<{ id: number; name: string; status: string; ends_at: string | null }[]>('/app/addons')
  const [co, setCo] = useState<CheckoutItem | null>(null)
  return (
    <>
      <PageHeader title={t('user.nav.addons')} subtitle={t('pricing.addons_body')} crumbs={[{ label: t('user.nav.subscription'), to: '/app/subscription' }, { label: t('user.nav.addons') }]} />
      {mine.data && mine.data.length > 0 && (
        <Card className="mb-6">
          <CardTitle>{t('billing.my_addons')}</CardTitle>
          <ul className="grid gap-2">
            {mine.data.map((a) => (
              <li key={a.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md bg-surface-2 px-3 py-2">
                <span className="font-medium text-fg">{a.name}</span>
                <span className="t-small text-muted">{a.ends_at ? t('billing.until', { date: dateTime(a.ends_at, false) }) : t('billing.no_expiry')}</span>
                <StatusChip status={a.status} />
              </li>
            ))}
          </ul>
        </Card>
      )}
      {!addons.data ? (
        <Skeleton className="h-60" />
      ) : addons.data.length === 0 ? (
        <EmptyState title={t('pricing.addons_empty')} />
      ) : (
        <ul className="grid grid-cols-2 gap-3 lg:grid-cols-3">
          {addons.data.map((a) => (
            <li key={a.id} className="flex flex-col gap-2 rounded-lg border border-line bg-surface p-4">
              <p className="font-semibold text-fg">{a.name}</p>
              <p className="t-small flex-1 text-muted">{a.description}</p>
              <p className="font-bold tabular-nums text-fg">{rupiah(a.price)}</p>
              <p className="t-small text-muted">
                {t(`addons.billing.${a.billing}`)}
                {a.duration_days ? ` — ${t('billing.days', { n: a.duration_days })}` : ''}
              </p>
              <Button size="sm" variant="secondary" onClick={() => setCo({ kind: 'addon', code: a.code, name: a.name, price: a.price })}>
                {t('billing.buy')}
              </Button>
            </li>
          ))}
        </ul>
      )}
      <CheckoutModal item={co} onClose={() => setCo(null)} onDone={mine.reload} />
    </>
  )
}

export function CustomPackagesPage() {
  const { t } = useTranslation()
  useDocTitle(t('user.nav.custom_packages'))
  const offers = useApi<{ id: number; code: string; name: string; description: string; price: number; duration_days: number; features: string[] }[]>('/app/custom-packages')
  const [co, setCo] = useState<CheckoutItem | null>(null)
  const [req, setReq] = useState(false)
  const [note, setNote] = useState('')
  const toast = useToast()
  return (
    <>
      <PageHeader
        title={t('user.nav.custom_packages')}
        subtitle={t('billing.custom_sub')}
        crumbs={[{ label: t('user.nav.subscription'), to: '/app/subscription' }, { label: t('user.nav.custom_packages') }]}
        actions={<Button variant="secondary" onClick={() => setReq(true)}>{t('billing.request_custom')}</Button>}
      />
      {!offers.data ? (
        <Skeleton className="h-40" />
      ) : offers.data.length === 0 ? (
        <EmptyState title={t('billing.no_custom')} body={t('billing.no_custom_body')} action={<Button onClick={() => setReq(true)}>{t('billing.request_custom')}</Button>} />
      ) : (
        <ul className="grid gap-3 md:grid-cols-2">
          {offers.data.map((o) => (
            <li key={o.id} className="grid gap-2 rounded-lg border border-line bg-surface p-5">
              <p className="t-h3">{o.name}</p>
              <p className="t-small text-muted">{o.description}</p>
              <p className="text-xl font-bold tabular-nums">{rupiah(o.price)}</p>
              <p className="t-small text-muted">{t('billing.days', { n: o.duration_days })}</p>
              <Button onClick={() => setCo({ kind: 'custom', code: o.code, name: o.name, price: o.price })}>{t('billing.buy')}</Button>
            </li>
          ))}
        </ul>
      )}
      <CheckoutModal item={co} onClose={() => setCo(null)} onDone={offers.reload} />
      <Modal
        open={req}
        onClose={() => setReq(false)}
        size="form"
        dirty={!!note}
        title={t('billing.request_custom')}
        footer={
          <Button
            onClick={async () => {
              await http.post('/app/custom-packages/request', { note })
              toast.push('success', t('billing.request_sent'))
              setReq(false)
              setNote('')
            }}
          >
            {t('common.send')}
          </Button>
        }
      >
        <label className="grid gap-1.5">
          <span className="t-label">{t('billing.request_note')}</span>
          <textarea value={note} onChange={(e) => setNote(e.target.value)} rows={5} className="rounded-md border border-line bg-surface p-3 text-fg focus:border-primary focus:outline-none" />
        </label>
      </Modal>
    </>
  )
}

/* ---------------- Affiliate (PRD 5.6) ---------------- */
interface AffInfo {
  code: string
  link: string
  active: boolean
  rate_label: string
  stats: { clicks: number; referrals: number; conversions: number; pending: number; available: number; reversed: number; paid: number }
}
interface Commission {
  id: number
  referred: string
  amount: number
  status: string
  created_at: string
}
export function AffiliateUserPage() {
  const { t } = useTranslation()
  useDocTitle(t('user.nav.affiliate'))
  const info = useApi<AffInfo>('/app/affiliate')
  const list = useServerList<Commission>('/app/affiliate/commissions')
  const [copied, setCopied] = useState<string | null>(null)
  const copy = async (v: string) => {
    try {
      await navigator.clipboard.writeText(v)
      setCopied(v)
      window.setTimeout(() => setCopied(null), 2000)
    } catch {
      /* noop */
    }
  }
  const s = info.data?.stats
  return (
    <>
      <PageHeader title={t('user.nav.affiliate')} subtitle={t('affiliate_user.subtitle')} />
      {!info.data ? (
        <Skeleton className="h-32" />
      ) : (
        <Card className="mb-6">
          <div className="grid gap-4 md:grid-cols-2">
            {[
              { label: t('affiliate_user.code'), value: info.data.code, icon: Copy },
              { label: t('affiliate_user.link'), value: info.data.link, icon: Link2 },
            ].map((x) => (
              <div key={x.label} className="grid gap-1.5">
                <span className="t-label text-muted">{x.label}</span>
                <div className="flex gap-2">
                  <input readOnly value={x.value} className="h-11 min-w-0 flex-1 rounded-md border border-line bg-surface-2 px-3 font-mono text-sm text-fg" aria-label={x.label} />
                  <Button variant="secondary" onClick={() => copy(x.value)} icon={copied === x.value ? <Check className="size-4" aria-hidden /> : <x.icon className="size-4" aria-hidden />}>
                    {copied === x.value ? t('common.copied') : t('common.copy')}
                  </Button>
                </div>
              </div>
            ))}
          </div>
          <p className="t-small mt-3 text-muted">{t('affiliate_user.rate', { rate: info.data.rate_label })}</p>
        </Card>
      )}
      {s && (
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <KpiCard label={t('affiliate_user.clicks')} value={number(s.clicks)} />
          <KpiCard label={t('affiliate_user.referrals')} value={number(s.referrals)} hint={t('affiliate_user.conversions', { n: number(s.conversions) })} />
          <KpiCard label={t('commission.pending')} value={rupiah(s.pending)} tone="warning" />
          <KpiCard label={t('commission.available')} value={rupiah(s.available)} tone="commission" to="/app/wallet/withdrawals" />
        </div>
      )}
      <section className="mt-6 grid gap-3">
        <h2 className="t-h2 text-fg">{t('affiliate_user.commissions')}</h2>
        <DataTable<Commission>
          list={list}
          emptyTitle={t('affiliate_user.empty')}
          filters={[{ key: 'status', label: t('common.status'), options: ['pending', 'approved', 'available', 'reversed', 'paid'].map((x) => ({ value: x, label: t(`commission.${x}`) })) }]}
          columns={[
            { key: 'referred', header: t('affiliate_user.referred'), primary: true },
            { key: 'amount', header: t('wallet.col_amount'), align: 'right', mobile: true, render: (r) => rupiah(r.amount) },
            { key: 'status', header: t('common.status'), mobile: true, render: (r) => <StatusChip status={r.status} label={t(`commission.${r.status}`)} /> },
            { key: 'created_at', header: t('wallet.col_date'), sortable: true, render: (r) => dateTime(r.created_at) },
          ]}
        />
      </section>
    </>
  )
}
