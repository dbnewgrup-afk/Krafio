import { useEffect, useRef, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Plus, Send, Bot, Globe, Monitor, Tablet, Smartphone, Save, Rocket, Link2, Unlink, RefreshCw, Trash2, FolderOpen, MonitorSmartphone } from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import type { AiJob, TemplateItem } from '@/api-client/types'
import { useAuth } from '@/shared/context/AuthContext'
import { useToast } from '@/shared/context/ToastContext'
import { useLocked } from '@/shared/layout/Locked'
import { useApi } from '@/shared/hooks/useApi'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { useIsDesktop } from '@/shared/hooks/useMediaQuery'
import { Card, ChipRow, PageHeader, Segmented, Tabs, Accordion } from '@/shared/ui/Misc'
import { Button, ButtonLink } from '@/shared/ui/Button'
import { TextField, TextArea, SelectField, inputCls } from '@/shared/ui/Field'
import { StatusChip, Badge } from '@/shared/ui/Status'
import { EmptyState, Skeleton } from '@/shared/ui/States'
import { Modal } from '@/shared/ui/Modal'
import { ConfirmDialog } from '@/shared/ui/ConfirmDialog'
import { TemplateCard, TemplatePreview } from '@/public-site/components/TemplateCard'
import { JobPreview } from './Studio'
import { dateTime, relative } from '@/shared/lib/format'
import { cn } from '@/shared/lib/cn'

/* ---------------- Template library (§19.4) ---------------- */
export function UserTemplatesPage() {
  const { t } = useTranslation()
  useDocTitle(t('user.nav.templates'))
  const nav = useNavigate()
  const { me } = useAuth()
  const { openLocked } = useLocked()
  const toast = useToast()
  const [params, setParams] = useSearchParams()
  const [type, setType] = useState('all')
  const [q, setQ] = useState('')
  const list = useApi<TemplateItem[]>('/public/templates', { type: type === 'all' ? '' : type, search: q, per_page: 60 })
  const useId = Number(params.get('use'))
  const open = list.data?.find((x) => x.id === useId) ?? null
  const premiumOk = me?.entitlements['templates.premium']?.enabled
  const use = async (tp: TemplateItem) => {
    if (tp.is_premium && !premiumOk) return openLocked('templates.premium')
    try {
      const r = await http.post<{ data: { id: number } }>('/app/projects', { name: tp.title, type: tp.type, template_id: tp.id })
      toast.push('success', t('templates.project_created'))
      nav(`/app/projects/${r.data.id}`)
    } catch (e) {
      if (e instanceof ApiError && e.code === 'feature_locked') openLocked('templates.premium')
    }
  }
  return (
    <>
      <PageHeader title={t('user.nav.templates')} subtitle={t('templates.body')} />
      <div className="grid gap-3">
        <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('templates.search')} aria-label={t('templates.search')} className={inputCls} />
        <ChipRow label={t('templates.category')} value={type} onChange={setType} items={[{ value: 'all', label: t('table.all') }, ...['photo', 'video', 'website', 'social', 'business'].map((x) => ({ value: x, label: t(`templates.types.${x}`) }))]} />
      </div>
      <div className="mt-5">
        {!list.data ? (
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">{Array.from({ length: 8 }).map((_, i) => <Skeleton key={i} className="aspect-square" />)}</div>
        ) : list.data.length === 0 ? (
          <EmptyState kind="filter" title={t('table.no_results')} />
        ) : (
          <ul className="grid grid-cols-2 gap-2 md:grid-cols-3 lg:grid-cols-4">
            {list.data.map((it) => (
              <li key={it.id}>
                <TemplateCard item={it} onClick={() => setParams({ use: String(it.id) })} />
              </li>
            ))}
          </ul>
        )}
      </div>
      <Modal
        open={!!open}
        onClose={() => { params.delete('use'); setParams(params, { replace: true }) }}
        size="media"
        title={open?.title ?? ''}
        footer={open && <Button onClick={() => use(open)}>{open.is_premium && !premiumOk ? t('templates.unlock') : t('templates.use')}</Button>}
      >
        {open && (
          <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
            <div className="mx-auto w-full max-w-xl"><TemplatePreview item={open} watermark={open.is_premium && !premiumOk} /></div>
            <div className="grid content-start gap-2">
              <p className="t-body text-muted">{open.description}</p>
              <p className="t-small text-muted">{t(`templates.types.${open.type}`)} — {open.ratio}</p>
              {open.is_premium && <Badge tone="warning">{t('templates.premium')}</Badge>}
            </div>
          </div>
        )}
      </Modal>
    </>
  )
}

