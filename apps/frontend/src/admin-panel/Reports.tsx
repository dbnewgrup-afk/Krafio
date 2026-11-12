import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Download, AlertTriangle } from 'lucide-react'
import { useApi } from '@/shared/hooks/useApi'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { Card, CardTitle, KpiCard, PageHeader } from '@/shared/ui/Misc'
import { Button } from '@/shared/ui/Button'
import { SelectField, TextField } from '@/shared/ui/Field'
import { Badge } from '@/shared/ui/Status'
import { EmptyState, Skeleton } from '@/shared/ui/States'
import { dateTime, number, rupiah } from '@/shared/lib/format'
import { cn } from '@/shared/lib/cn'

interface ReportData {
  range: { from: string; to: string; days: number }
  users_by_plan: { total: number; paid: number; conversion: number; suspended: number; plans: { code: string; name: string; users: number }[] }
  funnel: { registered: number; verified: number; active: number; paid: number; conversion: number }
  ai: { total: number; completed: number; failed: number; cancelled: number; running: number; success_rate: number | null; avg_seconds: number | null; stuck_recovered: number; provider_cost: number; by_type: { type: string; jobs: number; credits: number }[] }
  operations: { payment_success: number | null; payments: number; renewal_success: number | null; renewals: number; renewals_failed: number; tickets: number; tickets_within_sla: number | null; tickets_resolved: number }
  finance: { sales: number; topups: number; refunds: number; commission: number; withdrawals_paid: number; discounts: number; liabilities: number; daily: { date: string; amount: number }[] }
  kpi: { key: string; today: number; period: number; previous: number; delta: number | null }[]
  templates: { id: number; title: string; type: string; premium: boolean; uses: number }[]
  margin: { revenue: number; cost: number; margin: number | null; threshold: number; low: boolean; credits_used: number }
}

const MONEY_KPI = new Set(['revenue'])
const iso = (d: Date) => d.toISOString().slice(0, 10)

