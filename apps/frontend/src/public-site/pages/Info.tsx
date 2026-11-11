import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { CheckCircle2, Link2, Search, ShieldCheck, Clock3, HandCoins, Mail } from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import type { FaqItem } from '@/api-client/types'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { Accordion, ChipRow, Card } from '@/shared/ui/Misc'
import { EmptyState, Skeleton } from '@/shared/ui/States'
import { ButtonLink, Button } from '@/shared/ui/Button'
import { TextField, TextArea, inputCls } from '@/shared/ui/Field'
import { StatusChip } from '@/shared/ui/Status'
import { PageIntro } from './Catalog'
import { LegalBody, useLegalDoc, type LegalType } from '@/shared/layout/Legal'
import { useAuth } from '@/shared/context/AuthContext'
import { dateTime, rupiah } from '@/shared/lib/format'
import { cn } from '@/shared/lib/cn'

const wrap = 'mx-auto max-w-[1360px] px-4 py-8 md:px-6 md:py-12 lg:px-8'

export function AffiliatePage() {
  const { t } = useTranslation()
  const { settings } = useAuth()
  useDocTitle(t('public.nav.affiliate'))
  const steps = [
    { icon: Link2, key: 's1' },
    { icon: Clock3, key: 's2' },
    { icon: HandCoins, key: 's3' },
  ]
  const statuses = ['pending', 'approved', 'available', 'reversed', 'paid']
  return (
    <>
      <PageIntro title={t('affiliate_page.title')} body={t('affiliate_page.body')}>
        <div className="flex flex-wrap gap-2 pt-2">
          <ButtonLink to="/register">{t('affiliate_page.join')}</ButtonLink>
          <ButtonLink to="/affiliate-terms" variant="secondary">
            {t('legal.affiliate_terms_short')}
          </ButtonLink>
        </div>
      </PageIntro>
      <div className={cn(wrap, 'grid gap-8 lg:grid-cols-2 lg:gap-12')}>
        <section className="grid content-start gap-3">
          <h2 className="t-h2 text-fg">{t('affiliate_page.how')}</h2>
          <ol className="grid gap-3">
            {steps.map((s, i) => (
              <li key={s.key} className="flex gap-3 rounded-lg border border-line bg-surface p-4">
                <span className="grid size-10 shrink-0 place-items-center rounded-md bg-primary-soft text-primary">
                  <s.icon className="size-5" aria-hidden />
                </span>
                <div>
                  <p className="font-semibold text-fg">
                    {i + 1}. {t(`affiliate_page.${s.key}_title`)}
                  </p>
                  <p className="t-small text-muted">{t(`affiliate_page.${s.key}_body`, { min: rupiah(settings?.withdraw_min ?? 100000) })}</p>
                </div>
              </li>
            ))}
          </ol>
        </section>
        <section className="grid content-start gap-3">
          <h2 className="t-h2 text-fg">{t('affiliate_page.status_title')}</h2>
          <p className="t-body text-muted">{t('affiliate_page.status_body')}</p>
          <ul className="grid gap-2">
            {statuses.map((s) => (
              <li key={s} className="flex items-start gap-3 rounded-md border border-line bg-surface p-3">
                <StatusChip status={s} label={t(`commission.${s}`)} />
                <span className="t-small text-muted">{t(`affiliate_page.st_${s}`)}</span>
              </li>
            ))}
          </ul>
          <p className="t-small rounded-md bg-surface-2 p-3 text-muted">{t('affiliate_page.note')}</p>
        </section>
      </div>
    </>
  )
}

