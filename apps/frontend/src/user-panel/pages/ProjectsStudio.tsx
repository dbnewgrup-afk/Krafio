import { useEffect, useMemo, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { Link, useNavigate } from 'react-router-dom'
import { AnimatePresence, LayoutGroup, motion, useReducedMotion } from 'framer-motion'
import { useTranslation } from 'react-i18next'
import { Archive, ArchiveRestore, Check, Copy, ExternalLink, FolderOpen, LayoutGrid, List, Loader2, Pencil, Pin, PinOff, Plus, Search, Trash2, X } from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import type { TemplateItem } from '@/api-client/types'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { useIsMobile } from '@/shared/hooks/useMediaQuery'
import { useBackClose, useFocusTrap, useScrollLock } from '@/shared/hooks/useOverlay'
import { useToast } from '@/shared/context/ToastContext'
import { useLocked } from '@/shared/layout/Locked'
import { PageHeader, Segmented, ChipRow } from '@/shared/ui/Misc'
import { Button } from '@/shared/ui/Button'
import { ConfirmDialog } from '@/shared/ui/ConfirmDialog'
import { Modal } from '@/shared/ui/Modal'
import { TextField, TextArea, inputCls } from '@/shared/ui/Field'
import { StatusChip } from '@/shared/ui/Status'
import { EmptyState, Skeleton } from '@/shared/ui/States'
import { TemplatePreview } from '@/public-site/components/TemplateCard'
import { relative, number } from '@/shared/lib/format'
import { cn } from '@/shared/lib/cn'

export interface ProjectRow {
  id: number
  name: string
  type: 'photo' | 'video' | 'website' | 'social' | 'business'
  status: 'active' | 'archived'
  updated_at: string
  created_at?: string
  description: string | null
  tags: string[]
  pinned: boolean
  jobs_count: number
  template: TemplateItem | null
}
type Draft = Omit<ProjectRow, 'id' | 'updated_at' | 'jobs_count'> & { id?: number; template_id: number | null }
const TYPES = ['photo', 'video', 'website', 'social', 'business'] as const
const TYPE_TINT: Record<ProjectRow['type'], string> = { photo: 'bg-[var(--preview-1)]', video: 'bg-[var(--preview-4)]', website: 'bg-[var(--preview-3)]', social: 'bg-primary-soft', business: 'bg-surface-2' }

/** Kartu proyek — dipakai di halaman Proyek, ringkasan dashboard, dan pratinjau langsung editor. */
export function ProjectCard({ p, onField, highlight, compact, className }: { p: Pick<ProjectRow, 'name' | 'type' | 'status' | 'description' | 'tags' | 'pinned' | 'template'> & { updated_at?: string; jobs_count?: number }; onField?: (f: 'name' | 'cover' | 'tags') => void; highlight?: string | null; compact?: boolean; className?: string }) {
  const { t } = useTranslation()
  const flash = (f: string) => (highlight === f ? 'animate-[k-flash_0.9s_ease]' : '')
  const field = (f: 'name' | 'cover' | 'tags') => (onField ? { role: 'button' as const, tabIndex: 0, onClick: (e: React.MouseEvent) => { e.preventDefault(); onField(f) }, onKeyDown: (e: React.KeyboardEvent) => e.key === 'Enter' && onField(f), className: 'cursor-text rounded-sm hover:outline hover:outline-1 hover:outline-dashed hover:outline-primary/60' } : {})
  return (
    <div className={cn('grid h-full content-start gap-2 rounded-xl border border-line bg-surface p-3', p.status === 'archived' && 'opacity-70', className)}>
      <div {...field('cover')} className={cn('relative overflow-hidden rounded-lg', flash('cover'))}>
        {p.template ? (
          <TemplatePreview item={p.template} frame={16 / 9} />
        ) : (
          <span className={cn('grid aspect-video place-items-center rounded-lg text-fg/60', TYPE_TINT[p.type])}><FolderOpen className="size-7" aria-hidden /></span>
        )}
        {p.pinned && <span className="absolute left-2 top-2 grid size-7 place-items-center rounded-full bg-surface/95 text-primary shadow-k-sm"><Pin className="size-3.5" aria-label={t('pstudio.pinned')} /></span>}
      </div>
      <p {...field('name')} className={cn('truncate font-semibold text-fg', flash('name'))}>{p.name || t('pstudio.untitled')}</p>
      {!compact && p.description && <p className="t-small line-clamp-2 text-muted">{p.description}</p>}
      <p className="t-small flex flex-wrap items-center gap-x-2 gap-y-1 text-muted">
        <span>{t(`templates.types.${p.type}`)}</span>
        {p.updated_at && <span>· {relative(p.updated_at)}</span>}
        {p.jobs_count !== undefined && <span>· {t('pstudio.jobs', { n: number(p.jobs_count) })}</span>}
      </p>
      {!compact && p.tags.length > 0 && (
        <p {...field('tags')} className={cn('flex flex-wrap gap-1', flash('tags'))}>
          {p.tags.map((x) => <span key={x} className="rounded-full bg-surface-2 px-2 py-0.5 text-[11.5px] text-fg">#{x}</span>)}
        </p>
      )}
    </div>
  )
}

/**
 * Proyek pengguna dengan konsep WYSIWYG: buat/ubah/hapus, hasilnya langsung terlihat sebagai kartu yang sama
 * dengan di dashboard. Hapus dapat di-Undo 6 dtk, duplikat muncul di sebelah aslinya, sematkan ke atas.
 */
export function ProjectsStudio() {
  const { t } = useTranslation()
  useDocTitle(t('user.nav.projects'))
  const toast = useToast()
  const reduced = useReducedMotion()
  const nav = useNavigate()
  const [rows, setRows] = useState<ProjectRow[] | null>(null)
  const [q, setQ] = useState('')
  const [filter, setFilter] = useState<'all' | 'active' | 'pinned' | 'archived'>('active')
  const [type, setType] = useState<'all' | ProjectRow['type']>('all')
  const [sort, setSort] = useState<'recent' | 'name' | 'jobs'>('recent')
  const [view, setView] = useState<'grid' | 'list'>('grid')
  const [editing, setEditing] = useState<Draft | null>(null)
  const [confirmDel, setConfirmDel] = useState<ProjectRow | null>(null)
  const [selected, setSelected] = useState<number[]>([])

  const load = () => http.get<{ data: ProjectRow[] }>('/app/projects').then((r) => setRows(r.data)).catch(() => setRows([]))
  useEffect(() => {
    void load()
  }, [])

  const list = useMemo(() => {
    let xs = rows ?? []
    if (filter === 'active') xs = xs.filter((x) => x.status === 'active')
    if (filter === 'archived') xs = xs.filter((x) => x.status === 'archived')
    if (filter === 'pinned') xs = xs.filter((x) => x.pinned)
    if (type !== 'all') xs = xs.filter((x) => x.type === type)
    if (q) xs = xs.filter((x) => `${x.name} ${x.description ?? ''} ${x.tags.join(' ')}`.toLowerCase().includes(q.toLowerCase()))
    const by = sort === 'name' ? (a: ProjectRow, b: ProjectRow) => a.name.localeCompare(b.name) : sort === 'jobs' ? (a: ProjectRow, b: ProjectRow) => b.jobs_count - a.jobs_count : (a: ProjectRow, b: ProjectRow) => +new Date(b.updated_at) - +new Date(a.updated_at)
    return [...xs].sort((a, b) => Number(b.pinned) - Number(a.pinned) || by(a, b))
  }, [rows, filter, type, q, sort])

  const patch = async (p: ProjectRow, body: Partial<Draft>) => {
    const prev = rows
    setRows((xs) => xs?.map((x) => (x.id === p.id ? { ...x, ...(body as Partial<ProjectRow>) } : x)) ?? xs)
    try {
      const r = await http.put<{ data: ProjectRow }>(`/app/projects/${p.id}`, body)
      setRows((xs) => xs?.map((x) => (x.id === p.id ? r.data : x)) ?? xs)
    } catch (e) {
      setRows(prev)
      toast.push('error', e instanceof ApiError ? e.message : t('errors.server_title'))
    }
  }

  const remove = async (p: ProjectRow) => {
    setConfirmDel(null)
    setRows((xs) => xs?.filter((x) => x.id !== p.id) ?? xs)
    try {
      await http.del(`/app/projects/${p.id}`)
      toast.push('warning', t('pstudio.deleted', { name: p.name }), {
        label: t('studio_tpl.undo'),
        onClick: async () => {
          const r = await http.post<{ data: ProjectRow }>(`/app/projects/${p.id}/restore`)
          setRows((xs) => (xs ? [r.data, ...xs] : xs))
        },
      })
    } catch {
      void load()
      toast.push('error', t('errors.server_title'))
    }
  }

  const duplicate = async (p: ProjectRow) => {
    const r = await http.post<{ data: ProjectRow }>(`/app/projects/${p.id}/duplicate`)
    setRows((xs) => {
      if (!xs) return xs
      const out = [...xs]
      out.splice(out.findIndex((x) => x.id === p.id) + 1, 0, r.data)
      return out
    })
    toast.push('success', t('pstudio.duplicated'))
  }

  const bulk = async (status: 'active' | 'archived') => {
    await Promise.all(selected.map((id) => http.put(`/app/projects/${id}`, { status })))
    setSelected([])
    void load()
  }

  const blank: Draft = { name: '', type: 'photo', status: 'active', description: '', tags: [], pinned: false, template: null, template_id: null }

  return (
    <>
      <PageHeader title={t('user.nav.projects')} subtitle={t('projects.subtitle')} actions={<Button icon={<Plus className="size-4" aria-hidden />} onClick={() => setEditing(blank)}>{t('projects.new')}</Button>} />
      <div className="grid gap-3">
        <div className="flex flex-wrap items-center gap-2">
          <div className="relative min-w-[200px] flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" aria-hidden />
            <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('pstudio.search')} aria-label={t('pstudio.search')} className={cn(inputCls, 'h-10 pl-9 lg:h-10')} />
          </div>
          <select value={type} onChange={(e) => setType(e.target.value as typeof type)} aria-label={t('projects.type')} className={cn(inputCls.replace('w-full ', ''), 'h-10 w-auto lg:h-10')}>
            <option value="all">{t('table.all')}</option>
            {TYPES.map((x) => <option key={x} value={x}>{t(`templates.types.${x}`)}</option>)}
          </select>
          <select value={sort} onChange={(e) => setSort(e.target.value as typeof sort)} aria-label={t('studio_tpl.sort')} className={cn(inputCls.replace('w-full ', ''), 'h-10 w-auto lg:h-10')}>
            <option value="recent">{t('pstudio.sort_recent')}</option>
            <option value="name">{t('pstudio.sort_name')}</option>
            <option value="jobs">{t('pstudio.sort_jobs')}</option>
          </select>
          <Segmented label={t('studio_tpl.view')} value={view} onChange={setView} items={[{ value: 'grid', label: <LayoutGrid className="size-4" aria-label={t('studio_tpl.v_grid')} /> }, { value: 'list', label: <List className="size-4" aria-label={t('studio_tpl.v_list')} /> }]} />
        </div>
        <ChipRow label={t('pstudio.filter')} value={filter} onChange={setFilter} items={(['active', 'pinned', 'archived', 'all'] as const).map((x) => ({ value: x, label: t(`pstudio.f.${x}`) }))} />
        <AnimatePresence>
          {selected.length > 0 && (
            <motion.div initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: 'auto' }} exit={{ opacity: 0, height: 0 }} className="flex flex-wrap items-center gap-2 overflow-hidden rounded-lg bg-primary-soft px-3 py-2">
              <span className="t-label mr-auto text-primary">{t('studio_tpl.selected', { n: selected.length })}</span>
              <Button size="sm" variant="secondary" icon={<Archive className="size-4" aria-hidden />} onClick={() => void bulk('archived')}>{t('studio_tpl.archive')}</Button>
              <Button size="sm" variant="secondary" icon={<ArchiveRestore className="size-4" aria-hidden />} onClick={() => void bulk('active')}>{t('pstudio.restore')}</Button>
              <button type="button" onClick={() => setSelected([])} aria-label={t('common.cancel')} className="grid size-8 place-items-center rounded-md hover:bg-surface"><X className="size-4" aria-hidden /></button>
            </motion.div>
          )}
        </AnimatePresence>
      </div>

      <div className="mt-4">
        {!rows ? (
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">{Array.from({ length: 8 }).map((_, i) => <div key={i} className="grid gap-2 rounded-xl border border-line p-3"><Skeleton className="aspect-video" /><Skeleton className="h-4 w-2/3" /><Skeleton className="h-3 w-1/3" /></div>)}</div>
        ) : list.length === 0 ? (
          <EmptyState kind={q || filter !== 'active' || type !== 'all' ? 'filter' : 'empty'} title={q || type !== 'all' ? t('table.no_results') : t('projects.empty')} body={t('projects.empty_body')} action={<Button onClick={() => setEditing(blank)}>{t('projects.first')}</Button>} />
        ) : (
          <LayoutGroup>
            <motion.ul className={cn('grid gap-3', view === 'grid' ? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4' : 'grid-cols-1')} initial="hidden" animate="show" variants={{ hidden: {}, show: { transition: { staggerChildren: reduced ? 0 : 0.05 } } }}>
              <AnimatePresence mode="popLayout">
                {list.map((p) => {
                  const checked = selected.includes(p.id)
                  return (
                    <motion.li key={p.id} layout={!reduced} layoutId={reduced ? undefined : `prj-${p.id}`} variants={{ hidden: { opacity: 0, y: 12 }, show: { opacity: 1, y: 0 } }} exit={{ opacity: 0, scale: 0.85 }} transition={{ type: 'spring', stiffness: 260, damping: 28 }} className="group relative" whileHover={reduced ? undefined : { y: -4 }}>
                      {view === 'grid' ? (
                        <>
                          <Link to={`/app/projects/${p.id}`} className="block h-full rounded-xl outline-none transition-shadow focus-visible:ring-2 focus-visible:ring-primary group-hover:shadow-k-md">
                            <ProjectCard p={p} />
                          </Link>
                          <div className="pointer-events-none absolute right-5 top-5 flex gap-1 opacity-0 transition-opacity group-focus-within:pointer-events-auto group-focus-within:opacity-100 group-hover:pointer-events-auto group-hover:opacity-100">
                            <Act icon={Pencil} label={t('studio_tpl.edit')} onClick={() => setEditing({ ...p, template_id: p.template?.id ?? null })} />
                            <Act icon={p.pinned ? PinOff : Pin} label={p.pinned ? t('pstudio.unpin') : t('pstudio.pin')} onClick={() => void patch(p, { pinned: !p.pinned })} />
                            <Act icon={Copy} label={t('studio_tpl.duplicate')} onClick={() => void duplicate(p)} />
                            <Act icon={p.status === 'archived' ? ArchiveRestore : Archive} label={p.status === 'archived' ? t('pstudio.restore') : t('studio_tpl.archive')} onClick={() => void patch(p, { status: p.status === 'archived' ? 'active' : 'archived' })} />
                            <Act icon={Trash2} label={t('studio_tpl.delete')} danger onClick={() => setConfirmDel(p)} />
                          </div>
                          <label className={cn('absolute left-5 top-5 grid size-8 place-items-center rounded-md bg-surface/95 shadow-k-sm', !checked && 'opacity-0 group-focus-within:opacity-100 group-hover:opacity-100')}>
                            <input type="checkbox" checked={checked} onChange={(e) => setSelected(e.target.checked ? [...selected, p.id] : selected.filter((x) => x !== p.id))} aria-label={t('studio_tpl.select', { title: p.name })} className="size-4 accent-[var(--primary)]" />
                          </label>
                        </>
                      ) : (
                        <div className="flex items-center gap-3 rounded-xl border border-line bg-surface p-2.5">
                          <input type="checkbox" checked={checked} onChange={(e) => setSelected(e.target.checked ? [...selected, p.id] : selected.filter((x) => x !== p.id))} aria-label={t('studio_tpl.select', { title: p.name })} className="size-4 shrink-0 accent-[var(--primary)]" />
                          <div className="w-24 shrink-0">{p.template ? <TemplatePreview item={p.template} frame={16 / 9} /> : <span className={cn('grid aspect-video place-items-center rounded-lg', TYPE_TINT[p.type])}><FolderOpen className="size-5 text-fg/60" aria-hidden /></span>}</div>
                          <Link to={`/app/projects/${p.id}`} className="min-w-0 flex-1">
                            <p className="truncate text-sm font-semibold text-fg">{p.pinned && <Pin className="mr-1 inline size-3.5 text-primary" aria-hidden />}{p.name}</p>
                            <p className="t-small truncate text-muted">{t(`templates.types.${p.type}`)} · {relative(p.updated_at)} · {t('pstudio.jobs', { n: p.jobs_count })}</p>
                          </Link>
                          {p.status === 'archived' && <StatusChip status="archived" />}
                          <Act icon={Pencil} label={t('studio_tpl.edit')} plain onClick={() => setEditing({ ...p, template_id: p.template?.id ?? null })} />
                          <Act icon={Trash2} label={t('studio_tpl.delete')} plain danger onClick={() => setConfirmDel(p)} />
                        </div>
                      )}
                    </motion.li>
                  )
                })}
              </AnimatePresence>
            </motion.ul>
          </LayoutGroup>
        )}
      </div>
      <LayoutGroup>
        <AnimatePresence>
          {editing && (
            <ProjectEditor
              key="ed"
              initial={editing}
              onClose={() => setEditing(null)}
              onSaved={(r, isNew) => {
                setRows((xs) => (xs ? (isNew ? [r, ...xs] : xs.map((x) => (x.id === r.id ? r : x))) : xs))
                setEditing(null)
                toast.push('success', isNew ? t('pstudio.created') : t('studio_tpl.saved'), isNew ? { label: t('pstudio.open'), onClick: () => nav(`/app/projects/${r.id}`) } : undefined)
              }}
            />
          )}
        </AnimatePresence>
      </LayoutGroup>
      <ConfirmDialog open={!!confirmDel} onClose={() => setConfirmDel(null)} destructive title={t('pstudio.confirm_delete', { name: confirmDel?.name ?? '' })} body={t('pstudio.confirm_delete_body')} confirmLabel={t('studio_tpl.delete')} onConfirm={() => { if (confirmDel) void remove(confirmDel) }} />
    </>
  )
}

function Act({ icon: Icon, label, onClick, danger, plain }: { icon: typeof Pencil; label: string; onClick: () => void; danger?: boolean; plain?: boolean }) {
  return (
    <motion.button type="button" whileTap={{ scale: 0.92 }} onClick={(e) => { e.preventDefault(); e.stopPropagation(); onClick() }} aria-label={label} title={label} className={cn('grid size-8 place-items-center rounded-md', plain ? 'hover:bg-surface-2' : 'bg-surface/95 shadow-k-sm hover:bg-surface', danger ? 'text-danger' : 'text-fg')}>
      <Icon className="size-4" aria-hidden />
    </motion.button>
  )
}

function ProjectEditor({ initial, onClose, onSaved }: { initial: Draft; onClose: () => void; onSaved: (r: ProjectRow, isNew: boolean) => void }) {
  const { t } = useTranslation()
  const reduced = useReducedMotion()
  const isMobile = useIsMobile()
  const { openLocked } = useLocked()
  const [d, setD] = useState<Draft>(initial)
  const [dirty, setDirty] = useState(false)
  const [busy, setBusy] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [changed, setChanged] = useState<string | null>(null)
  const [tab, setTab] = useState<'form' | 'preview'>('form')
  const [tpls, setTpls] = useState<TemplateItem[] | null>(null)
  const [tagInput, setTagInput] = useState('')
  const [leave, setLeave] = useState(false)
  const ref = useRef<HTMLDivElement>(null)
  const refs = useRef<Record<string, HTMLElement | null>>({})
  const [openedAt] = useState(() => new Date().toISOString())
  useScrollLock(true)
  useFocusTrap(ref, true)
  const tryClose = () => (dirty ? setLeave(true) : onClose())
  useBackClose(true, tryClose)

  useEffect(() => {
    http.get<{ data: TemplateItem[] }>('/public/templates', { type: d.type, per_page: 24 }).then((r) => setTpls(r.data)).catch(() => setTpls([]))
  }, [d.type])

  const set = <K extends keyof Draft>(k: K, v: Draft[K], f?: string) => {
    setD((x) => ({ ...x, [k]: v }))
    setDirty(true)
    if (f) {
      setChanged(null)
      window.setTimeout(() => setChanged(f), 0)
    }
  }
  const focusField = (f: string) => {
    if (isMobile) setTab('form')
    window.setTimeout(() => {
      const el = refs.current[f]
      el?.scrollIntoView({ block: 'center' })
      ;(el?.querySelector('input,textarea,button') as HTMLElement | null)?.focus()
    }, 40)
  }

  const save = async () => {
    setBusy(true)
    setErrors({})
    try {
      const body = { name: d.name, type: d.type, template_id: d.template_id, description: d.description, tags: d.tags, status: d.status, pinned: d.pinned }
      const r = d.id ? await http.put<{ data: ProjectRow }>(`/app/projects/${d.id}`, body) : await http.post<{ data: ProjectRow }>('/app/projects', body).then(async (c) => (d.description || d.tags.length || d.pinned ? http.put<{ data: ProjectRow }>(`/app/projects/${c.data.id}`, body) : c))
      onSaved(r.data, !d.id)
    } catch (e) {
      if (e instanceof ApiError) {
        if (e.code === 'feature_locked') openLocked('templates.premium')
        setErrors(e.fields)
      }
    } finally {
      setBusy(false)
    }
  }

  const preview = (
    <div className="grid content-start gap-3">
      <p className="t-label text-muted">{t('studio_tpl.preview')}</p>
      <motion.div layoutId={d.id && !reduced ? `prj-${d.id}` : undefined} className="mx-auto w-full max-w-[300px]">
        <ProjectCard p={{ ...d, updated_at: openedAt }} onField={(f) => focusField(f)} highlight={changed} />
      </motion.div>
      <p className="t-small text-muted">{t('studio_tpl.pv.hint')}</p>
    </div>
  )

  return createPortal(
    <>
      <motion.div className="fixed inset-0 z-modal bg-[var(--overlay-dark)]" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} onClick={tryClose} aria-hidden />
      <motion.div ref={ref} role="dialog" aria-modal="true" aria-labelledby="prj-ed-t" className="fixed inset-y-0 right-0 z-modal flex w-full max-w-[920px] flex-col bg-surface shadow-k-lg md:rounded-l-2xl" initial={reduced ? { opacity: 0 } : { x: '100%' }} animate={reduced ? { opacity: 1 } : { x: 0 }} exit={reduced ? { opacity: 0 } : { x: '100%' }} transition={{ type: 'spring', stiffness: 260, damping: 32 }} onKeyDown={(e) => e.key === 'Escape' && tryClose()}>
        <header className="flex items-center gap-3 border-b border-line px-4 py-3 md:px-6">
          <h2 id="prj-ed-t" className="t-h3 min-w-0 flex-1 truncate">{d.id ? t('pstudio.edit_title', { name: d.name }) : t('projects.new')}</h2>
          {d.id && <Link to={`/app/projects/${d.id}`} className="t-label inline-flex items-center gap-1 text-primary hover:underline"><ExternalLink className="size-4" aria-hidden />{t('pstudio.open')}</Link>}
          <button type="button" onClick={tryClose} aria-label={t('common.close')} className="grid size-10 place-items-center rounded-md hover:bg-surface-2"><X className="size-5" aria-hidden /></button>
        </header>
        {isMobile && <div className="border-b border-line px-4 py-2"><Segmented label={t('studio_tpl.preview')} value={tab} onChange={setTab} items={[{ value: 'form', label: t('studio_tpl.tab_form') }, { value: 'preview', label: t('studio_tpl.preview') }]} /></div>}
        <div className="grid min-h-0 flex-1 overflow-hidden md:grid-cols-[minmax(0,1fr)_340px]">
          {(!isMobile || tab === 'form') && (
            <div className="grid min-h-0 content-start gap-5 overflow-y-auto px-4 py-5 md:px-6">
              <div ref={(el) => { refs.current.name = el }}>
                <TextField label={t('projects.name')} required value={d.name} onChange={(e) => set('name', e.target.value, 'name')} error={errors.name?.[0]} maxLength={120} placeholder={t('pstudio.name_ph')} />
              </div>
              <div className="grid gap-2">
                <p className="t-label">{t('projects.type')}</p>
                <div className="snap-row -mx-1 gap-1.5 px-1">
                  {TYPES.map((x) => <button key={x} type="button" aria-pressed={d.type === x} onClick={() => { set('type', x, 'cover'); set('template_id', null); set('template', null) }} className={cn('t-label h-9 whitespace-nowrap rounded-full border px-3.5', d.type === x ? 'border-primary bg-primary text-primary-contrast' : 'border-line hover:border-primary/50')}>{t(`templates.types.${x}`)}</button>)}
                </div>
              </div>
              <TextArea label={t('pstudio.description')} optional rows={3} value={d.description ?? ''} onChange={(e) => set('description', e.target.value)} maxLength={300} hint={`${(d.description ?? '').length}/300`} />
              <div ref={(el) => { refs.current.tags = el }} className="grid gap-2">
                <p className="t-label">{t('pstudio.tags')} <span className="text-muted">({t('common.optional')})</span></p>
                <div className="flex flex-wrap items-center gap-1.5">
                  {d.tags.map((x) => <span key={x} className="inline-flex h-8 items-center gap-1 rounded-full bg-surface-2 pl-3 pr-1 text-[13px]">#{x}<button type="button" onClick={() => set('tags', d.tags.filter((y) => y !== x), 'tags')} aria-label={t('common.remove')} className="grid size-6 place-items-center rounded-full hover:bg-surface"><X className="size-3" aria-hidden /></button></span>)}
                  {d.tags.length < 6 && <form onSubmit={(e) => { e.preventDefault(); const v = tagInput.trim().toLowerCase().slice(0, 24); if (v && !d.tags.includes(v)) set('tags', [...d.tags, v], 'tags'); setTagInput('') }}><input value={tagInput} onChange={(e) => setTagInput(e.target.value)} placeholder={t('pstudio.tag_ph')} aria-label={t('pstudio.tags')} className="h-8 w-40 rounded-full border border-dashed border-line bg-transparent px-3 text-[13px] focus:border-primary focus:outline-none" /></form>}
                </div>
              </div>
              <div ref={(el) => { refs.current.cover = el }} className="grid gap-2">
                <p className="t-label">{t('pstudio.template')} <span className="text-muted">({t('common.optional')})</span></p>
                {!tpls ? <Skeleton className="h-28" /> : (
                  <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    <button type="button" aria-pressed={!d.template_id} onClick={() => { set('template_id', null, 'cover'); set('template', null) }} className={cn('grid aspect-square place-items-center rounded-lg border-2 text-[12px] text-muted', !d.template_id ? 'border-primary bg-primary-soft text-primary' : 'border-dashed border-line')}>{t('pstudio.no_template')}</button>
                    {tpls.map((tp) => (
                      <button key={tp.id} type="button" aria-pressed={d.template_id === tp.id} aria-label={tp.title} onClick={() => { set('template_id', tp.id, 'cover'); set('template', tp) }} className={cn('relative rounded-lg border-2 p-0.5', d.template_id === tp.id ? 'border-primary' : 'border-transparent hover:border-line')}>
                        <TemplatePreview item={tp} frame={1} />
                        {d.template_id === tp.id && <span className="absolute right-1 top-1 grid size-5 place-items-center rounded-full bg-primary text-primary-contrast"><Check className="size-3" aria-hidden /></span>}
                      </button>
                    ))}
                  </div>
                )}
              </div>
              <div className="flex flex-wrap gap-4">
                <label className="inline-flex items-center gap-2 text-sm"><input type="checkbox" checked={d.pinned} onChange={(e) => set('pinned', e.target.checked)} className="size-4 accent-[var(--primary)]" />{t('pstudio.pin')}</label>
                {d.id && <label className="inline-flex items-center gap-2 text-sm"><input type="checkbox" checked={d.status === 'archived'} onChange={(e) => set('status', e.target.checked ? 'archived' : 'active')} className="size-4 accent-[var(--primary)]" />{t('studio_tpl.archive')}</label>}
              </div>
            </div>
          )}
          {(!isMobile || tab === 'preview') && <div className="min-h-0 overflow-y-auto border-line bg-bg-subtle/60 px-4 py-5 md:border-l">{preview}</div>}
        </div>
        <footer className="flex justify-end gap-2 border-t border-line px-4 py-3 pb-[calc(12px+var(--safe-b))] md:px-6">
          <Button variant="secondary" onClick={tryClose}>{t('common.cancel')}</Button>
          <Button onClick={() => void save()} loading={busy} disabled={!d.name.trim()} icon={busy ? <Loader2 className="size-4" aria-hidden /> : undefined}>{d.id ? t('studio_tpl.save') : t('projects.create')}</Button>
        </footer>
      </motion.div>
      <Modal open={leave} onClose={() => setLeave(false)} size="confirm" level={2} title={t('studio_tpl.leave_title')} footer={<><Button variant="secondary" onClick={() => setLeave(false)}>{t('studio_tpl.keep_editing')}</Button><Button variant="danger" onClick={() => { setLeave(false); onClose() }}>{t('studio_tpl.discard')}</Button></>}>
        <p className="t-body text-muted">{t('studio_tpl.leave_body')}</p>
      </Modal>
    </>,
    document.body,
  )
}
