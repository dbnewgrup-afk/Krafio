import { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ArrowRight, Check, Search } from 'lucide-react'
import { FEATURES, featureBySlug } from '@/shared/features'
import { http } from '@/api-client/http'
import type { Addon, Plan, TemplateItem } from '@/api-client/types'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { ButtonLink, Button } from '@/shared/ui/Button'
import { Accordion, ChipRow, Segmented } from '@/shared/ui/Misc'
import { EmptyState, Skeleton } from '@/shared/ui/States'
import { Modal } from '@/shared/ui/Modal'
import { Carousel } from '@/shared/ui/Carousel'
import { inputCls } from '@/shared/ui/Field'
import { PlanCard } from '../components/PlanCard'
import { TemplateCard, TemplatePreview } from '../components/TemplateCard'
import { rupiah, number } from '@/shared/lib/format'
import { useAuth } from '@/shared/context/AuthContext'
import { cn } from '@/shared/lib/cn'

export function PageIntro({ title, body, children }: { title: string; body?: string; children?: React.ReactNode }) {
  return (
    <div className="border-b border-line bg-bg-subtle">
      <div className="mx-auto grid max-w-[1360px] gap-3 px-4 py-8 md:px-6 md:py-12 lg:px-8">
        <h1 className="t-h1 text-fg lg:text-[40px] lg:leading-[48px]" data-page-title tabIndex={-1}>
          {title}
        </h1>
        {body && <p className="t-body max-w-[64ch] text-muted lg:text-lg lg:leading-7">{body}</p>}
        {children}
      </div>
    </div>
  )
}

const wrap = 'mx-auto max-w-[1360px] px-4 py-8 md:px-6 md:py-12 lg:px-8'

/* ---------------- Fitur ---------------- */
export function FeaturesPage() {
  const { t } = useTranslation()
  useDocTitle(t('public.nav.features'))
  const [group, setGroup] = useState<'all' | 'create' | 'edit' | 'grow'>('all')
  const list = FEATURES.filter((f) => group === 'all' || f.group === group)
  return (
    <>
      <PageIntro title={t('features_page.title')} body={t('features_page.body')} />
      <div className={wrap}>
        <ChipRow
          label={t('features_page.title')}
          value={group}
          onChange={setGroup}
          items={[
            { value: 'all', label: t('table.all') },
            { value: 'create', label: t('home.group.create') },
            { value: 'edit', label: t('home.group.edit') },
            { value: 'grow', label: t('home.group.grow') },
          ]}
        />
        <ul className="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-3">
          {list.map((f) => (
            <li key={f.slug}>
              <Link to={`/features/${f.slug}`} className="group grid h-full content-start gap-2 rounded-lg border border-line bg-surface p-4 transition-[border-color] duration-[120ms] hover:border-primary/50 md:p-5">
                <span className="grid size-10 place-items-center rounded-md bg-primary-soft text-primary">
                  <f.icon className="size-5" aria-hidden />
                </span>
                <h2 className="t-h3 text-fg">{t(`features.${f.key}`)}</h2>
                <p className="t-small text-muted">{t(`features_desc.${f.key}`)}</p>
              </Link>
            </li>
          ))}
        </ul>
      </div>
    </>
  )
}

