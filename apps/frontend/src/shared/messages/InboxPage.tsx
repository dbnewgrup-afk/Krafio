import { useEffect, useMemo, useRef, useState, type KeyboardEvent as RKeyboardEvent } from 'react'
import { useSearchParams } from 'react-router-dom'
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion'
import { useTranslation } from 'react-i18next'
import {
  AlertTriangle, Archive, ArrowLeft, Check, CheckCheck, ChevronDown, Clock, Download, Filter, Inbox, Loader2, MoreHorizontal, PanelRightClose, PanelRightOpen, Paperclip, Plus, RotateCw, Search, Send, ShieldBan, Smile, StickyNote, Tag, UserPlus, X, BellOff, Bell,
} from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { useIsDesktop, useIsMobile } from '@/shared/hooks/useMediaQuery'
import { useToast } from '@/shared/context/ToastContext'
import { PageHeader, Menu, MenuItem } from '@/shared/ui/Misc'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { ConfirmDialog } from '@/shared/ui/ConfirmDialog'
import { TextField, TextArea, inputCls } from '@/shared/ui/Field'
import { StatusChip } from '@/shared/ui/Status'
import { EmptyState, Skeleton } from '@/shared/ui/States'
import { CountUp } from '@/shared/motion/CountUp'
import { RevealCard } from '@/shared/motion/Reveal'
import { dateTime, relative, rupiah, number } from '@/shared/lib/format'
import { cn } from '@/shared/lib/cn'
import { useMessages } from './MessagesProvider'
import { Avatar } from './MessageToasts'
import { EMPTY_QUERY, useConversations, useThread, type ListQuery } from './useInbox'
import { basePath, type Area, type Conversation, type Message, type Priority, type Thread } from './types'

const PRIORITY_TONE: Record<Priority, string> = { low: 'bg-surface-2 text-muted', normal: 'bg-info/12 text-info', high: 'bg-warning/14 text-warning', urgent: 'bg-danger/12 text-danger' }
const EMOJI = ['🙏', '👍', '😊', '🎉', '✅', '⏳', '📎', '🔥']

/**
 * Pusat pesan. Admin: memantau & membalas semua percakapan (statistik, SLA, bulk, assign, catatan internal).
 * User: inbox tiket miliknya dengan CS. Desktop 3 panel; tablet detail menjadi drawer; mobile daftar & thread terpisah.
 */
export function InboxPage({ area }: { area: Area }) {
  const { t } = useTranslation()
  useDocTitle(t('inbox.title'))
  const isDesktop = useIsDesktop()
  const isMobile = useIsMobile()
  const [params, setParams] = useSearchParams()
  const [q, setQ] = useState<ListQuery>(EMPTY_QUERY)
  const list = useConversations(area, q)
  const activeId = Number(params.get('c')) || null
  const th = useThread(area, activeId)
  const [detail, setDetail] = useState(true)
  const [drawer, setDrawer] = useState(false)
  const [selected, setSelected] = useState<number[]>([])
  const [creating, setCreating] = useState(false)
  const composerRef = useRef<HTMLTextAreaElement>(null)
  const admin = area === 'admin'

  const select = (id: number | null) => {
    if (id) params.set('c', String(id))
    else params.delete('c')
    setParams(params, { replace: !!activeId && !!id })
  }

  // Thread terbuka → unread di daftar jadi 0 secara lokal
  useEffect(() => {
    if (th.thread) list.patchLocal(th.thread.conversation.id, { unread: 0 })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [th.thread?.conversation.id])

  // Navigasi keyboard: J/K pindah percakapan, R fokus balas, Esc tutup panel
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const el = e.target as HTMLElement
      if (el.closest('input, textarea, select, [contenteditable="true"]')) return
      const items = list.items ?? []
      const i = items.findIndex((c) => c.id === activeId)
      if (e.key === 'j' || e.key === 'k') {
        const n = e.key === 'j' ? Math.min(items.length - 1, i + 1) : Math.max(0, i - 1)
        if (items[n]) select(items[n].id)
      } else if (e.key === 'r' && activeId) {
        e.preventDefault()
        composerRef.current?.focus()
      } else if (e.key === 'Escape') {
        if (drawer) setDrawer(false)
        else if (isMobile && activeId) select(null)
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [list.items, activeId, drawer, isMobile])

  const filters = admin ? ['all', 'unread', 'mine', 'unassigned', 'resolved', 'archived'] : ['all', 'unread', 'open', 'resolved']
  const showList = !isMobile || !activeId
  const showThread = !isMobile || !!activeId

  return (
    <>
      <PageHeader
        title={admin ? t('inbox.admin_title') : t('inbox.user_title')}
        subtitle={admin ? t('inbox.admin_sub') : t('inbox.user_sub')}
        actions={!admin ? <Button icon={<Plus className="size-4" aria-hidden />} onClick={() => setCreating(true)}>{t('inbox.new_conversation')}</Button> : undefined}
      />
      {admin && <StatsStrip stats={list.stats} />}

      <motion.div
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.35, ease: [0.22, 1, 0.36, 1] }}
        className={cn('mt-4 grid overflow-hidden rounded-2xl border border-line bg-surface shadow-k-sm', 'h-[calc(100dvh-var(--nav-h)-var(--inbox-offset,190px))] min-h-[520px]', admin ? '[--inbox-offset:300px] max-md:[--inbox-offset:250px]' : '[--inbox-offset:200px]')}
        style={{ gridTemplateColumns: isMobile ? '1fr' : isDesktop && admin && activeId ? `minmax(260px,320px) minmax(0,1fr) ${detail ? '300px' : '0px'}` : 'minmax(260px,320px) minmax(0,1fr)', transition: 'grid-template-columns 240ms cubic-bezier(.2,.8,.2,1)' }}
      >
        {showList && (
          <ConversationList
            area={area}
            items={list.items}
            q={q}
            setQ={setQ}
            filters={filters}
            activeId={activeId}
            onSelect={select}
            selected={selected}
            setSelected={setSelected}
            admins={list.admins}
            tags={list.tags}
            onBulkDone={() => {
              setSelected([])
              void list.reload()
            }}
          />
        )}
        {showThread &&
          (activeId ? (
            <ChatThread
              area={area}
              th={th}
              composerRef={composerRef}
              onBack={isMobile ? () => select(null) : undefined}
              detailOpen={isDesktop ? detail : drawer}
              onToggleDetail={admin ? () => (isDesktop ? setDetail((d) => !d) : setDrawer(true)) : undefined}
              admins={list.admins}
              onChanged={(c) => list.patchLocal(c.id, c)}
            />
          ) : (
            <div className="hidden place-items-center border-l border-line bg-bg-subtle md:grid">
              <EmptyState kind="empty" title={t('inbox.pick_title')} body={t('inbox.pick_body')} className="border-0" />
            </div>
          ))}
        {admin && isDesktop && activeId && (
          <div className={cn('min-w-0 overflow-hidden border-l border-line', !detail && 'border-l-0')} aria-hidden={!detail}>
            {detail && th.thread && <DetailPanel th={th.thread} area={area} admins={list.admins} onChanged={(c) => { list.patchLocal(c.id, c); void th.reload() }} />}
          </div>
        )}
      </motion.div>

      {admin && !isDesktop && (
        <Modal open={drawer && !!th.thread} onClose={() => setDrawer(false)} size="form" title={t('inbox.details')}>
          {th.thread && <DetailPanel th={th.thread} area={area} admins={list.admins} onChanged={(c) => { list.patchLocal(c.id, c); void th.reload() }} />}
        </Modal>
      )}
      {!admin && <NewConversation open={creating} onClose={() => setCreating(false)} onCreated={(c) => { setCreating(false); void list.reload(); select(c.id) }} />}
    </>
  )
}

