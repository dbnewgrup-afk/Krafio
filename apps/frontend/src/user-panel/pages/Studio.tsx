import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useParams, useSearchParams, Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Download, RotateCcw, Sparkles, Upload, X, Ban, ShieldCheck } from 'lucide-react'
import { http, ApiError, newIdempotencyKey } from '@/api-client/http'
import type { AiJob } from '@/api-client/types'
import { useAuth } from '@/shared/context/AuthContext'
import { useToast } from '@/shared/context/ToastContext'
import { useLocked } from '@/shared/layout/Locked'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { useIsDesktop } from '@/shared/hooks/useMediaQuery'
import { useFabLift } from '@/shared/hooks/useFabLift'
import { PageHeader, Segmented, Tabs, Card } from '@/shared/ui/Misc'
import { Button } from '@/shared/ui/Button'
import { SelectField, TextArea, TextField } from '@/shared/ui/Field'
import { StatusChip } from '@/shared/ui/Status'
import { EmptyState, Skeleton } from '@/shared/ui/States'
import { Modal } from '@/shared/ui/Modal'
import { FEATURES } from '@/shared/features'
import { number, relative } from '@/shared/lib/format'
import { cn } from '@/shared/lib/cn'
import { NotFoundPage } from '@/public-site/pages/Catalog'

type Kind = 'photo' | 'video' | 'edit-photo' | 'edit-video' | 'brainstorm' | 'clipper'
const MAP: Record<Kind, { type: string; feature: string; upload?: 'image' | 'video'; text?: boolean }> = {
  photo: { type: 'photo_generate', feature: 'photo.generate' },
  video: { type: 'video_generate', feature: 'video.generate' },
  'edit-photo': { type: 'photo_edit', feature: 'photo.edit', upload: 'image' },
  'edit-video': { type: 'video_edit', feature: 'video.edit', upload: 'video' },
  brainstorm: { type: 'content_brainstorm', feature: 'content.brainstorm', text: true },
  clipper: { type: 'video_clipper', feature: 'video.clipper', upload: 'video' },
}
const LIMITS = { image: { mime: ['image/jpeg', 'image/png', 'image/webp'], max: 10 * 1024 * 1024 }, video: { mime: ['video/mp4', 'video/quicktime', 'video/webm'], max: 200 * 1024 * 1024 } }

interface Estimate {
  cost: number
  remaining: number | null
  quota: number | null
  enabled: boolean
  unit?: string
}

export default function Studio() {
  const { kind = 'photo' } = useParams()
  const cfg = MAP[kind as Kind]
  if (!cfg) return <NotFoundPage />
  return <StudioInner key={kind} kind={kind as Kind} />
}

