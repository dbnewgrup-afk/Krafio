import { useCallback, useEffect, useMemo, useRef, useState, type DragEvent as RDragEvent, type KeyboardEvent as RKeyboardEvent } from 'react'
import { createPortal } from 'react-dom'
import { AnimatePresence, LayoutGroup, motion, useReducedMotion } from 'framer-motion'
import { useTranslation } from 'react-i18next'
import {
  Archive, Check, CheckCircle2, Copy, Eye, EyeOff, GripVertical, ImagePlus, LayoutGrid, List, Loader2, Monitor, Move, Pencil, Plus, Rows3, Search, Settings2, Smartphone, Tablet, Trash2, X, Crosshair,
} from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import type { TemplateItem } from '@/api-client/types'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { useIsMobile } from '@/shared/hooks/useMediaQuery'
import { useBackClose, useFocusTrap, useScrollLock } from '@/shared/hooks/useOverlay'
import { useToast } from '@/shared/context/ToastContext'
import { PageHeader, Segmented, ChipRow, Menu } from '@/shared/ui/Misc'
import { Button } from '@/shared/ui/Button'
import { ConfirmDialog } from '@/shared/ui/ConfirmDialog'
import { Modal } from '@/shared/ui/Modal'
import { TextField, TextArea, SelectField, Switch, inputCls } from '@/shared/ui/Field'
import { StatusChip } from '@/shared/ui/Status'
import { EmptyState, Skeleton } from '@/shared/ui/States'
import { TemplateCard, DEFAULT_CARD_DISPLAY, type CardDisplay, type CardField, type CardPreviewState } from '@/public-site/components/TemplateCard'
import { relative, number } from '@/shared/lib/format'
import { cn } from '@/shared/lib/cn'

/* ============================ Data ============================ */

type Status = 'draft' | 'pending' | 'active' | 'archived'
interface Row {
  id: number
  title_id: string
  title_en: string | null
  description_id: string | null
  description_en: string | null
  type: TemplateItem['type']
  category: string | null
  ratio: string
  palette: string[]
  cover_url: string | null
  focus: { x: number; y: number } | null
  badges: string[] | null
  structure: { gallery?: string[] } | null
  is_premium: boolean
  is_featured: boolean
  status: Status
  usage_count: number
  sort: number
  updated_at: string
}
type Draft = Omit<Row, 'id' | 'usage_count' | 'sort' | 'updated_at'> & { id?: number }

const slug = (s: string) => s.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
const toItem = (r: Draft & { id?: number; usage_count?: number }): TemplateItem => ({
  id: r.id ?? 0,
  title: r.title_id || '…',
  type: r.type,
  category: r.category ?? '',
  ratio: r.ratio,
  is_premium: r.is_premium,
  status: r.status,
  is_featured: r.is_featured,
  palette: r.palette,
  usage_count: r.usage_count ?? 0,
  description: r.description_id ?? '',
  art: r.title_id ? slug(r.title_id) : undefined,
  cover_url: r.structure?.gallery?.[0] ?? r.cover_url,
  focus: r.focus,
  badges: r.badges ?? [],
})
const BLANK: Draft = { title_id: '', title_en: null, description_id: '', description_en: null, type: 'photo', category: null, ratio: '1:1', palette: ['#F4E7D3', '#C7A27C', '#3E2C23'], cover_url: null, focus: null, badges: [], structure: { gallery: [] }, is_premium: false, is_featured: false, status: 'draft' }
const STATUS_FILTERS = ['all', 'active', 'draft', 'pending', 'archived'] as const

/** CRUD template dengan update optimistik + rollback. */
function useProducts() {
  const { t } = useTranslation()
  const toast = useToast()
  const [rows, setRows] = useState<Row[] | null>(null)
  const load = useCallback(async () => {
    const r = await http.get<{ data: Row[] }>('/admin/r/templates', { per_page: 100, sort: 'sort' })
    setRows(r.data)
  }, [])
  useEffect(() => {
    load().catch(() => setRows([]))
  }, [load])

  const optimistic = async (next: (xs: Row[]) => Row[], run: () => Promise<void>) => {
    const prev = rows
    setRows((xs) => (xs ? next(xs) : xs))
    try {
      await run()
    } catch (e) {
      setRows(prev)
      toast.push('error', e instanceof ApiError ? e.message : t('errors.server_title'))
      throw e
    }
  }
  const save = async (d: Draft): Promise<Row> => {
    const body = { ...d, cover_url: d.structure?.gallery?.[0] ?? d.cover_url, badges: d.badges ?? [] }
    const r = d.id ? await http.put<{ data: Row }>(`/admin/r/templates/${d.id}`, body) : await http.post<{ data: Row }>('/admin/r/templates', body)
    setRows((xs) => (xs ? (d.id ? xs.map((x) => (x.id === r.data.id ? r.data : x)) : [r.data, ...xs]) : xs))
    return r.data
  }
  const setStatus = (ids: number[], status: Status) =>
    optimistic(
      (xs) => xs.map((x) => (ids.includes(x.id) ? { ...x, status } : x)),
      async () => {
        await Promise.all(ids.map((id) => http.put(`/admin/r/templates/${id}`, { status })))
      },
    )
  const remove = (ids: number[]) => Promise.all(ids.map((id) => http.del(`/admin/r/templates/${id}`)))
  const duplicate = async (id: number) => {
    const r = await http.post<{ data: Row }>(`/admin/templates/${id}/duplicate`)
    setRows((xs) => {
      if (!xs) return xs
      const i = xs.findIndex((x) => x.id === id)
      const out = [...xs]
      out.splice(i + 1, 0, r.data)
      return out
    })
    return r.data
  }
  const reorder = (ids: number[]) => http.post('/admin/templates/reorder', { ids })
  return { rows, setRows, reload: load, save, setStatus, remove, duplicate, reorder }
}

/** Hapus dengan Undo: kartu disembunyikan dulu, benar-benar dihapus setelah 6 dtk. */
function useUndoDelete(onCommit: (ids: number[]) => Promise<unknown>) {
  const [hidden, setHidden] = useState<number[]>([])
  const timers = useRef(new Map<string, number>())
  const schedule = (ids: number[]) => {
    setHidden((h) => [...h, ...ids])
    const key = ids.join(',')
    timers.current.set(key, window.setTimeout(() => {
      timers.current.delete(key)
      void onCommit(ids).finally(() => setHidden((h) => h.filter((x) => !ids.includes(x))))
    }, 6000))
    return () => {
      const tm = timers.current.get(key)
      if (tm) window.clearTimeout(tm)
      timers.current.delete(key)
      setHidden((h) => h.filter((x) => !ids.includes(x)))
    }
  }
  useEffect(() => () => timers.current.forEach((tm) => window.clearTimeout(tm)), [])
  return { hidden, schedule }
}

