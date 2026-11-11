import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ArrowRight, Lock, Plus } from 'lucide-react'
import { useApi } from '@/shared/hooks/useApi'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { useAuth } from '@/shared/context/AuthContext'
import { Card, CardTitle, PageHeader } from '@/shared/ui/Misc'
import { Carousel } from '@/shared/ui/Carousel'
import { StatusChip } from '@/shared/ui/Status'
import { EmptyState, ErrorState, Skeleton } from '@/shared/ui/States'
import { ButtonLink } from '@/shared/ui/Button'
import { FEATURES } from '@/shared/features'
import { useLocked } from '@/shared/layout/Locked'
import { dateTime, number, relative, rupiah } from '@/shared/lib/format'
import type { AiJob, Entitlement } from '@/api-client/types'
import { cn } from '@/shared/lib/cn'
import { useIsMobile } from '@/shared/hooks/useMediaQuery'
import { motion } from 'framer-motion'
import { CountUp } from '@/shared/motion/CountUp'
import { RevealCard } from '@/shared/motion/Reveal'
import { useIntro } from '@/shared/motion/intro'
import { DashboardHero } from '@/shared/ui/DashboardHero'

interface DashData {
  jobs_summary: Record<'queued' | 'processing' | 'completed' | 'failed', number>
  recent_jobs: AiJob[]
  recent_projects: { id: number; name: string; type: string; updated_at: string }[]
  notices: { id: string; title: string; body: string; created_at: string }[]
}

export function QuotaBar({ e }: { e: Entitlement }) {
  const { t } = useTranslation()
  const pct = e.quota ? Math.min(100, Math.round(((e.quota - (e.remaining ?? 0)) / e.quota) * 100)) : 0
  const { base } = useIntro()
  return (
    <div className="grid gap-1.5">
      <div className="flex items-baseline justify-between gap-2">
        <span className="t-small truncate text-fg">{t(`features.${e.key.replace('.', '_')}`)}</span>
        <span className="t-small tabular-nums text-muted">{e.quota ? <><CountUp to={e.remaining ?? 0} duration={1.1} /> / {number(e.quota)}</> : t('user.dash.unlimited')}</span>
      </div>
      {e.quota ? (
        <div className="h-1.5 overflow-hidden rounded-full bg-surface-2" role="progressbar" aria-valuenow={pct} aria-valuemin={0} aria-valuemax={100} aria-label={t('user.dash.quota_used', { pct })}>
          <motion.div className={cn('h-full origin-left rounded-full', pct > 85 ? 'bg-danger' : pct > 60 ? 'bg-warning' : 'bg-primary')} style={{ width: `${pct}%` }} initial={{ scaleX: 0 }} animate={{ scaleX: 1 }} transition={{ type: 'spring', stiffness: 120, damping: 18, delay: base + 0.2 }} />
        </div>
      ) : null}
    </div>
  )
}

