import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useParams, useSearchParams, Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Download, RotateCcw, Sparkles, Upload, X, Ban, ShieldCheck, Info, FileText } from 'lucide-react'
import { http, ApiError, newIdempotencyKey } from '@/api-client/http'
import type { AiJob, AiTool } from '@/api-client/types'
import { useAuth } from '@/shared/context/AuthContext'
import { useToast } from '@/shared/context/ToastContext'
import { useLocked } from '@/shared/layout/Locked'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { useIsDesktop } from '@/shared/hooks/useMediaQuery'
import { useFabLift } from '@/shared/hooks/useFabLift'
import { PageHeader, Tabs, Card } from '@/shared/ui/Misc'
import { Button } from '@/shared/ui/Button'
import { StatusChip } from '@/shared/ui/Status'
import { EmptyState, Skeleton } from '@/shared/ui/States'
import { Modal } from '@/shared/ui/Modal'
import { ToolForm } from '@/shared/ai/ToolForm'
import { toolDefaults, visibleValues, type ToolValues } from '@/shared/ai/toolValues'
import { DocResult } from '@/shared/ai/DocResult'
import { number, relative } from '@/shared/lib/format'
import { previewStyle } from '@/shared/lib/assets'
import { cn } from '@/shared/lib/cn'
import { NotFoundPage } from '@/public-site/pages/Catalog'

/** Slug URL /app/create/:kind → kunci tool di registry AI Tools (backend). */
const STUDIO_KINDS: Record<string, string> = {
  photo: 'photo_generate',
  video: 'video_generate',
  'edit-photo': 'photo_edit',
  'edit-video': 'video_edit',
  clipper: 'video_clipper',
  script: 'content_script',
  caption: 'content_caption',
  brainstorm: 'content_brainstorm',
  plan: 'content_plan',
  copy: 'copywriting',
  article: 'article_writer',
  story: 'story_series',
  prompt: 'prompt_optimizer',
  'script-video': 'script_video',
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
  const key = STUDIO_KINDS[kind]
  if (!key) return <NotFoundPage />
  return <StudioLoader key={kind} toolKey={key} />
}

function StudioLoader({ toolKey }: { toolKey: string }) {
  const { t } = useTranslation()
  const [tool, setTool] = useState<AiTool | null | false>(null)
  useEffect(() => {
    http
      .get<{ data: AiTool }>(`/app/ai-tools/${toolKey}`)
      .then((r) => setTool(r.data))
      .catch(() => setTool(false))
  }, [toolKey])
  if (tool === null)
    return (
      <div className="grid gap-4">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-96" />
      </div>
    )
  if (tool === false) return <EmptyState title={t('tools.disabled_title')} body={t('tools.disabled_body')} />
  return <StudioInner tool={tool} />
}