/* ---------------- Proyek & aset ---------------- */
interface Project {
  id: number
  name: string
  type: string
  status: string
  updated_at: string
  template?: TemplateItem | null
  jobs?: AiJob[]
}
export function ProjectsPage() {
  const { t } = useTranslation()
  useDocTitle(t('user.nav.projects'))
  const list = useApi<Project[]>('/app/projects')
  const [open, setOpen] = useState(false)
  const [name, setName] = useState('')
  const [type, setType] = useState('photo')
  const nav = useNavigate()
  return (
    <>
      <PageHeader title={t('user.nav.projects')} subtitle={t('projects.subtitle')} actions={<Button icon={<Plus className="size-4" aria-hidden />} onClick={() => setOpen(true)}>{t('projects.new')}</Button>} />
      {!list.data ? (
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">{Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-36" />)}</div>
      ) : list.data.length === 0 ? (
        <EmptyState title={t('projects.empty')} body={t('projects.empty_body')} action={<Button onClick={() => setOpen(true)}>{t('projects.first')}</Button>} />
      ) : (
        <ul className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          {list.data.map((p) => (
            <li key={p.id}>
              <Link to={`/app/projects/${p.id}`} className="grid h-full content-start gap-2 rounded-lg border border-line bg-surface p-3 hover:border-primary/50">
                {p.template ? <TemplatePreview item={p.template} frame={16 / 9} /> : <span className="grid aspect-video place-items-center rounded-md bg-surface-2 text-muted"><FolderOpen className="size-6" aria-hidden /></span>}
                <p className="truncate font-semibold text-fg">{p.name}</p>
                <p className="t-small text-muted">{t(`templates.types.${p.type}`)} — {relative(p.updated_at)}</p>
              </Link>
            </li>
          ))}
        </ul>
      )}
      <Modal
        open={open}
        onClose={() => setOpen(false)}
        size="form"
        dirty={!!name}
        title={t('projects.new')}
        footer={
          <Button
            disabled={!name.trim()}
            onClick={async () => {
              const r = await http.post<{ data: { id: number } }>('/app/projects', { name, type })
              setOpen(false)
              nav(`/app/projects/${r.data.id}`)
            }}
          >
            {t('projects.create')}
          </Button>
        }
      >
        <div className="grid gap-4">
          <TextField label={t('projects.name')} required value={name} onChange={(e) => setName(e.target.value)} />
          <SelectField label={t('projects.type')} value={type} onChange={(e) => setType(e.target.value)}>
            {['photo', 'video', 'website', 'social', 'business'].map((x) => <option key={x} value={x}>{t(`templates.types.${x}`)}</option>)}
          </SelectField>
        </div>
      </Modal>
    </>
  )
}

export function ProjectDetailPage() {
  const { id } = useParams()
  const { t } = useTranslation()
  const p = useApi<Project>(`/app/projects/${id}`)
  useDocTitle(p.data?.name ?? t('user.nav.projects'))
  const nav = useNavigate()
  const [del, setDel] = useState(false)
  const [preview, setPreview] = useState<AiJob | null>(null)
  return (
    <>
      <PageHeader
        title={p.data?.name ?? '…'}
        crumbs={[{ label: t('user.nav.projects'), to: '/app/projects' }, { label: p.data?.name ?? '' }]}
        actions={
          <>
            <ButtonLink to={`/app/create/${p.data?.type === 'video' ? 'video' : 'photo'}?template=${p.data?.template?.id ?? ''}&project=${id}`} icon={<Plus className="size-4" aria-hidden />}>
              {t('projects.generate_here')}
            </ButtonLink>
            <Button variant="secondary" icon={<Trash2 className="size-4" aria-hidden />} onClick={() => setDel(true)}>
              {t('common.delete')}
            </Button>
          </>
        }
      />
      {!p.data ? (
        <Skeleton className="h-60" />
      ) : (
        <div className="grid gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
          <Card className="h-fit">
            {p.data.template ? <TemplatePreview item={p.data.template} /> : <span className="grid aspect-square place-items-center rounded-md bg-surface-2 text-muted"><FolderOpen className="size-8" aria-hidden /></span>}
            <dl className="t-small mt-3 grid gap-1">
              <div className="flex justify-between"><dt className="text-muted">{t('projects.type')}</dt><dd>{t(`templates.types.${p.data.type}`)}</dd></div>
              <div className="flex justify-between"><dt className="text-muted">{t('support.updated')}</dt><dd>{dateTime(p.data.updated_at)}</dd></div>
            </dl>
          </Card>
          <section>
            <h2 className="t-h2 mb-3">{t('projects.outputs')}</h2>
            {!p.data.jobs?.length ? (
              <EmptyState title={t('projects.no_outputs')} />
            ) : (
              <ul className="grid grid-cols-2 gap-3 lg:grid-cols-3">
                {p.data.jobs.map((j) => (
                  <li key={j.id} className="grid gap-2 rounded-lg border border-line bg-surface p-2">
                    <button type="button" onClick={() => setPreview(j)} className="aspect-square rounded-md" style={j.result?.preview_palette ? { background: `linear-gradient(150deg, ${j.result.preview_palette[0]}, ${j.result.preview_palette[1]} 55%, ${j.result.preview_palette[2]})` } : undefined} aria-label={t('studio.view')} />
                    <StatusChip status={j.status} />
                  </li>
                ))}
              </ul>
            )}
          </section>
        </div>
      )}
      <ConfirmDialog
        open={del}
        onClose={() => setDel(false)}
        destructive
        title={t('projects.delete_title', { name: p.data?.name ?? '' })}
        body={t('projects.delete_body')}
        confirmLabel={t('projects.delete')}
        onConfirm={async () => {
          await http.del(`/app/projects/${id}`)
          nav('/app/projects')
        }}
      />
      <Modal open={!!preview} onClose={() => setPreview(null)} size="media" title={preview?.prompt ?? ''}>{preview && <JobPreview job={preview} />}</Modal>
    </>
  )
}