export function FeatureDetailPage() {
  const { slug = '' } = useParams()
  const { t } = useTranslation()
  const f = featureBySlug(slug)
  useDocTitle(f ? t(`features.${f.key}`) : '')
  const [plans, setPlans] = useState<Plan[] | null>(null)
  useEffect(() => {
    http.get<{ data: Plan[] }>('/public/plans').then((r) => setPlans(r.data)).catch(() => setPlans([]))
  }, [])
  if (!f) return <NotFoundPage />
  const included = plans?.filter((p) => p.features.includes(f.entitlement)) ?? []
  const points = ['p1', 'p2', 'p3']
  return (
    <>
      <PageIntro title={t(`features.${f.key}`)} body={t(`features_desc.${f.key}`)}>
        <div className="flex flex-wrap gap-2 pt-2">
          <ButtonLink to={`/register?next=${encodeURIComponent(f.panel)}`}>{t('features_page.try', { name: t(`features.${f.key}`) })}</ButtonLink>
          <ButtonLink to="/pricing" variant="secondary">
            {t('home.compare_plans')}
          </ButtonLink>
        </div>
      </PageIntro>
      <div className={cn(wrap, 'grid gap-8 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)] lg:gap-12')}>
        <section className="grid content-start gap-4">
          <h2 className="t-h2 text-fg">{t('features_page.how')}</h2>
          <ol className="grid gap-3">
            {points.map((p, i) => (
              <li key={p} className="flex gap-3 rounded-lg border border-line bg-surface p-4">
                <span className="grid size-8 shrink-0 place-items-center rounded-full bg-primary-soft font-semibold text-primary">{i + 1}</span>
                <p className="t-body text-fg">{t(`feature_points.${f.key}.${p}`)}</p>
              </li>
            ))}
          </ol>
          <div className="rounded-lg border border-line bg-bg-subtle p-4">
            <p className="t-label text-fg">{t('features_page.transparent_title')}</p>
            <p className="t-small mt-1 text-muted">{t('features_page.transparent_body')}</p>
          </div>
        </section>
        <aside className="grid content-start gap-3">
          <h2 className="t-h3 text-fg">{t('features_page.available_in')}</h2>
          {!plans ? (
            <Skeleton className="h-32" />
          ) : included.length === 0 ? (
            <p className="t-small text-muted">{t('features_page.addon_only')}</p>
          ) : (
            <ul className="grid gap-2">
              {included.map((p) => (
                <li key={p.id} className="flex items-center justify-between gap-3 rounded-md border border-line bg-surface px-4 py-3">
                  <span className="font-semibold text-fg">{p.name}</span>
                  <span className="t-small text-muted">{p.quotas[f.entitlement] ? t('pricing.quota', { n: number(p.quotas[f.entitlement]) }) : t('pricing.included')}</span>
                </li>
              ))}
            </ul>
          )}
          <Link to="/features" className="t-label mt-2 inline-flex items-center gap-1 text-primary hover:underline">
            {t('home.all_features')}
            <ArrowRight className="size-4" aria-hidden />
          </Link>
        </aside>
      </div>
    </>
  )
}

/* ---------------- Harga ---------------- */
export function PricingPage() {
  const { t } = useTranslation()
  useDocTitle(t('public.nav.pricing'))
  const [cycle, setCycle] = useState<'monthly' | 'yearly'>('monthly')
  const [plans, setPlans] = useState<Plan[] | null>(null)
  const [addons, setAddons] = useState<Addon[] | null>(null)
  useEffect(() => {
    http.get<{ data: Plan[] }>('/public/plans').then((r) => setPlans(r.data)).catch(() => setPlans([]))
    http.get<{ data: Addon[] }>('/public/addons').then((r) => setAddons(r.data)).catch(() => setAddons([]))
  }, [])
  return (
    <>
      <PageIntro title={t('pricing.title')} body={t('pricing.body')}>
        <div className="pt-2">
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
      </PageIntro>
      <div className={wrap}>
        {!plans ? (
          <div className="grid gap-3 md:grid-cols-3">
            {Array.from({ length: 3 }).map((_, i) => (
              <Skeleton key={i} className="h-96" />
            ))}
          </div>
        ) : plans.length === 0 ? (
          <EmptyState title={t('pricing.empty')} />
        ) : (
          <Carousel label={t('pricing.title')} itemClass="basis-[86%] sm:basis-[48%] lg:basis-[calc((100%-3rem)/4)]">
            {plans.map((p) => (
              <PlanCard key={p.id} plan={p} cycle={cycle} />
            ))}
          </Carousel>
        )}
        <p className="t-small mt-4 text-muted">{t('pricing.price_note')}</p>

        <section className="mt-10 grid gap-6 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:gap-12" aria-labelledby="addon-h">
          <div className="grid content-start gap-2">
            <h2 id="addon-h" className="t-h2 text-fg">
              {t('pricing.addons_title')}
            </h2>
            <p className="t-body text-muted">{t('pricing.addons_body')}</p>
            <Link to="/addons" className="t-label inline-flex items-center gap-1 text-primary hover:underline">
              {t('pricing.all_addons')}
              <ArrowRight className="size-4" aria-hidden />
            </Link>
          </div>
          {!addons ? <Skeleton className="h-40" /> : <AddonGrid addons={addons.slice(0, 4)} />}
        </section>

        <section className="mt-10 grid gap-4" aria-labelledby="pq-h">
          <h2 id="pq-h" className="t-h2 text-fg">
            {t('pricing.questions')}
          </h2>
          <Accordion
            items={['renew', 'expire', 'upgrade', 'refund'].map((k) => ({
              id: k,
              title: t(`pricing.faq.${k}_q`),
              body: t(`pricing.faq.${k}_a`),
            }))}
          />
        </section>
      </div>
    </>
  )
}