/* ------------------------------------------------------------------ */

function StatsStrip({ stats }: { stats: ReturnType<typeof useConversations>['stats'] }) {
  const { t } = useTranslation()
  const cards = [
    { k: 'unread', v: stats?.unread, tone: 'before:bg-primary' },
    { k: 'open', v: stats?.open, tone: 'before:bg-info' },
    { k: 'avg', v: stats?.avg_response_minutes, tone: 'before:bg-wallet-commission', suffix: ` ${t('inbox.minutes')}` },
    { k: 'resolved_today', v: stats?.resolved_today, tone: 'before:bg-success' },
  ]
  return (
    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
      {cards.map((c, i) => (
        <RevealCard key={c.k} order={i} className={cn('relative grid gap-0.5 overflow-hidden rounded-xl border border-line bg-surface p-4 pl-5 before:absolute before:inset-y-0 before:left-0 before:w-1', c.tone)}>
          <span className="t-small text-muted">{t(`inbox.stat.${c.k}`)}</span>
          <span className="text-[24px] font-bold leading-8 text-fg">{c.v == null ? (stats ? '—' : <Skeleton className="h-7 w-16" />) : <CountUp to={c.v} suffix={c.suffix} overshoot={i === 0} />}</span>
          {c.k === 'open' && !!stats?.sla_breached && (
            <span className="t-small inline-flex items-center gap-1 font-semibold text-danger">
              <AlertTriangle className="size-3.5" aria-hidden />
              {t('inbox.sla_breached_n', { n: stats.sla_breached })}
            </span>
          )}
        </RevealCard>
      ))}
    </div>
  )
}

/* ------------------------------------------------------------------ */

