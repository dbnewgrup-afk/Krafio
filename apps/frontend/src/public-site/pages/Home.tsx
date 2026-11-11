import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ArrowRight, Check, Image as ImageIcon, Sparkles, Wallet, HandCoins, Globe, SearchCheck } from 'lucide-react'
import { Carousel } from '@/shared/ui/Carousel'
import { ButtonLink } from '@/shared/ui/Button'
import { Accordion, Tabs } from '@/shared/ui/Misc'
import { Skeleton } from '@/shared/ui/States'
import { FEATURES } from '@/shared/features'
import { http } from '@/api-client/http'
import type { FaqItem, Plan, TemplateItem } from '@/api-client/types'
import { rupiah } from '@/shared/lib/format'
import { useAuth } from '@/shared/context/AuthContext'
import { TemplateCard } from '../components/TemplateCard'
import { PlanCard } from '../components/PlanCard'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { cn } from '@/shared/lib/cn'
import { img } from '@/shared/lib/assets'

export default function Home() {
  const { t } = useTranslation()
  useDocTitle(t('public.home_title'))
  return (
    <>
      <Hero />
      <FeatureWorkbench />
      <TemplatesStrip />
      <MoneySplit />
      <PricingPreview />
      <FaqPreview />
    </>
  )
}

/* ---------- Hero: carousel 3 slide, CTA sama di setiap slide (§14.3, §14.4) ---------- */
function Hero() {
  const { t } = useTranslation()
  const slides = [
    { key: 'photo', visual: <PhotoPanelVisual /> },
    { key: 'website', visual: <WebsiteVisual /> },
    { key: 'seo', visual: <SeoVisual /> },
  ]
  return (
    <section className="border-b border-line bg-bg-subtle">
      <div className="mx-auto max-w-[1360px] px-4 pb-6 pt-6 md:px-6 lg:px-8 lg:pb-10 lg:pt-10">
        <div className="k-hero-stage rounded-2xl border border-line px-3 pb-3 pt-4 shadow-k-md md:px-6 md:pb-4 md:pt-8 lg:px-4 lg:pb-5 lg:pt-10">
        <Carousel label={t('home.hero_label')} itemClass="basis-full" autoplay interval={6500} loop gap="gap-0" variant="hero">
          {slides.map((s, i) => (
            <div key={s.key} className="grid items-center gap-6 px-1 pb-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:gap-12 lg:px-6">
              <div className="grid content-center gap-4 lg:gap-5">
                {i === 0 ? <h1 className="t-display text-fg">{t(`home.hero.${s.key}.title`)}</h1> : <h2 className="t-display text-fg">{t(`home.hero.${s.key}.title`)}</h2>}
                <p className="t-body max-w-[46ch] text-muted lg:text-lg lg:leading-7">{t(`home.hero.${s.key}.body`)}</p>
                <div className="flex flex-wrap gap-2 pt-1">
                  <ButtonLink to="/register" size="lg" icon={<Sparkles className="size-4" aria-hidden />}>
                    {t('public.start_free')}
                  </ButtonLink>
                  <ButtonLink to="/templates" size="lg" variant="secondary">
                    {t('home.see_templates')}
                  </ButtonLink>
                </div>
              </div>
              <div className="min-w-0">{s.visual}</div>
            </div>
          ))}
        </Carousel>
        </div>
      </div>
    </section>
  )
}

/** Visual hero = antarmuka produk sesungguhnya (panel Generate) dalam versi statis, bukan ilustrasi. */
function PhotoPanelVisual() {
  const { t } = useTranslation()
  return (
    <div aria-hidden className="grid gap-3 rounded-xl border border-line-strong bg-surface p-4 shadow-k-md md:p-5">
      <div className="flex items-center gap-2">
        <span className="grid size-8 place-items-center rounded-md bg-primary-soft text-primary">
          <ImageIcon className="size-4" />
        </span>
        <p className="font-semibold text-fg">{t('features.photo_generate')}</p>
      </div>
      <div className="rounded-md border border-line bg-surface-2 p-3 text-sm text-fg">{t('home.visual.prompt')}</div>
      <div className="grid grid-cols-3 gap-2 text-[12px]">
        {['1:1', '4:5', '9:16'].map((r, i) => (
          <span key={r} className={cn('rounded-md border px-2 py-1.5 text-center font-semibold', i === 0 ? 'border-primary bg-primary-soft text-primary' : 'border-line text-muted')}>
            {r}
          </span>
        ))}
      </div>
      <div className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 rounded-md bg-bg-subtle p-3">
        <div className="text-[13px] leading-5">
          <p className="text-muted">{t('generate.estimate')}</p>
          <p className="font-semibold text-fg">{t('home.visual.estimate')}</p>
        </div>
        <span className="rounded-md bg-primary px-3 py-2 text-[13px] font-semibold text-primary-contrast">{t('home.visual.cta')}</span>
      </div>
      <img src={img('samples/product-kopi.svg')} alt="" width={480} height={480} className="aspect-[4/3] w-full rounded-lg object-cover object-[50%_40%]" />
      <p className="t-small text-muted">{t('home.visual.note')}</p>
    </div>
  )
}