function AddonGrid({ addons }: { addons: Addon[] }) {
  const { t } = useTranslation()
  if (addons.length === 0) return <EmptyState title={t('pricing.addons_empty')} />
  return (
    <ul className="grid grid-cols-2 gap-3">
      {addons.map((a) => (
        <li key={a.id} className="grid content-start gap-1.5 rounded-lg border border-line bg-surface p-4">
          <p className="font-semibold text-fg">{a.name}</p>
          <p className="t-small text-muted">{a.description}</p>
          <p className="mt-1 font-semibold tabular-nums text-fg">{rupiah(a.price)}</p>
          <p className="t-small text-muted">{t(`addons.billing.${a.billing}`)}</p>
        </li>
      ))}
    </ul>
  )
}

export function AddonsPage() {
  const { t } = useTranslation()
  useDocTitle(t('footer.addons'))
  const [addons, setAddons] = useState<Addon[] | null>(null)
  useEffect(() => {
    http.get<{ data: Addon[] }>('/public/addons').then((r) => setAddons(r.data)).catch(() => setAddons([]))
  }, [])
  return (
    <>
      <PageIntro title={t('pricing.addons_title')} body={t('pricing.addons_body')} />
      <div className={wrap}>{!addons ? <Skeleton className="h-60" /> : <AddonGrid addons={addons} />}</div>
    </>
  )
}