export function AssetsPage() {
  const { t } = useTranslation()
  useDocTitle(t('user.nav.assets'))
  const [kind, setKind] = useState('all')
  const list = useApi<{ id: number; name: string; kind: string; size: number; created_at: string; job?: AiJob }[]>('/app/assets', { kind: kind === 'all' ? '' : kind })
  const [preview, setPreview] = useState<AiJob | null>(null)
  return (
    <>
      <PageHeader title={t('user.nav.assets')} subtitle={t('assets.subtitle')} />
      <ChipRow label={t('assets.kind')} value={kind} onChange={setKind} items={[{ value: 'all', label: t('table.all') }, { value: 'generated', label: t('assets.generated') }, { value: 'upload', label: t('assets.uploads') }]} />
      <div className="mt-5">
        {!list.data ? (
          <Skeleton className="h-60" />
        ) : list.data.length === 0 ? (
          <EmptyState title={t('assets.empty')} action={<ButtonLink to="/app/create/photo" size="sm">{t('user.dash.first_job')}</ButtonLink>} />
        ) : (
          <ul className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
            {list.data.map((a) => (
              <li key={a.id} className="grid gap-1.5 rounded-lg border border-line bg-surface p-2">
                <button type="button" disabled={!a.job} onClick={() => a.job && setPreview(a.job)} className="aspect-square rounded-md bg-surface-2" style={a.job?.result?.preview_palette ? { background: `linear-gradient(150deg, ${a.job.result.preview_palette[0]}, ${a.job.result.preview_palette[1]} 55%, ${a.job.result.preview_palette[2]})` } : undefined} aria-label={a.name} />
                <p className="t-small truncate font-semibold text-fg">{a.name}</p>
                <p className="t-small text-muted">{relative(a.created_at)}</p>
              </li>
            ))}
          </ul>
        )}
      </div>
      <Modal open={!!preview} onClose={() => setPreview(null)} size="media" title={preview?.prompt ?? ''}>{preview && <JobPreview job={preview} />}</Modal>
    </>
  )
}