function ConversationList({
  area, items, q, setQ, filters, activeId, onSelect, selected, setSelected, admins, tags, onBulkDone,
}: {
  area: Area
  items: Conversation[] | null
  q: ListQuery
  setQ: (q: ListQuery) => void
  filters: string[]
  activeId: number | null
  onSelect: (id: number) => void
  selected: number[]
  setSelected: (ids: number[]) => void
  admins: { id: number; name: string }[]
  tags: string[]
  onBulkDone: () => void
}) {
  const { t } = useTranslation()
  const toast = useToast()
  const reduced = useReducedMotion()
  const admin = area === 'admin'
  const [adv, setAdv] = useState(false)
  const [busy, setBusy] = useState(false)
  const advCount = ['priority', 'assignee', 'tag', 'from', 'to'].filter((k) => q[k as keyof ListQuery]).length + (q.sort !== 'recent' ? 1 : 0)

  const bulk = async (action: string, extra: Record<string, unknown> = {}) => {
    setBusy(true)
    try {
      const r = await http.post<{ data: { affected: number } }>('/admin/inbox/bulk', { ids: selected, action, ...extra })
      toast.push('success', t('inbox.bulk_done', { n: r.data.affected }))
      onBulkDone()
    } catch {
      toast.push('error', t('errors.server_title'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="flex min-h-0 min-w-0 flex-col border-line md:border-r">
      <div className="grid gap-2 border-b border-line p-3">
        <div className="flex gap-2">
          <div className="relative min-w-0 flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" aria-hidden />
            <input type="search" value={q.search} onChange={(e) => setQ({ ...q, search: e.target.value })} placeholder={t('inbox.search_ph')} aria-label={t('inbox.search_ph')} className={cn(inputCls, 'h-10 pl-9 lg:h-9')} />
          </div>
          {admin && (
            <button type="button" onClick={() => setAdv((a) => !a)} aria-expanded={adv} aria-label={t('inbox.advanced')} className={cn('relative grid size-10 shrink-0 place-items-center rounded-md border lg:size-9', adv || advCount ? 'border-primary text-primary' : 'border-line text-muted hover:text-fg')}>
              <Filter className="size-4" aria-hidden />
              {advCount > 0 && <span className="absolute -right-1 -top-1 grid size-4 place-items-center rounded-full bg-primary text-[10px] font-bold text-primary-contrast">{advCount}</span>}
            </button>
          )}
        </div>
        <div className="snap-row -mx-3 gap-1.5 px-3" role="radiogroup" aria-label={t('inbox.filter')}>
          {filters.map((f) => (
            <button key={f} type="button" role="radio" aria-checked={q.filter === f} onClick={() => setQ({ ...q, filter: f })} className={cn('t-label h-8 whitespace-nowrap rounded-full border px-3 transition-[background-color,color,border-color] duration-[120ms]', q.filter === f ? 'border-primary bg-primary text-primary-contrast' : 'border-line bg-surface text-fg hover:border-primary/50')}>
              {t(`inbox.f.${f}`)}
            </button>
          ))}
        </div>
        <AnimatePresence initial={false}>
          {admin && adv && (
            <motion.div initial={{ height: 0, opacity: 0 }} animate={{ height: 'auto', opacity: 1 }} exit={{ height: 0, opacity: 0 }} className="grid grid-cols-2 gap-2 overflow-hidden">
              <select aria-label={t('inbox.sort')} value={q.sort} onChange={(e) => setQ({ ...q, sort: e.target.value })} className={cn(inputCls, 'h-9 text-sm lg:h-9')}>
                {['recent', 'oldest', 'priority'].map((s) => <option key={s} value={s}>{t(`inbox.sort_${s}`)}</option>)}
              </select>
              <select aria-label={t('inbox.priority')} value={q.priority} onChange={(e) => setQ({ ...q, priority: e.target.value })} className={cn(inputCls, 'h-9 text-sm lg:h-9')}>
                <option value="">{t('inbox.any_priority')}</option>
                {(['urgent', 'high', 'normal', 'low'] as Priority[]).map((p) => <option key={p} value={p}>{t(`inbox.p.${p}`)}</option>)}
              </select>
              <select aria-label={t('inbox.assignee')} value={q.assignee} onChange={(e) => setQ({ ...q, assignee: e.target.value })} className={cn(inputCls, 'h-9 text-sm lg:h-9')}>
                <option value="">{t('inbox.any_assignee')}</option>
                <option value="none">{t('inbox.unassigned')}</option>
                {admins.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
              </select>
              <select aria-label={t('inbox.tag')} value={q.tag} onChange={(e) => setQ({ ...q, tag: e.target.value })} className={cn(inputCls, 'h-9 text-sm lg:h-9')}>
                <option value="">{t('inbox.any_tag')}</option>
                {tags.map((x) => <option key={x} value={x}>#{x}</option>)}
              </select>
              <input type="date" aria-label={t('inbox.from')} value={q.from} onChange={(e) => setQ({ ...q, from: e.target.value })} className={cn(inputCls, 'h-9 text-sm lg:h-9')} />
              <input type="date" aria-label={t('inbox.to')} value={q.to} onChange={(e) => setQ({ ...q, to: e.target.value })} className={cn(inputCls, 'h-9 text-sm lg:h-9')} />
              {advCount > 0 && <button type="button" onClick={() => setQ({ ...EMPTY_QUERY, filter: q.filter, search: q.search })} className="t-label col-span-2 justify-self-start text-primary hover:underline">{t('table.reset_filter')}</button>}
            </motion.div>
          )}
        </AnimatePresence>
      </div>

      {/* Toolbar aksi massal */}
      <AnimatePresence>
        {admin && selected.length > 0 && (
          <motion.div initial={{ opacity: 0, y: -6 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -6 }} className="flex flex-wrap items-center gap-1.5 border-b border-line bg-primary-soft px-3 py-2">
            <span className="t-label mr-auto text-primary">{t('inbox.selected', { n: selected.length })}</span>
            <BulkBtn icon={CheckCheck} label={t('inbox.mark_read')} onClick={() => bulk('read')} disabled={busy} />
            <Menu label={t('inbox.assign')} align="right" width="w-56" trigger={({ toggle, open, id }) => <BulkBtn icon={UserPlus} label={t('inbox.assign')} onClick={toggle} expanded={open} controls={id} disabled={busy} />}>
              {(close) => (
                <>
                  {admins.map((a) => <MenuItem key={a.id} onClick={() => { close(); void bulk('assign', { assignee_id: a.id }) }}>{a.name}</MenuItem>)}
                  <MenuItem onClick={() => { close(); void bulk('assign', { assignee_id: null }) }}>{t('inbox.unassigned')}</MenuItem>
                </>
              )}
            </Menu>
            <BulkBtn icon={Check} label={t('inbox.resolve')} onClick={() => bulk('resolve')} disabled={busy} />
            <BulkBtn icon={Archive} label={t('inbox.archive')} onClick={() => bulk('archive')} disabled={busy} />
            <button type="button" onClick={() => setSelected([])} aria-label={t('common.cancel')} className="grid size-8 place-items-center rounded-md text-muted hover:bg-surface hover:text-fg"><X className="size-4" aria-hidden /></button>
          </motion.div>
        )}
      </AnimatePresence>

      <div className="min-h-0 flex-1 overflow-y-auto" role="listbox" aria-label={t('inbox.conversations')}>
        {!items ? (
          <div className="grid gap-1 p-2">{Array.from({ length: 7 }).map((_, i) => <Skeleton key={i} className="h-[68px]" />)}</div>
        ) : items.length === 0 ? (
          <EmptyState kind={q.search || q.filter !== 'all' ? 'filter' : 'empty'} title={q.search || q.filter !== 'all' ? t('table.no_results') : t('inbox.empty_title')} body={q.search || q.filter !== 'all' ? undefined : admin ? t('inbox.empty_admin') : t('inbox.empty_user')} className="m-3 border-0" />
        ) : (
          <motion.ul initial="hidden" animate="show" variants={{ hidden: {}, show: { transition: { staggerChildren: reduced ? 0 : 0.04 } } }}>
            {items.map((c) => (
              <ConversationRow key={c.id} c={c} area={area} active={c.id === activeId} onSelect={() => onSelect(c.id)} checked={selected.includes(c.id)} onCheck={admin ? (v) => setSelected(v ? [...selected, c.id] : selected.filter((x) => x !== c.id)) : undefined} />
            ))}
          </motion.ul>
        )}
      </div>
    </div>
  )
}

function BulkBtn({ icon: Icon, label, onClick, disabled, expanded, controls }: { icon: typeof Check; label: string; onClick: () => void; disabled?: boolean; expanded?: boolean; controls?: string }) {
  return (
    <button type="button" onClick={onClick} disabled={disabled} aria-expanded={expanded} aria-controls={controls} title={label} className="inline-flex h-8 items-center gap-1 rounded-md bg-surface px-2 text-[12.5px] font-medium text-fg shadow-k-sm hover:bg-surface-2 disabled:opacity-50">
      <Icon className="size-3.5" aria-hidden />
      <span className="max-xl:sr-only">{label}</span>
    </button>
  )
}

function ConversationRow({ c, area, active, onSelect, checked, onCheck }: { c: Conversation; area: Area; active: boolean; onSelect: () => void; checked: boolean; onCheck?: (v: boolean) => void }) {
  const { t } = useTranslation()
  const name = area === 'admin' ? c.requester.name : c.assignee?.name ?? t('inbox.team')
  return (
    <motion.li variants={{ hidden: { opacity: 0, y: 6 }, show: { opacity: 1, y: 0 } }} className={cn('group relative border-b border-line/70', active ? 'bg-primary-soft' : 'hover:bg-surface-2')}>
      {active && <span className="absolute inset-y-2 left-0 w-[3px] rounded-r bg-primary" aria-hidden />}
      <div className="flex items-start gap-2.5 px-3 py-3">
        {onCheck && (
          <input type="checkbox" checked={checked} onChange={(e) => onCheck(e.target.checked)} aria-label={t('inbox.select_conv', { name })} className={cn('mt-2.5 size-4 shrink-0 accent-[var(--primary)]', !checked && 'opacity-0 group-hover:opacity-100 focus:opacity-100')} />
        )}
        <button type="button" role="option" aria-selected={active} onClick={onSelect} className="flex min-w-0 flex-1 items-start gap-2.5 text-left">
          <Avatar name={name} size={36} online={area === 'admin' ? c.requester.online : undefined} />
          <span className="grid min-w-0 flex-1 gap-0.5">
            <span className="flex items-baseline gap-2">
              <span className={cn('truncate text-sm text-fg', c.unread ? 'font-bold' : 'font-semibold')}>{name}</span>
              <span className="t-small ml-auto shrink-0 text-muted">{relative(c.last_message_at)}</span>
            </span>
            <span className="truncate text-[13px] text-fg/90">{c.subject}</span>
            <span className={cn('truncate text-[13px]', c.unread ? 'font-medium text-fg' : 'text-muted')}>
              {c.last_from === (area === 'admin' ? 'admin' : 'user') && <span className="text-muted">{t('inbox.you')}: </span>}
              {c.preview}
            </span>
            <span className="mt-1 flex flex-wrap items-center gap-1">
              {c.sla_breached && (
                <span className="inline-flex h-5 items-center gap-1 rounded-full bg-danger/12 px-1.5 text-[11px] font-semibold text-danger">
                  <Clock className="size-3" aria-hidden />
                  {t('inbox.sla_late')}
                </span>
              )}
              {area === 'admin' && c.priority !== 'normal' && <span className={cn('h-5 rounded-full px-1.5 text-[11px] font-semibold leading-5', PRIORITY_TONE[c.priority])}>{t(`inbox.p.${c.priority}`)}</span>}
              <StatusChip status={c.status} label={t(`inbox.s.${c.status}`)} />
              {c.tags.slice(0, 2).map((x) => <span key={x} className="text-[11px] text-muted">#{x}</span>)}
            </span>
          </span>
          {c.unread > 0 && (
            <motion.span key={c.unread} initial={{ scale: 1 }} animate={{ scale: [1, 1.2, 1] }} transition={{ duration: 0.3 }} className="mt-1 min-w-5 rounded-full bg-primary px-1.5 text-center text-[11px] font-semibold leading-5 text-primary-contrast tabular-nums">
              {c.unread}
            </motion.span>
          )}
        </button>
      </div>
    </motion.li>
  )
}

/* ------------------------------------------------------------------ */

function dayLabel(iso: string, t: (k: string) => string) {
  const d = new Date(iso)
  const today = new Date()
  const y = new Date()
  y.setDate(today.getDate() - 1)
  if (d.toDateString() === today.toDateString()) return t('inbox.today')
  if (d.toDateString() === y.toDateString()) return t('inbox.yesterday')
  return dateTime(iso, false)
}

function ChatThread({
  area, th, composerRef, onBack, detailOpen, onToggleDetail, admins, onChanged,
}: {
  area: Area
  th: ReturnType<typeof useThread>
  composerRef: React.RefObject<HTMLTextAreaElement | null>
  onBack?: () => void
  detailOpen: boolean
  onToggleDetail?: () => void
  admins: { id: number; name: string }[]
  onChanged: (c: Conversation) => void
}) {
  const { t } = useTranslation()
  const reduced = useReducedMotion()
  const toast = useToast()
  const { settings, setSettings } = useMessages()
  const scroller = useRef<HTMLDivElement>(null)
  const thread = th.thread
  const admin = area === 'admin'
  const mine = admin ? 'admin' : 'user'
  const [blocking, setBlocking] = useState(false)

  const groups = useMemo(() => {
    const out: { day: string; items: Message[] }[] = []
    for (const m of thread?.messages ?? []) {
      const day = new Date(m.created_at).toDateString()
      if (!out.length || out[out.length - 1].day !== day) out.push({ day, items: [m] })
      else out[out.length - 1].items.push(m)
    }
    return out
  }, [thread?.messages])

  // Gulir halus ke pesan terbaru
  const count = thread?.messages.length ?? 0
  useEffect(() => {
    const el = scroller.current
    if (!el) return
    el.scrollTo({ top: el.scrollHeight, behavior: reduced || count < 2 ? 'auto' : 'smooth' })
  }, [count, thread?.typing, reduced])

  if (th.error && !thread) return <div className="grid place-items-center border-l border-line"><EmptyState kind="error" title={t('errors.server_title')} action={<Button onClick={() => void th.reload()}>{t('common.retry')}</Button>} className="border-0" /></div>
  if (!thread) return <div className="grid content-start gap-3 border-l border-line p-4">{Array.from({ length: 5 }).map((_, i) => <Skeleton key={i} className={cn('h-14', i % 2 ? 'ml-auto w-2/3' : 'w-3/4')} />)}</div>

  const c = thread.conversation
  const name = admin ? c.requester.name : c.assignee?.name ?? t('inbox.team')
  const muted = settings.muted.includes(c.id)

  const update = async (patch: Record<string, unknown>) => {
    try {
      const r = await http.patch<{ data: Conversation }>(`/admin/inbox/${c.id}`, patch)
      onChanged(r.data)
      th.setThread((x) => (x ? { ...x, conversation: r.data } : x))
      toast.push('success', t('inbox.updated'))
    } catch {
      toast.push('error', t('errors.server_title'))
    }
  }

  return (
    <motion.section key={c.id} initial={reduced ? false : { opacity: 0, x: 8 }} animate={{ opacity: 1, x: 0 }} transition={{ duration: 0.22 }} className="flex min-h-0 min-w-0 flex-col md:border-l md:border-line" aria-label={t('inbox.thread_with', { name })}>
      {/* Header */}
      <header className="flex items-center gap-2 border-b border-line px-3 py-2.5 md:px-4">
        {onBack && (
          <button type="button" onClick={onBack} aria-label={t('common.back')} className="grid size-10 place-items-center rounded-md hover:bg-surface-2"><ArrowLeft className="size-5" aria-hidden /></button>
        )}
        <Avatar name={name} size={38} online={admin ? c.requester.online : undefined} />
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-semibold text-fg">{name}</p>
          <p className="t-small truncate text-muted">
            {thread.typing ? <span className="text-primary">{t('inbox.typing')}</span> : admin ? (c.requester.online ? t('inbox.online') : c.requester.email ?? t('inbox.guest')) : `${c.number} · ${c.subject}`}
          </p>
        </div>
        {admin ? (
          <div className="flex items-center gap-1">
            <Menu label={t('inbox.assign')} width="w-56" trigger={({ toggle, open, id }) => <HeaderBtn icon={UserPlus} label={c.assignee ? t('inbox.assigned_to', { name: c.assignee.name }) : t('inbox.assign')} onClick={toggle} expanded={open} controls={id} />}>
              {(close) => (
                <>
                  {admins.map((a) => <MenuItem key={a.id} active={c.assignee?.id === a.id} onClick={() => { close(); void update({ assignee_id: a.id }) }}>{a.name}</MenuItem>)}
                  <MenuItem onClick={() => { close(); void update({ assignee_id: null }) }}>{t('inbox.unassigned')}</MenuItem>
                </>
              )}
            </Menu>
            {c.status === 'resolved' || c.status === 'closed' ? (
              <HeaderBtn icon={RotateCw} label={t('inbox.reopen')} onClick={() => void update({ status: 'open' })} />
            ) : (
              <HeaderBtn icon={Check} label={t('inbox.resolve')} onClick={() => void update({ status: 'resolved' })} primary />
            )}
            <Menu label={t('inbox.more_actions')} width="w-60" trigger={({ toggle, open, id }) => <HeaderBtn icon={MoreHorizontal} label={t('inbox.more_actions')} onClick={toggle} expanded={open} controls={id} />}>
              {(close) => (
                <>
                  {(['urgent', 'high', 'normal', 'low'] as Priority[]).map((p) => <MenuItem key={p} active={c.priority === p} onClick={() => { close(); void update({ priority: p }) }}>{t('inbox.set_priority', { p: t(`inbox.p.${p}`) })}</MenuItem>)}
                  <MenuItem icon={muted ? <Bell className="size-4" /> : <BellOff className="size-4" />} onClick={() => { close(); setSettings({ muted: muted ? settings.muted.filter((x) => x !== c.id) : [...settings.muted, c.id] }) }}>{muted ? t('inbox.unmute') : t('inbox.mute')}</MenuItem>
                  <MenuItem icon={<Archive className="size-4" />} onClick={() => { close(); void update({ archived: !c.archived }) }}>{c.archived ? t('inbox.unarchive') : t('inbox.archive')}</MenuItem>
                  <MenuItem icon={<Download className="size-4" />} onClick={() => { close(); void exportChat(c.id, c.number) }}>{t('inbox.export')}</MenuItem>
                  {c.requester.id && <MenuItem danger icon={<ShieldBan className="size-4" />} onClick={() => { close(); setBlocking(true) }}>{t('inbox.block_user')}</MenuItem>}
                </>
              )}
            </Menu>
            {onToggleDetail && <HeaderBtn icon={detailOpen ? PanelRightClose : PanelRightOpen} label={t('inbox.details')} onClick={onToggleDetail} expanded={detailOpen} />}
          </div>
        ) : (
          <StatusChip status={c.status} label={t(`inbox.s.${c.status}`)} />
        )}
      </header>

      {/* Pesan */}
      <div ref={scroller} className="min-h-0 flex-1 overflow-y-auto bg-bg-subtle px-3 py-4 md:px-5" aria-live="polite" aria-relevant="additions">
        {groups.map((g) => (
          <div key={g.day} className="grid gap-2">
            <p className="t-small sticky top-0 z-sticky mx-auto my-2 rounded-full bg-surface px-3 py-0.5 text-muted shadow-k-sm">{dayLabel(g.items[0].created_at, t)}</p>
            {g.items.map((m, i) => (
              <MessageBubble key={m.client_id ?? m.id} m={m} own={m.from === mine} grouped={i > 0 && g.items[i - 1].from === m.from} onRetry={() => void th.send(m.body, m.attachments, m.client_id ?? undefined)} />
            ))}
          </div>
        ))}
        <AnimatePresence>{thread.typing && <TypingDots key="typing" />}</AnimatePresence>
      </div>

      {c.status === 'closed' && !admin ? (
        <p className="t-small border-t border-line bg-surface p-3 text-center text-muted">{t('inbox.closed_note')}</p>
      ) : (
        <Composer area={area} innerRef={composerRef} onSend={(body, att) => th.send(body, att)} onTyping={th.typing} />
      )}

      {admin && c.requester.id && (
        <ConfirmDialog
          open={blocking}
          onClose={() => setBlocking(false)}
          destructive
          requireReason
          title={t('inbox.block_title', { name })}
          body={t('inbox.block_body')}
          confirmLabel={t('inbox.block_user')}
          onConfirm={async (reason) => {
            await http.post(`/admin/users/${c.requester.id}/status`, { status: 'suspended', reason })
            toast.push('success', t('inbox.blocked'))
            setBlocking(false)
          }}
        />
      )}
    </motion.section>
  )
}

async function exportChat(id: number, number: string) {
  const res = await fetch(`/api/admin/inbox/${id}/export`, { credentials: 'include', headers: { Accept: 'text/plain' } })
  const blob = await res.blob()
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `${number}.txt`
  a.click()
  window.setTimeout(() => URL.revokeObjectURL(a.href), 2000)
}

function HeaderBtn({ icon: Icon, label, onClick, expanded, controls, primary }: { icon: typeof Check; label: string; onClick: () => void; expanded?: boolean; controls?: string; primary?: boolean }) {
  return (
    <button type="button" onClick={onClick} aria-label={label} title={label} aria-expanded={expanded} aria-controls={controls} className={cn('inline-flex h-9 items-center gap-1.5 rounded-md px-2.5 text-[13px] font-medium', primary ? 'bg-primary text-primary-contrast hover:bg-primary-hover' : 'text-fg hover:bg-surface-2')}>
      <Icon className="size-4" aria-hidden />
      <span className={primary ? 'max-xl:sr-only' : 'sr-only'}>{label}</span>
    </button>
  )
}

function MessageBubble({ m, own, grouped, onRetry }: { m: Message; own: boolean; grouped: boolean; onRetry: () => void }) {
  const { t } = useTranslation()
  const reduced = useReducedMotion()
  const time = new Date(m.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
  if (m.from === 'system') return <p className="t-small mx-auto max-w-sm text-center text-muted">{m.body}</p>
  return (
    <motion.div initial={reduced ? false : { opacity: 0, scale: 0.96, y: 6 }} animate={{ opacity: 1, scale: 1, y: 0 }} transition={{ duration: 0.2 }} className={cn('flex', own ? 'justify-end' : 'justify-start', grouped ? '-mt-1' : 'mt-1')} style={{ transformOrigin: own ? 'bottom right' : 'bottom left' }}>
      <div className={cn('max-w-[min(78%,560px)] rounded-2xl px-3.5 py-2 shadow-k-sm', own ? 'rounded-br-md bg-primary text-primary-contrast' : 'rounded-bl-md border border-line bg-surface text-fg', m.status === 'failed' && 'ring-2 ring-danger/50')}>
        {!own && !grouped && m.author && <p className="mb-0.5 text-[12px] font-semibold opacity-80">{m.author}</p>}
        <p className="whitespace-pre-wrap break-words text-[14.5px] leading-[1.45]">{m.body}</p>
        {m.attachments.length > 0 && (
          <div className="mt-2 grid grid-cols-2 gap-1.5">
            {m.attachments.map((a) => (
              <a key={a.url} href={a.url} target="_blank" rel="noopener noreferrer" className="block overflow-hidden rounded-lg bg-black/10">
                <img src={a.url} alt={a.name ?? ''} loading="lazy" className="aspect-square w-full object-cover" />
              </a>
            ))}
          </div>
        )}
        <p className={cn('mt-1 flex items-center justify-end gap-1 text-[11px]', own ? 'text-primary-contrast/75' : 'text-muted')}>
          {time}
          {own && <MsgStatusIcon s={m.status} />}
        </p>
        {m.status === 'failed' && (
          <button type="button" onClick={onRetry} className="mt-1 inline-flex items-center gap-1 rounded-md bg-white/90 px-2 py-0.5 text-[12px] font-semibold text-danger">
            <RotateCw className="size-3" aria-hidden />
            {t('inbox.retry')}
          </button>
        )}
      </div>
    </motion.div>
  )
}

function MsgStatusIcon({ s }: { s: Message['status'] }) {
  const { t } = useTranslation()
  if (s === 'sending') return <Clock className="size-3" aria-label={t('inbox.ms.sending')} />
  if (s === 'failed') return <AlertTriangle className="size-3" aria-label={t('inbox.ms.failed')} />
  if (s === 'read') return <CheckCheck className="size-3.5" aria-label={t('inbox.ms.read')} />
  if (s === 'delivered') return <CheckCheck className="size-3.5 opacity-60" aria-label={t('inbox.ms.delivered')} />
  return <Check className="size-3" aria-label={t('inbox.ms.sent')} />
}

function TypingDots() {
  const { t } = useTranslation()
  const reduced = useReducedMotion()
  return (
    <motion.div initial={{ opacity: 0, y: 6 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0 }} className="mt-2 flex">
      <span className="inline-flex items-center gap-1 rounded-2xl rounded-bl-md border border-line bg-surface px-3.5 py-3" role="status" aria-label={t('inbox.typing')}>
        {[0, 1, 2].map((i) => (
          <motion.span key={i} className="size-1.5 rounded-full bg-muted" animate={reduced ? undefined : { y: [0, -4, 0], opacity: [0.5, 1, 0.5] }} transition={{ duration: 0.9, repeat: Infinity, delay: i * 0.15 }} />
        ))}
      </span>
    </motion.div>
  )
}

/* ------------------------------------------------------------------ */

function Composer({ area, innerRef, onSend, onTyping }: { area: Area; innerRef: React.RefObject<HTMLTextAreaElement | null>; onSend: (body: string, att: { url: string; name?: string }[]) => Promise<void>; onTyping: () => void }) {
  const { t } = useTranslation()
  const toast = useToast()
  const [text, setText] = useState('')
  const [att, setAtt] = useState<{ url: string; name?: string }[]>([])
  const [uploading, setUploading] = useState(false)
  const [emoji, setEmoji] = useState(false)
  const [canned, setCanned] = useState<{ key: string; title: string; body: string }[] | null>(null)
  const [slashIdx, setSlashIdx] = useState(0)
  const fileRef = useRef<HTMLInputElement>(null)
  const admin = area === 'admin'

  useEffect(() => {
    if (!admin) return
    http.get<{ data: { key: string; title: string; body: string }[] }>('/admin/inbox/canned').then((r) => setCanned(r.data)).catch(() => setCanned([]))
  }, [admin])

  // Tinggi textarea otomatis (maks ±8 baris)
  useEffect(() => {
    const el = innerRef.current
    if (!el) return
    el.style.height = 'auto'
    el.style.height = `${Math.min(220, el.scrollHeight)}px`
  }, [text, innerRef])

  const slash = admin && text.startsWith('/') ? (canned ?? []).filter((c) => `/${c.key} ${c.title}`.toLowerCase().includes(text.slice(1).toLowerCase())) : []

  const submit = async () => {
    const body = text.trim()
    if (!body && !att.length) return
    setText('')
    const a = att
    setAtt([])
    await onSend(body || t('inbox.attachment_only'), a)
    innerRef.current?.focus()
  }

  const upload = async (files: FileList | null) => {
    if (!files?.length) return
    setUploading(true)
    try {
      for (const f of Array.from(files).slice(0, 5 - att.length)) {
        const fd = new FormData()
        fd.append('file', f)
        const r = await http.post<{ data: { url: string; name: string } }>(admin ? '/admin/media' : '/app/inbox-attachments', fd)
        setAtt((x) => [...x, { url: r.data.url, name: r.data.name }])
      }
    } catch (e) {
      toast.push('error', e instanceof ApiError ? e.message : t('errors.server_title'))
    } finally {
      setUploading(false)
      if (fileRef.current) fileRef.current.value = ''
    }
  }

  const onKey = (e: RKeyboardEvent<HTMLTextAreaElement>) => {
    if (slash.length) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault()
        setSlashIdx((i) => (i + (e.key === 'ArrowDown' ? 1 : slash.length - 1)) % slash.length)
        return
      }
      if (e.key === 'Enter' || e.key === 'Tab') {
        e.preventDefault()
        setText(slash[Math.min(slashIdx, slash.length - 1)].body)
        return
      }
    }
    if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
      e.preventDefault()
      void submit()
    }
  }

  return (
    <div className="relative border-t border-line bg-surface p-2.5 md:p-3">
      <AnimatePresence>
        {slash.length > 0 && (
          <motion.ul initial={{ opacity: 0, y: 6 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0 }} className="absolute inset-x-3 bottom-full z-popover mb-2 max-h-60 overflow-y-auto rounded-xl border border-line bg-surface p-1 shadow-k-lg" role="listbox" aria-label={t('inbox.canned')}>
            {slash.map((c, i) => (
              <li key={c.key} role="option" aria-selected={i === slashIdx}>
                <button type="button" onMouseDown={(e) => { e.preventDefault(); setText(c.body) }} className={cn('grid w-full gap-0.5 rounded-lg px-3 py-2 text-left', i === slashIdx ? 'bg-primary-soft' : 'hover:bg-surface-2')}>
                  <span className="text-sm font-semibold text-fg">/{c.key} · {c.title}</span>
                  <span className="t-small line-clamp-1 text-muted">{c.body}</span>
                </button>
              </li>
            ))}
          </motion.ul>
        )}
      </AnimatePresence>
      {att.length > 0 && (
        <div className="mb-2 flex flex-wrap gap-2">
          {att.map((a) => (
            <span key={a.url} className="relative">
              <img src={a.url} alt={a.name ?? ''} className="size-14 rounded-lg border border-line object-cover" />
              <button type="button" onClick={() => setAtt((x) => x.filter((y) => y.url !== a.url))} aria-label={t('common.remove')} className="absolute -right-1.5 -top-1.5 grid size-5 place-items-center rounded-full bg-fg text-bg"><X className="size-3" aria-hidden /></button>
            </span>
          ))}
        </div>
      )}
      <div className="flex items-end gap-1.5 rounded-xl border border-line bg-bg-subtle p-1.5 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
        <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden onChange={(e) => void upload(e.target.files)} />
        <button type="button" onClick={() => fileRef.current?.click()} aria-label={t('inbox.attach')} disabled={uploading || att.length >= 5} className="grid size-9 shrink-0 place-items-center rounded-lg text-muted hover:bg-surface hover:text-fg disabled:opacity-50">
          {uploading ? <Loader2 className="size-4 animate-spin" aria-hidden /> : <Paperclip className="size-4" aria-hidden />}
        </button>
        <div className="relative">
          <button type="button" onClick={() => setEmoji((x) => !x)} aria-expanded={emoji} aria-label={t('inbox.emoji')} className="grid size-9 shrink-0 place-items-center rounded-lg text-muted hover:bg-surface hover:text-fg">
            <Smile className="size-4" aria-hidden />
          </button>
          {emoji && (
            <div className="absolute bottom-full left-0 z-popover mb-2 grid grid-cols-4 gap-1 rounded-xl border border-line bg-surface p-1.5 shadow-k-lg">
              {EMOJI.map((x) => <button key={x} type="button" onClick={() => { setText((s) => s + x); setEmoji(false); innerRef.current?.focus() }} className="grid size-9 place-items-center rounded-md text-lg hover:bg-surface-2" aria-label={x}>{x}</button>)}
            </div>
          )}
        </div>
        <textarea
          ref={innerRef}
          rows={1}
          value={text}
          onChange={(e) => {
            setText(e.target.value)
            setSlashIdx(0)
            onTyping()
          }}
          onKeyDown={onKey}
          placeholder={admin ? t('inbox.composer_ph_admin') : t('inbox.composer_ph')}
          aria-label={t('inbox.composer')}
          maxLength={5000}
          className="max-h-[220px] min-h-9 min-w-0 flex-1 resize-none bg-transparent px-1.5 py-2 text-[15px] leading-5 text-fg placeholder:text-muted/70 focus:outline-none"
        />
        <motion.button type="button" whileTap={{ scale: 0.94 }} onClick={() => void submit()} disabled={!text.trim() && !att.length} aria-label={t('inbox.send')} className="grid size-9 shrink-0 place-items-center rounded-lg bg-primary text-primary-contrast disabled:opacity-40">
          <Send className="size-4" aria-hidden />
        </motion.button>
      </div>
      <p className="t-small mt-1.5 hidden px-1 text-muted md:block">{admin ? t('inbox.composer_hint_admin') : t('inbox.composer_hint')}</p>
    </div>
  )
}

/* ------------------------------------------------------------------ */

function DetailPanel({ th, area, admins, onChanged }: { th: Thread; area: Area; admins: { id: number; name: string }[]; onChanged: (c: Conversation) => void }) {
  const { t } = useTranslation()
  const toast = useToast()
  const c = th.conversation
  const p = th.profile
  const [note, setNote] = useState('')
  const [tagInput, setTagInput] = useState('')
  const [tab, setTab] = useState<'info' | 'activity'>('info')

  const update = async (patch: Record<string, unknown>) => {
    try {
      const r = await http.patch<{ data: Conversation }>(`${basePath(area)}/${c.id}`, patch)
      onChanged(r.data)
    } catch {
      toast.push('error', t('errors.server_title'))
    }
  }

  return (
    <aside className="grid h-full min-w-0 content-start gap-4 overflow-y-auto p-4 [&_h3]:t-label [&_h3]:text-muted" aria-label={t('inbox.details')}>
      {/* Profil */}
      <div className="grid justify-items-center gap-2 text-center">
        <Avatar name={p?.name ?? c.requester.name} size={56} online={c.requester.online} />
        <div className="min-w-0">
          <p className="truncate font-semibold text-fg">{p?.name ?? c.requester.name}</p>
          <p className="t-small truncate text-muted">{p?.email ?? '—'}</p>
        </div>
        {p?.guest ? <StatusChip status="inactive" label={t('inbox.guest')} /> : p?.status && <StatusChip status={p.status} />}
      </div>
      {!p?.guest && p && (
        <dl className="grid grid-cols-2 gap-2 text-center">
          {[
            [t('inbox.plan'), p.plan ?? '—'],
            [t('inbox.orders'), number(p.orders ?? 0)],
            [t('inbox.balance'), rupiah(p.topup_balance ?? 0)],
            [t('inbox.tickets'), number(p.tickets ?? 0)],
          ].map(([k, v]) => (
            <div key={k} className="rounded-lg bg-bg-subtle px-2 py-2">
              <dt className="t-small text-muted">{k}</dt>
              <dd className="truncate text-sm font-semibold tabular-nums text-fg">{v}</dd>
            </div>
          ))}
        </dl>
      )}
      {p?.joined_at && <p className="t-small text-muted">{t('inbox.joined', { date: dateTime(p.joined_at, false) })}{p.last_login_at ? ` · ${t('inbox.last_seen', { when: relative(p.last_login_at) })}` : ''}</p>}
      {th.page && <p className="t-small break-all text-muted">{t('inbox.from_page')}: <span className="text-fg">{th.page}</span></p>}

      <div className="flex gap-1 rounded-md bg-surface-2 p-1" role="tablist">
        {(['info', 'activity'] as const).map((k) => <button key={k} type="button" role="tab" aria-selected={tab === k} onClick={() => setTab(k)} className={cn('t-label h-8 flex-1 rounded-sm', tab === k ? 'bg-surface text-fg shadow-k-sm' : 'text-muted')}>{t(`inbox.tab_${k}`)}</button>)}
      </div>

      {tab === 'info' ? (
        <>
          <section className="grid gap-2">
            <h3>{t('inbox.status')}</h3>
            <div className="grid grid-cols-3 gap-1">
              {(['open', 'pending', 'resolved'] as const).map((s) => (
                <button key={s} type="button" onClick={() => void update({ status: s })} aria-pressed={c.status === s} className={cn('t-label h-9 rounded-md border', c.status === s ? 'border-primary bg-primary-soft text-primary' : 'border-line text-fg hover:bg-surface-2')}>{t(`inbox.s.${s}`)}</button>
              ))}
            </div>
          </section>
          <section className="grid gap-2">
            <h3>{t('inbox.priority')}</h3>
            <div className="grid grid-cols-4 gap-1">
              {(['low', 'normal', 'high', 'urgent'] as Priority[]).map((x) => (
                <button key={x} type="button" onClick={() => void update({ priority: x })} aria-pressed={c.priority === x} className={cn('h-9 rounded-md border text-[12px] font-semibold', c.priority === x ? cn('border-transparent', PRIORITY_TONE[x]) : 'border-line text-muted hover:text-fg')}>{t(`inbox.p.${x}`)}</button>
              ))}
            </div>
            {c.sla_due_at && <p className={cn('t-small', c.sla_breached ? 'font-semibold text-danger' : 'text-muted')}>{c.sla_breached ? t('inbox.sla_overdue', { when: relative(c.sla_due_at) }) : t('inbox.sla_due', { when: relative(c.sla_due_at) })}</p>}
          </section>
          <section className="grid gap-2">
            <h3>{t('inbox.assignee')}</h3>
            <div className="relative">
              <select value={c.assignee?.id ?? ''} onChange={(e) => void update({ assignee_id: e.target.value ? Number(e.target.value) : null })} className={cn(inputCls, 'h-10 appearance-none pr-8 lg:h-10')} aria-label={t('inbox.assignee')}>
                <option value="">{t('inbox.unassigned')}</option>
                {admins.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
              </select>
              <ChevronDown className="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-muted" aria-hidden />
            </div>
          </section>
          <section className="grid gap-2">
            <h3>{t('inbox.tags')}</h3>
            <div className="flex flex-wrap gap-1.5">
              {c.tags.map((x) => (
                <span key={x} className="inline-flex h-7 items-center gap-1 rounded-full bg-surface-2 pl-2.5 pr-1 text-[12.5px] text-fg">
                  #{x}
                  <button type="button" onClick={() => void update({ tags: c.tags.filter((y) => y !== x) })} aria-label={t('common.remove')} className="grid size-5 place-items-center rounded-full hover:bg-surface"><X className="size-3" aria-hidden /></button>
                </span>
              ))}
              <form onSubmit={(e) => { e.preventDefault(); const v = tagInput.trim().toLowerCase(); if (v && !c.tags.includes(v)) void update({ tags: [...c.tags, v] }); setTagInput('') }} className="flex items-center">
                <Tag className="mr-1 size-3.5 text-muted" aria-hidden />
                <input value={tagInput} onChange={(e) => setTagInput(e.target.value)} placeholder={t('inbox.add_tag')} aria-label={t('inbox.add_tag')} maxLength={24} className="h-7 w-24 rounded-md bg-transparent px-1 text-[12.5px] focus:bg-surface-2 focus:outline-none" />
              </form>
            </div>
          </section>
          <section className="grid gap-2">
            <h3 className="flex items-center gap-1.5"><StickyNote className="size-3.5" aria-hidden />{t('inbox.notes')}</h3>
            <p className="t-small text-muted">{t('inbox.notes_hint')}</p>
            <form
              className="grid gap-2"
              onSubmit={async (e) => {
                e.preventDefault()
                if (!note.trim()) return
                await http.post(`/admin/inbox/${c.id}/notes`, { body: note.trim() }).catch(() => toast.push('error', t('errors.server_title')))
                setNote('')
                onChanged(c)
              }}
            >
              <textarea value={note} onChange={(e) => setNote(e.target.value)} rows={2} placeholder={t('inbox.note_ph')} aria-label={t('inbox.notes')} className={cn(inputCls, 'h-auto min-h-16 py-2 text-sm lg:h-auto')} />
              <Button type="submit" size="sm" variant="secondary" disabled={!note.trim()}>{t('inbox.add_note')}</Button>
            </form>
            <ul className="grid gap-2">
              {(th.notes ?? []).map((n) => (
                <li key={n.id} className="rounded-lg border border-warning/30 bg-warning/8 p-2.5">
                  <p className="whitespace-pre-wrap text-[13px] text-fg">{n.body}</p>
                  <p className="t-small mt-1 text-muted">{n.author} · {relative(n.created_at)}</p>
                </li>
              ))}
            </ul>
          </section>
          {p?.recent && p.recent.length > 0 && (
            <section className="grid gap-1.5">
              <h3>{t('inbox.history')}</h3>
              {p.recent.map((r) => (
                <a key={r.id} href={`?c=${r.id}`} className="flex items-center justify-between gap-2 rounded-md px-2 py-1.5 text-[13px] hover:bg-surface-2">
                  <span className="truncate">{r.subject}</span>
                  <StatusChip status={r.status} label={t(`inbox.s.${r.status}`)} />
                </a>
              ))}
            </section>
          )}
        </>
      ) : (
        <ol className="grid gap-3 border-l border-line pl-4">
          {(th.activity ?? []).length === 0 && <p className="t-small text-muted">{t('inbox.no_activity')}</p>}
          {(th.activity ?? []).map((a) => (
            <li key={a.id} className="relative">
              <span className="absolute -left-[21px] top-1.5 size-2.5 rounded-full border-2 border-surface bg-primary" aria-hidden />
              <p className="text-[13px] text-fg">
                <span className="font-semibold">{a.actor}</span> {t(`inbox.act.${a.action}`, { ...(a.meta ?? {}), to: actTo(a.meta), defaultValue: a.action })}
              </p>
              <p className="t-small text-muted">{dateTime(a.created_at)}</p>
            </li>
          ))}
        </ol>
      )}
    </aside>
  )
}

function actTo(meta: Record<string, unknown> | null): string {
  const v = meta?.to
  if (Array.isArray(v)) return v.join(', ') || '—'
  return typeof v === 'string' && v ? v : '—'
}

/* ------------------------------------------------------------------ */

function NewConversation({ open, onClose, onCreated }: { open: boolean; onClose: () => void; onCreated: (c: Conversation) => void }) {
  const { t } = useTranslation()
  const [subject, setSubject] = useState('')
  const [body, setBody] = useState('')
  const [busy, setBusy] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  return (
    <Modal
      open={open}
      onClose={onClose}
      size="form"
      icon={<Inbox className="size-5 text-primary" aria-hidden />}
      title={t('inbox.new_conversation')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>{t('common.cancel')}</Button>
          <Button
            loading={busy}
            onClick={async () => {
              setBusy(true)
              setErrors({})
              try {
                const r = await http.post<{ data: Conversation }>('/app/inbox', { subject, body, page: window.location.pathname })
                setSubject('')
                setBody('')
                onCreated(r.data)
              } catch (e) {
                if (e instanceof ApiError) setErrors(e.fields)
              } finally {
                setBusy(false)
              }
            }}
          >
            {t('inbox.send')}
          </Button>
        </>
      }
    >
      <div className="grid gap-4">
        <TextField label={t('cs.subject')} required value={subject} onChange={(e) => setSubject(e.target.value)} error={errors.subject?.[0]} placeholder={t('inbox.subject_ph')} />
        <TextArea label={t('cs.message')} required rows={5} value={body} onChange={(e) => setBody(e.target.value)} error={errors.body?.[0]} placeholder={t('inbox.body_ph')} />
      </div>
    </Modal>
  )
}