export default function Dashboard() {
  const { t } = useTranslation()
  useDocTitle(t('user.nav.dashboard'))
  const { me } = useAuth()
  const { openLocked } = useLocked()
  const isMobile = useIsMobile()
  const { data, loading, error, reload } = useApi<DashData>('/app/dashboard')
  if (!me) return null
  const ents = Object.values(me.entitlements).filter((e) => e.enabled && e.quota)

  const summaryCards = [
    <RevealCard key="plan" order={1} className="grid h-full content-start gap-1 rounded-lg border border-line bg-surface p-4">
      <span className="t-small text-muted">{t('user.dash.plan')}</span>
      <span className="text-[22px] font-bold leading-7 text-fg">{me.plan?.name ?? t('locked.no_plan')}</span>
      <span className="t-small text-muted">{me.plan?.ends_at ? t('user.dash.active_until', { date: dateTime(me.plan.ends_at, false) }) : t('user.dash.no_expiry')}</span>
      <Link to="/app/subscription" className="t-label mt-1 text-primary hover:underline">
        {t('user.dash.manage_plan')}
      </Link>
    </RevealCard>,
    <RevealCard key="topup" order={2} className="grid h-full content-start gap-1 rounded-lg border border-line border-l-4 border-l-wallet-topup bg-surface p-4">
      <span className="t-small text-muted">{t('wallet.topup')}</span>
      <span className="text-[22px] font-bold leading-7 tabular-nums text-fg"><CountUp to={me.balances.topup} format={rupiah} overshoot /></span>
      <span className="t-small text-muted">{t('wallet.not_withdrawable')}</span>
      <Link to="/app/wallet/topup" className="t-label mt-1 text-primary hover:underline">
        {t('user.nav.topup')}
      </Link>
    </RevealCard>,
    <RevealCard key="comm" order={3} className="grid h-full content-start gap-1 rounded-lg border border-line border-l-4 border-l-wallet-commission bg-surface p-4">
      <span className="t-small text-muted">{t('wallet.commission')}</span>
      <span className="text-[22px] font-bold leading-7 tabular-nums text-fg"><CountUp to={me.balances.commission} format={rupiah} delay={0.1} /></span>
      <span className="t-small text-muted">{t('wallet.withdrawable_min', { min: rupiah(100000) })}</span>
      <Link to="/app/wallet/withdrawals" className="t-label mt-1 text-primary hover:underline">
        {t('user.nav.withdrawals')}
      </Link>
    </RevealCard>,
  ]

  return (
    <>
      <PageHeader
        title={t('user.dash.greeting', { name: me.name.split(' ')[0] })}
        subtitle={t('user.dash.subtitle')}
        actions={
          <ButtonLink to="/app/create/photo" icon={<Plus className="size-4" aria-hidden />} className="max-md:hidden">
            {t('user.dash.new_creation')}
          </ButtonLink>
        }
      />

      <DashboardHero area="user" />

      {/* Mobile: kartu ringkas horizontal scroll (§8 butir 3); desktop: 3 kolom */}
      {isMobile ? (
        <Carousel label={t('user.dash.summary')} itemClass="basis-[80%]" variant="strip">
          {summaryCards}
        </Carousel>
      ) : (
        <div className="grid grid-cols-3 gap-4">{summaryCards}</div>
      )}

      <div className="mt-6 grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <div className="grid content-start gap-4">
          <RevealCard order={4}>
          <Card>
            <CardTitle action={<Link to="/app/usage" className="t-label text-primary hover:underline">{t('user.dash.see_usage')}</Link>}>{t('user.dash.recent_jobs')}</CardTitle>
            {error ? (
              <ErrorState status={error.status} onRetry={reload} compact />
            ) : loading || !data ? (
              <div className="grid gap-2">
                {Array.from({ length: 4 }).map((_, i) => (
                  <Skeleton key={i} className="h-12" />
                ))}
              </div>
            ) : (
              <>
                <div className="mb-3 grid grid-cols-4 gap-2">
                  {(['queued', 'processing', 'completed', 'failed'] as const).map((k) => (
                    <div key={k} className="rounded-md bg-surface-2 px-2 py-2 text-center">
                      <p className="text-lg font-bold tabular-nums text-fg"><CountUp to={data.jobs_summary[k] ?? 0} /></p>
                      <p className="t-small truncate text-muted">{t(`status.${k}`)}</p>
                    </div>
                  ))}
                </div>
                {data.recent_jobs.length === 0 ? (
                  <EmptyState title={t('user.dash.no_jobs')} body={t('user.dash.no_jobs_body')} action={<ButtonLink to="/app/create/photo" size="sm">{t('user.dash.first_job')}</ButtonLink>} />
                ) : (
                  <ul className="divide-y divide-line">
                    {data.recent_jobs.map((j) => (
                      <li key={j.id} className="flex items-center gap-3 py-2.5">
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-sm font-medium text-fg">{j.prompt}</p>
                          <p className="t-small text-muted">
                            {t(`job_types.${j.type}`)} — {relative(j.created_at)}
                          </p>
                        </div>
                        <StatusChip status={j.status} />
                      </li>
                    ))}
                  </ul>
                )}
              </>
            )}
          </Card>
          </RevealCard>

          <RevealCard order={5}>

          <Card>
            <CardTitle>{t('user.dash.shortcuts')}</CardTitle>
            <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3">
              {FEATURES.map((f) => {
                const enabled = me.entitlements[f.entitlement]?.enabled
                const inner = (
                  <>
                    <f.icon className="size-5 shrink-0 text-primary" aria-hidden />
                    <span className="min-w-0 flex-1 truncate text-left text-sm font-medium">{t(`features.${f.key}`)}</span>
                    {!enabled && <Lock className="size-4 shrink-0 text-muted" aria-label={t('nav.locked')} />}
                  </>
                )
                const cls = 'flex min-h-12 w-full items-center gap-2.5 rounded-md border border-line px-3 hover:border-primary/50'
                return (
                  <li key={f.slug}>
                    {enabled ? (
                      <Link to={f.panel} className={cls}>
                        {inner}
                      </Link>
                    ) : (
                      <button type="button" className={cls} onClick={() => openLocked(f.entitlement)}>
                        {inner}
                      </button>
                    )}
                  </li>
                )
              })}
            </ul>
          </Card>
          </RevealCard>
        </div>

        <div className="grid content-start gap-4">
          <RevealCard order={6}>
          <Card>
            <CardTitle>{t('user.dash.quota')}</CardTitle>
            {ents.length === 0 ? <p className="t-small text-muted">{t('user.dash.no_quota')}</p> : <div className="grid gap-3">{ents.map((e) => <QuotaBar key={e.key} e={e} />)}</div>}
          </Card>
          </RevealCard>
          <RevealCard order={7}>
          <Card>
            <CardTitle action={<Link to="/app/projects" className="t-label text-primary hover:underline">{t('common.see_all')}</Link>}>{t('user.dash.recent_projects')}</CardTitle>
            {!data ? (
              <Skeleton className="h-24" />
            ) : data.recent_projects.length === 0 ? (
              <p className="t-small text-muted">{t('user.dash.no_projects')}</p>
            ) : (
              <ul className="grid gap-1">
                {data.recent_projects.map((p) => (
                  <li key={p.id}>
                    <Link to={`/app/projects/${p.id}`} className="flex items-center justify-between gap-3 rounded-md px-2 py-2 hover:bg-surface-2">
                      <span className="truncate text-sm font-medium text-fg">{p.name}</span>
                      <span className="t-small shrink-0 text-muted">{relative(p.updated_at)}</span>
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </Card>
          </RevealCard>
          <RevealCard order={8}>
          <Card>
            <CardTitle action={<Link to="/app/notifications" className="t-label text-primary hover:underline">{t('common.see_all')}</Link>}>{t('user.dash.notices')}</CardTitle>
            {!data ? (
              <Skeleton className="h-20" />
            ) : data.notices.length === 0 ? (
              <p className="t-small text-muted">{t('notif.empty')}</p>
            ) : (
              <ul className="grid gap-2">
                {data.notices.map((n) => (
                  <li key={n.id} className="rounded-md bg-surface-2 p-3">
                    <p className="text-sm font-semibold text-fg">{n.title}</p>
                    <p className="t-small text-muted">{n.body}</p>
                  </li>
                ))}
              </ul>
            )}
            <Link to="/app/notifications" className="t-label mt-3 inline-flex items-center gap-1 text-primary hover:underline">
              {t('notif.see_all')}
              <ArrowRight className="size-4" aria-hidden />
            </Link>
          </Card>
          </RevealCard>
        </div>
      </div>
    </>
  )
}