/* ---------------- AI Assistant (PRD 6.9) ---------------- */
interface ChatMsg {
  id: number
  role: 'user' | 'assistant'
  content: string
  created_at: string
}
export function AssistantPage() {
  const { t } = useTranslation()
  useDocTitle(t('features.chatbot_basic'))
  const { me, refresh } = useAuth()
  const { openLocked } = useLocked()
  const chat = useApi<{ messages: ChatMsg[]; provider_configured: boolean }>('/app/assistant')
  const [msgs, setMsgs] = useState<ChatMsg[]>([])
  const [q, setQ] = useState('')
  const [busy, setBusy] = useState(false)
  const end = useRef<HTMLDivElement>(null)
  useEffect(() => {
    if (chat.data) setMsgs(chat.data.messages)
  }, [chat.data])
  useEffect(() => end.current?.scrollIntoView({ block: 'end' }), [msgs])
  const send = async (e: FormEvent) => {
    e.preventDefault()
    const text = q.trim()
    if (!text) return
    if (!me?.ai_consent) {
      await http.post('/me/ai-consent')
      await refresh()
    }
    setQ('')
    setMsgs((m) => [...m, { id: Date.now(), role: 'user', content: text, created_at: new Date().toISOString() }])
    setBusy(true)
    try {
      const r = await http.post<{ data: ChatMsg }>('/app/assistant', { message: text })
      setMsgs((m) => [...m, r.data])
      void refresh()
    } catch (er) {
      if (er instanceof ApiError && er.code === 'quota_exceeded') openLocked('chatbot.basic', 'quota')
    } finally {
      setBusy(false)
    }
  }
  return (
    <>
      <PageHeader title={t('features.chatbot_basic')} subtitle={t('assistant.subtitle')} />
      {chat.data && !chat.data.provider_configured && <p className="t-small mb-3 rounded-md bg-info/10 p-3 text-info">{t('assistant.no_provider')}</p>}
      <Card className="flex h-[calc(100dvh-var(--nav-h)-260px)] min-h-[420px] flex-col p-0 md:p-0">
        <div className="min-h-0 flex-1 overflow-y-auto p-4" aria-live="polite">
          {msgs.length === 0 ? (
            <div className="grid h-full place-items-center text-center">
              <div className="grid justify-items-center gap-2">
                <Bot className="size-10 text-primary" aria-hidden />
                <p className="t-h3">{t('assistant.empty_title')}</p>
                <div className="flex flex-wrap justify-center gap-2 pt-2">
                  {['s1', 's2', 's3'].map((s) => (
                    <button key={s} type="button" onClick={() => setQ(t(`assistant.suggest.${s}`))} className="t-small rounded-full border border-line px-3 py-1.5 hover:border-primary/50">
                      {t(`assistant.suggest.${s}`)}
                    </button>
                  ))}
                </div>
              </div>
            </div>
          ) : (
            <ul className="grid gap-3">
              {msgs.map((m) => (
                <li key={m.id} className={cn('max-w-[85%] whitespace-pre-line rounded-lg px-3.5 py-2.5 text-sm', m.role === 'user' ? 'justify-self-end bg-primary text-primary-contrast' : 'bg-surface-2 text-fg')}>
                  {m.content}
                </li>
              ))}
              {busy && <li className="t-small text-muted">{t('cs.typing')}</li>}
            </ul>
          )}
          <div ref={end} />
        </div>
        <form onSubmit={send} className="flex gap-2 border-t border-line p-3">
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('assistant.placeholder')} aria-label={t('assistant.placeholder')} className={inputCls} />
          <Button type="submit" loading={busy} aria-label={t('common.send')} icon={<Send className="size-4" aria-hidden />}>
            <span className="max-sm:sr-only">{t('common.send')}</span>
          </Button>
        </form>
      </Card>
    </>
  )
}