export function FaqPage() {
  const { t } = useTranslation()
  useDocTitle(t('public.nav.faq'))
  const [params] = useSearchParams()
  const [items, setItems] = useState<FaqItem[] | null>(null)
  const [q, setQ] = useState(params.get('search') ?? '')
  const [cat, setCat] = useState('all')
  useEffect(() => {
    http.get<{ data: FaqItem[] }>('/public/faqs', { limit: 200 }).then((r) => setItems(r.data)).catch(() => setItems([]))
  }, [])
  const cats = useMemo(() => Array.from(new Set((items ?? []).map((f) => f.category))), [items])
  const list = (items ?? []).filter((f) => (cat === 'all' || f.category === cat) && (!q || `${f.question} ${f.answer}`.toLowerCase().includes(q.toLowerCase())))
  return (
    <>
      <PageIntro title={t('faq_page.title')} body={t('faq_page.body')}>
        <div className="relative max-w-xl pt-2">
          <Search className="pointer-events-none absolute left-3 top-[calc(50%+4px)] size-4 -translate-y-1/2 text-muted" aria-hidden />
          <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('faq_page.search')} aria-label={t('faq_page.search')} className={cn(inputCls, 'pl-9')} />
        </div>
      </PageIntro>
      <div className={wrap}>
        {cats.length > 1 && (
          <div className="mb-4">
            <ChipRow label={t('templates.category')} value={cat} onChange={setCat} items={[{ value: 'all', label: t('table.all') }, ...cats.map((c) => ({ value: c, label: t(`faq_cat.${c}`) }))]} />
          </div>
        )}
        {!items ? (
          <Skeleton className="h-80" />
        ) : list.length === 0 ? (
          <EmptyState kind="filter" title={t('table.no_results')} body={t('faq_page.no_results')} action={<ButtonLink to="/contact" variant="secondary">{t('footer.contact')}</ButtonLink>} />
        ) : (
          <div className="max-w-3xl">
            <Accordion items={list.map((f) => ({ id: f.id, title: f.question, body: f.answer }))} />
          </div>
        )}
      </div>
    </>
  )
}

export function ContactPage() {
  const { t } = useTranslation()
  const { settings, me } = useAuth()
  useDocTitle(t('footer.contact'))
  const [form, setForm] = useState({ name: me?.name ?? '', email: me?.email ?? '', subject: '', message: '' })
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [busy, setBusy] = useState(false)
  const [done, setDone] = useState<string | null>(null)
  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setErrors({})
    try {
      const r = await http.post<{ data: { number: string } }>('/support/tickets', { ...form, page: '/contact' })
      setDone(r.data.number)
    } catch (err) {
      if (err instanceof ApiError) setErrors(err.fields)
    } finally {
      setBusy(false)
    }
  }
  return (
    <>
      <PageIntro title={t('contact.title')} body={t('contact.body')} />
      <div className={cn(wrap, 'grid gap-8 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)] lg:gap-12')}>
        <Card>
          {done ? (
            <div className="grid justify-items-center gap-2 py-8 text-center">
              <CheckCircle2 className="size-10 text-success" aria-hidden />
              <h2 className="t-h2">{t('cs.ticket_sent')}</h2>
              <p className="t-body text-muted">{t('cs.ticket_number', { n: done })}</p>
            </div>
          ) : (
            <form onSubmit={submit} noValidate className="grid gap-4">
              <div className="grid gap-4 md:grid-cols-2">
                <TextField label={t('fields.name')} required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} error={errors.name?.[0]} autoComplete="name" />
                <TextField label={t('fields.email')} required type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} error={errors.email?.[0]} autoComplete="email" />
              </div>
              <TextField label={t('cs.subject')} required value={form.subject} onChange={(e) => setForm({ ...form, subject: e.target.value })} error={errors.subject?.[0]} />
              <TextArea label={t('cs.message')} required rows={6} value={form.message} onChange={(e) => setForm({ ...form, message: e.target.value })} error={errors.message?.[0]} />
              <div>
                <Button type="submit" loading={busy} icon={<Mail className="size-4" aria-hidden />}>
                  {t('cs.send_ticket')}
                </Button>
              </div>
            </form>
          )}
        </Card>
        <aside className="grid content-start gap-3">
          <div className="rounded-lg border border-line bg-bg-subtle p-4">
            <p className="t-label text-fg">{t('cs.hours')}</p>
            <p className="t-body text-muted">{settings?.cs.hours ?? '—'}</p>
            <p className="t-label mt-3 text-fg">{t('cs.reply_estimate')}</p>
            <p className="t-body text-muted">{settings?.cs.reply_estimate ?? '—'}</p>
          </div>
          <Link to="/faq" className="t-label text-primary hover:underline">
            {t('cs.open_help_center')}
          </Link>
        </aside>
      </div>
    </>
  )
}

