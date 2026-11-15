import { useCallback, useEffect, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { CalendarClock, Link2, RefreshCw, Send, Unlink, ExternalLink, ImagePlus, X as XIcon } from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import { useApi } from '@/shared/hooks/useApi'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { useToast } from '@/shared/context/ToastContext'
import { useLocked } from '@/shared/layout/Locked'
import { Card, CardTitle, PageHeader, Segmented } from '@/shared/ui/Misc'
import { Button } from '@/shared/ui/Button'
import { TextArea, TextField } from '@/shared/ui/Field'
import { StatusChip } from '@/shared/ui/Status'
import { EmptyState, Skeleton } from '@/shared/ui/States'
import { Modal } from '@/shared/ui/Modal'
import { ConfirmDialog } from '@/shared/ui/ConfirmDialog'
import { dateTime, number, relative } from '@/shared/lib/format'
import { cn } from '@/shared/lib/cn'

interface Platform {
  platform: string
  label: string
  available: boolean
  via: { direct: boolean; postiz: boolean }
  status: 'connected' | 'disconnected' | 'expired' | 'not_available'
  provider: 'direct' | 'postiz' | null
  account_name: string | null
  username: string | null
  avatar_url: string | null
  stats: Record<string, number>
  synced_at: string | null
  expires_at: string | null
  last_error: string | null
  scopes: string[]
  can_publish: boolean
}
interface SocialData {
  postiz_enabled: boolean
  entitled: boolean
  limit: number
  platforms: Platform[]
}
interface SocialPostRow {
  id: number
  content: string
  platforms: string[]
  type: 'now' | 'schedule' | 'draft'
  status: string
  error: string | null
  scheduled_at: string | null
  created_at: string
  provider?: 'direct' | 'postiz'
  results?: Record<string, { ok: boolean; url: string | null; error: string | null }>
}

interface MediaItem {
  key: string
  name: string
  video: boolean
  id?: number
  error?: string
}

/** Unggah satu file ke /app/uploads (multipart; cookie Sanctum + XSRF). */
function uploadMedia(f: File, fallback: string): Promise<number> {
  return new Promise((resolve, reject) => {
    const fd = new FormData()
    fd.append('file', f)
    const x = new XMLHttpRequest()
    x.open('POST', '/api/app/uploads')
    x.withCredentials = true
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
    if (m) x.setRequestHeader('X-XSRF-TOKEN', decodeURIComponent(m[1]))
    x.setRequestHeader('Accept', 'application/json')
    x.onload = () => {
      if (x.status >= 200 && x.status < 300) return resolve((JSON.parse(x.responseText).data as { id: number }).id)
      let msg = fallback
      try {
        msg = JSON.parse(x.responseText).message ?? msg
      } catch {
        /* respons bukan JSON */
      }
      reject(new Error(msg))
    }
    x.onerror = () => reject(new Error(fallback))
    x.send(fd)
  })
}

/**
 * Integrasi media sosial (PRD 6.11, 30). "Hubungkan" membuka POPUP ke halaman login resmi platform (atau Postiz);
 * pengguna login sendiri, platform memanggil callback REST Krafio, lalu popup mengirim postMessage dan menutup diri.
 * Bila popup diblokir, halaman diarahkan penuh dan kembali ke sini dengan ?social=connected|error.
 */
export function SocialPage() {
  const { t } = useTranslation()
  useDocTitle(t('features.social_integrations'))
  const toast = useToast()
  const { openLocked } = useLocked()
  const [params, setParams] = useSearchParams()
  const { data, reload } = useApi<SocialData>('/app/social')
  const [ask, setAsk] = useState<Platform | null>(null)
  const [disc, setDisc] = useState<Platform | null>(null)
  const [busy, setBusy] = useState<string | null>(null)
  const popup = useRef<Window | null>(null)

  // Hasil dari popup callback
  useEffect(() => {
    const onMsg = (e: MessageEvent) => {
      if (e.origin !== window.location.origin || e.data?.type !== 'krafio:social') return
      toast.push(e.data.ok ? 'success' : 'error', e.data.message)
      setBusy(null)
      reload()
    }
    window.addEventListener('message', onMsg)
    return () => window.removeEventListener('message', onMsg)
  }, [reload, toast])
  // Fallback tanpa popup: kembali dengan query string
  useEffect(() => {
    const s = params.get('social')
    if (!s) return
    toast.push(s === 'connected' ? 'success' : 'error', params.get('message') ?? '')
    setParams({}, { replace: true })
  }, [params, setParams, toast])
  // Popup ditutup pengguna tanpa menyelesaikan → hentikan status "menunggu"
  useEffect(() => {
    if (!busy) return
    const id = window.setInterval(() => {
      if (popup.current?.closed) {
        setBusy(null)
        reload()
      }
    }, 1000)
    return () => window.clearInterval(id)
  }, [busy, reload])

  const connect = async (p: Platform, via?: 'direct' | 'postiz') => {
    // Buka jendela dulu (sinkron dengan klik) agar tidak diblokir browser, lalu isi URL-nya
    const w = window.open('', 'krafio-social', 'width=560,height=720,menubar=no,toolbar=no')
    try {
      const r = await http.post<{ data: { authorize_url: string } }>(`/app/social/${p.platform}/connect`, via ? { via } : {})
      setAsk(null)
      if (w) {
        w.location.href = r.data.authorize_url
        popup.current = w
        setBusy(p.platform)
      } else {
        window.location.href = r.data.authorize_url
      }
    } catch (e) {
      w?.close()
      setAsk(null)
      if (e instanceof ApiError && (e.code === 'feature_locked' || e.code === 'quota_exceeded')) openLocked('social.integrations', e.code === 'quota_exceeded' ? 'quota' : undefined)
      else toast.push('error', e instanceof ApiError ? e.message : t('errors.server_body'))
    }
  }

  const sync = async (p: Platform) => {
    setBusy(`sync-${p.platform}`)
    try {
      await http.post(`/app/social/${p.platform}/sync`)
      reload()
    } catch (e) {
      toast.push('error', e instanceof ApiError ? e.message : t('errors.server_body'))
    } finally {
      setBusy(null)
    }
  }

  if (!data)
    return (
      <>
        <PageHeader title={t('features.social_integrations')} subtitle={t('social.subtitle')} />
        <Skeleton className="h-60" />
      </>
    )

  const connected = data.platforms.filter((p) => p.status === 'connected').length

  return (
    <>
      <PageHeader title={t('features.social_integrations')} subtitle={t('social.subtitle')} />
      {!data.entitled && (
        <p className="t-small mb-4 rounded-md bg-warning/10 p-3 text-warning">
          {t('social.locked')}{' '}
          <button type="button" className="font-semibold underline" onClick={() => openLocked('social.integrations')}>
            {t('social.see_plans')}
          </button>
        </p>
      )}
      <p className="t-small mb-4 text-muted">{t('social.usage', { count: connected, limit: data.limit })}</p>
      <ul className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        {data.platforms.map((a) => (
          <li key={a.platform} className="grid content-start gap-3 rounded-lg border border-line bg-surface p-4">
            <div className="flex items-start justify-between gap-2">
              <div className="flex min-w-0 items-center gap-3">
                {a.avatar_url ? (
                  <img src={a.avatar_url} alt="" referrerPolicy="no-referrer" className="size-10 shrink-0 rounded-full border border-line object-cover" />
                ) : (
                  <span className="grid size-10 shrink-0 place-items-center rounded-full bg-surface-2 text-sm font-semibold text-muted">{a.label.slice(0, 1)}</span>
                )}
                <div className="min-w-0">
                  <p className="t-h3 truncate">{a.label}</p>
                  {a.username || a.account_name ? <p className="t-small truncate text-fg">{a.username ? `@${a.username.replace(/^@/, '')}` : a.account_name}</p> : null}
                </div>
              </div>
              <StatusChip status={a.status} label={t(`social.st_${a.status}`)} />
            </div>

            {a.status !== 'not_available' && a.status !== 'disconnected' && Object.keys(a.stats ?? {}).length > 0 && (
              <dl className="grid grid-cols-3 gap-2 rounded-md bg-surface-2 p-2 text-center">
                {Object.entries(a.stats).slice(0, 3).map(([k, v]) => (
                  <div key={k}>
                    <dd className="font-semibold tabular-nums text-fg">{number(v)}</dd>
                    <dt className="t-small text-muted">{t(`social.stat.${k}`, { defaultValue: k })}</dt>
                  </div>
                ))}
              </dl>
            )}
            {a.provider && <p className="t-small text-muted">{t(a.provider === 'postiz' ? 'social.via_postiz' : 'social.via_direct')}{a.synced_at ? ` · ${t('social.synced', { when: relative(a.synced_at) })}` : ''}</p>}
            {a.expires_at && <p className="t-small text-muted">{t('social.token_until', { date: dateTime(a.expires_at, false) })}</p>}
            {a.last_error && <p className="t-small text-danger">{a.last_error}</p>}

            <div className="flex flex-wrap gap-2">
              {!a.available ? (
                <p className="t-small text-muted">{t('social.not_available_hint')}</p>
              ) : a.status === 'connected' ? (
                <>
                  <Button size="sm" variant="secondary" loading={busy === `sync-${a.platform}`} icon={<RefreshCw className="size-4" aria-hidden />} onClick={() => sync(a)}>
                    {t('social.sync')}
                  </Button>
                  <Button size="sm" variant="ghost" icon={<Unlink className="size-4" aria-hidden />} onClick={() => setDisc(a)}>
                    {t('social.disconnect')}
                  </Button>
                </>
              ) : (
                <Button size="sm" loading={busy === a.platform} icon={<Link2 className="size-4" aria-hidden />} onClick={() => setAsk(a)}>
                  {busy === a.platform ? t('social.waiting') : a.status === 'expired' ? t('social.reconnect') : t('social.connect')}
                </Button>
              )}
            </div>
          </li>
        ))}
      </ul>

      <Composer data={data} />

      {/* Layar izin dalam bahasa sederhana sebelum login resmi (§11.4) */}
      <Modal
        open={!!ask}
        onClose={() => setAsk(null)}
        size="info"
        title={ask ? t('social.permission_title', { platform: ask.label }) : ''}
        footer={
          ask && (
            <div className="flex flex-wrap justify-end gap-2">
              {ask.via.direct && ask.via.postiz && (
                <Button variant="secondary" icon={<ExternalLink className="size-4" aria-hidden />} onClick={() => connect(ask, 'postiz')}>
                  {t('social.via_postiz_btn')}
                </Button>
              )}
              <Button icon={<ExternalLink className="size-4" aria-hidden />} onClick={() => connect(ask, ask.via.direct ? 'direct' : 'postiz')}>
                {t('social.continue_oauth')}
              </Button>
            </div>
          )
        }
      >
        <p className="t-body text-muted">{t('social.permission_body2')}</p>
        {ask && (
          <ul className="t-small mt-3 grid gap-1 text-fg">
            {ask.scopes.map((s) => (
              <li key={s} className="rounded bg-surface-2 px-2 py-1 font-mono">
                {s}
              </li>
            ))}
          </ul>
        )}
        <p className="t-small mt-3 text-muted">{t('social.popup_note')}</p>
      </Modal>
      <ConfirmDialog
        open={!!disc}
        onClose={() => setDisc(null)}
        title={disc ? t('social.disconnect_title', { platform: disc.label }) : ''}
        body={t('social.disconnect_body')}
        confirmLabel={t('social.disconnect')}
        destructive
        onConfirm={async () => {
          await http.post(`/app/social/${disc!.platform}/disconnect`)
          setDisc(null)
          reload()
        }}
      />
    </>
  )
}

/** Buat posting / jadwal: langsung ke API resmi platform (akun terhubung langsung) atau lewat Postiz. */
function Composer({ data }: { data: SocialData }) {
  const { t } = useTranslation()
  const toast = useToast()
  const [params] = useSearchParams()
  const posts = useApi<SocialPostRow[]>('/app/social/posts')
  const targets = data.platforms.filter((p) => p.can_publish)
  const [content, setContent] = useState(params.get('compose') ?? '')
  const [chosen, setChosen] = useState<string[]>([])
  const [type, setType] = useState<'now' | 'schedule' | 'draft'>('schedule')
  const [when, setWhen] = useState('')
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState<string | null>(null)
  const [media, setMedia] = useState<MediaItem[]>([])
  const uploading = media.some((m) => !m.id && !m.error)
  const pick = (files: FileList | null) => {
    for (const f of Array.from(files ?? []).slice(0, 10 - media.length)) {
      const key = `${f.name}-${f.size}-${Math.random()}`
      setMedia((c) => [...c, { key, name: f.name, video: f.type.startsWith('video/') }])
      uploadMedia(f, t('studio.upload_failed'))
        .then((id) => setMedia((c) => c.map((m) => (m.key === key ? { ...m, id } : m))))
        .catch((e: Error) => setMedia((c) => c.map((m) => (m.key === key ? { ...m, error: e.message } : m))))
    }
  }
  const act = async (id: number, action: 'publish' | 'cancel') => {
    try {
      await http.post(`/app/social/posts/${id}/${action}`)
      toast.push('success', t(`social.action_${action}_done`))
      posts.reload()
    } catch (e) {
      toast.push('error', e instanceof ApiError ? e.message : (e as Error).message)
    }
  }
  // Muat ulang daftar selama ada posting yang sedang diproses
  const pending = posts.data?.some((p) => p.status === 'queued' || p.status === 'publishing')
  useEffect(() => {
    if (!pending) return
    const reload = posts.reload
    const h = window.setInterval(() => reload(), 5000)
    return () => window.clearInterval(h)
  }, [pending, posts.reload])
  // Batas awal jadwal (5 menit dari saat composer dibuka) — dihitung sekali, bukan saat render
  const [minWhen] = useState(() => new Date(Date.now() + 5 * 60000).toISOString().slice(0, 16))
  const toggle = useCallback((p: string) => setChosen((c) => (c.includes(p) ? c.filter((x) => x !== p) : [...c, p])), [])

  const submit = async () => {
    setErr(null)
    if (!content.trim() || chosen.length === 0) return setErr(t('social.compose_missing'))
    if (type === 'schedule' && !when) return setErr(t('social.compose_when'))
    setBusy(true)
    try {
      const upload_ids = media.filter((m) => m.id).map((m) => m.id)
      await http.post('/app/social/posts', { content, platforms: chosen, type, scheduled_at: type === 'schedule' ? new Date(when).toISOString() : null, upload_ids })
      toast.push('success', t(`social.posted_${type}`))
      setContent('')
      setMedia([])
      posts.reload()
    } catch (e) {
      setErr(e instanceof ApiError ? e.message : (e as Error).message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="mt-8 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
      <Card>
        <CardTitle>
          <span className="inline-flex items-center gap-2">
            <CalendarClock className="size-4 text-primary" aria-hidden />
            {t('social.compose_title')}
          </span>
        </CardTitle>
        {targets.length === 0 ? (
          <p className="t-small text-muted">{data.platforms.some((p) => p.available) ? t('social.compose_no_targets') : t('social.compose_needs_setup')}</p>
        ) : (
          <div className="grid gap-4">
            <TextArea label={t('social.compose_content')} rows={5} maxLength={5000} value={content} onChange={(e) => setContent(e.target.value)} hint={`${content.length}/5000`} />
            <div className="grid gap-1.5">
              <span className="t-label text-fg">{t('social.compose_targets')}</span>
              <div className="flex flex-wrap gap-2">
                {targets.map((p) => (
                  <button key={p.platform} type="button" aria-pressed={chosen.includes(p.platform)} onClick={() => toggle(p.platform)} className={cn('t-label h-9 rounded-full border px-3.5', chosen.includes(p.platform) ? 'border-primary bg-primary/10 text-primary' : 'border-line text-fg hover:border-primary/50')}>
                    {p.label}
                  </button>
                ))}
              </div>
            </div>
            <div className="grid gap-1.5">
              <span className="t-label text-fg">{t('social.compose_media')}</span>
              <div className="flex flex-wrap gap-2">
                {media.map((m) => (
                  <span key={m.key} className={cn('t-small inline-flex max-w-full items-center gap-1.5 rounded-full border px-3 py-1.5', m.error ? 'border-danger/40 text-danger' : 'border-line text-fg')} title={m.error}>
                    <span className="truncate">{(m.video ? '🎬 ' : '🖼️ ') + m.name}</span>
                    <span className="text-muted">{m.error ? t('social.media_failed') : m.id ? '' : t('social.media_uploading')}</span>
                    <button type="button" aria-label={t('common.remove', { defaultValue: 'Hapus' })} onClick={() => setMedia((c) => c.filter((x) => x.key !== m.key))}>
                      <XIcon className="size-3.5" aria-hidden />
                    </button>
                  </span>
                ))}
                {media.length < 10 && (
                  <label className="t-label inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-full border border-dashed border-line px-3.5 text-fg hover:border-primary/50">
                    <ImagePlus className="size-4" aria-hidden />
                    {t('social.media_add')}
                    <input type="file" className="sr-only" multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm" onChange={(e) => (pick(e.target.files), (e.target.value = ''))} />
                  </label>
                )}
              </div>
              <p className="t-small text-muted">{t('social.media_hint')}</p>
            </div>
            <Segmented label={t('social.compose_type')} value={type} onChange={setType} items={(['now', 'schedule', 'draft'] as const).map((v) => ({ value: v, label: t(`social.type_${v}`) }))} />
            {type === 'schedule' && <TextField type="datetime-local" label={t('social.compose_when_label')} value={when} onChange={(e) => setWhen(e.target.value)} min={minWhen} />}
            {err && <p className="t-small rounded-md bg-danger/10 p-3 text-danger">{err}</p>}
            <Button loading={busy} disabled={uploading} icon={<Send className="size-4" aria-hidden />} onClick={submit}>
              {t(`social.submit_${type}`)}
            </Button>
          </div>
        )}
      </Card>
      <Card>
        <CardTitle>{t('social.posts_title')}</CardTitle>
        {!posts.data ? (
          <Skeleton className="h-40" />
        ) : posts.data.length === 0 ? (
          <EmptyState icon={<CalendarClock className="size-7" aria-hidden />} title={t('social.posts_empty')} body={t('social.posts_empty_body')} />
        ) : (
          <ul className="grid gap-2">
            {posts.data.map((p) => (
              <li key={p.id} className="rounded-md border border-line p-3">
                <div className="mb-1 flex items-center justify-between gap-2">
                  <span className="t-small text-muted">{p.platforms.join(', ')}</span>
                  <StatusChip status={p.status === 'sent' || p.status === 'scheduled' ? 'completed' : p.status === 'failed' || p.status === 'partial' ? 'failed' : 'queued'} label={t(`social.post_st_${p.status}`, { defaultValue: p.status })} />
                </div>
                <p className="line-clamp-2 text-sm text-fg">{p.content}</p>
                <p className="t-small mt-1 text-muted">{p.scheduled_at ? t('social.scheduled_for', { when: dateTime(p.scheduled_at) }) : relative(p.created_at)}</p>
                {p.results && Object.keys(p.results).length > 0 && (
                  <div className="mt-1 flex flex-wrap gap-x-3 gap-y-1">
                    {Object.entries(p.results).map(([pl, r]) =>
                      r.ok && r.url ? (
                        <a key={pl} href={r.url} target="_blank" rel="noreferrer" className="t-small inline-flex items-center gap-1 text-primary hover:underline">
                          ✓ {pl} <ExternalLink className="size-3" aria-hidden />
                        </a>
                      ) : (
                        <span key={pl} className={cn('t-small', r.ok ? 'text-success' : 'text-danger')}>
                          {r.ok ? '✓' : '✗'} {pl}
                        </span>
                      ),
                    )}
                  </div>
                )}
                {p.error && <p className="t-small text-danger">{p.error}</p>}
                {p.provider === 'direct' && ['failed', 'partial', 'draft', 'scheduled'].includes(p.status) && (
                  <div className="mt-2 flex flex-wrap gap-2">
                    <Button size="sm" variant="secondary" onClick={() => act(p.id, 'publish')}>
                      {t(p.status === 'failed' || p.status === 'partial' ? 'social.action_retry' : 'social.action_publish_now')}
                    </Button>
                    {['draft', 'scheduled'].includes(p.status) && (
                      <Button size="sm" variant="ghost" onClick={() => act(p.id, 'cancel')}>
                        {t('social.action_cancel')}
                      </Button>
                    )}
                  </div>
                )}
              </li>
            ))}
          </ul>
        )}
      </Card>
    </section>
  )
}