/* ---------------- Website builder (§19.5, PRD 6.7) ---------------- */
interface Site {
  id: number
  name: string
  subdomain: string
  status: string
  updated_at: string
  content?: { blocks: { id: string; type: string; title: string; body: string }[]; seo: { title: string; description: string } }
}
export function WebsitesPage() {
  const { t } = useTranslation()
  useDocTitle(t('features.website_builder'))
  const list = useApi<Site[]>('/app/websites')
  const nav = useNavigate()
  const [open, setOpen] = useState(false)
  const [step, setStep] = useState(1)
  const [f, setF] = useState({ name: '', business: '', subdomain: '', services: '' })
  const [err, setErr] = useState<Record<string, string[]>>({})
  return (
    <>
      <PageHeader title={t('features.website_builder')} subtitle={t('features_desc.website_builder')} actions={<Button icon={<Plus className="size-4" aria-hidden />} onClick={() => { setStep(1); setOpen(true) }}>{t('websites.new')}</Button>} />
      {!list.data ? (
        <Skeleton className="h-40" />
      ) : list.data.length === 0 ? (
        <EmptyState title={t('websites.empty')} body={t('websites.empty_body')} action={<Button onClick={() => setOpen(true)}>{t('websites.new')}</Button>} />
      ) : (
        <ul className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
          {list.data.map((s) => (
            <li key={s.id}>
              <Link to={`/app/websites/${s.id}`} className="grid gap-2 rounded-lg border border-line bg-surface p-4 hover:border-primary/50">
                <div className="flex items-center justify-between gap-2"><Globe className="size-5 text-primary" aria-hidden /><StatusChip status={s.status} /></div>
                <p className="font-semibold text-fg">{s.name}</p>
                <p className="t-mono truncate text-muted">{s.subdomain}.krafio.site</p>
                <p className="t-small text-muted">{relative(s.updated_at)}</p>
              </Link>
            </li>
          ))}
        </ul>
      )}
      <Modal
        open={open}
        onClose={() => setOpen(false)}
        size="wizard"
        dirty={!!f.name}
        title={t('websites.wizard', { step, total: 2 })}
        footer={
          step === 1 ? (
            <Button disabled={!f.name || !f.business} onClick={() => setStep(2)}>{t('common.next')}</Button>
          ) : (
            <>
              <Button variant="secondary" onClick={() => setStep(1)}>{t('common.back')}</Button>
              <Button
                onClick={async () => {
                  try {
                    const r = await http.post<{ data: Site }>('/app/websites', f)
                    setOpen(false)
                    nav(`/app/websites/${r.data.id}`)
                  } catch (e) {
                    if (e instanceof ApiError) setErr(e.fields)
                  }
                }}
              >
                {t('websites.create')}
              </Button>
            </>
          )
        }
      >
        {step === 1 ? (
          <div className="grid gap-4">
            <TextField label={t('websites.name')} required value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} />
            <TextArea label={t('websites.business')} required rows={3} value={f.business} onChange={(e) => setF({ ...f, business: e.target.value })} hint={t('websites.business_hint')} />
          </div>
        ) : (
          <div className="grid gap-4">
            <TextArea label={t('websites.services')} optional rows={3} value={f.services} onChange={(e) => setF({ ...f, services: e.target.value })} />
            <TextField label={t('websites.subdomain')} required value={f.subdomain} onChange={(e) => setF({ ...f, subdomain: e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '') })} hint={`${f.subdomain || 'nama-bisnis'}.krafio.site`} error={err.subdomain?.[0]} />
          </div>
        )}
      </Modal>
    </>
  )
}

