import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { CircleCheck, CircleAlert, CircleX, Info, Download, RotateCcw, SearchCheck } from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import { useLocked } from '@/shared/layout/Locked'
import { useApi } from '@/shared/hooks/useApi'
import { Card, CardTitle, Tabs } from '@/shared/ui/Misc'
import { Button } from '@/shared/ui/Button'
import { TextField, TextArea, SelectField } from '@/shared/ui/Field'
import { Badge } from '@/shared/ui/Status'
import { EmptyState, Skeleton } from '@/shared/ui/States'
import { relative } from '@/shared/lib/format'
import { cn } from '@/shared/lib/cn'

/* Analisis konten SEO bergaya Yoast (docs/seo.md): isi form → klik satu tombol → skor SEO & keterbacaan. */

export type Rating = 'good' | 'ok' | 'bad' | 'feedback'
export interface SeoFinding {
  id: number
  rule: string
  category: 'seo' | 'readability' | 'technical' | null
  title: string | null
  severity: 'critical' | 'warning' | 'info'
  rating: Rating | null
  score: number | null
  location: string
  message: string
  recommendation: string
  state: 'open' | 'resolved' | 'ignored'
}
export interface SeoInput {
  content_type: string
  keyphrase: string
  synonyms?: string | null
  seo_title?: string | null
  meta_description?: string | null
  slug?: string | null
  url?: string | null
  content?: string
}
export interface SeoAuditItem {
  id: number
  kind: 'url' | 'content'
  url: string | null
  title: string | null
  keyphrase: string | null
  content_type: string | null
  score: number
  readability_score: number | null
  score_rating: Rating | null
  readability_rating: Rating | null
  ruleset_version: string
  status: string
  created_at: string
  input: SeoInput | null
  stats: { words: number; sentences: number; paragraphs: number; subheadings: number; images: number; links: number; keyphrase_count: number; content_words: string[] } | null
  findings: SeoFinding[]
}
interface SeoConfig {
  enabled: boolean
  content_types: string[]
  default_type: string
  url_import: boolean
  max_chars: number
  entitlement: string
  cost: number
  advanced: boolean
  ruleset_version: string
  rules: { code: string; name: string; category: string; tier: string; available: boolean }[]
}
/** Data awal dari editor website (tombol "Analisis SEO lengkap"). */
export interface SeoPrefill extends Partial<SeoInput> {
  source?: string
}

const EMPTY: Required<Omit<SeoInput, 'content'>> & { content: string } = { content_type: '', keyphrase: '', synonyms: '', seo_title: '', meta_description: '', slug: '', url: '', content: '' }

const RATING_UI: Record<Rating, { icon: typeof CircleCheck; cls: string; dot: string }> = {
  good: { icon: CircleCheck, cls: 'text-success', dot: 'bg-success' },
  ok: { icon: CircleAlert, cls: 'text-warning', dot: 'bg-warning' },
  bad: { icon: CircleX, cls: 'text-danger', dot: 'bg-danger' },
  feedback: { icon: Info, cls: 'text-muted', dot: 'bg-muted' },
}

/** Lebar teks seperti judul Google (Arial 20px) memakai canvas peramban. */
let measureCtx: CanvasRenderingContext2D | null | undefined
function titlePx(text: string): number {
  if (measureCtx === undefined) measureCtx = typeof document === 'undefined' ? null : document.createElement('canvas').getContext('2d')
  if (!measureCtx) return text.length * 10
  measureCtx.font = '20px Arial'
  return Math.round(measureCtx.measureText(text).width)
}

/** URL tanpa skema ("contoh.com/halaman") dilengkapi https:// agar lolos validasi. */
function withScheme(url: string): string {
  const u = url.trim()
  return u && !/^https?:\/\//i.test(u) ? `https://${u}` : u
}