function StudioInner({ tool }: { tool: AiTool }) {
  const { t } = useTranslation()
  useDocTitle(tool.label)
  const { me, refresh } = useAuth()
  const toast = useToast()
  const { openLocked } = useLocked()
  const isDesktop = useIsDesktop()
  const [params] = useSearchParams()
  const textMode = tool.capability === 'text'
  const [tab, setTab] = useState<'create' | 'results'>('create')
  const [values, setValues] = useState<ToolValues>(() => toolDefaults(tool, params.get('prompt') ? { [tool.prompt_field]: params.get('prompt') } : undefined))
  const [fieldErrs, setFieldErrs] = useState<Record<string, string[]>>({})
  const [file, setFile] = useState<File | null>(null)
  const [fileErr, setFileErr] = useState<string | null>(null)
  const [upload, setUpload] = useState<{ pct: number; id?: number; duration?: number | null; width?: number | null; height?: number | null; error?: string } | null>(null)
  const [est, setEst] = useState<Estimate | null>(null)
  const [jobs, setJobs] = useState<AiJob[] | null>(null)
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState<string | null>(null)
  const [consent, setConsent] = useState(false)
  const [previewId, setPreviewId] = useState<number | null>(null)
  const xhr = useRef<XMLHttpRequest | null>(null)
  const stickyRef = useRef<HTMLDivElement>(null)

  const sendValues = useMemo(() => {
    const v = visibleValues(tool, values)
    if (params.get('template')) v.template_id = params.get('template')
    return v
  }, [tool, values, params])
  const sendKey = JSON.stringify(sendValues)

  // Estimasi biaya/kuota ditampilkan SEBELUM job dijalankan (§1.1 butir 4, §8 butir 5)
  useEffect(() => {
    const id = window.setTimeout(() => {
      http
        .get<{ data: Estimate }>('/app/jobs/estimate', { type: tool.type, params: sendKey, upload_id: upload?.id })
        .then((r) => setEst(r.data))
        .catch(() => setEst(null))
    }, 250)
    return () => window.clearTimeout(id)
  }, [tool.type, sendKey, upload?.id])

  const loadJobs = useCallback(() => {
    http
      .get<{ data: AiJob[] }>('/app/jobs', { type: tool.type, per_page: 20 })
      .then((r) => setJobs(r.data))
      .catch(() => setJobs([]))
  }, [tool.type])
  useEffect(loadJobs, [loadJobs])

  // Polling job aktif; pengguna boleh meninggalkan halaman, job tetap berjalan (§8 butir 6)
  const active = jobs?.some((j) => j.status === 'queued' || j.status === 'processing')
  const prevActive = useRef<Set<number>>(new Set())
  useEffect(() => {
    if (!active) return
    const id = window.setInterval(loadJobs, textMode ? 3000 : 2000)
    return () => window.clearInterval(id)
  }, [active, loadJobs, textMode])
  useEffect(() => {
    if (!jobs) return
    const nowActive = new Set(jobs.filter((j) => j.status === 'queued' || j.status === 'processing').map((j) => j.id))
    jobs.forEach((j) => {
      if (prevActive.current.has(j.id) && !nowActive.has(j.id)) {
        if (j.status === 'completed') toast.push('success', t('studio.done_toast'), { label: t('studio.view'), onClick: () => { setTab('results'); setPreviewId(j.id) } })
        else if (j.status === 'failed') toast.push('error', j.error ?? t('studio.failed_toast'))
        void refresh()
      }
    })
    prevActive.current = nowActive
  }, [jobs, toast, t, refresh])
  const preview = jobs?.find((j) => j.id === previewId) ?? null

  const pickFile = (f: File | null) => {
    setFileErr(null)
    setUpload(null)
    if (!f || !tool.upload) return setFile(null)
    const L = LIMITS[tool.upload]
    // Validasi tipe/ukuran sebelum unggah (§18.1)
    if (!L.mime.includes(f.type)) return setFileErr(t('studio.bad_type'))
    if (f.size > L.max) return setFileErr(t('studio.too_big', { mb: Math.round(L.max / 1024 / 1024) }))
    setFile(f)
    // Unggah langsung saat dipilih: durasi video diukur server → estimasi kredit akurat sebelum Generate
    uploadFile(f).catch((e: Error) => setUpload({ pct: 0, error: e.message }))
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
          const d = JSON.parse(x.responseText).data as { id: number; duration?: number | null; width?: number | null; height?: number | null }
          setUpload({ pct: 100, ...d })
          resolve(d.id)
        } else {
          let msg = t('studio.upload_failed')
          try {
            msg = JSON.parse(x.responseText).message ?? msg
          } catch {
            /* respons bukan JSON */
          }
          reject(new Error(msg))
        }
      }
      x.onerror = () => reject(new Error(t('studio.upload_failed')))
      x.onabort = () => reject(new Error(t('studio.upload_cancelled')))
      x.send(fd)
    })

  const submit = async () => {
    setErr(null)
    setFieldErrs({})
    if (!me?.ai_consent) return setConsent(true)
    if (est && !est.enabled) return openLocked(tool.entitlement)
    if (est && est.remaining !== null && est.remaining < est.cost) return openLocked(est.unit === 'credit' ? 'credits' : tool.entitlement, 'quota')
    const missing = tool.fields.filter((f) => f.required && f.key in sendValues && !String(sendValues[f.key] ?? '').trim())
    if (missing.length) {
      setFieldErrs(Object.fromEntries(missing.map((f) => [f.key, [t('tools.required', { field: f.label })]])))
      return
    }
    if (tool.upload && !file) return setErr(t('studio.file_required'))
    setBusy(true)
    try {
      if (tool.upload && file && upload && !upload.id && !upload.error) throw new Error(t('tools.upload_wait'))
      const uploadId = tool.upload && file ? (upload?.id ?? (await uploadFile(file))) : undefined
      await http.post('/app/jobs', { type: tool.type, params: sendValues, upload_id: uploadId }, { idempotencyKey: newIdempotencyKey() })
      toast.push('info', textMode ? t('tools.queued_text') : t('studio.queued_toast'))
      if (!isDesktop) setTab('results')
      loadJobs()
      void refresh()
    } catch (e) {
      if (e instanceof ApiError) {
        if (e.code === 'feature_locked') openLocked(tool.entitlement)
        else if (e.code === 'quota_exceeded') openLocked(tool.entitlement, 'quota')
        else if (e.code === 'subscription_expired') openLocked(tool.entitlement, 'expired')
        else {
          setFieldErrs(e.fields ?? {})
          setErr(e.message)
        }
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

  const reuse = (j: AiJob) => {
    setValues(toolDefaults(tool, { ...(j.params ?? {}), [tool.prompt_field]: (j.params?.[tool.prompt_field] as string) ?? j.prompt }))
    setTab('create')
  }

  const unitLabel = (n: number) => (est?.unit === 'credit' ? t('studio.credits', { count: n }) : t('studio.requests', { count: n }))
  const costLabel = est && est.cost > 0 ? `${t('studio.generate')} · ${unitLabel(est.cost)}` : t('studio.generate')
  const localOp = !!tool.local_ops?.includes(String(values.operation))
  // Tanpa penyedia AI aktif tidak ada hasil apa pun (tidak ada hasil contoh) → tombol Generate nonaktif
  const unavailable = !tool.provider_ready && !localOp

  const form = (
    <div className="grid gap-4">
      {unavailable && (
        <p role="status" className="t-small flex items-start gap-2 rounded-md bg-warning/10 p-3 text-warning">
          <Info className="mt-0.5 size-4 shrink-0" aria-hidden />
          {textMode ? t('tools.unavailable_text') : t('tools.unavailable_media')}
        </p>
      )}
      {tool.upload && (
        <div className="grid gap-1.5">
          <span className="t-label text-fg">
            {t(tool.upload === 'image' ? 'studio.source_image' : 'studio.source_video')} <span className="text-muted">({t('common.required')})</span>
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
            <span className="t-small text-muted">{t(tool.upload === 'image' ? 'studio.image_rules' : 'studio.video_rules')}</span>
            <input type="file" className="sr-only" accept={LIMITS[tool.upload].mime.join(',')} onChange={(e) => pickFile(e.target.files?.[0] ?? null)} />
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
          {upload?.error && <p className="t-small text-danger">{upload.error}</p>}
          {upload?.id && (
            <p className="t-small text-muted">
              {t('tools.uploaded')}
              {upload.duration ? ` · ${fmtTime(upload.duration)}` : ''}
              {upload.width && upload.height ? ` · ${upload.width}×${upload.height}` : ''}
            </p>
          )}
          {file && (
            <button type="button" onClick={() => pickFile(null)} className="t-small justify-self-start text-primary hover:underline">
              {t('studio.remove_file')}
            </button>
          )}
        </div>
      )}

      <ToolForm tool={tool} values={values} onChange={setValues} errors={fieldErrs} />

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
          <p className="font-semibold tabular-nums text-fg">{est ? unitLabel(est.cost) : '—'}</p>
        </div>
        <div className="text-right text-sm">
          <p className="text-muted">{t('studio.remaining')}</p>
          <p className="font-semibold tabular-nums text-fg">{est ? (est.remaining === null ? t('user.dash.unlimited') : number(est.remaining)) : '—'}</p>
        </div>
      </div>
      <Button size="lg" block loading={busy} disabled={unavailable} onClick={submit} icon={<Sparkles className="size-4" aria-hidden />}>
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
          {est ? unitLabel(est.cost) : '—'}
          <span className="font-normal text-muted"> · {t('studio.remaining')} {est ? (est.remaining === null ? t('user.dash.unlimited') : number(est.remaining)) : '—'}</span>
        </p>
      </div>
      <Button size="lg" loading={busy} disabled={unavailable} onClick={submit} icon={<Sparkles className="size-4" aria-hidden />}>
        {t('studio.generate')}
      </Button>
    </div>
  )

  const results = <JobResults jobs={jobs} onPreview={(j) => setPreviewId(j.id)} onCancel={cancel} onRetry={reuse} textMode={textMode} />
  const crumbRoot = textMode ? { label: t('user.nav.write'), to: '/app/create/script' } : { label: t('user.nav.studio'), to: '/app/create/photo' }

  return (
    <>
      <PageHeader title={tool.label} subtitle={tool.description} crumbs={[crumbRoot, { label: tool.label }]} />
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
      <Modal open={!!preview} onClose={() => setPreviewId(null)} size="media" title={preview?.result?.title ?? preview?.prompt ?? ''}>
        {preview && <JobPreview job={preview} />}
      </Modal>
    </>
  )
}

export function JobPreview({ job }: { job: AiJob }) {
  const { t } = useTranslation()
  const r = job.result
  const pal = r?.preview_palette ?? []
  if (r?.format === 'doc') return <DocResult result={r} jobId={job.id} running={job.status === 'queued' || job.status === 'processing'} />
  if (r?.items?.length)
    return (
      <ol className="grid gap-2">
        {r.items.map((x, i) => (
          <li key={i} className="rounded-md border border-line p-3 text-sm text-fg">
            <span className="mr-2 font-semibold text-primary">{i + 1}.</span>
            {typeof x === 'string' ? x : Object.values(x).join(' — ')}
          </li>
        ))}
      </ol>
    )
  // Klip video (Clipper/Edit Video): poster thumbnail, rentang waktu, unduh satu/semua
  if (r?.clips?.length)
    return (
      <div className="grid gap-3">
        {!!r.notes?.length && (
          <ul className="t-small grid gap-1 rounded-md bg-info/10 p-3 text-info">
            {r.notes.map((n) => (
              <li key={n}>{n}</li>
            ))}
          </ul>
        )}
        <div className="flex flex-wrap items-center justify-between gap-2">
          <p className="t-small text-muted">{t('tools.clips_summary', { count: r.clips.length, src: fmtTime(r.source_duration ?? 0) })}</p>
          {r.clips.length > 1 && (
            <Button size="sm" variant="secondary" icon={<Download className="size-3.5" aria-hidden />} onClick={() => r.clips!.forEach((c, i) => window.setTimeout(() => { const a = document.createElement('a'); a.href = c.url; a.download = `krafio-klip-${i + 1}.mp4`; a.click() }, i * 400))}>
              {t('tools.download_all')}
            </Button>
          )}
        </div>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {r.clips.map((c, i) => (
            <figure key={c.url} className="grid gap-2 rounded-lg border border-line p-2">
              <video src={c.url} poster={c.thumb ?? undefined} controls playsInline preload="none" className="aspect-[9/16] w-full rounded-md bg-black object-contain" />
              <figcaption className="flex items-center justify-between gap-2">
                <span className="t-small tabular-nums text-fg">
                  {t('tools.clip_n', { n: i + 1 })} · {fmtTime(c.start)}–{fmtTime(c.end ?? 0)}
                </span>
                <a href={c.url} download={`krafio-klip-${i + 1}.mp4`} className="t-label inline-flex items-center gap-1 text-primary hover:underline">
                  <Download className="size-4" aria-hidden />
                  {t('studio.download')}
                </a>
              </figcaption>
            </figure>
          ))}
        </div>
      </div>
    )
  // Hasil nyata (provider AI atau FFmpeg/Imagick lokal) — bisa diputar & diunduh
  if (r?.media?.length)
    return (
      <div className="grid gap-3">
        <div className="mx-auto grid w-full max-w-3xl gap-3 sm:grid-cols-2">
          {r.media.map((m, i) => (
            <figure key={m.url} className="grid gap-2">
              {m.kind === 'video' ? (
                <video src={m.url} controls playsInline preload="metadata" className="w-full rounded-lg border border-line bg-surface-2" />
              ) : (
                <img src={m.url} alt={t('studio.result_alt', { n: i + 1 })} loading="lazy" className="w-full rounded-lg border border-line bg-surface-2 object-contain" />
              )}
              <figcaption className="flex items-center justify-between gap-2">
                <a href={m.url} download className="t-label inline-flex items-center gap-1.5 text-primary hover:underline">
                  <Download className="size-4" aria-hidden />
                  {t('studio.download')}
                </a>
                {r.clips?.[i] && (
                  <span className="t-small tabular-nums text-muted">
                    {fmtTime(r.clips[i].start)}–{fmtTime(r.clips[i].end ?? 0)}
                  </span>
                )}
              </figcaption>
            </figure>
          ))}
        </div>
        {r.fallback && <p className="t-small text-muted">{t('studio.fallback_note')}</p>}
      </div>
    )
  return (
    <div className="grid gap-3">
      <div className="mx-auto grid w-full max-w-3xl gap-3 sm:grid-cols-2">
        {Array.from({ length: Math.max(1, r?.preview_images?.length ?? Math.ceil(pal.length / 3)) }).map((_, i) => (
          <div key={i} className="aspect-square rounded-lg border border-line bg-surface-2" style={previewStyle(r, i)} />
        ))}
      </div>
      {r?.note && <p className="t-small rounded-md bg-info/10 p-3 text-info">{r.note}</p>}
      <p className="t-small text-muted">{t('studio.download_note')}</p>
    </div>
  )
}

const fmtTime = (s: number) => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`

/** Cuplikan teks pertama dari hasil doc (kartu riwayat). */
function docSnippet(j: AiJob): string | null {
  const r = j.result
  if (!r) return null
  const first = r.parts?.[0]?.sections?.[0]?.content
  if (first) return first
  const it = r.items?.[0]
  return typeof it === 'string' ? it : it ? Object.values(it).filter(Boolean).slice(0, 2).join(' — ') : null
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
  // Belum ada hasil: kosong — tidak ada gambar/teks contoh sebelum pengguna menekan Generate
  if (jobs.length === 0) return <EmptyState icon={<Sparkles className="size-7" aria-hidden />} title={t('studio.no_results')} body={t('studio.no_results_body')} />
  return (
    <ul className={cn('grid gap-3', textMode ? 'grid-cols-1' : 'grid-cols-2')}>
      {jobs.map((j) => {
        const running = j.status === 'queued' || j.status === 'processing'
        const viewable = j.status === 'completed' || (running && !!j.result?.format)
        return (
          <li key={j.id} className="grid content-start gap-2 rounded-lg border border-line bg-surface p-2.5">
            {textMode ? (
              <button type="button" disabled={!viewable} onClick={() => onPreview(j)} className="grid gap-1 rounded-md bg-surface-2 p-3 text-left disabled:cursor-default">
                <p className="line-clamp-2 text-sm font-semibold text-fg">{j.result?.title ?? j.prompt}</p>
                {docSnippet(j) && <p className="t-small line-clamp-2 text-muted">{docSnippet(j)}</p>}
                {running && (
                  <span className="mt-1 h-1.5 overflow-hidden rounded-full bg-line">
                    <span className="block h-full rounded-full bg-primary transition-[width]" style={{ width: `${Math.max(6, j.progress)}%` }} />
                  </span>
                )}
                {j.result?.parts && j.result.parts.length > 1 && <span className="t-small text-primary">{t('tools.parts_count', { count: j.result.parts.length })}</span>}
              </button>
            ) : (
              <button type="button" disabled={j.status !== 'completed'} onClick={() => onPreview(j)} aria-label={t('studio.view')} className="relative aspect-square overflow-hidden rounded-md bg-surface-2 disabled:cursor-default">
                {j.status === 'completed' && j.result?.media?.[0]?.kind === 'video' ? (
                  <video src={j.result.media[0].url} poster={j.result.clips?.[0]?.thumb ?? undefined} muted playsInline preload="none" className="absolute inset-0 h-full w-full object-cover" />
                ) : j.status === 'completed' && (j.result?.preview_images?.length || j.result?.preview_palette) ? (
                  <span className="absolute inset-0" style={previewStyle(j.result)} />
                ) : running ? (
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
                  {j.status === 'failed' ? t('common.retry') : t('tools.reuse')}
                </Button>
              )}
              {j.status === 'completed' && (
                <Button size="sm" variant="ghost" icon={textMode ? <FileText className="size-3.5" aria-hidden /> : <Download className="size-3.5" aria-hidden />} onClick={() => onPreview(j)}>
                  {textMode ? t('tools.open') : t('studio.view')}
                </Button>
              )}
            </div>
          </li>
        )
      })}
    </ul>
  )
}