export function WebsiteEditorPage() {
  const { id } = useParams()
  const { t } = useTranslation()
  const toast = useToast()
  const isDesktop = useIsDesktop()
  const site = useApi<Site>(`/app/websites/${id}`)
  useDocTitle(site.data?.name ?? t('features.website_builder'))
  const [content, setContent] = useState<Site['content'] | null>(null)
  const [sel, setSel] = useState<string | null>(null)
  const [device, setDevice] = useState<'desktop' | 'tablet' | 'mobile'>('desktop')
  const [saving, setSaving] = useState(false)
  const [dirty, setDirty] = useState(false)
  const [sheet, setSheet] = useState(false)
  const [issues, setIssues] = useState<string[] | null>(null)
  useEffect(() => {
    if (site.data?.content) {
      setContent(site.data.content)
      setSel(site.data.content.blocks[0]?.id ?? null)
    }
  }, [site.data])
  // Autosave (§19.5)
  useEffect(() => {
    if (!dirty || !content) return
    const h = window.setTimeout(async () => {
      setSaving(true)
      await http.put(`/app/websites/${id}`, { content }).catch(() => undefined)
      setSaving(false)
      setDirty(false)
    }, 1500)
    return () => window.clearTimeout(h)
  }, [content, dirty, id])
  if (!site.data || !content) return <Skeleton className="h-96" />
  const block = content.blocks.find((b) => b.id === sel)
  const update = (patch: Partial<{ title: string; body: string }>) => {
    setContent({ ...content, blocks: content.blocks.map((b) => (b.id === sel ? { ...b, ...patch } : b)) })
    setDirty(true)
  }
  const publish = async () => {
    try {
      await http.post(`/app/websites/${id}/publish`, { content })
      toast.push('success', t('websites.published'))
      setIssues(null)
      site.reload()
    } catch (e) {
      if (e instanceof ApiError) setIssues((e.data as any)?.issues ?? [e.message])
    }
  }
  const props = block ? (
    <div className="grid gap-4">
      <TextField label={t('websites.block_title')} value={block.title} onChange={(e) => update({ title: e.target.value })} />
      <TextArea label={t('websites.block_body')} rows={6} value={block.body} onChange={(e) => update({ body: e.target.value })} />
      <p className="t-label mt-2">SEO</p>
      <TextField label={t('websites.seo_title')} value={content.seo.title} onChange={(e) => { setContent({ ...content, seo: { ...content.seo, title: e.target.value } }); setDirty(true) }} hint={t('websites.chars', { n: content.seo.title.length, max: 60 })} />
      <TextArea label={t('websites.seo_desc')} rows={3} value={content.seo.description} onChange={(e) => { setContent({ ...content, seo: { ...content.seo, description: e.target.value } }); setDirty(true) }} hint={t('websites.chars', { n: content.seo.description.length, max: 160 })} />
    </div>
  ) : null
  const W = { desktop: 'max-w-full', tablet: 'max-w-[768px]', mobile: 'max-w-[390px]' }[device]
  return (
    <>
      <PageHeader
        title={site.data.name}
        subtitle={<span className="inline-flex flex-wrap items-center gap-2"><StatusChip status={site.data.status} /><span className="t-small text-muted">{saving ? t('websites.saving') : dirty ? t('websites.unsaved') : t('websites.saved')}</span></span>}
        crumbs={[{ label: t('features.website_builder'), to: '/app/websites' }, { label: site.data.name }]}
        actions={
          <>
            <Button variant="secondary" icon={<Save className="size-4" aria-hidden />} onClick={async () => { await http.put(`/app/websites/${id}`, { content }); setDirty(false); toast.push('success', t('common.saved')) }}>{t('common.save')}</Button>
            <Button icon={<Rocket className="size-4" aria-hidden />} onClick={publish}>{t('websites.publish')}</Button>
          </>
        }
      />
      {!isDesktop && <p className="t-small mb-3 flex items-center gap-2 rounded-md bg-info/10 p-2 text-info"><MonitorSmartphone className="size-4" aria-hidden />{t('common.wide_screen_hint')}</p>}
      {issues && (
        <div role="alert" className="mb-4 rounded-md border border-danger/40 bg-danger/10 p-3">
          <p className="t-label text-danger">{t('websites.publish_blocked')}</p>
          <ul className="t-small mt-1 list-disc pl-5 text-danger">{issues.map((i) => <li key={i}>{i}</li>)}</ul>
        </div>
      )}
      <div className="mb-3 flex justify-center">
        <Segmented label={t('websites.device')} value={device} onChange={setDevice} items={[{ value: 'desktop', label: <Monitor className="size-4" aria-label="Desktop" /> }, { value: 'tablet', label: <Tablet className="size-4" aria-label="Tablet" /> }, { value: 'mobile', label: <Smartphone className="size-4" aria-label="Mobile" /> }]} />
      </div>
      <div className={cn('grid gap-4', isDesktop && 'grid-cols-[200px_minmax(0,1fr)_300px]')}>
        {isDesktop && (
          <nav aria-label={t('home.visual.structure')} className="grid content-start gap-1">
            {content.blocks.map((b) => (
              <button key={b.id} type="button" onClick={() => setSel(b.id)} className={cn('rounded-md px-3 py-2 text-left text-sm', sel === b.id ? 'bg-primary-soft font-semibold text-primary' : 'hover:bg-surface-2')}>{t(`websites.block_types.${b.type}`)}</button>
            ))}
          </nav>
        )}
        {/* Kanvas: pratinjau hasil asli, bingkai netral, tidak ikut tema (§4.3) */}
        <div className="overflow-hidden rounded-lg border border-line bg-surface-2 p-3">
          <div className={cn('mx-auto bg-white text-[#0f172a]', W)} style={{ colorScheme: 'light' }}>
            {content.blocks.map((b) => (
              <section key={b.id} onClick={() => { setSel(b.id); if (!isDesktop) setSheet(true) }} className={cn('cursor-pointer border-2 px-6 py-8', sel === b.id ? 'border-[#4338CA]' : 'border-transparent', b.type === 'hero' && 'bg-[#1F3A4D] text-white')}>
                <h2 className={b.type === 'hero' ? 'text-3xl font-bold' : 'text-xl font-semibold'}>{b.title}</h2>
                <p className="mt-2 whitespace-pre-line text-sm opacity-80">{b.body}</p>
              </section>
            ))}
          </div>
        </div>
        {isDesktop && <aside className="rounded-lg border border-line bg-surface p-4">{props}</aside>}
      </div>
      {!isDesktop && (
        <div className="fixed inset-x-0 bottom-[calc(64px+var(--safe-b))] z-sticky flex gap-2 overflow-x-auto border-t border-line bg-surface p-2 md:bottom-0">
          {content.blocks.map((b) => (
            <button key={b.id} type="button" onClick={() => { setSel(b.id); setSheet(true) }} className={cn('t-label h-10 shrink-0 rounded-md px-3', sel === b.id ? 'bg-primary-soft text-primary' : 'bg-surface-2')}>{t(`websites.block_types.${b.type}`)}</button>
          ))}
        </div>
      )}
      <Modal open={sheet && !isDesktop} onClose={() => setSheet(false)} size="confirm" title={block ? t(`websites.block_types.${block.type}`) : ''}>{props}</Modal>
    </>
  )
}