/* ============================ Halaman ============================ */

const DEVICE_W = { desktop: '100%', tablet: '820px', mobile: '400px' } as const
const COLS = { desktop: 'grid-cols-2 md:grid-cols-3 xl:grid-cols-4', tablet: 'grid-cols-2', mobile: 'grid-cols-1' } as const

function readDisplay(): CardDisplay {
  try {
    return { ...DEFAULT_CARD_DISPLAY, ...JSON.parse(localStorage.getItem('krafio.admin.card') ?? '{}') }
  } catch {
    return DEFAULT_CARD_DISPLAY
  }
}

export function TemplateStudio() {
  const { t } = useTranslation()
  useDocTitle(t('studio_tpl.title'))
  const toast = useToast()
  const reduced = useReducedMotion()
  const p = useProducts()
  const undo = useUndoDelete(p.remove)
  const [q, setQ] = useState('')
  const [status, setStatus] = useState<(typeof STATUS_FILTERS)[number]>('all')
  const [sort, setSort] = useState<'order' | 'newest' | 'popular'>('order')
  const [view, setView] = useState<'grid' | 'list' | 'compact'>('grid')
  const [device, setDevice] = useState<keyof typeof DEVICE_W>('desktop')
  const [display, setDisplayState] = useState<CardDisplay>(readDisplay)
  const [arrange, setArrange] = useState(false)
  const [selected, setSelected] = useState<number[]>([])
  const [editing, setEditing] = useState<Draft | null>(null)
  const [confirmDel, setConfirmDel] = useState<number[] | null>(null)
  const [approving, setApproving] = useState<number | null>(null)
  const [dragId, setDragId] = useState<number | null>(null)
  const [overId, setOverId] = useState<number | null>(null)
  const gridRef = useRef<HTMLUListElement>(null)

  const setDisplay = (patch: Partial<CardDisplay>) =>
    setDisplayState((d) => {
      const n = { ...d, ...patch }
      try {
        localStorage.setItem('krafio.admin.card', JSON.stringify(n))
      } catch {
        /* noop */
      }
      return n
    })

  const list = useMemo(() => {
    let xs = (p.rows ?? []).filter((r) => !undo.hidden.includes(r.id))
    if (status !== 'all') xs = xs.filter((r) => r.status === status)
    if (q) xs = xs.filter((r) => `${r.title_id} ${r.title_en ?? ''} ${r.category ?? ''}`.toLowerCase().includes(q.toLowerCase()))
    if (sort === 'newest') xs = [...xs].sort((a, b) => b.id - a.id)
    if (sort === 'popular') xs = [...xs].sort((a, b) => b.usage_count - a.usage_count)
    if (sort === 'order') xs = [...xs].sort((a, b) => a.sort - b.sort || a.id - b.id)
    return xs
  }, [p.rows, undo.hidden, status, q, sort])

  const counts = useMemo(() => Object.fromEntries(STATUS_FILTERS.map((s) => [s, (p.rows ?? []).filter((r) => s === 'all' || r.status === s).length])), [p.rows])

  const askDelete = (ids: number[]) => setConfirmDel(ids)
  const doDelete = (ids: number[]) => {
    setConfirmDel(null)
    setSelected([])
    const cancel = undo.schedule(ids)
    toast.push('warning', t('studio_tpl.deleted', { n: ids.length }), { label: t('studio_tpl.undo'), onClick: cancel })
  }

  const approve = async (r: Row) => {
    setApproving(r.id)
    try {
      await p.setStatus([r.id], 'active')
      window.setTimeout(() => setApproving(null), 900)
    } catch {
      setApproving(null)
    }
  }

  // Navigasi keyboard grid: panah pindah fokus, Enter edit, Delete hapus
  const onGridKey = (e: RKeyboardEvent<HTMLUListElement>) => {
    const items = Array.from(gridRef.current?.querySelectorAll<HTMLElement>('[data-card]') ?? [])
    const i = items.indexOf(document.activeElement as HTMLElement)
    if (i < 0) return
    const cols = view !== 'grid' ? 1 : Math.max(1, Math.round((gridRef.current?.clientWidth ?? 1) / (items[0]?.clientWidth || 1)))
    const map: Record<string, number> = { ArrowRight: 1, ArrowLeft: -1, ArrowDown: cols, ArrowUp: -cols }
    if (map[e.key] !== undefined) {
      e.preventDefault()
      if (arrange && e.altKey) {
        const ids = list.map((x) => x.id)
        const j = Math.min(ids.length - 1, Math.max(0, i + map[e.key]))
        const [m] = ids.splice(i, 1)
        ids.splice(j, 0, m)
        commitOrder(ids)
        window.setTimeout(() => gridRef.current?.querySelectorAll<HTMLElement>('[data-card]')[j]?.focus(), 30)
        return
      }
      items[Math.min(items.length - 1, Math.max(0, i + map[e.key]))]?.focus()
    } else if (e.key === 'Enter') {
      const r = list[i]
      if (r) setEditing({ ...r })
    } else if (e.key === 'Delete') {
      const r = list[i]
      if (r) askDelete([r.id])
    }
  }

  const commitOrder = (ids: number[]) => {
    p.setRows((xs) => (xs ? xs.map((x) => ({ ...x, sort: ids.indexOf(x.id) >= 0 ? ids.indexOf(x.id) + 1 : x.sort })) : xs))
    void p.reorder(ids).catch(() => toast.push('error', t('errors.server_title')))
  }

  const onDragOver = (e: RDragEvent, id: number) => {
    if (!arrange || dragId == null || dragId === id) return
    e.preventDefault()
    setOverId(id)
    const ids = list.map((x) => x.id)
    const from = ids.indexOf(dragId)
    const to = ids.indexOf(id)
    if (from < 0 || to < 0 || from === to) return
    ids.splice(from, 1)
    ids.splice(to, 0, dragId)
    p.setRows((xs) => (xs ? xs.map((x) => ({ ...x, sort: ids.indexOf(x.id) >= 0 ? ids.indexOf(x.id) + 1 : x.sort })) : xs))
  }

  return (
    <>
      <PageHeader
        title={t('studio_tpl.title')}
        subtitle={t('studio_tpl.subtitle')}
        crumbs={[{ label: t('admin.nav.g_templates'), to: '/admin/templates' }, { label: t('admin.nav.template_studio') }]}
        actions={
          <motion.div whileTap={{ scale: 0.97 }}>
            <Button icon={<Plus className="size-4" aria-hidden />} onClick={() => setEditing({ ...BLANK, palette: [...BLANK.palette] })}>{t('studio_tpl.new')}</Button>
          </motion.div>
        }
      />

      {/* Toolbar */}
      <div className="sticky top-[calc(var(--nav-h)+var(--safe-t))] z-sticky -mx-4 grid gap-3 border-b border-line bg-bg-subtle/95 px-4 pb-3 pt-1 backdrop-blur md:-mx-6 md:px-6 lg:-mx-8 lg:px-8">
        <div className="flex flex-wrap items-center gap-2">
          <div className="relative min-w-[200px] flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" aria-hidden />
            <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('studio_tpl.search')} aria-label={t('studio_tpl.search')} className={cn(inputCls, 'h-10 pl-9 lg:h-10')} />
          </div>
          <select value={sort} onChange={(e) => setSort(e.target.value as typeof sort)} aria-label={t('studio_tpl.sort')} className={cn(inputCls.replace('w-full ', ''), 'h-10 w-auto lg:h-10')}>
            <option value="order">{t('studio_tpl.sort_order')}</option>
            <option value="newest">{t('studio_tpl.sort_newest')}</option>
            <option value="popular">{t('studio_tpl.sort_popular')}</option>
          </select>
          <Segmented label={t('studio_tpl.view')} value={view} onChange={setView} items={[{ value: 'grid', label: <LayoutGrid className="size-4" aria-label={t('studio_tpl.v_grid')} /> }, { value: 'list', label: <List className="size-4" aria-label={t('studio_tpl.v_list')} /> }, { value: 'compact', label: <Rows3 className="size-4" aria-label={t('studio_tpl.v_compact')} /> }]} />
          <Segmented label={t('studio_tpl.preview_as')} value={device} onChange={setDevice} items={[{ value: 'desktop', label: <Monitor className="size-4" aria-label="Desktop" /> }, { value: 'tablet', label: <Tablet className="size-4" aria-label="Tablet" /> }, { value: 'mobile', label: <Smartphone className="size-4" aria-label="Mobile" /> }]} />
          <CardSettings display={display} setDisplay={setDisplay} />
          <Button variant={arrange ? 'primary' : 'secondary'} size="sm" icon={<Move className="size-4" aria-hidden />} onClick={() => { setArrange((a) => !a); setSort('order') }} aria-pressed={arrange}>{t('studio_tpl.arrange')}</Button>
        </div>
        <ChipRow label={t('studio_tpl.status')} value={status} onChange={setStatus} items={STATUS_FILTERS.map((s) => ({ value: s, label: <span className="inline-flex items-center gap-1.5">{t(`studio_tpl.s.${s}`)}<span className="tabular-nums opacity-70">{counts[s] ?? 0}</span></span> }))} />
        <AnimatePresence>
          {selected.length > 0 && (
            <motion.div initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: 'auto' }} exit={{ opacity: 0, height: 0 }} className="flex flex-wrap items-center gap-2 overflow-hidden rounded-lg bg-primary-soft px-3 py-2">
              <span className="t-label mr-auto text-primary">{t('studio_tpl.selected', { n: selected.length })}</span>
              <Button size="sm" variant="secondary" icon={<CheckCircle2 className="size-4" aria-hidden />} onClick={() => void p.setStatus(selected, 'active').then(() => setSelected([]))}>{t('studio_tpl.publish')}</Button>
              <Button size="sm" variant="secondary" icon={<Archive className="size-4" aria-hidden />} onClick={() => void p.setStatus(selected, 'archived').then(() => setSelected([]))}>{t('studio_tpl.archive')}</Button>
              <Button size="sm" variant="danger" icon={<Trash2 className="size-4" aria-hidden />} onClick={() => askDelete(selected)}>{t('studio_tpl.delete')}</Button>
              <button type="button" onClick={() => setSelected([])} aria-label={t('common.cancel')} className="grid size-8 place-items-center rounded-md hover:bg-surface"><X className="size-4" aria-hidden /></button>
            </motion.div>
          )}
        </AnimatePresence>
        {arrange && <p className="t-small text-muted">{t('studio_tpl.arrange_hint')}</p>}
      </div>

      {/* Grid kartu */}
      <div className="mx-auto mt-4 transition-[max-width] duration-300" style={{ maxWidth: DEVICE_W[device] }}>
        {!p.rows ? (
          <div className={cn('grid gap-3', COLS[device])}>{Array.from({ length: 8 }).map((_, i) => <div key={i} className="grid gap-2 p-2"><Skeleton className="aspect-[4/5]" /><Skeleton className="h-4 w-2/3" /><Skeleton className="h-3 w-1/3" /></div>)}</div>
        ) : list.length === 0 ? (
          <EmptyState kind={q || status !== 'all' ? 'filter' : 'empty'} title={q || status !== 'all' ? t('table.no_results') : t('studio_tpl.empty')} action={<Button onClick={() => setEditing({ ...BLANK })}>{t('studio_tpl.new')}</Button>} />
        ) : (
          <LayoutGroup>
            <motion.ul ref={gridRef} onKeyDown={onGridKey} className={cn('grid gap-3', view === 'grid' ? COLS[device] : 'grid-cols-1', view === 'compact' && 'gap-1')} initial="hidden" animate="show" variants={{ hidden: {}, show: { transition: { staggerChildren: reduced ? 0 : 0.05 } } }}>
              <AnimatePresence mode="popLayout">
                {list.map((r) => {
                  const item = toItem(r)
                  const dim = r.status === 'draft' || r.status === 'pending' || r.status === 'archived'
                  const checked = selected.includes(r.id)
                  return (
                    <motion.li
                      key={r.id}
                      layout={!reduced}
                      layoutId={reduced ? undefined : `tpl-${r.id}`}
                      variants={{ hidden: { opacity: 0, y: 12 }, show: { opacity: 1, y: 0 } }}
                      exit={{ opacity: 0, scale: 0.85, transition: { duration: 0.22 } }}
                      transition={{ type: 'spring', stiffness: 260, damping: 28 }}
                      className={cn('group relative rounded-2xl', overId === r.id && dragId !== r.id && 'ring-2 ring-primary ring-offset-2 ring-offset-bg-subtle', dragId === r.id && 'opacity-60')}
                      draggable={arrange}
                      onDragStart={(e: unknown) => { const ev = e as RDragEvent; ev.dataTransfer.effectAllowed = 'move'; setDragId(r.id) }}
                      onDragOver={(e: unknown) => onDragOver(e as RDragEvent, r.id)}
                      onDragEnd={() => { setDragId(null); setOverId(null); commitOrder(list.map((x) => x.id)) }}
                    >
                      {view === 'grid' ? (
                        <div data-card tabIndex={0} aria-label={`${item.title} — ${t(`studio_tpl.s.${r.status}`)}`} className={cn('relative rounded-2xl outline-none focus-visible:ring-2 focus-visible:ring-primary', dim && 'opacity-70 saturate-[.85]')}>
                          <TemplateCard item={item} display={display} onClick={() => !arrange && setEditing({ ...r })} />
                          {/* Overlay aksi saat hover */}
                          {!arrange && (
                            <div className="pointer-events-none absolute inset-x-2 top-2 flex justify-end gap-1 opacity-0 transition-opacity duration-150 group-focus-within:pointer-events-auto group-focus-within:opacity-100 group-hover:pointer-events-auto group-hover:opacity-100">
                              <CardAction icon={Pencil} label={t('studio_tpl.edit')} onClick={() => setEditing({ ...r })} />
                              <CardAction icon={Copy} label={t('studio_tpl.duplicate')} onClick={() => void p.duplicate(r.id).then(() => toast.push('success', t('studio_tpl.duplicated')))} />
                              <CardAction icon={r.status === 'active' ? EyeOff : Eye} label={r.status === 'active' ? t('studio_tpl.unpublish') : t('studio_tpl.publish')} onClick={() => void p.setStatus([r.id], r.status === 'active' ? 'draft' : 'active')} />
                              <CardAction icon={Trash2} label={t('studio_tpl.delete')} danger onClick={() => askDelete([r.id])} />
                            </div>
                          )}
                          {arrange && <span className="absolute left-3 top-3 grid size-8 cursor-grab place-items-center rounded-md bg-surface/95 text-muted shadow-k-sm active:cursor-grabbing"><GripVertical className="size-4" aria-hidden /></span>}
                          <label className={cn('absolute left-3 top-3 grid size-8 place-items-center rounded-md bg-surface/95 shadow-k-sm', arrange && 'hidden', !checked && 'opacity-0 group-focus-within:opacity-100 group-hover:opacity-100')}>
                            <input type="checkbox" checked={checked} onChange={(e) => setSelected(e.target.checked ? [...selected, r.id] : selected.filter((x) => x !== r.id))} aria-label={t('studio_tpl.select', { title: item.title })} className="size-4 accent-[var(--primary)]" />
                          </label>
                          <div className="flex items-center justify-between gap-2 px-2.5 pb-1">
                            <StatusPill status={r.status} done={approving === r.id} />
                            {r.status === 'pending' ? (
                              <div className="flex gap-1">
                                <Button size="sm" variant="ghost" onClick={() => setEditing({ ...r })}>{t('studio_tpl.review')}</Button>
                                <ApproveButton busy={approving === r.id} onClick={() => void approve(r)} />
                              </div>
                            ) : (
                              <span className="t-small tabular-nums text-muted">{t('studio_tpl.used', { n: number(r.usage_count) })}</span>
                            )}
                          </div>
                        </div>
                      ) : (
                        <div data-card tabIndex={0} className={cn('flex items-center gap-3 rounded-xl border border-line bg-surface outline-none focus-visible:ring-2 focus-visible:ring-primary', view === 'compact' ? 'px-3 py-1.5' : 'p-2.5', dim && 'opacity-75')}>
                          {arrange ? <GripVertical className="size-4 shrink-0 cursor-grab text-muted" aria-hidden /> : <input type="checkbox" checked={checked} onChange={(e) => setSelected(e.target.checked ? [...selected, r.id] : selected.filter((x) => x !== r.id))} aria-label={t('studio_tpl.select', { title: item.title })} className="size-4 shrink-0 accent-[var(--primary)]" />}
                          {view === 'list' && <div className="w-20 shrink-0"><TemplateCard item={item} display={{ ...display, variant: 'minimal', showBadge: false }} className="p-0 hover:bg-transparent" /></div>}
                          <button type="button" onClick={() => setEditing({ ...r })} className="min-w-0 flex-1 text-left">
                            <p className="truncate text-sm font-semibold text-fg">{item.title}</p>
                            <p className="t-small truncate text-muted">{t(`templates.types.${r.type}`)} · {r.ratio} · {r.is_premium ? t('templates.premium') : t('templates.free')} · {relative(r.updated_at)}</p>
                          </button>
                          <StatusPill status={r.status} done={approving === r.id} />
                          <div className="flex gap-0.5">
                            <CardAction icon={Copy} label={t('studio_tpl.duplicate')} onClick={() => void p.duplicate(r.id)} plain />
                            <CardAction icon={Trash2} label={t('studio_tpl.delete')} danger plain onClick={() => askDelete([r.id])} />
                          </div>
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
            <ProductEditor
              key="editor"
              initial={editing}
              display={display}
              onClose={() => setEditing(null)}
              onSave={async (d) => {
                const saved = await p.save(d)
                return saved
              }}
              onDelete={editing.id ? () => { const id = editing.id!; setEditing(null); askDelete([id]) } : undefined}
            />
          )}
        </AnimatePresence>
      </LayoutGroup>
      <ConfirmDialog open={!!confirmDel} onClose={() => setConfirmDel(null)} destructive title={t('studio_tpl.confirm_delete', { n: confirmDel?.length ?? 0 })} body={t('studio_tpl.confirm_delete_body')} confirmLabel={t('studio_tpl.delete')} onConfirm={() => { if (confirmDel) doDelete(confirmDel) }} />
    </>
  )
}

function CardAction({ icon: Icon, label, onClick, danger, plain }: { icon: typeof Pencil; label: string; onClick: () => void; danger?: boolean; plain?: boolean }) {
  return (
    <motion.button type="button" whileTap={{ scale: 0.92 }} onClick={(e) => { e.stopPropagation(); onClick() }} aria-label={label} title={label} className={cn('grid size-8 place-items-center rounded-md', plain ? 'hover:bg-surface-2' : 'bg-surface/95 shadow-k-sm hover:bg-surface', danger ? 'text-danger' : 'text-fg')}>
      <Icon className="size-4" aria-hidden />
    </motion.button>
  )
}

function StatusPill({ status, done }: { status: Status; done?: boolean }) {
  const { t } = useTranslation()
  return (
    <motion.span key={status} initial={{ opacity: 0.4, scale: 0.92 }} animate={{ opacity: 1, scale: 1 }} transition={{ duration: 0.25 }}>
      <StatusChip status={done ? 'active' : status === 'pending' ? 'under_review' : status} label={t(`studio_tpl.s.${done ? 'active' : status}`)} />
    </motion.span>
  )
}

function ApproveButton({ busy, onClick }: { busy: boolean; onClick: () => void }) {
  const { t } = useTranslation()
  return (
    <motion.button type="button" whileTap={{ scale: 0.95 }} onClick={onClick} disabled={busy} className="inline-flex h-8 items-center gap-1.5 rounded-md bg-success px-2.5 text-[12.5px] font-semibold text-white disabled:opacity-90 dark:text-bg">
      <AnimatePresence mode="wait" initial={false}>
        {busy ? (
          <motion.span key="b" initial={{ scale: 0.5, opacity: 0 }} animate={{ scale: 1, opacity: 1 }} exit={{ scale: 0.5, opacity: 0 }}><Check className="size-4" aria-hidden /></motion.span>
        ) : (
          <motion.span key="a" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>{t('studio_tpl.approve')}</motion.span>
        )}
      </AnimatePresence>
    </motion.button>
  )
}

function CardSettings({ display, setDisplay }: { display: CardDisplay; setDisplay: (p: Partial<CardDisplay>) => void }) {
  const { t } = useTranslation()
  return (
    <Menu label={t('studio_tpl.card_settings')} width="w-72" trigger={({ toggle, open, id }) => (
      <button type="button" onClick={toggle} aria-expanded={open} aria-controls={id} className="inline-flex h-10 items-center gap-1.5 rounded-md border border-line bg-surface px-3 text-sm font-medium hover:bg-surface-2">
        <Settings2 className="size-4" aria-hidden />
        <span className="max-md:sr-only">{t('studio_tpl.card_settings')}</span>
      </button>
    )}>
      {() => (
        <div className="grid gap-3 p-3">
          <SelectField label={t('studio_tpl.cs.variant')} value={display.variant} onChange={(e) => setDisplay({ variant: e.target.value as CardDisplay['variant'] })}>
            {(['image-top', 'overlay', 'horizontal', 'minimal'] as const).map((v) => <option key={v} value={v}>{t(`studio_tpl.cs.v_${v.replace('-', '_')}`)}</option>)}
          </SelectField>
          <SelectField label={t('studio_tpl.cs.ratio')} value={display.imageRatio} onChange={(e) => setDisplay({ imageRatio: e.target.value as CardDisplay['imageRatio'] })}>
            {(['4/5', '1/1', '4/3', '16/9'] as const).map((v) => <option key={v} value={v}>{v.replace('/', ':')}</option>)}
          </SelectField>
          <SelectField label={t('studio_tpl.cs.hover')} value={display.hoverEffect} onChange={(e) => setDisplay({ hoverEffect: e.target.value as CardDisplay['hoverEffect'] })}>
            {(['zoom', 'lift', 'tilt', 'none'] as const).map((v) => <option key={v} value={v}>{t(`studio_tpl.cs.h_${v}`)}</option>)}
          </SelectField>
          <Switch label={t('studio_tpl.cs.badge')} checked={display.showBadge} onChange={(v) => setDisplay({ showBadge: v })} />
          <Switch label={t('studio_tpl.cs.price')} checked={display.showPrice} onChange={(v) => setDisplay({ showPrice: v })} />
          <Switch label={t('studio_tpl.cs.creator')} checked={display.showCreator} onChange={(v) => setDisplay({ showCreator: v })} />
          <button type="button" onClick={() => setDisplay(DEFAULT_CARD_DISPLAY)} className="t-label justify-self-start text-primary hover:underline">{t('studio_tpl.cs.reset')}</button>
        </div>
      )}
    </Menu>
  )
}

/* ============================ Editor ============================ */

/** State form + autosave draft (5 dtk setelah perubahan terakhir, hanya untuk draft/arsip). */
function useProductForm(initial: Draft, onSave: (d: Draft) => Promise<Row>) {
  const [d, setD] = useState<Draft>(initial)
  const [dirty, setDirty] = useState(false)
  const [saving, setSaving] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle')
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [changed, setChanged] = useState<CardField | null>(null)
  const fieldOf: Partial<Record<keyof Draft, CardField>> = { title_id: 'title', is_premium: 'price', badges: 'badge', is_featured: 'badge', type: 'type', structure: 'image', cover_url: 'image', focus: 'image', palette: 'image', ratio: 'image' }

  const set = <K extends keyof Draft>(k: K, v: Draft[K]) => {
    setD((x) => ({ ...x, [k]: v }))
    setDirty(true)
    setErrors((e) => ({ ...e, [k]: [] }))
    const f = fieldOf[k]
    if (f) {
      setChanged(null)
      window.setTimeout(() => setChanged(f), 0)
    }
  }

  const persist = useCallback(
    async (patch: Partial<Draft> = {}) => {
      setSaving('saving')
      try {
        const saved = await onSave({ ...d, ...patch })
        setD((x) => ({ ...x, ...patch, id: saved.id }))
        setDirty(false)
        setSaving('saved')
        return saved
      } catch (e) {
        if (e instanceof ApiError) setErrors(e.fields)
        setSaving('error')
        throw e
      }
    },
    [d, onSave],
  )

  // Autosave draft
  useEffect(() => {
    if (!dirty || !d.title_id.trim() || (d.status !== 'draft' && d.status !== 'archived')) return
    const id = window.setTimeout(() => void persist().catch(() => undefined), 5000)
    return () => window.clearTimeout(id)
  }, [d, dirty, persist])

  return { d, set, dirty, saving, errors, persist, changed }
}

function ProductEditor({ initial, display, onClose, onSave, onDelete }: { initial: Draft; display: CardDisplay; onClose: () => void; onSave: (d: Draft) => Promise<Row>; onDelete?: () => void }) {
  const { t } = useTranslation()
  const toast = useToast()
  const reduced = useReducedMotion()
  const isMobile = useIsMobile()
  const f = useProductForm(initial, onSave)
  const { d, set } = f
  const ref = useRef<HTMLDivElement>(null)
  const [mobileTab, setMobileTab] = useState<'form' | 'preview'>('form')
  const [previewMode, setPreviewMode] = useState<'card' | 'detail' | 'mobile'>('card')
  const [pState, setPState] = useState<CardPreviewState>('default')
  const [confirmLeave, setConfirmLeave] = useState(false)
  const [cats, setCats] = useState<{ slug: string; name_id: string; type: string }[]>([])
  const [uploading, setUploading] = useState(false)
  const [dragOver, setDragOver] = useState(false)
  const [focusMode, setFocusMode] = useState(false)
  const [preview, setPreview] = useState(() => toItem(initial))
  const fileRef = useRef<HTMLInputElement>(null)
  const fieldRefs = useRef<Partial<Record<CardField, HTMLElement | null>>>({})

  useScrollLock(true)
  useFocusTrap(ref, true)
  const tryClose = () => (f.dirty ? setConfirmLeave(true) : onClose())
  useBackClose(true, tryClose)
  useEffect(() => {
    http.get<{ data: { slug: string; name_id: string; type: string }[] }>('/admin/r/template-categories', { per_page: 100 }).then((r) => setCats(r.data)).catch(() => undefined)
  }, [])
  // Pratinjau langsung, debounce ≤100 ms
  useEffect(() => {
    const id = window.setTimeout(() => setPreview(toItem(d)), 60)
    return () => window.clearTimeout(id)
  }, [d])
  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && !confirmLeave) {
        e.stopPropagation()
        tryClose()
      }
    }
    window.addEventListener('keydown', k)
    return () => window.removeEventListener('keydown', k)
  })

  const gallery = d.structure?.gallery ?? (d.cover_url ? [d.cover_url] : [])
  const setGallery = (g: string[]) => set('structure', { ...(d.structure ?? {}), gallery: g })

  const upload = async (files: FileList | File[] | null) => {
    if (!files || !files.length) return
    setUploading(true)
    try {
      const urls: string[] = []
      for (const file of Array.from(files).slice(0, 8 - gallery.length)) {
        const fd = new FormData()
        fd.append('file', file)
        const r = await http.post<{ data: { url: string } }>('/admin/media', fd)
        urls.push(r.data.url)
      }
      setGallery([...gallery, ...urls])
    } catch (e) {
      toast.push('error', e instanceof ApiError ? e.message : t('errors.server_title'))
    } finally {
      setUploading(false)
    }
  }

  const focusField = (fld: CardField) => {
    if (isMobile) setMobileTab('form')
    window.setTimeout(() => {
      const el = fieldRefs.current[fld]
      el?.scrollIntoView({ block: 'center', behavior: reduced ? 'auto' : 'smooth' })
      ;(el?.querySelector('input,textarea,select,button') as HTMLElement | null)?.focus()
    }, 40)
  }

  const publish = async () => {
    try {
      await f.persist({ status: 'active' })
      toast.push('success', t('studio_tpl.published'))
      onClose()
    } catch {
      /* error di form */
    }
  }
  const saveDraft = async () => {
    try {
      await f.persist({ status: d.status === 'active' ? 'active' : 'draft' })
      toast.push('success', t('studio_tpl.saved'))
      onClose()
    } catch {
      /* error di form */
    }
  }

  const err = (k: string) => f.errors[k]?.[0]
  const badgeOpts = ['new', 'promo']

  const form = (
    <div className="grid gap-5">
      <div ref={(el) => { fieldRefs.current.image = el }} className="grid gap-2">
        <p className="t-label text-fg">{t('studio_tpl.f.gallery')}</p>
        <div
          onDragOver={(e) => { e.preventDefault(); setDragOver(true) }}
          onDragLeave={() => setDragOver(false)}
          onDrop={(e) => { e.preventDefault(); setDragOver(false); if (e.dataTransfer.files.length) void upload(e.dataTransfer.files) }}
          className={cn('grid gap-2 rounded-xl border-2 border-dashed p-3 transition-colors', dragOver ? 'border-primary bg-primary-soft' : 'border-line')}
        >
          {gallery.length > 0 && (
            <GalleryReorder gallery={gallery} setGallery={setGallery} focus={d.focus} focusMode={focusMode} onFocus={(pt) => set('focus', pt)} />
          )}
          <div className="flex flex-wrap items-center gap-2">
            <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp" multiple hidden onChange={(e) => void upload(e.target.files)} />
            <Button size="sm" variant="secondary" loading={uploading} icon={<ImagePlus className="size-4" aria-hidden />} onClick={() => fileRef.current?.click()} disabled={gallery.length >= 8}>{t('studio_tpl.f.upload')}</Button>
            {gallery.length > 0 && <Button size="sm" variant={focusMode ? 'primary' : 'ghost'} icon={<Crosshair className="size-4" aria-hidden />} onClick={() => setFocusMode((x) => !x)} aria-pressed={focusMode}>{t('studio_tpl.f.focus')}</Button>}
            <p className="t-small text-muted">{t('studio_tpl.f.gallery_hint')}</p>
          </div>
        </div>
      </div>

      <div ref={(el) => { fieldRefs.current.title = el }}>
        <TextField label={t('studio_tpl.f.title')} required value={d.title_id} onChange={(e) => set('title_id', e.target.value)} error={err('title_id')} maxLength={160} placeholder="Kopi susu gula aren" />
      </div>
      <TextField label={t('studio_tpl.f.title_en')} optional value={d.title_en ?? ''} onChange={(e) => set('title_en', e.target.value || null)} error={err('title_en')} maxLength={160} />
      <TextArea label={t('studio_tpl.f.description')} optional rows={3} value={d.description_id ?? ''} onChange={(e) => set('description_id', e.target.value)} error={err('description_id')} maxLength={1000} hint={`${(d.description_id ?? '').length}/1000`} />
      <div className="grid gap-4 sm:grid-cols-2">
        <div ref={(el) => { fieldRefs.current.type = el }}>
          <SelectField label={t('studio_tpl.f.type')} value={d.type} onChange={(e) => set('type', e.target.value as Draft['type'])} error={err('type')}>
            {['photo', 'video', 'website', 'social', 'business'].map((x) => <option key={x} value={x}>{t(`templates.types.${x}`)}</option>)}
          </SelectField>
        </div>
        <SelectField label={t('studio_tpl.f.category')} optional value={d.category ?? ''} onChange={(e) => set('category', e.target.value || null)}>
          <option value="">—</option>
          {cats.filter((c) => c.type === d.type).map((c) => <option key={c.slug} value={c.slug}>{c.name_id}</option>)}
        </SelectField>
        <SelectField label={t('studio_tpl.f.ratio')} value={d.ratio} onChange={(e) => set('ratio', e.target.value)}>
          {['1:1', '4:5', '9:16', '16:9', '4:3'].map((x) => <option key={x} value={x}>{x}</option>)}
        </SelectField>
        <SelectField label={t('studio_tpl.f.status')} value={d.status} onChange={(e) => set('status', e.target.value as Status)}>
          {(['draft', 'pending', 'active', 'archived'] as const).map((x) => <option key={x} value={x}>{t(`studio_tpl.s.${x}`)}</option>)}
        </SelectField>
      </div>
      <div ref={(el) => { fieldRefs.current.price = el }} className="grid gap-2">
        <p className="t-label text-fg">{t('studio_tpl.f.access')}</p>
        <Segmented label={t('studio_tpl.f.access')} value={d.is_premium ? 'premium' : 'free'} onChange={(v) => set('is_premium', v === 'premium')} items={[{ value: 'free', label: t('templates.free') }, { value: 'premium', label: t('templates.premium') }]} />
        <p className="t-small text-muted">{t('studio_tpl.f.access_hint')}</p>
      </div>
      <div ref={(el) => { fieldRefs.current.badge = el }} className="grid gap-2">
        <p className="t-label text-fg">{t('studio_tpl.f.labels')}</p>
        <div className="flex flex-wrap gap-2">
          <ToggleChip on={d.is_featured} onClick={() => set('is_featured', !d.is_featured)}>{t('templates.badge.featured')}</ToggleChip>
          {badgeOpts.map((b) => <ToggleChip key={b} on={(d.badges ?? []).includes(b)} onClick={() => set('badges', (d.badges ?? []).includes(b) ? (d.badges ?? []).filter((x) => x !== b) : [...(d.badges ?? []), b])}>{t(`templates.badge.${b}`)}</ToggleChip>)}
          <CustomBadge badges={d.badges ?? []} onAdd={(b) => set('badges', [...(d.badges ?? []), b].slice(0, 4))} onRemove={(b) => set('badges', (d.badges ?? []).filter((x) => x !== b))} reserved={badgeOpts} />
        </div>
      </div>
      <div className="grid gap-2">
        <p className="t-label text-fg">{t('studio_tpl.f.palette')}</p>
        <div className="flex gap-2">
          {d.palette.map((c, i) => (
            <label key={i} className="grid gap-1 text-center">
              <input type="color" value={c} onChange={(e) => { const p = [...d.palette]; p[i] = e.target.value.toUpperCase(); set('palette', p) }} className="h-11 w-16 cursor-pointer rounded-md border border-line bg-surface p-1" aria-label={t('studio_tpl.f.color', { n: i + 1 })} />
              <span className="font-mono text-[11px] text-muted">{c}</span>
            </label>
          ))}
        </div>
        <p className="t-small text-muted">{t('studio_tpl.f.palette_hint')}</p>
      </div>
    </div>
  )

  const previewPane = (
    <div className="grid content-start gap-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <Segmented label={t('studio_tpl.preview')} value={previewMode} onChange={setPreviewMode} items={[{ value: 'card', label: t('studio_tpl.pv.card') }, { value: 'detail', label: t('studio_tpl.pv.detail') }, { value: 'mobile', label: t('studio_tpl.pv.mobile') }]} />
      </div>
      <div className="flex flex-wrap gap-1.5" role="radiogroup" aria-label={t('studio_tpl.pv.state')}>
        {(['default', 'hover', 'locked', 'featured'] as const).map((s) => <ToggleChip key={s} on={pState === s} onClick={() => setPState(s)}>{t(`studio_tpl.pv.s_${s}`)}</ToggleChip>)}
      </div>
      <div className="grid place-items-center rounded-2xl border border-line bg-bg-subtle p-5">
        {previewMode === 'card' && (
          <motion.div layoutId={d.id && !reduced ? `tpl-${d.id}` : undefined} className="w-full max-w-[280px]">
            {d.title_id || gallery.length ? (
              <TemplateCard item={preview} display={display} previewState={pState} onField={focusField} highlight={f.changed} />
            ) : (
              <div className="grid gap-2 p-2"><Skeleton className="aspect-[4/5]" /><Skeleton className="h-4 w-2/3" /><Skeleton className="h-3 w-1/3" /><p className="t-small mt-1 text-center text-muted">{t('studio_tpl.pv.empty')}</p></div>
            )}
          </motion.div>
        )}
        {previewMode === 'mobile' && (
          <div className="w-[260px] rounded-[30px] border-[6px] border-fg/85 bg-bg p-3 shadow-k-lg">
            <div className="mx-auto mb-3 h-1.5 w-16 rounded-full bg-fg/20" aria-hidden />
            <div className="grid grid-cols-2 gap-1">
              <TemplateCard item={preview} display={{ ...display, imageRatio: '4/5' }} previewState={pState} onField={focusField} highlight={f.changed} className="p-1" />
              <div className="grid content-start gap-1 p-1 opacity-50"><Skeleton className="aspect-[4/5]" /><Skeleton className="h-3 w-2/3" /></div>
            </div>
          </div>
        )}
        {previewMode === 'detail' && (
          <div className="grid w-full max-w-md gap-3 rounded-xl border border-line bg-surface p-4 shadow-k-md">
            <p className="t-h3 text-fg">{preview.title}</p>
            <p className="t-small text-muted">{t(`templates.types.${preview.type}`)} · {preview.ratio} · {preview.is_premium ? t('templates.premium') : t('templates.free')}</p>
            <TemplateCard item={preview} display={{ ...display, variant: 'minimal', showBadge: true }} previewState={pState} onField={focusField} highlight={f.changed} className="p-0" />
            <p className="t-body text-muted">{preview.description || t('studio_tpl.pv.no_desc')}</p>
            <span className="inline-flex h-10 w-fit items-center rounded-md bg-primary px-4 text-sm font-semibold text-primary-contrast">{t('templates.use')}</span>
          </div>
        )}
      </div>
      <p className="t-small text-muted">{t('studio_tpl.pv.hint')}</p>
    </div>
  )

  return createPortal(
    <>
      <motion.div className="fixed inset-0 z-modal bg-[var(--overlay-dark)]" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} onClick={tryClose} aria-hidden />
      <motion.div
        ref={ref}
        role="dialog"
        aria-modal="true"
        aria-labelledby="tpl-editor-title"
        className="fixed inset-y-0 right-0 z-modal flex w-full max-w-[1120px] flex-col bg-surface shadow-k-lg md:rounded-l-2xl"
        initial={reduced ? { opacity: 0 } : { x: '100%' }}
        animate={reduced ? { opacity: 1 } : { x: 0 }}
        exit={reduced ? { opacity: 0 } : { x: '100%' }}
        transition={{ type: 'spring', stiffness: 260, damping: 32 }}
      >
        <header className="flex items-center gap-3 border-b border-line px-4 py-3 md:px-6">
          <div className="min-w-0 flex-1">
            <h2 id="tpl-editor-title" className="t-h3 truncate text-fg">{d.id ? t('studio_tpl.edit_title', { title: d.title_id || '…' }) : t('studio_tpl.new')}</h2>
            <p className="t-small flex items-center gap-1.5 text-muted" aria-live="polite">
              {f.saving === 'saving' ? <><Loader2 className="size-3.5 animate-spin" aria-hidden />{t('studio_tpl.saving')}</> : f.saving === 'saved' && !f.dirty ? <><Check className="size-3.5 text-success" aria-hidden />{t('studio_tpl.saved_auto')}</> : f.dirty ? t('studio_tpl.unsaved') : t('studio_tpl.autosave_hint')}
            </p>
          </div>
          <button type="button" onClick={tryClose} aria-label={t('common.close')} className="grid size-10 place-items-center rounded-md hover:bg-surface-2"><X className="size-5" aria-hidden /></button>
        </header>
        {isMobile && (
          <div className="border-b border-line px-4 py-2">
            <Segmented label={t('studio_tpl.preview')} value={mobileTab} onChange={setMobileTab} items={[{ value: 'form', label: t('studio_tpl.tab_form') }, { value: 'preview', label: t('studio_tpl.preview') }]} />
          </div>
        )}
        <div className="grid min-h-0 flex-1 overflow-hidden md:grid-cols-[minmax(0,1fr)_minmax(300px,400px)]">
          {(!isMobile || mobileTab === 'form') && <div className="min-h-0 overflow-y-auto px-4 py-5 md:px-6">{form}</div>}
          {(!isMobile || mobileTab === 'preview') && <div className="min-h-0 overflow-y-auto border-line bg-bg-subtle/60 px-4 py-5 md:border-l md:px-5">{previewPane}</div>}
        </div>
        <footer className="flex flex-wrap items-center gap-2 border-t border-line px-4 py-3 pb-[calc(12px+var(--safe-b))] md:px-6">
          {onDelete && <Button variant="ghost" className="text-danger" icon={<Trash2 className="size-4" aria-hidden />} onClick={onDelete}>{t('studio_tpl.delete')}</Button>}
          <div className="ml-auto flex gap-2">
            <Button variant="secondary" onClick={tryClose}>{t('common.cancel')}</Button>
            <Button variant="secondary" onClick={() => void saveDraft()} loading={f.saving === 'saving'}>{d.status === 'active' ? t('studio_tpl.save') : t('studio_tpl.save_draft')}</Button>
            {d.status !== 'active' && <Button onClick={() => void publish()} loading={f.saving === 'saving'} icon={<CheckCircle2 className="size-4" aria-hidden />}>{t('studio_tpl.publish')}</Button>}
          </div>
        </footer>
      </motion.div>
      <Modal open={confirmLeave} onClose={() => setConfirmLeave(false)} size="confirm" level={2} title={t('studio_tpl.leave_title')} footer={<><Button variant="secondary" onClick={() => setConfirmLeave(false)}>{t('studio_tpl.keep_editing')}</Button><Button variant="danger" onClick={() => { setConfirmLeave(false); onClose() }}>{t('studio_tpl.discard')}</Button></>}>
        <p className="t-body text-muted">{t('studio_tpl.leave_body')}</p>
      </Modal>
    </>,
    document.body,
  )
}