export function AboutPage() {
  const { t } = useTranslation()
  useDocTitle(t('footer.about'))
  return (
    <>
      <PageIntro title={t('about.title')} body={t('about.body')} />
      <div className={cn(wrap, 'grid gap-4 md:grid-cols-3')}>
        {['p1', 'p2', 'p3'].map((k) => (
          <div key={k} className="rounded-lg border border-line bg-surface p-5">
            <ShieldCheck className="mb-2 size-6 text-primary" aria-hidden />
            <h2 className="t-h3 text-fg">{t(`about.${k}_title`)}</h2>
            <p className="t-small mt-1 text-muted">{t(`about.${k}_body`)}</p>
          </div>
        ))}
      </div>
    </>
  )
}

interface StatusResp {
  overall: string
  components: { key: string; status: string }[]
  checked_at: string
  maintenance: { active: boolean; message: string | null }
}
export function StatusPage() {
  const { t } = useTranslation()
  useDocTitle(t('footer.status'))
  const [s, setS] = useState<StatusResp | null>(null)
  const [err, setErr] = useState(false)
  useEffect(() => {
    http.get<{ data: StatusResp }>('/public/status').then((r) => setS(r.data)).catch(() => setErr(true))
  }, [])
  return (
    <>
      <PageIntro title={t('status_page.title')} body={t('status_page.body')} />
      <div className={wrap}>
        {err ? (
          <EmptyState kind="error" title={t('status_page.unreachable')} />
        ) : !s ? (
          <Skeleton className="h-60" />
        ) : (
          <div className="grid max-w-3xl gap-4">
            <div className="flex flex-wrap items-center gap-3 rounded-lg border border-line bg-surface p-4">
              <StatusChip status={s.overall} label={t(`status_page.overall_${s.overall}`)} />
              <span className="t-small text-muted">{t('status_page.checked', { at: dateTime(s.checked_at) })}</span>
            </div>
            {s.maintenance.active && <p className="rounded-md bg-warning/14 p-3 text-sm text-fg">{s.maintenance.message}</p>}
            <ul className="divide-y divide-line rounded-lg border border-line bg-surface">
              {s.components.map((c) => (
                <li key={c.key} className="flex items-center justify-between gap-3 px-4 py-3">
                  <span className="font-medium text-fg">{t(`status_page.c_${c.key}`)}</span>
                  <StatusChip status={c.status} label={t(`status_page.s_${c.status}`)} />
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>
    </>
  )
}

export function LegalPage({ type }: { type: LegalType }) {
  const { t } = useTranslation()
  const { doc, error } = useLegalDoc(type)
  useDocTitle(doc?.title ?? t(`legal.${type}`))
  return (
    <>
      <PageIntro title={doc?.title ?? t(`legal.${type}`)} body={doc ? `v${doc.version} — ${t('legal.effective', { date: dateTime(doc.effective_at, false) })}` : undefined}>
        <div className="pt-1">
          <Button variant="secondary" size="sm" onClick={() => window.print()}>
            {t('legal.print')}
          </Button>
        </div>
      </PageIntro>
      <div className={cn(wrap, 'flex h-[min(78dvh,900px)] flex-col')}>
        {error ? <EmptyState kind="error" title={t('errors.server_title')} /> : !doc ? <Skeleton className="h-80" /> : <LegalBody doc={doc} />}
      </div>
    </>
  )
}