/* ---------------- SEO tools (§19.6) ---------------- */
interface Audit {
  id: number
  url: string
  score: number
  ruleset_version: string
  status: string
  created_at: string
  findings: { id: number; rule: string; severity: 'critical' | 'warning' | 'info'; location: string; message: string; recommendation: string; state: 'open' | 'resolved' | 'ignored' }[]
}
export function SeoPage() {
  const { t } = useTranslation()
  useDocTitle(t('features.seo_basic'))
  const { openLocked } = useLocked()
  const audits = useApi<Audit[]>('/app/seo/audits')
  const [url, setUrl] = useState('')
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState<string | null>(null)
  const [sel, setSel] = useState<number | null>(null)
  const [sev, setSev] = useState<'critical' | 'warning' | 'info'>('critical')
  const cur = audits.data?.find((a) => a.id === sel) ?? audits.data?.[0]
  const run = async (e: FormEvent) => {
    e.preventDefault()
    setErr(null)
    setBusy(true)
    try {
      const r = await http.post<{ data: Audit }>('/app/seo/audits', { url })
      audits.reload()
      setSel(r.data.id)
    } catch (er) {
      if (er instanceof ApiError) {
        if (er.code === 'feature_locked') openLocked('seo.basic')
        else if (er.code === 'quota_exceeded') openLocked('seo.basic', 'quota')
        else setErr(er.fields.url?.[0] ?? er.message)
      }
    } finally {
      setBusy(false)
    }
  }
  const setState = async (fid: number, state: string) => {
    await http.patch(`/app/seo/findings/${fid}`, { state })
    audits.reload()
  }
  return (
    <>
      <PageHeader title={t('features.seo_basic')} subtitle={t('seo.subtitle')} />
      <Card>
        <form onSubmit={run} className="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
          <TextField label={t('seo.url')} type="url" inputMode="url" required value={url} onChange={(e) => setUrl(e.target.value)} placeholder="https://contoh.com" error={err} />
          <Button type="submit" loading={busy}>{t('seo.run')}</Button>
        </form>
      </Card>
      <div className="mt-6">
        {!audits.data ? (
          <Skeleton className="h-60" />
        ) : !cur ? (
          <EmptyState title={t('seo.empty')} body={t('seo.empty_body')} />
        ) : (
          <div className="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
            <div className="grid content-start gap-3">
              <Card>
                <p className="t-small text-muted">{t('seo.score_title')}</p>
                <p className="text-5xl font-bold tabular-nums text-fg">{cur.score}</p>
                <p className="t-small mt-1 text-muted">{t('seo.ruleset', { v: cur.ruleset_version })}</p>
                <p className="t-small mt-2 text-muted">{t('seo.score_note')}</p>
              </Card>
              {audits.data.length > 1 && (
                <ul className="grid gap-1">
                  {audits.data.map((a) => (
                    <li key={a.id}>
                      <button type="button" onClick={() => setSel(a.id)} className={cn('w-full rounded-md px-3 py-2 text-left', cur.id === a.id ? 'bg-primary-soft' : 'hover:bg-surface-2')}>
                        <span className="block truncate text-sm font-medium">{a.url}</span>
                        <span className="t-small text-muted">{relative(a.created_at)} — {a.score}</span>
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
            <div>
              <p className="t-mono mb-2 truncate text-muted">{cur.url}</p>
              <Tabs
                label={t('seo.findings')}
                value={sev}
                onChange={setSev}
                items={(['critical', 'warning', 'info'] as const).map((s) => ({ value: s, label: t(`seo.sev_${s}`), count: cur.findings.filter((f) => f.severity === s && f.state === 'open').length }))}
              />
              <div className="mt-4">
                {cur.findings.filter((f) => f.severity === sev).length === 0 ? (
                  <EmptyState title={t('seo.no_findings')} />
                ) : (
                  <Accordion
                    items={cur.findings
                      .filter((f) => f.severity === sev)
                      .map((f) => ({
                        id: f.id,
                        title: (
                          <span className="flex flex-1 items-center gap-2">
                            <span className="flex-1">{f.message}</span>
                            <StatusChip status={f.state === 'open' ? 'pending' : f.state} label={t(`seo.state_${f.state}`)} />
                          </span>
                        ),
                        body: (
                          <div className="grid gap-2">
                            <p><span className="font-semibold text-fg">{t('seo.location')}:</span> {f.location}</p>
                            <p><span className="font-semibold text-fg">{t('seo.recommendation')}:</span> {f.recommendation}</p>
                            <div className="flex gap-2">
                              {f.state !== 'resolved' && <Button size="sm" variant="secondary" onClick={() => setState(f.id, 'resolved')}>{t('seo.mark_resolved')}</Button>}
                              {f.state !== 'ignored' && <Button size="sm" variant="ghost" onClick={() => setState(f.id, 'ignored')}>{t('seo.ignore')}</Button>}
                              {f.state !== 'open' && <Button size="sm" variant="ghost" onClick={() => setState(f.id, 'open')}>{t('seo.reopen')}</Button>}
                            </div>
                          </div>
                        ),
                      }))}
                  />
                )}
              </div>
            </div>
          </div>
        )}
      </div>
    </>
  )
}

/* ---------------- Integrasi sosial (§19.7, PRD 6.11) ---------------- */
interface SocialAcc {
  platform: string
  available: boolean
  status: 'connected' | 'disconnected' | 'expired' | 'not_available'
  account_name: string | null
  expires_at: string | null
  permissions: string[]
}
export function SocialPage() {
  const { t } = useTranslation()
  useDocTitle(t('features.social_integrations'))
  const list = useApi<SocialAcc[]>('/app/social')
  const [ask, setAsk] = useState<SocialAcc | null>(null)
  const [disc, setDisc] = useState<SocialAcc | null>(null)
  const toast = useToast()
  return (
    <>
      <PageHeader title={t('features.social_integrations')} subtitle={t('social.subtitle')} />
      {!list.data ? (
        <Skeleton className="h-60" />
      ) : (
        <ul className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
          {list.data.map((a) => (
            <li key={a.platform} className="grid content-start gap-3 rounded-lg border border-line bg-surface p-4">
              <div className="flex items-center justify-between gap-2">
                <p className="t-h3">{t(`platforms.${a.platform}`)}</p>
                <StatusChip status={a.status} label={t(`social.st_${a.status}`)} />
              </div>
              {a.account_name && <p className="t-small text-fg">{a.account_name}</p>}
              {a.expires_at && <p className="t-small text-muted">{t('social.token_until', { date: dateTime(a.expires_at, false) })}</p>}
              <div className="flex flex-wrap gap-2">
                {!a.available ? (
                  <Badge>{t('social.not_available')}</Badge>
                ) : a.status === 'connected' ? (
                  <>
                    <Button size="sm" variant="secondary" icon={<RefreshCw className="size-4" aria-hidden />} onClick={() => setAsk(a)}>{t('social.reconnect')}</Button>
                    <Button size="sm" variant="ghost" icon={<Unlink className="size-4" aria-hidden />} onClick={() => setDisc(a)}>{t('social.disconnect')}</Button>
                  </>
                ) : (
                  <Button size="sm" icon={<Link2 className="size-4" aria-hidden />} onClick={() => setAsk(a)}>{a.status === 'expired' ? t('social.reconnect') : t('social.connect')}</Button>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}
      {/* Layar izin dalam bahasa sederhana sebelum OAuth resmi (§11.4) */}
      <Modal
        open={!!ask}
        onClose={() => setAsk(null)}
        size="info"
        title={ask ? t('social.permission_title', { platform: t(`platforms.${ask.platform}`) }) : ''}
        footer={
          <Button
            onClick={async () => {
              try {
                const r = await http.post<{ data: { redirect_url: string | null } }>(`/app/social/${ask!.platform}/connect`)
                if (r.data.redirect_url) window.location.href = r.data.redirect_url
              } catch (e) {
                toast.push('error', e instanceof ApiError ? e.message : t('errors.server_body'))
                setAsk(null)
              }
            }}
          >
            {t('social.continue_oauth')}
          </Button>
        }
      >
        <p className="t-body text-muted">{t('social.permission_body')}</p>
        <ul className="mt-3 grid gap-1.5">
          {ask?.permissions.map((p) => <li key={p} className="t-small rounded-md bg-surface-2 px-3 py-2 text-fg">{t(`social.perm.${p}`, { defaultValue: p })}</li>)}
        </ul>
      </Modal>
      <ConfirmDialog
        open={!!disc}
        onClose={() => setDisc(null)}
        destructive
        title={disc ? t('social.disconnect_title', { platform: t(`platforms.${disc.platform}`) }) : ''}
        confirmLabel={t('social.disconnect')}
        onConfirm={async () => {
          await http.post(`/app/social/${disc!.platform}/disconnect`)
          list.reload()
        }}
      />
    </>
  )
}