export function ContentAnalyzerPanel() {
  const { t } = useTranslation()
  const { openLocked } = useLocked()
  const location = useLocation()
  const nav = useNavigate()
  const cfg = useApi<SeoConfig>('/app/seo/config')
  const list = useApi<SeoAuditItem[]>('/app/seo/audits', { kind: 'content' })
  const audits = list.data
  const reload = list.reload
  // Isi otomatis dari editor website (state navigasi "seoPrefill")
  const prefill = (location.state as { seoPrefill?: SeoPrefill } | null)?.seoPrefill
  const [f, setF] = useState(() => {
    if (!prefill) return { ...EMPTY }
    const { source: _src, ...rest } = prefill
    return { ...EMPTY, ...Object.fromEntries(Object.entries(rest).map(([k, v]) => [k, v ?? ''])) } as typeof EMPTY
  })
  const [errs, setErrs] = useState<Record<string, string[]>>({})
  const [formErr, setFormErr] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [importing, setImporting] = useState(false)
  const [result, setResult] = useState<SeoAuditItem | null>(null)
  const [source] = useState<string | null>(() => prefill?.source ?? null)

  // Bersihkan state navigasi agar form tidak terisi ulang saat halaman dimuat ulang
  useEffect(() => {
    if (prefill) nav(location.pathname, { replace: true, state: null })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const latest = audits?.[0] ?? null
  const shown = result ?? latest
  const set = (k: keyof typeof EMPTY, v: string) => setF((s) => ({ ...s, [k]: v }))
  const fail = (e: unknown) => {
    if (!(e instanceof ApiError)) return setFormErr((e as Error).message)
    const ent = cfg.data?.entitlement ?? 'seo.basic'
    if (e.code === 'feature_locked') openLocked(ent)
    else if (e.code === 'quota_exceeded') openLocked(ent, 'quota')
    else {
      setErrs(e.fields ?? {})
      setFormErr(Object.keys(e.fields ?? {}).length ? null : e.message)
    }
  }

  const importUrl = async () => {
    setErrs({})
    setFormErr(null)
    const url = withScheme(f.url ?? '')
    if (!url) return setErrs({ url: [t('seo.ca.url_required')] })
    setImporting(true)
    try {
      const r = await http.post<{ data: { seo_title: string; meta_description: string; slug: string; content: string; url: string } }>('/app/seo/import', { url })
      // "Ambil dari URL" = ambil semuanya dari halaman tersebut (judul, meta, slug, konten)
      setF((s) => ({ ...s, url, seo_title: r.data.seo_title, meta_description: r.data.meta_description, slug: r.data.slug, content: r.data.content }))
      setResult(null)
    } catch (e) {
      fail(e)
    } finally {
      setImporting(false)
    }
  }

  const analyze = async (e: FormEvent) => {
    e.preventDefault()
    setErrs({})
    setFormErr(null)
    setBusy(true)
    try {
      const body = Object.fromEntries(Object.entries({ ...f, content_type: ctype, url: withScheme(f.url ?? '') }).map(([k, v]) => [k, typeof v === 'string' && v.trim() === '' && k !== 'content' ? null : v]))
      const r = await http.post<{ data: SeoAuditItem }>('/app/seo/analyze', body)
      setResult(r.data)
      reload()
      window.setTimeout(() => document.getElementById('seo-result')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50)
    } catch (er) {
      fail(er)
    } finally {
      setBusy(false)
    }
  }

  const reedit = async (a: SeoAuditItem) => {
    const r = await http.get<{ data: SeoAuditItem }>(`/app/seo/audits/${a.id}`)
    const i = r.data.input
    if (i) setF({ ...EMPTY, ...Object.fromEntries(Object.entries(i).map(([k, v]) => [k, v ?? ''])) } as typeof EMPTY)
    setResult(r.data)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  if (!cfg.data) return cfg.error ? <EmptyState title={cfg.error.message} /> : <Skeleton className="h-96" />
  if (!cfg.data.enabled) return <EmptyState title={t('seo.ca.disabled')} body={t('seo.ca.disabled_body')} />
  const c = cfg.data
  // Jenis konten mengikuti setup Admin: bila belum dipilih / tidak diizinkan → default dari Admin
  const ctype = c.content_types.includes(f.content_type) ? f.content_type : c.default_type
  const px = titlePx(f.seo_title || '')
  const metaLen = (f.meta_description || '').length

  return (
    <div className="grid gap-6">
      <Card>
        <CardTitle>{t('seo.ca.form_title')}</CardTitle>
        {source && <p className="t-small mb-3 rounded-md bg-primary-soft px-3 py-2 text-fg">{t('seo.ca.prefilled', { name: source })}</p>}
        <form onSubmit={analyze} className="grid gap-4" noValidate>
          <div className="grid gap-4 md:grid-cols-[200px_minmax(0,1fr)] md:items-start">
            <SelectField label={t('seo.ca.content_type')} value={ctype} onChange={(e) => set('content_type', e.target.value)} hint={t(`seo.ca.type_hint.${ctype}`, { defaultValue: '' }) || undefined}>
              {c.content_types.map((x) => <option key={x} value={x}>{t(`seo.ca.types.${x}`, { defaultValue: x })}</option>)}
            </SelectField>
            <TextField label={t('seo.ca.keyphrase')} required value={f.keyphrase} onChange={(e) => set('keyphrase', e.target.value)} hint={t('seo.ca.keyphrase_hint')} error={errs.keyphrase?.[0]} placeholder={t('seo.ca.keyphrase_ph')} />
          </div>
          <TextField label={t('seo.ca.synonyms')} optional value={f.synonyms ?? ''} onChange={(e) => set('synonyms', e.target.value)} hint={t('seo.ca.synonyms_hint')} error={errs.synonyms?.[0]} />
          <div className={cn('grid gap-3', c.url_import && 'md:grid-cols-[minmax(0,1fr)_auto] md:items-end')}>
            <TextField label={t('seo.ca.url')} optional type="url" inputMode="url" value={f.url ?? ''} onChange={(e) => set('url', e.target.value)} placeholder={t('seo.ca.url_ph')} hint={t('seo.ca.url_hint')} error={errs.url?.[0]} />
            {c.url_import && (
              <Button variant="secondary" icon={<Download className="size-4" aria-hidden />} loading={importing} onClick={importUrl}>{t('seo.ca.import')}</Button>
            )}
          </div>
          <div className="grid gap-4 md:grid-cols-2 md:items-start">
            <TextField label={t('seo.ca.seo_title')} value={f.seo_title ?? ''} onChange={(e) => set('seo_title', e.target.value)} error={errs.seo_title?.[0]}
              hint={<span className={cn(px > 600 && 'text-danger')}>{t('seo.ca.title_px', { px, n: (f.seo_title || '').length })}</span>} />
            <TextField label={t('seo.ca.slug')} value={f.slug ?? ''} onChange={(e) => set('slug', e.target.value.toLowerCase().replace(/\s+/g, '-'))} placeholder={t('seo.ca.slug_ph')} error={errs.slug?.[0]} />
          </div>
          <TextArea label={t('seo.ca.meta')} rows={3} value={f.meta_description ?? ''} onChange={(e) => set('meta_description', e.target.value)} error={errs.meta_description?.[0]}
            hint={<span className={cn((metaLen > 156 || (metaLen > 0 && metaLen <= 120)) && 'text-warning')}>{t('seo.ca.meta_chars', { n: metaLen })}</span>} />
          <SnippetPreview title={f.seo_title || ''} url={f.url || ''} slug={f.slug || ''} meta={f.meta_description || ''} px={px} />
          <TextArea label={t('seo.ca.content')} required rows={14} className="font-mono text-[13px]" value={f.content} maxLength={c.max_chars} onChange={(e) => set('content', e.target.value)} hint={t('seo.ca.content_hint')} error={errs.content?.[0]} />
          {formErr && <p role="alert" className="t-small text-danger">{formErr}</p>}
          <div className="flex flex-wrap items-center gap-3">
            <Button type="submit" loading={busy} icon={<SearchCheck className="size-4" aria-hidden />}>{t('seo.ca.analyze')}</Button>
            <span className="t-small text-muted">{c.cost > 0 ? t('seo.ca.cost', { n: c.cost }) : t('seo.ca.free')}</span>
          </div>
        </form>
      </Card>

      <div id="seo-result" className="scroll-mt-20">
        {!audits ? (
          <Skeleton className="h-60" />
        ) : !shown ? (
          <EmptyState title={t('seo.ca.empty')} body={t('seo.ca.empty_body')} />
        ) : (
          <div className="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
            <div className="grid content-start gap-3">
              <ScoreCard label={t('seo.ca.seo_score')} score={shown.score} rating={shown.score_rating} note={t('seo.ca.seo_score_note')} />
              <ScoreCard label={t('seo.ca.read_score')} score={shown.readability_score ?? 0} rating={shown.readability_rating} note={t('seo.ca.read_score_note')} />
              <p className="t-small text-muted">{t('seo.score_note')} {t('seo.ruleset', { v: shown.ruleset_version })}</p>
              {audits.length > 0 && (
                <div>
                  <p className="t-label mb-2">{t('seo.ca.history')}</p>
                  <ul className="grid gap-1">
                    {audits.map((a) => (
                      <li key={a.id}>
                        <button type="button" onClick={() => setResult(a)} className={cn('w-full rounded-md px-3 py-2 text-left', shown.id === a.id ? 'bg-primary-soft' : 'hover:bg-surface-2')}>
                          <span className="block truncate text-sm font-medium">{a.title || a.keyphrase || a.url}</span>
                          <span className="t-small text-muted">{relative(a.created_at)} — SEO {a.score} · {t('seo.ca.read_short')} {a.readability_score ?? 0}</span>
                        </button>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
            <ResultList audit={shown} onReedit={() => reedit(shown)} />
          </div>
        )}
      </div>
    </div>
  )
}

function SnippetPreview({ title, url, slug, meta, px }: { title: string; url: string; slug: string; meta: string; px: number }) {
  const { t } = useTranslation()
  let host = t('seo.ca.preview_host')
  try {
    if (url) host = new URL(withScheme(url)).host
  } catch {
    /* URL belum valid */
  }
  return (
    <div className="rounded-lg border border-line bg-surface-2 p-4" aria-label={t('seo.ca.preview')}>
      <p className="t-label mb-2 text-muted">{t('seo.ca.preview')}</p>
      <p className="truncate text-[13px] text-muted">{host}{slug ? ` › ${slug}` : ''}</p>
      <p className={cn('truncate text-[18px] leading-snug text-info', px > 600 && 'text-warning')} style={{ maxWidth: 600, fontFamily: 'Arial, sans-serif' }}>{title || t('seo.ca.preview_title')}</p>
      <p className="line-clamp-2 text-[13px] text-muted">{meta || t('seo.ca.preview_meta')}</p>
    </div>
  )
}

function ScoreCard({ label, score, rating, note }: { label: string; score: number; rating: Rating | null; note: string }) {
  const { t } = useTranslation()
  const r = rating ?? 'feedback'
  const ui = RATING_UI[r]
  const Icon = ui.icon
  return (
    <Card>
      <p className="t-small text-muted">{label}</p>
      <p className={cn('flex items-center gap-2 text-5xl font-bold tabular-nums', ui.cls)}>
        <Icon className="size-8" aria-hidden />
        {score}
      </p>
      <p className={cn('t-label mt-1', ui.cls)}>{t(`seo.ca.rating.${r}`)}</p>
      <p className="t-small mt-2 text-muted">{note}</p>
    </Card>
  )
}

function ResultList({ audit, onReedit }: { audit: SeoAuditItem; onReedit: () => void }) {
  const { t } = useTranslation()
  const [cat, setCat] = useState<'seo' | 'readability'>('seo')
  const items = audit.findings.filter((x) => (x.category ?? 'seo') === cat)
  const groups = useMemo(
    () => (['bad', 'ok', 'good', 'feedback'] as Rating[]).map((r) => ({ r, list: items.filter((x) => (x.rating ?? 'feedback') === r) })).filter((g) => g.list.length > 0),
    [items],
  )
  const s = audit.stats
  return (
    <div className="grid content-start gap-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="truncate font-semibold text-fg">{audit.title || audit.url || audit.keyphrase}</p>
          <p className="t-small text-muted">
            {t('seo.ca.keyphrase')}: <span className="font-medium text-fg">{audit.input?.keyphrase ?? audit.keyphrase}</span>
            {audit.content_type && <> · {t(`seo.ca.types.${audit.content_type}`, { defaultValue: audit.content_type })}</>}
          </p>
        </div>
        <Button size="sm" variant="secondary" icon={<RotateCcw className="size-4" aria-hidden />} onClick={onReedit}>{t('seo.ca.reedit')}</Button>
      </div>
      {s && (
        <div className="flex flex-wrap gap-2">
          <Badge>{t('seo.ca.stat_words', { n: s.words })}</Badge>
          <Badge>{t('seo.ca.stat_keyphrase', { n: s.keyphrase_count })}</Badge>
          <Badge>{t('seo.ca.stat_subheadings', { n: s.subheadings })}</Badge>
          <Badge>{t('seo.ca.stat_images', { n: s.images })}</Badge>
          <Badge>{t('seo.ca.stat_links', { n: s.links })}</Badge>
          {s.content_words?.length > 0 && <Badge tone="info">{t('seo.ca.stat_content_words', { words: s.content_words.join(', ') })}</Badge>}
        </div>
      )}
      <Tabs
        label={t('seo.findings')}
        value={cat}
        onChange={setCat}
        items={(['seo', 'readability'] as const).map((k) => ({ value: k, label: t(`seo.ca.cat_${k}`), count: audit.findings.filter((x) => (x.category ?? 'seo') === k && (x.rating === 'bad' || x.rating === 'ok')).length }))}
      />
      {groups.length === 0 ? (
        <EmptyState title={t('seo.no_findings')} />
      ) : (
        groups.map((g) => (
          <section key={g.r} aria-labelledby={`seo-g-${g.r}`}>
            <h3 id={`seo-g-${g.r}`} className="t-label mb-2 text-fg">{t(`seo.ca.group.${g.r}`)} ({g.list.length})</h3>
            <ul className="divide-y divide-line rounded-lg border border-line bg-surface">
              {g.list.map((x) => (
                <FindingRow key={x.id} f={x} />
              ))}
            </ul>
          </section>
        ))
      )}
    </div>
  )
}

function FindingRow({ f }: { f: SeoFinding }) {
  const { t } = useTranslation()
  const ui = RATING_UI[f.rating ?? 'feedback']
  return (
    <li className="flex gap-3 p-3">
      <span className={cn('mt-1.5 size-2.5 shrink-0 rounded-full', ui.dot)} aria-hidden />
      <div className="grid min-w-0 gap-1">
        <p className="text-sm">
          <span className="font-semibold text-fg">{f.title ?? f.rule}</span>
          <span className="sr-only"> ({t(`seo.ca.rating.${f.rating ?? 'feedback'}`)})</span>: <span className="text-fg">{f.message}</span>
        </p>
        {f.recommendation && f.rating !== 'good' && <p className="t-small text-muted">{t('seo.recommendation')}: {f.recommendation}</p>}
      </div>
    </li>
  )
}