/** Laporan & Analitik (blueprint halaman 10) — satu warna data (primary), angka dalam tinta teks, tabel KPI sebagai tampilan tabel. */
export function AdminReports() {
  const { t } = useTranslation()
  useDocTitle(t('admin.nav.reports'))
  const [range] = useState(() => {
    const to = new Date()
    const from = new Date(to.getTime() - 29 * 864e5)
    return { from: iso(from), to: iso(to) }
  })
  const [filters, setFilters] = useState({ from: range.from, to: range.to, plan: '', gateway: '' })
  const [applied, setApplied] = useState(filters)
  const qs = useMemo(() => new URLSearchParams(Object.entries(applied).filter(([, v]) => v)).toString(), [applied])
  const r = useApi<ReportData>(`/admin/reports?${qs}`)
  const d = r.data

  return (
    <>
      <PageHeader
        title={t('admin.nav.reports')}
        subtitle={t('reports.subtitle')}
        crumbs={[{ label: t('admin.nav.g_overview') }, { label: t('admin.nav.reports') }]}
        actions={<a href={`/api/admin/reports?${qs}&export=csv`} className="inline-flex"><Button variant="secondary" icon={<Download className="size-4" aria-hidden />}>{t('reports.export')}</Button></a>}
      />

      {/* Filter: satu baris di atas semua grafik */}
      <form className="mb-5 grid gap-3 rounded-lg border border-line bg-surface p-4 sm:grid-cols-2 lg:grid-cols-[repeat(4,minmax(0,1fr))_auto] lg:items-end" onSubmit={(e) => { e.preventDefault(); setApplied(filters) }}>
        <TextField label={t('reports.from')} type="date" value={filters.from} max={filters.to} onChange={(e) => setFilters({ ...filters, from: e.target.value })} />
        <TextField label={t('reports.to')} type="date" value={filters.to} min={filters.from} onChange={(e) => setFilters({ ...filters, to: e.target.value })} />
        <SelectField label={t('reports.plan')} value={filters.plan} onChange={(e) => setFilters({ ...filters, plan: e.target.value })}>
          <option value="">{t('table.all')}</option>
          {['starter', 'pro', 'business'].map((p) => <option key={p} value={p}>{t(`promo.plan.${p}`)}</option>)}
        </SelectField>
        <SelectField label={t('reports.gateway')} value={filters.gateway} onChange={(e) => setFilters({ ...filters, gateway: e.target.value })}>
          <option value="">{t('table.all')}</option>
          {['balance', 'jualkirim', 'midtrans', 'xendit', 'tripay', 'manual'].map((g) => <option key={g} value={g}>{t(`refund.gateway.${g}`, { defaultValue: g })}</option>)}
        </SelectField>
        <Button type="submit">{t('reports.apply')}</Button>
      </form>

      {r.error ? (
        <EmptyState kind="error" title={t('errors.server_title')} action={<Button onClick={r.reload}>{t('common.retry')}</Button>} />
      ) : !d ? (
        <div className="grid gap-4"><Skeleton className="h-28" /><Skeleton className="h-72" /><Skeleton className="h-72" /></div>
      ) : (
        <div className="grid gap-5">
          {d.margin.low && (
            <div role="alert" className="flex flex-wrap items-center gap-3 rounded-lg border border-danger/40 bg-danger/8 px-4 py-3">
              <AlertTriangle className="size-5 text-danger" aria-hidden />
              <p className="t-small flex-1 text-fg">{t('reports.margin_low', { margin: d.margin.margin, threshold: d.margin.threshold })}</p>
              <Link to="/admin/ai/models" className="t-label text-primary hover:underline">{t('reports.review_models')}</Link>
            </div>
          )}

          {/* Ringkasan */}
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <KpiCard label={t('reports.k.sales')} value={rupiah(d.finance.sales)} hint={t('reports.range', { from: dateTime(d.range.from, false), to: dateTime(d.range.to, false) })} />
            <KpiCard label={t('reports.k.paid_users')} value={number(d.users_by_plan.paid)} hint={t('reports.conversion', { pct: d.users_by_plan.conversion })} />
            <KpiCard label={t('reports.k.ai_success')} value={d.ai.success_rate === null ? '—' : `${d.ai.success_rate}%`} hint={t('reports.jobs', { n: number(d.ai.total) })} />
            <KpiCard label={t('reports.k.ai_margin')} value={d.margin.margin === null ? '—' : `${d.margin.margin}%`} tone={d.margin.low ? 'warning' : undefined} hint={t('reports.margin_hint', { revenue: rupiah(d.margin.revenue), cost: rupiah(d.margin.cost) })} />
          </div>

          <div className="grid gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
            <Card>
              <CardTitle>{t('reports.daily_sales')}</CardTitle>
              <DailyBars rows={d.finance.daily} label={t('reports.daily_sales')} />
            </Card>
            <Card>
              <CardTitle>{t('reports.users_by_plan')}</CardTitle>
              <HBars rows={d.users_by_plan.plans.map((p) => ({ label: p.name, value: p.users }))} total={d.users_by_plan.total} />
              <p className="t-small mt-3 text-muted">{t('reports.users_note', { total: number(d.users_by_plan.total), suspended: number(d.users_by_plan.suspended) })}</p>
            </Card>
          </div>

          <div className="grid gap-5 lg:grid-cols-2">
            <Card>
              <CardTitle>{t('reports.funnel')}</CardTitle>
              <HBars rows={(['registered', 'verified', 'active', 'paid'] as const).map((k) => ({ label: t(`reports.f.${k}`), value: d.funnel[k] }))} total={Math.max(1, d.funnel.registered)} showPct />
              <p className="t-small mt-3 text-muted">{t('reports.funnel_note', { pct: d.funnel.conversion })}</p>
            </Card>
            <Card>
              <CardTitle>{t('reports.operations')}</CardTitle>
              <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <Stat k={t('reports.o.payment_success')} v={pct(d.operations.payment_success)} sub={t('reports.o.of', { n: number(d.operations.payments) })} />
                <Stat k={t('reports.o.renewal_success')} v={pct(d.operations.renewal_success)} sub={t('reports.o.renewals', { ok: d.operations.renewals, fail: d.operations.renewals_failed })} />
                <Stat k={t('reports.o.sla')} v={pct(d.operations.tickets_within_sla)} sub={t('reports.o.tickets', { n: d.operations.tickets, done: d.operations.tickets_resolved })} />
                <Stat k={t('reports.o.avg_ai')} v={d.ai.avg_seconds === null ? '—' : t('reports.o.seconds', { n: d.ai.avg_seconds })} sub={t('reports.o.recovered', { n: d.ai.stuck_recovered })} />
              </dl>
            </Card>
          </div>

          <div className="grid gap-5 lg:grid-cols-2">
            <Card>
              <CardTitle>{t('reports.finance')}</CardTitle>
              <dl className="grid gap-2 text-sm">
                {(['sales', 'topups', 'refunds', 'discounts', 'commission', 'withdrawals_paid', 'liabilities'] as const).map((k) => (
                  <div key={k} className="flex justify-between gap-3 border-b border-line pb-2 last:border-0">
                    <dt className="text-muted">{t(`reports.fin.${k}`)}</dt>
                    <dd className="font-semibold tabular-nums text-fg">{rupiah(d.finance[k])}</dd>
                  </div>
                ))}
              </dl>
            </Card>
            <Card>
              <CardTitle>{t('reports.ai_jobs')}</CardTitle>
              <dl className="mb-3 grid grid-cols-4 gap-2 text-center text-sm">
                {(['completed', 'failed', 'cancelled', 'running'] as const).map((k) => (
                  <div key={k} className="rounded-md bg-surface-2 p-2">
                    <dd className="text-lg font-bold tabular-nums text-fg">{number(d.ai[k])}</dd>
                    <dt className="t-small text-muted">{t(`reports.ai.${k}`)}</dt>
                  </div>
                ))}
              </dl>
              <HBars rows={d.ai.by_type.map((x) => ({ label: t(`reports.job_types.${x.type}`, { defaultValue: x.type }), value: x.jobs }))} total={Math.max(1, d.ai.total)} />
            </Card>
          </div>

          {/* KPI — tampilan tabel (blueprint "7. KPI Dashboard") */}
          <Card>
            <CardTitle>{t('reports.kpi')}</CardTitle>
            <div className="-mx-4 overflow-x-auto px-4">
              <table className="w-full min-w-[560px] text-sm">
                <thead>
                  <tr className="border-b border-line text-left text-muted">
                    <th className="py-2 font-medium">{t('reports.kpi_name')}</th>
                    <th className="py-2 text-right font-medium">{t('reports.today')}</th>
                    <th className="py-2 text-right font-medium">{t('reports.period')}</th>
                    <th className="py-2 text-right font-medium">{t('reports.previous')}</th>
                    <th className="py-2 text-right font-medium">Δ</th>
                  </tr>
                </thead>
                <tbody>
                  {d.kpi.map((k) => {
                    const fmt = (v: number) => (MONEY_KPI.has(k.key) ? rupiah(v) : number(v))
                    const bad = k.key === 'renewals_failed'
                    const up = (k.delta ?? 0) > 0
                    return (
                      <tr key={k.key} className="border-b border-line last:border-0">
                        <td className="py-2 text-fg">{t(`reports.kpis.${k.key}`)}</td>
                        <td className="py-2 text-right tabular-nums">{fmt(k.today)}</td>
                        <td className="py-2 text-right font-semibold tabular-nums">{fmt(k.period)}</td>
                        <td className="py-2 text-right tabular-nums text-muted">{fmt(k.previous)}</td>
                        <td className={cn('py-2 text-right tabular-nums', k.delta === null || k.delta === 0 ? 'text-muted' : up !== bad ? 'text-success' : 'text-danger')}>
                          {k.delta === null ? '—' : `${up ? '▲' : k.delta < 0 ? '▼' : ''} ${Math.abs(k.delta)}%`}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          </Card>

          <Card>
            <CardTitle>{t('reports.templates')}</CardTitle>
            {d.templates.length === 0 ? <p className="t-small text-muted">{t('reports.no_data')}</p> : (
              <HBars rows={d.templates.map((x) => ({ label: x.title, value: x.uses, tag: x.premium ? t('reports.premium') : undefined }))} total={Math.max(1, ...d.templates.map((x) => x.uses))} />
            )}
          </Card>
        </div>
      )}
    </>
  )
}

const pct = (v: number | null) => (v === null ? '—' : `${v}%`)

function Stat({ k, v, sub }: { k: string; v: string; sub?: string }) {
  return (
    <div className="grid gap-0.5">
      <dt className="t-small text-muted">{k}</dt>
      <dd className="text-xl font-bold tabular-nums text-fg">{v}</dd>
      {sub && <dd className="t-small text-muted">{sub}</dd>}
    </div>
  )
}

/** Batang horizontal satu seri: label kiri, nilai kanan (tinta teks), batang primary tipis dengan ujung membulat. */
function HBars({ rows, total, showPct }: { rows: { label: string; value: number; tag?: string }[]; total: number; showPct?: boolean }) {
  const { t } = useTranslation()
  const max = Math.max(1, ...rows.map((r) => r.value))
  if (rows.length === 0) return <p className="t-small text-muted">{t('reports.no_data')}</p>
  return (
    <ul className="grid gap-2.5">
      {rows.map((r) => (
        <li key={r.label} className="grid gap-1" title={`${r.label}: ${number(r.value)}`}>
          <div className="flex items-baseline justify-between gap-3 text-sm">
            <span className="truncate text-fg">{r.label}{r.tag && <span className="ml-2"><Badge tone="info">{r.tag}</Badge></span>}</span>
            <span className="shrink-0 tabular-nums text-muted">
              {number(r.value)}{showPct ? ` · ${Math.round((r.value / total) * 100)}%` : ''}
            </span>
          </div>
          <div className="h-2 rounded-full bg-surface-2" aria-hidden>
            <div className="h-full rounded-full bg-primary" style={{ width: `${Math.max(r.value > 0 ? 2 : 0, (r.value / max) * 100)}%` }} />
          </div>
        </li>
      ))}
    </ul>
  )
}

/** Batang vertikal pendapatan harian + tooltip hover/fokus; tabel ringkas untuk pembaca layar. */
function DailyBars({ rows, label }: { rows: { date: string; amount: number }[]; label: string }) {
  const { t } = useTranslation()
  const [hover, setHover] = useState<number | null>(null)
  if (rows.length === 0) return <p className="t-small text-muted">{t('reports.no_sales')}</p>
  const max = Math.max(1, ...rows.map((r) => r.amount))
  const total = rows.reduce((s, r) => s + r.amount, 0)
  const cur = hover !== null ? rows[hover] : null
  return (
    <div className="grid gap-2">
      <div className="flex items-baseline justify-between gap-2 text-sm">
        <span className="text-muted">{cur ? dateTime(cur.date, false) : t('reports.total')}</span>
        <span className="font-semibold tabular-nums text-fg">{rupiah(cur ? cur.amount : total)}</span>
      </div>
      <div className="flex h-44 items-end gap-[2px] border-b border-line" role="img" aria-label={`${label}: ${rupiah(total)}`} onMouseLeave={() => setHover(null)}>
        {rows.map((r, i) => (
          <button
            key={r.date}
            type="button"
            onMouseEnter={() => setHover(i)}
            onFocus={() => setHover(i)}
            onBlur={() => setHover(null)}
            aria-label={`${dateTime(r.date, false)}: ${rupiah(r.amount)}`}
            className="group flex h-full min-w-0 flex-1 items-end focus-visible:outline-none"
          >
            <span className={cn('block w-full rounded-t-[4px] transition-colors duration-[120ms]', hover === i ? 'bg-primary-hover' : 'bg-primary', 'group-focus-visible:ring-2 group-focus-visible:ring-primary/40')} style={{ height: `${Math.max(2, (r.amount / max) * 100)}%` }} />
          </button>
        ))}
      </div>
      <div className="t-small flex justify-between text-muted">
        <span>{dateTime(rows[0].date, false)}</span>
        <span>{dateTime(rows[rows.length - 1].date, false)}</span>
      </div>
    </div>
  )
}