function WebsiteVisual() {
  const { t } = useTranslation()
  const blocks = ['hero', 'about', 'services', 'contact']
  return (
    <div aria-hidden className="grid gap-3 rounded-xl border border-line-strong bg-surface p-4 shadow-k-md md:grid-cols-[150px_minmax(0,1fr)] md:p-5">
      <div className="grid content-start gap-1.5">
        <p className="t-label text-muted">{t('home.visual.structure')}</p>
        {blocks.map((b, i) => (
          <span key={b} className={cn('rounded-md px-2.5 py-2 text-[13px] font-medium', i === 0 ? 'bg-primary-soft text-primary' : 'text-fg')}>
            {t(`home.visual.blocks.${b}`)}
          </span>
        ))}
      </div>
      <img src={img('samples/site-cafe.svg')} alt="" width={960} height={540} className="w-full rounded-lg border border-line" />
      <div className="flex items-center gap-2 md:col-span-2">
        <Globe className="size-4 text-primary" />
        <span className="t-small text-muted">{t('home.visual.publish_check')}</span>
      </div>
    </div>
  )
}

function SeoVisual() {
  const { t } = useTranslation()
  const rows = [
    { sev: 'danger', key: 'meta' },
    { sev: 'warning', key: 'alt' },
    { sev: 'success', key: 'heading' },
  ]
  return (
    <div aria-hidden className="grid gap-3 rounded-xl border border-line-strong bg-surface p-4 shadow-k-md md:p-5">
      <div className="flex items-center gap-2">
        <span className="grid size-8 place-items-center rounded-md bg-primary-soft text-primary">
          <SearchCheck className="size-4" />
        </span>
        <p className="font-semibold text-fg">{t('seo.score_title')}</p>
      </div>
      <p className="t-small text-muted">{t('seo.score_note')}</p>
      <ul className="grid gap-2">
        {rows.map((r) => (
          <li key={r.key} className="flex items-center gap-3 rounded-md border border-line px-3 py-2.5">
            <span className={cn('size-2.5 shrink-0 rounded-full', r.sev === 'danger' ? 'bg-danger' : r.sev === 'warning' ? 'bg-warning' : 'bg-success')} />
            <span className="flex-1 text-sm text-fg">{t(`home.visual.seo.${r.key}`)}</span>
            <span className="t-small text-muted">{t(`home.visual.seo_sev.${r.sev}`)}</span>
          </li>
        ))}
      </ul>
    </div>
  )
}