function StudioInner({ kind }: { kind: Kind }) {
  const { t } = useTranslation()
  const cfg = MAP[kind]
  const feat = FEATURES.find((f) => f.entitlement === cfg.feature)!
  useDocTitle(t(`features.${feat.key}`))
  const { me, refresh } = useAuth()
  const toast = useToast()
  const { openLocked } = useLocked()
  const isDesktop = useIsDesktop()
  const [params] = useSearchParams()
  const [tab, setTab] = useState<'create' | 'results'>('create')
  const [prompt, setPrompt] = useState(params.get('prompt') ?? '')
  const [opts, setOpts] = useState<Record<string, string>>({ ratio: '1:1', style: 'product', count: '1', quality: 'standard', operation: 'inpaint', minutes: '1', source_minutes: '5', duration: '5', tone: 'friendly', platform: 'instagram', ideas: '5', clip_len: '30', clips: '3' })
  const [file, setFile] = useState<File | null>(null)
  const [fileErr, setFileErr] = useState<string | null>(null)
  const [upload, setUpload] = useState<{ pct: number; id?: number } | null>(null)
  const [est, setEst] = useState<Estimate | null>(null)
  const [jobs, setJobs] = useState<AiJob[] | null>(null)
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState<string | null>(null)
  const [consent, setConsent] = useState(false)
  const [preview, setPreview] = useState<AiJob | null>(null)
  const xhr = useRef<XMLHttpRequest | null>(null)
  const stickyRef = useRef<HTMLDivElement>(null)

  const params2 = useMemo(() => {
    const base: Record<string, string> = {}
    if (kind === 'photo') Object.assign(base, { ratio: opts.ratio, style: opts.style, count: opts.count, quality: opts.quality })
    if (kind === 'edit-photo') Object.assign(base, { operation: opts.operation })
    if (kind === 'edit-video') Object.assign(base, { minutes: opts.minutes })
    if (kind === 'video') Object.assign(base, { ratio: opts.ratio, duration: opts.duration })
    if (kind === 'brainstorm') Object.assign(base, { tone: opts.tone, platform: opts.platform, ideas: opts.ideas })
    if (kind === 'clipper') Object.assign(base, { clip_len: opts.clip_len, clips: opts.clips, source_minutes: opts.source_minutes })
    if (params.get('template')) base.template_id = params.get('template')!
    return base
  }, [kind, opts, params])

  // Estimasi biaya/kuota ditampilkan SEBELUM job dijalankan (§1.1 butir 4, §8 butir 5)
  useEffect(() => {
    const id = window.setTimeout(() => {
      http
        .get<{ data: Estimate }>('/app/jobs/estimate', { type: cfg.type, ...params2 })
        .then((r) => setEst(r.data))
        .catch(() => setEst(null))
    }, 200)
    return () => window.clearTimeout(id)
  }, [cfg.type, params2])

  const loadJobs = useCallback(() => {
    http
      .get<{ data: AiJob[] }>('/app/jobs', { type: cfg.type, per_page: 20 })
      .then((r) => setJobs(r.data))
      .catch(() => setJobs([]))
  }, [cfg.type])
  useEffect(loadJobs, [loadJobs])

  // Polling job aktif; pengguna boleh meninggalkan halaman, job tetap berjalan (§8 butir 6)
  const active = jobs?.some((j) => j.status === 'queued' || j.status === 'processing')
  const prevActive = useRef<Set<number>>(new Set())
  useEffect(() => {
    if (!active) return
    const id = window.setInterval(loadJobs, 2000)
    return () => window.clearInterval(id)
  }, [active, loadJobs])
  useEffect(() => {
    if (!jobs) return
    const nowActive = new Set(jobs.filter((j) => j.status === 'queued' || j.status === 'processing').map((j) => j.id))
    jobs.forEach((j) => {
      if (prevActive.current.has(j.id) && !nowActive.has(j.id)) {
        if (j.status === 'completed') toast.push('success', t('studio.done_toast'), { label: t('studio.view'), onClick: () => { setTab('results'); setPreview(j) } })
        else if (j.status === 'failed') toast.push('error', t('studio.failed_toast'))
        void refresh()
      }
    })
    prevActive.current = nowActive
  }, [jobs, toast, t, refresh])

  const pickFile = (f: File | null) => {
    setFileErr(null)
    setUpload(null)
    if (!f || !cfg.upload) return setFile(null)
    const L = LIMITS[cfg.upload]
    // Validasi tipe/ukuran sebelum unggah (§18.1)
    if (!L.mime.includes(f.type)) return setFileErr(t('studio.bad_type'))
    if (f.size > L.max) return setFileErr(t('studio.too_big', { mb: Math.round(L.max / 1024 / 1024) }))
    setFile(f)
  }

  const uploadFile = (f: File): Promise<number> =>
    new Promise((resolve, reject) => {
      const fd = new FormData()
      fd.append('file', f)
      const x = new XMLHttpRequest()
      xhr.current = x
      x.open('POST', '/api/app/uploads')
      x.withCredentials = true
      const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
      if (m) x.setRequestHeader('X-XSRF-TOKEN', decodeURIComponent(m[1]))
      x.setRequestHeader('Accept', 'application/json')
      x.upload.onprogress = (e) => e.lengthComputable && setUpload({ pct: Math.round((e.loaded / e.total) * 100) })
      x.onload = () => {
        if (x.status >= 200 && x.status < 300) {
          const id = JSON.parse(x.responseText).data.id as number
          setUpload({ pct: 100, id })
          resolve(id)
        } else reject(new Error(t('studio.upload_failed')))
      }
      x.onerror = () => reject(new Error(t('studio.upload_failed')))
      x.onabort = () => reject(new Error(t('studio.upload_cancelled')))
      x.send(fd)
    })

  const submit = async () => {
    setErr(null)
    if (!me?.ai_consent) return setConsent(true)
    if (est && !est.enabled) return openLocked(cfg.feature)
    if (est && est.remaining !== null && est.remaining < est.cost) return openLocked(est.unit === 'credit' ? 'credits' : cfg.feature, 'quota')
    if (!prompt.trim() && kind !== 'clipper') return setErr(t('studio.prompt_required'))
    if (cfg.upload && !file) return setErr(t('studio.file_required'))
    setBusy(true)
    try {
      const uploadId = cfg.upload && file ? (upload?.id ?? (await uploadFile(file))) : undefined
      await http.post('/app/jobs', { type: cfg.type, prompt: prompt.trim(), params: params2, upload_id: uploadId }, { idempotencyKey: newIdempotencyKey() })
      toast.push('info', t('studio.queued_toast'))
      if (!isDesktop) setTab('results')
      loadJobs()
      void refresh()
    } catch (e) {
      if (e instanceof ApiError) {
        if (e.code === 'feature_locked') openLocked(cfg.feature)
        else if (e.code === 'quota_exceeded') openLocked(cfg.feature, 'quota')
        else if (e.code === 'subscription_expired') openLocked(cfg.feature, 'expired')
        else setErr(e.fields.prompt?.[0] ?? e.message)
      } else setErr((e as Error).message)
    } finally {
      setBusy(false)
    }
  }

  const cancel = async (j: AiJob) => {
    await http.post(`/app/jobs/${j.id}/cancel`).catch(() => undefined)
    loadJobs()
    void refresh()
  }

  const costLabel = est && est.unit === 'credit' && est.cost > 0 ? t('studio.generate_cost', { count: est.cost }) : t('studio.generate')

  const form = (
    <div className="grid gap-4">
      {cfg.upload && (
        <div className="grid gap-1.5">
          <span className="t-label text-fg">
            {t(cfg.upload === 'image' ? 'studio.source_image' : 'studio.source_video')} <span className="text-muted">({t('common.required')})</span>
          </span>
          <label
            className={cn('grid cursor-pointer justify-items-center gap-2 rounded-lg border-2 border-dashed px-4 py-6 text-center', fileErr ? 'border-danger' : 'border-line hover:border-primary/50')}
            onDragOver={(e) => e.preventDefault()}
            onDrop={(e) => {
              e.preventDefault()
              pickFile(e.dataTransfer.files[0] ?? null)
            }}
          >
            <Upload className="size-6 text-primary" aria-hidden />
            <span className="text-sm font-semibold text-fg">{file ? file.name : t('studio.drop')}</span>
            <span className="t-small text-muted">{t(cfg.upload === 'image' ? 'studio.image_rules' : 'studio.video_rules')}</span>
            <input type="file" className="sr-only" accept={LIMITS[cfg.upload].mime.join(',')} onChange={(e) => pickFile(e.target.files?.[0] ?? null)} />
          </label>
          {fileErr && <p className="t-small text-danger">{fileErr}</p>}
          {upload && upload.pct < 100 && (
            <div className="flex items-center gap-3">
              <div className="h-2 flex-1 overflow-hidden rounded-full bg-surface-2" role="progressbar" aria-valuenow={upload.pct} aria-valuemin={0} aria-valuemax={100}>
                <div className="h-full bg-primary" style={{ width: `${upload.pct}%` }} />
              </div>
              <span className="t-small tabular-nums text-muted">{upload.pct}%</span>
              <button type="button" onClick={() => xhr.current?.abort()} aria-label={t('common.cancel')} className="grid size-8 place-items-center rounded-md hover:bg-surface-2">
                <X className="size-4" aria-hidden />
              </button>
            </div>
          )}
          {file && (
            <button type="button" onClick={() => pickFile(null)} className="t-small justify-self-start text-primary hover:underline">
              {t('studio.remove_file')}
            </button>
          )}
        </div>
      )}

      {kind === 'brainstorm' ? (
        <TextField label={t('studio.topic')} required value={prompt} onChange={(e) => setPrompt(e.target.value)} placeholder={t('studio.topic_ph')} />
      ) : (
        <TextArea label={kind === 'clipper' ? t('studio.clip_instruction') : cfg.upload ? t('studio.instruction') : t('studio.prompt')} required={kind !== 'clipper'} optional={kind === 'clipper'} rows={4} value={prompt} onChange={(e) => setPrompt(e.target.value)} placeholder={t(`studio.ph.${kind}`)} hint={t('studio.prompt_hint')} maxLength={1000} />
      )}

      {(kind === 'photo' || kind === 'video') && (
        <div className="grid gap-1.5">
          <span className="t-label text-fg">{t('studio.ratio')}</span>
          <Segmented label={t('studio.ratio')} value={opts.ratio} onChange={(v) => setOpts({ ...opts, ratio: v })} items={['1:1', '4:5', '9:16', '16:9'].map((r) => ({ value: r, label: r }))} />
        </div>
      )}
      {kind === 'photo' && (
        <div className="grid grid-cols-2 gap-3">
          <SelectField label={t('studio.style')} value={opts.style} onChange={(e) => setOpts({ ...opts, style: e.target.value })}>
            {['product', 'lifestyle', 'flatlay', 'studio', 'illustration'].map((s) => (
              <option key={s} value={s}>
                {t(`studio.styles.${s}`)}
              </option>
            ))}
          </SelectField>
          <SelectField label={t('studio.count')} value={opts.count} onChange={(e) => setOpts({ ...opts, count: e.target.value })}>
            {['1', '2', '3', '4'].map((s) => (
              <option key={s} value={s}>
                {s}
              </option>
            ))}
          </SelectField>
          <SelectField label={t('studio.quality')} value={opts.quality} onChange={(e) => setOpts({ ...opts, quality: e.target.value })} hint={Number(me?.limits?.photo_max_px ?? 1024) < 2048 ? t('studio.hd_locked') : undefined} wrapClassName="col-span-2">
            <option value="standard">{t('studio.q_standard')}</option>
            <option value="hd" disabled={Number(me?.limits?.photo_max_px ?? 1024) < 2048}>{t('studio.q_hd')}</option>
          </SelectField>
        </div>
      )}
      {kind === 'edit-photo' && (
        <SelectField label={t('studio.operation')} value={opts.operation} onChange={(e) => setOpts({ ...opts, operation: e.target.value })}>
          {['inpaint', 'remove_bg', 'upscale'].map((s) => (
            <option key={s} value={s}>{t(`studio.ops.${s}`)}</option>
          ))}
        </SelectField>
      )}
      {kind === 'edit-video' && (
        <SelectField label={t('studio.video_minutes')} value={opts.minutes} onChange={(e) => setOpts({ ...opts, minutes: e.target.value })}>
          {['1', '2', '3', '5', '10'].map((s) => (
            <option key={s} value={s}>{t('studio.minutes', { n: s })}</option>
          ))}
        </SelectField>
      )}
      {kind === 'video' && (
        <SelectField label={t('studio.duration')} value={opts.duration} onChange={(e) => setOpts({ ...opts, duration: e.target.value })} hint={t('studio.duration_hint', { s: Number(me?.limits?.video_max_seconds ?? 0), res: String(me?.limits?.video_resolution ?? '720p') })}>
          {['5', '10', '15'].map((s) => (
            <option key={s} value={s} disabled={Number(s) > Number(me?.limits?.video_max_seconds ?? 0)}>
              {t('studio.seconds', { n: s })}
            </option>
          ))}
        </SelectField>
      )}
      {kind === 'brainstorm' && (
        <div className="grid grid-cols-2 gap-3">
          <SelectField label={t('studio.platform')} value={opts.platform} onChange={(e) => setOpts({ ...opts, platform: e.target.value })}>
            {['instagram', 'tiktok', 'facebook', 'threads', 'whatsapp', 'website'].map((s) => (
              <option key={s} value={s}>
                {t(`platforms.${s}`)}
              </option>
            ))}
          </SelectField>
          <SelectField label={t('studio.tone')} value={opts.tone} onChange={(e) => setOpts({ ...opts, tone: e.target.value })}>
            {['friendly', 'professional', 'playful', 'persuasive'].map((s) => (
              <option key={s} value={s}>
                {t(`studio.tones.${s}`)}
              </option>
            ))}
          </SelectField>
        </div>
      )}
      {kind === 'clipper' && (
        <div className="grid grid-cols-2 gap-3">
          <SelectField label={t('studio.clip_len')} value={opts.clip_len} onChange={(e) => setOpts({ ...opts, clip_len: e.target.value })}>
            {['15', '30', '60'].map((s) => (
              <option key={s} value={s}>
                {t('studio.seconds', { n: s })}
              </option>
            ))}
          </SelectField>
          <SelectField label={t('studio.source_minutes')} value={opts.source_minutes} onChange={(e) => setOpts({ ...opts, source_minutes: e.target.value })} wrapClassName="col-span-2" hint={t('studio.source_hint', { n: Number(me?.limits?.clipper_max_minutes ?? 0) })}>
            {['5', '10', '15', '30', '60', '120'].map((s) => (
              <option key={s} value={s} disabled={Number(s) > Number(me?.limits?.clipper_max_minutes ?? 0)}>{t('studio.minutes', { n: s })}</option>
            ))}
          </SelectField>
          <SelectField label={t('studio.clip_count')} value={opts.clips} onChange={(e) => setOpts({ ...opts, clips: e.target.value })}>
            {['1', '3', '5'].map((s) => (
              <option key={s} value={s}>
                {s}
              </option>
            ))}
          </SelectField>
        </div>
      )}
      {err && (
        <p role="alert" className="t-small rounded-md bg-danger/10 p-3 text-danger">
          {err}
        </p>
      )}
    </div>
  )

  const estimatePanel = (
    <div className="grid gap-3">
      <div className="flex items-center justify-between gap-3 rounded-md bg-surface-2 p-3">
        <div className="text-sm">
          <p className="text-muted">{t('generate.estimate')}</p>
          <p className="font-semibold tabular-nums text-fg">{est ? (est.unit === 'credit' ? t('studio.credits', { count: est.cost }) : t('studio.requests', { count: est.cost })) : '—'}</p>
        </div>
        <div className="text-right text-sm">
          <p className="text-muted">{t('studio.remaining')}</p>
          <p className="font-semibold tabular-nums text-fg">{est ? (est.remaining === null ? t('user.dash.unlimited') : number(est.remaining)) : '—'}</p>
        </div>
      </div>
      <Button size="lg" block loading={busy} onClick={submit} icon={<Sparkles className="size-4" aria-hidden />}>
        {costLabel}
      </Button>
      <p className="t-small text-muted">{t('studio.refund_note')}</p>
    </div>
  )

  useFabLift(stickyRef, !isDesktop && tab === 'create')

  const compactBar = (
    <div className="flex items-center gap-3">
      <div className="min-w-0 flex-1 text-sm leading-5">
        <p className="text-muted">{t('generate.estimate')}</p>
        <p className="truncate font-semibold tabular-nums text-fg">
          {est ? (est.unit === 'credit' ? t('studio.credits', { count: est.cost }) : t('studio.requests', { count: est.cost })) : '—'}
          <span className="font-normal text-muted"> · {t('studio.remaining')} {est ? (est.remaining === null ? t('user.dash.unlimited') : number(est.remaining)) : '—'}</span>
        </p>
      </div>
      <Button size="lg" loading={busy} onClick={submit} icon={<Sparkles className="size-4" aria-hidden />}>
        {t('studio.generate')}
      </Button>
    </div>
  )

  const results = <JobResults jobs={jobs} onPreview={setPreview} onCancel={cancel} onRetry={(j) => { setPrompt(j.prompt); setTab('create') }} textMode={!!cfg.text} />

  return (
    <>
      <PageHeader title={t(`features.${feat.key}`)} subtitle={t(`features_desc.${feat.key}`)} crumbs={[{ label: t('user.nav.studio'), to: '/app/create/photo' }, { label: t(`features.${feat.key}`) }]} />
      {isDesktop ? (
        <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
          <Card className="h-fit">
            {form}
            <div className="mt-5 border-t border-line pt-4">{estimatePanel}</div>
          </Card>
          <section aria-label={t('studio.results')}>
            <h2 className="t-h2 mb-3 text-fg">{t('studio.results')}</h2>
            {results}
          </section>
        </div>
      ) : (
        <>
          <Tabs label={t('studio.results')} value={tab} onChange={setTab} items={[{ value: 'create', label: t('studio.tab_create') }, { value: 'results', label: t('studio.results'), count: jobs?.filter((j) => j.status === 'queued' || j.status === 'processing').length || undefined }]} />
          <div className="mt-4 pb-28">{tab === 'create' ? form : results}</div>
          {/* Sticky action bar (§1.3) */}
          {tab === 'create' && <div ref={stickyRef} className="fixed inset-x-0 bottom-[calc(64px+var(--safe-b))] z-sticky border-t border-line bg-surface px-4 py-2.5 shadow-k-md md:bottom-0">{compactBar}</div>}
        </>
      )}

      <Modal
        open={consent}
        onClose={() => setConsent(false)}
        size="info"
        icon={<ShieldCheck className="size-5 text-primary" aria-hidden />}
        title={t('studio.consent_title')}
        footer={
          <Button
            onClick={async () => {
              await http.post('/me/ai-consent')
              await refresh()
              setConsent(false)
            }}
          >
            {t('studio.consent_ok')}
          </Button>
        }
      >
        <p className="t-body text-muted">{t('studio.consent_body')}</p>
        <Link to="?modal=privacy" className="t-label mt-2 inline-block text-primary hover:underline">
          {t('legal.privacy')}
        </Link>
      </Modal>
      <Modal open={!!preview} onClose={() => setPreview(null)} size="media" title={preview?.prompt ?? ''}>
        {preview && <JobPreview job={preview} />}
      </Modal>
    </>
  )
}