function ToggleChip({ on, onClick, children }: { on: boolean; onClick: () => void; children: React.ReactNode }) {
  return (
    <button type="button" role="switch" aria-checked={on} onClick={onClick} className={cn('t-label h-8 rounded-full border px-3 transition-[background-color,color,border-color] duration-[120ms]', on ? 'border-primary bg-primary text-primary-contrast' : 'border-line bg-surface text-fg hover:border-primary/50')}>
      {children}
    </button>
  )
}

function CustomBadge({ badges, onAdd, onRemove, reserved }: { badges: string[]; onAdd: (b: string) => void; onRemove: (b: string) => void; reserved: string[] }) {
  const { t } = useTranslation()
  const [v, setV] = useState('')
  const custom = badges.filter((b) => !reserved.includes(b))
  return (
    <>
      {custom.map((b) => (
        <span key={b} className="inline-flex h-8 items-center gap-1 rounded-full bg-surface-2 pl-3 pr-1 text-[13px]">
          {b}
          <button type="button" onClick={() => onRemove(b)} aria-label={t('common.remove')} className="grid size-6 place-items-center rounded-full hover:bg-surface"><X className="size-3" aria-hidden /></button>
        </span>
      ))}
      {badges.length < 4 && (
        <form onSubmit={(e) => { e.preventDefault(); const x = v.trim().toLowerCase().slice(0, 24); if (x && !badges.includes(x)) onAdd(x); setV('') }}>
          <input value={v} onChange={(e) => setV(e.target.value)} placeholder={t('studio_tpl.f.custom_badge')} aria-label={t('studio_tpl.f.custom_badge')} maxLength={24} className="h-8 w-36 rounded-full border border-dashed border-line bg-transparent px-3 text-[13px] focus:border-primary focus:outline-none" />
        </form>
      )}
    </>
  )
}