/* ---------- Fitur: tab per kelompok + grid (anti vertical sprawl) ---------- */
function FeatureWorkbench() {
  const { t } = useTranslation()
  const [group, setGroup] = useState<'create' | 'edit' | 'grow'>('create')
  const items = FEATURES.filter((f) => f.group === group)
  return (
    <section className="mx-auto max-w-[1360px] px-4 py-8 md:px-6 md:py-12 lg:px-8 lg:py-16" aria-labelledby="feat-h">
      <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div className="grid gap-1">
          <h2 id="feat-h" className="t-h1 text-fg">
            {t('home.features_title')}
          </h2>
          <p className="t-body max-w-[60ch] text-muted">{t('home.features_body')}</p>
        </div>
        <Link to="/features" className="t-label inline-flex items-center gap-1 text-primary hover:underline">
          {t('home.all_features')}
          <ArrowRight className="size-4" aria-hidden />
        </Link>
      </div>
      <Tabs
        label={t('home.features_title')}
        value={group}
        onChange={setGroup}
        items={[
          { value: 'create', label: t('home.group.create') },
          { value: 'edit', label: t('home.group.edit') },
          { value: 'grow', label: t('home.group.grow') },
        ]}
      />
      <ul className="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4" role="tabpanel">
        {items.map((f, i) => (
          <li key={f.slug} className={cn(i === 0 && 'col-span-2 lg:row-span-2')}>
            <Link to={`/features/${f.slug}`} className={cn('group grid h-full content-start gap-2 rounded-lg border border-line bg-surface p-4 transition-[border-color] duration-[120ms] hover:border-primary/50', i === 0 && 'gap-3 bg-bg-subtle p-5 lg:p-6')}>
              {i === 0 && <img src={img(`features/${f.slug}.svg`)} alt="" width={480} height={360} className="mb-1 w-full max-w-md rounded-lg" />}
              <span className={cn('grid place-items-center rounded-md bg-primary-soft text-primary', i === 0 ? 'size-12' : 'size-10')}>
                <f.icon className={i === 0 ? 'size-6' : 'size-5'} aria-hidden />
              </span>
              <h3 className={i === 0 ? 't-h2 text-fg' : 't-h3 text-fg'}>{t(`features.${f.key}`)}</h3>
              <p className={cn('text-muted', i === 0 ? 't-body' : 't-small')}>{t(`features_desc.${f.key}`)}</p>
              {i === 0 && (
                <span className="t-label mt-2 inline-flex items-center gap-1 text-primary">
                  {t('home.learn_more')}
                  <ArrowRight className="size-4 transition-transform duration-[120ms] group-hover:translate-x-0.5" aria-hidden />
                </span>
              )}
            </Link>
          </li>
        ))}
      </ul>
    </section>
  )
}

function TemplatesStrip() {
  const { t } = useTranslation()
  const [items, setItems] = useState<TemplateItem[] | null>(null)
  useEffect(() => {
    http.get<{ data: TemplateItem[] }>('/public/templates', { featured: 1, per_page: 10 }).then((r) => setItems(r.data)).catch(() => setItems([]))
  }, [])
  if (items && items.length === 0) return null
  return (
    <section className="border-y border-line bg-bg-subtle py-8 md:py-12" aria-labelledby="tpl-h">
      <div className="mx-auto max-w-[1360px] px-4 md:px-6 lg:px-8">
        <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
          <h2 id="tpl-h" className="t-h1 text-fg">
            {t('home.templates_title')}
          </h2>
          <Link to="/templates" className="t-label inline-flex items-center gap-1 text-primary hover:underline">
            {t('home.see_templates')}
            <ArrowRight className="size-4" aria-hidden />
          </Link>
        </div>
        {!items ? (
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            {Array.from({ length: 4 }).map((_, i) => (
              <Skeleton key={i} className="aspect-square" />
            ))}
          </div>
        ) : (
          <Carousel label={t('home.templates_title')} itemClass="basis-[78%] sm:basis-[44%] md:basis-[31%] lg:basis-[23.5%]">
            {items.map((tp) => (
              <TemplateCard key={tp.id} item={tp} to={`/templates?id=${tp.id}`} />
            ))}
          </Carousel>
        )}
      </div>
    </section>
  )
}