export function JobPreview({ job }: { job: AiJob }) {
  const { t } = useTranslation()
  const pal = job.result?.preview_palette ?? []
  if (job.result?.items?.length)
    return (
      <ol className="grid gap-2">
        {job.result.items.map((x, i) => (
          <li key={i} className="rounded-md border border-line p-3 text-sm text-fg">
            <span className="mr-2 font-semibold text-primary">{i + 1}.</span>
            {x}
          </li>
        ))}
      </ol>
    )
  return (
    <div className="grid gap-3">
      <div className="mx-auto grid w-full max-w-3xl gap-3 sm:grid-cols-2">
        {(pal.length ? chunk(pal, 3) : [['#cbd5e1', '#64748b', '#0f172a']]).map((p, i) => (
          <div key={i} className="aspect-square rounded-lg border border-line" style={{ background: `linear-gradient(150deg, ${p[0]}, ${p[1]} 55%, ${p[2]})` }} />
        ))}
      </div>
      {job.result?.note && <p className="t-small rounded-md bg-info/10 p-3 text-info">{job.result.note}</p>}
      <p className="t-small text-muted">{t('studio.download_note')}</p>
    </div>
  )
}
function chunk<T>(a: T[], n: number): T[][] {
  const out: T[][] = []
  for (let i = 0; i < a.length; i += n) out.push(a.slice(i, i + n))
  return out
}