/** Galeri: seret untuk mengurutkan (gambar pertama = cover), klik cover saat mode fokus untuk menentukan titik fokus. */
function GalleryReorder({ gallery, setGallery, focus, focusMode, onFocus }: { gallery: string[]; setGallery: (g: string[]) => void; focus: { x: number; y: number } | null; focusMode: boolean; onFocus: (p: { x: number; y: number }) => void }) {
  const { t } = useTranslation()
  const [drag, setDrag] = useState<number | null>(null)
  return (
    <ul className="grid grid-cols-4 gap-2 sm:grid-cols-5">
      {gallery.map((u, i) => (
        <motion.li
          key={u}
          layout
          draggable={!focusMode}
          onDragStart={() => setDrag(i)}
          onDragOver={(e: unknown) => {
            ;(e as RDragEvent).preventDefault()
            if (drag === null || drag === i) return
            const g = [...gallery]
            const [m] = g.splice(drag, 1)
            g.splice(i, 0, m)
            setDrag(i)
            setGallery(g)
          }}
          onDragEnd={() => setDrag(null)}
          className={cn('group relative aspect-square overflow-hidden rounded-lg border bg-surface-2', i === 0 ? 'col-span-2 row-span-2 border-primary' : 'border-line', !focusMode && 'cursor-grab')}
        >
          <img
            src={u}
            alt=""
            className={cn('h-full w-full object-cover', focusMode && i === 0 && 'cursor-crosshair')}
            style={i === 0 && focus ? { objectPosition: `${focus.x}% ${focus.y}%` } : undefined}
            onClick={(e) => {
              if (!focusMode || i !== 0) return
              const r = e.currentTarget.getBoundingClientRect()
              onFocus({ x: Math.round(((e.clientX - r.left) / r.width) * 100), y: Math.round(((e.clientY - r.top) / r.height) * 100) })
            }}
            draggable={false}
          />
          {i === 0 && <span className="absolute left-1.5 top-1.5 rounded-full bg-primary px-2 py-0.5 text-[10px] font-bold text-primary-contrast">{t('studio_tpl.f.cover')}</span>}
          {i === 0 && focusMode && focus && <span className="pointer-events-none absolute size-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow" style={{ left: `${focus.x}%`, top: `${focus.y}%` }} aria-hidden />}
          <button type="button" onClick={() => setGallery(gallery.filter((x) => x !== u))} aria-label={t('common.remove')} className="absolute right-1 top-1 grid size-6 place-items-center rounded-full bg-black/60 text-white opacity-0 group-hover:opacity-100 focus:opacity-100"><X className="size-3.5" aria-hidden /></button>
        </motion.li>
      ))}
    </ul>
  )
}