/* ---------------- Template ---------------- */
export function TemplatesPage() {
  const { t } = useTranslation()
  useDocTitle(t('public.nav.templates'))
  const { me } = useAuth()
  const nav = useNavigate()
  const [params, setParams] = useSearchParams()
  const [type, setType] = useState('all')
  const [q, setQ] = useState('')
  const [sort, setSort] = useState('popular')
  const [items, setItems] = useState<TemplateItem[] | null>(null)
  useEffect(() => {
    setItems(null)
    const id = window.setTimeout(() => {
      http
        .get<{ data: TemplateItem[] }>('/public/templates', { type: type === 'all' ? '' : type, search: q, sort, per_page: 48 })
        .then((r) => setItems(r.data))
        .catch(() => setItems([]))
    }, 250)
    return () => window.clearTimeout(id)
  }, [type, q, sort])
  const openId = Number(params.get('id'))
  const open = useMemo(() => items?.find((x) => x.id === openId) ?? null, [items, openId])
  const related = useMemo(() => (open ? (items ?? []).filter((x) => x.type === open.type && x.id !== open.id).slice(0, 6) : []), [items, open])
  const types = ['photo', 'video', 'website', 'social', 'business']

  return (
    <>
      <PageIntro title={t('templates.title')} body={t('templates.body')} />
      <div className={wrap}>
        <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_220px]">
          <div className="relative">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" aria-hidden />
            <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('templates.search')} aria-label={t('templates.search')} className={cn(inputCls, 'pl-9')} />
          </div>
          <select value={sort} onChange={(e) => setSort(e.target.value)} aria-label={t('templates.sort')} className={inputCls}>
            <option value="popular">{t('templates.sort_popular')}</option>
            <option value="newest">{t('templates.sort_newest')}</option>
          </select>
        </div>
        <div className="mt-3">
          <ChipRow label={t('templates.category')} value={type} onChange={setType} items={[{ value: 'all', label: t('table.all') }, ...types.map((x) => ({ value: x, label: t(`templates.types.${x}`) }))]} />
        </div>
        <div className="mt-5">
          {!items ? (
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
              {Array.from({ length: 8 }).map((_, i) => (
                <Skeleton key={i} className="aspect-square" />
              ))}
            </div>
          ) : items.length === 0 ? (
            <EmptyState kind="filter" title={t('table.no_results')} body={t('table.no_results_body')} action={<Button variant="secondary" onClick={() => { setQ(''); setType('all') }}>{t('table.reset_filter')}</Button>} />
          ) : (
            <ul className="grid grid-cols-2 gap-2 md:grid-cols-3 lg:grid-cols-4">
              {items.map((it) => (
                <li key={it.id}>
                  <TemplateCard item={it} onClick={() => setParams({ id: String(it.id) })} />
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>
      <Modal
        open={!!open}
        onClose={() => {
          params.delete('id')
          setParams(params, { replace: true })
        }}
        size="media"
        title={open?.title ?? ''}
        headerExtra={open && <p className="t-small text-muted">{t(`templates.types.${open.type}`)} · {open.ratio} · {open.is_premium ? t('templates.premium') : t('templates.free')}</p>}
        footer={
          open && (
            <Button
              onClick={() => {
                const target = `/app/templates?use=${open.id}`
                nav(me ? target : `/register?next=${encodeURIComponent(target)}`)
              }}
            >
              {t('templates.use')}
            </Button>
          )
        }
      >
        {open && (
          <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
            <div className="mx-auto w-full max-w-xl">
              <TemplatePreview item={open} watermark={!me} />
            </div>
            <div className="grid content-start gap-3">
              <p className="t-body text-muted">{open.description}</p>
              <ul className="grid gap-1.5">
                <li className="t-small flex items-center gap-2 text-fg">
                  <Check className="size-4 text-success" aria-hidden />
                  {t('templates.editable')}
                </li>
                <li className="t-small flex items-center gap-2 text-fg">
                  <Check className="size-4 text-success" aria-hidden />
                  {t('templates.ratio', { ratio: open.ratio })}
                </li>
              </ul>
              {related.length > 0 && (
                <>
                  <p className="t-label mt-2 text-fg">{t('templates.related')}</p>
                  <Carousel label={t('templates.related')} itemClass="basis-[46%]" variant="strip">
                    {related.map((r) => (
                      <TemplateCard key={r.id} item={r} onClick={() => setParams({ id: String(r.id) })} />
                    ))}
                  </Carousel>
                </>
              )}
            </div>
          </div>
        )}
      </Modal>
    </>
  )
}

/* ---------------- 404 ---------------- */
export function NotFoundPage() {
  const { t } = useTranslation()
  useDocTitle(t('errors.not_found_title'))
  const nav = useNavigate()
  const [q, setQ] = useState('')
  return (
    <div className="mx-auto grid max-w-xl justify-items-center gap-4 px-4 py-16 text-center">
      <p className="t-mono text-muted">404</p>
      <h1 className="t-h1 text-fg" data-page-title tabIndex={-1}>
        {t('errors.not_found_title')}
      </h1>
      <p className="t-body text-muted">{t('errors.not_found_body')}</p>
      <form
        className="relative w-full"
        onSubmit={(e) => {
          e.preventDefault()
          nav(`/faq?search=${encodeURIComponent(q)}`)
        }}
      >
        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" aria-hidden />
        <input value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('errors.search_help')} aria-label={t('errors.search_help')} className={cn(inputCls, 'pl-9')} />
      </form>
      <div className="flex flex-wrap justify-center gap-2">
        {[
          ['/', t('errors.go_home')],
          ['/features', t('public.nav.features')],
          ['/pricing', t('public.nav.pricing')],
          ['/templates', t('public.nav.templates')],
        ].map(([to, l]) => (
          <Link key={to} to={to} className="t-label inline-flex h-10 items-center rounded-full border border-line px-4 hover:bg-surface-2">
            {l}
          </Link>
        ))}
      </div>
    </div>
  )
}