/* ---------- Saldo top-up vs komisi: aturan nyata dari PRD Bab 5 ---------- */
function MoneySplit() {
  const { t } = useTranslation()
  const { settings } = useAuth()
  const min = settings?.topup_min ?? 50000
  const max = settings?.topup_max ?? 1000000
  const wmin = settings?.withdraw_min ?? 100000
  return (
    <section className="mx-auto max-w-[1360px] px-4 py-8 md:px-6 md:py-12 lg:px-8 lg:py-16" aria-labelledby="money-h">
      <div className="grid gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:gap-12">
        <div className="grid content-start gap-3">
          <h2 id="money-h" className="t-h1 text-fg">
            {t('home.money_title')}
          </h2>
          <p className="t-body max-w-[52ch] text-muted">{t('home.money_body')}</p>
          <Link to="/affiliate" className="t-label inline-flex items-center gap-1 text-primary hover:underline">
            {t('home.affiliate_link')}
            <ArrowRight className="size-4" aria-hidden />
          </Link>
        </div>
        <div className="grid gap-3 sm:grid-cols-2">
          <div className="grid content-start gap-2 rounded-lg border border-line border-t-4 border-t-wallet-topup bg-surface p-5">
            <Wallet className="size-6 text-wallet-topup" aria-hidden />
            <h3 className="t-h3 text-fg">{t('wallet.topup')}</h3>
            <p className="t-small text-muted">{t('home.topup_desc')}</p>
            <ul className="mt-1 grid gap-1.5">
              <Rule>{t('home.topup_range', { min: rupiah(min), max: rupiah(max) })}</Rule>
              <Rule>{t('wallet.not_withdrawable')}</Rule>
            </ul>
          </div>
          <div className="grid content-start gap-2 rounded-lg border border-line border-t-4 border-t-wallet-commission bg-surface p-5">
            <HandCoins className="size-6 text-wallet-commission" aria-hidden />
            <h3 className="t-h3 text-fg">{t('wallet.commission')}</h3>
            <p className="t-small text-muted">{t('home.commission_desc')}</p>
            <ul className="mt-1 grid gap-1.5">
              <Rule>{t('wallet.withdrawable_min', { min: rupiah(wmin) })}</Rule>
              <Rule>{t('home.withdraw_time')}</Rule>
            </ul>
          </div>
        </div>
      </div>
    </section>
  )
}

function Rule({ children }: { children: React.ReactNode }) {
  return (
    <li className="t-small flex items-start gap-2 text-fg">
      <Check className="mt-0.5 size-4 shrink-0 text-success" aria-hidden />
      {children}
    </li>
  )
}

function PricingPreview() {
  const { t } = useTranslation()
  const [plans, setPlans] = useState<Plan[] | null>(null)
  useEffect(() => {
    http.get<{ data: Plan[] }>('/public/plans').then((r) => setPlans(r.data)).catch(() => setPlans([]))
  }, [])
  if (plans && plans.length === 0) return null
  return (
    <section className="border-t border-line bg-bg-subtle py-8 md:py-12 lg:py-16" aria-labelledby="price-h">
      <div className="mx-auto max-w-[1360px] px-4 md:px-6 lg:px-8">
        <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
          <h2 id="price-h" className="t-h1 text-fg">
            {t('home.pricing_title')}
          </h2>
          <Link to="/pricing" className="t-label inline-flex items-center gap-1 text-primary hover:underline">
            {t('home.compare_plans')}
            <ArrowRight className="size-4" aria-hidden />
          </Link>
        </div>
        {!plans ? (
          <div className="grid gap-3 md:grid-cols-3">
            {Array.from({ length: 3 }).map((_, i) => (
              <Skeleton key={i} className="h-72" />
            ))}
          </div>
        ) : (
          <Carousel label={t('home.pricing_title')} itemClass="basis-[84%] sm:basis-[48%] lg:basis-[calc((100%-3rem)/4)]" dots>
            {plans.map((p) => (
              <PlanCard key={p.id} plan={p} cycle="monthly" />
            ))}
          </Carousel>
        )}
      </div>
    </section>
  )
}

function FaqPreview() {
  const { t } = useTranslation()
  const [items, setItems] = useState<FaqItem[] | null>(null)
  useEffect(() => {
    http.get<{ data: FaqItem[] }>('/public/faqs', { limit: 6 }).then((r) => setItems(r.data)).catch(() => setItems([]))
  }, [])
  return (
    <section className="mx-auto max-w-[1360px] px-4 py-8 md:px-6 md:py-12 lg:px-8 lg:py-16" aria-labelledby="faq-h">
      <div className="grid gap-6 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:gap-12">
        <div className="grid content-start gap-3">
          <h2 id="faq-h" className="t-h1 text-fg">
            {t('home.faq_title')}
          </h2>
          <p className="t-body text-muted">{t('home.faq_body')}</p>
          <div className="flex flex-wrap gap-2 pt-1">
            <ButtonLink to="/register">{t('public.start_free')}</ButtonLink>
            <ButtonLink to="/faq" variant="secondary">
              {t('home.all_faq')}
            </ButtonLink>
          </div>
        </div>
        {!items ? <Skeleton className="h-64" /> : <Accordion items={items.map((f) => ({ id: f.id, title: f.question, body: f.answer }))} />}
      </div>
    </section>
  )
}