function JobResults({ jobs, onPreview, onCancel, onRetry, textMode }: { jobs: AiJob[] | null; onPreview: (j: AiJob) => void; onCancel: (j: AiJob) => void; onRetry: (j: AiJob) => void; textMode: boolean }) {
  const { t } = useTranslation()
  if (!jobs)
    return (
      <div className="grid grid-cols-2 gap-3">
        {Array.from({ length: 4 }).map((_, i) => (
          <Skeleton key={i} className="aspect-square" />
        ))}
      </div>
    )
  if (jobs.length === 0) return <EmptyState title={t('studio.no_results')} body={t('studio.no_results_body')} />
  return (
    <ul className={cn('grid gap-3', textMode ? 'grid-cols-1' : 'grid-cols-2')}>
      {jobs.map((j) => (
        <li key={j.id} className="grid content-start gap-2 rounded-lg border border-line bg-surface p-2.5">
          {textMode ? (
            <button type="button" disabled={j.status !== 'completed'} onClick={() => onPreview(j)} className="grid gap-1 rounded-md bg-surface-2 p-3 text-left disabled:cursor-default">
              <p className="line-clamp-2 text-sm font-semibold text-fg">{j.prompt}</p>
              {j.result?.items && <p className="t-small line-clamp-2 text-muted">{j.result.items[0]}</p>}
            </button>
          ) : (
            <button type="button" disabled={j.status !== 'completed'} onClick={() => onPreview(j)} aria-label={t('studio.view')} className="relative aspect-square overflow-hidden rounded-md bg-surface-2 disabled:cursor-default">
              {j.status === 'completed' && j.result?.preview_palette ? (
                <span className="absolute inset-0" style={{ background: `linear-gradient(150deg, ${j.result.preview_palette[0]}, ${j.result.preview_palette[1]} 55%, ${j.result.preview_palette[2]})` }} />
              ) : j.status === 'queued' || j.status === 'processing' ? (
                <span className="absolute inset-0 grid place-items-center">
                  <span className="grid w-3/4 gap-2">
                    <span className="h-1.5 overflow-hidden rounded-full bg-line">
                      <span className="block h-full rounded-full bg-primary" style={{ width: `${Math.max(6, j.progress)}%` }} />
                    </span>
                    <span className="t-small text-center tabular-nums text-muted">{j.progress}%</span>
                  </span>
                </span>
              ) : (
                <span className="absolute inset-0 grid place-items-center text-muted">
                  <Ban className="size-6" aria-hidden />
                </span>
              )}
            </button>
          )}
          <div className="flex items-center justify-between gap-2">
            <StatusChip status={j.status} />
            <span className="t-small text-muted">{relative(j.created_at)}</span>
          </div>
          {!textMode && <p className="t-small line-clamp-2 text-fg">{j.prompt}</p>}
          {j.status === 'failed' && <p className="t-small text-danger">{j.error ?? t('studio.failed_generic')} {t('studio.quota_returned')}</p>}
          <div className="flex flex-wrap gap-1">
            {j.status === 'queued' && (
              <Button size="sm" variant="ghost" onClick={() => onCancel(j)}>
                {t('common.cancel')}
              </Button>
            )}
            {(j.status === 'failed' || j.status === 'completed') && (
              <Button size="sm" variant="ghost" icon={<RotateCcw className="size-3.5" aria-hidden />} onClick={() => onRetry(j)}>
                {j.status === 'failed' ? t('common.retry') : t('studio.variation')}
              </Button>
            )}
            {j.status === 'completed' && !textMode && (
              <Button size="sm" variant="ghost" icon={<Download className="size-3.5" aria-hidden />} onClick={() => onPreview(j)}>
                {t('studio.view')}
              </Button>
            )}
          </div>
        </li>
      ))}
    </ul>
  )
}
