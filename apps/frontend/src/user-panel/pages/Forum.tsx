import { useEffect, useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Flag, Lock, MessageSquare, Pencil, Pin, Plus, Search, ShieldCheck, Trash2, MessagesSquare } from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import { useAuth } from '@/shared/context/AuthContext'
import { useLocked } from '@/shared/layout/Locked'
import { useToast } from '@/shared/context/ToastContext'
import { useApi } from '@/shared/hooks/useApi'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { Card, PageHeader } from '@/shared/ui/Misc'
import { Button, ButtonLink } from '@/shared/ui/Button'
import { TextField, TextArea, SelectField } from '@/shared/ui/Field'
import { Badge, StatusChip } from '@/shared/ui/Status'
import { EmptyState, Skeleton } from '@/shared/ui/States'
import { Modal } from '@/shared/ui/Modal'
import { ConfirmDialog } from '@/shared/ui/ConfirmDialog'
import { relative, dateTime } from '@/shared/lib/format'
import { useForumCategories, type ForumCategory } from './forumData'
import { cn } from '@/shared/lib/cn'

/* Forum Diskusi privat pelanggan (akses dari paket, tanpa kredit). Pengaturan & moderasi oleh Admin Krafio. */

interface Author {
  id: number | null
  name: string
  initials: string
  is_admin: boolean
}
interface Thread {
  id: number
  category: { id: number; slug: string; name: string }
  title: string
  excerpt: string
  body?: string
  author: Author
  is_pinned: boolean
  is_locked: boolean
  replies_count: number
  last_reply_at: string | null
  last_reply_by: string | null
  created_at: string
  edited_at: string | null
  is_mine: boolean
  can_edit: boolean
}
interface Post {
  id: number
  body: string
  author: Author
  created_at: string
  edited_at: string | null
  is_mine: boolean
  can_edit: boolean
}
interface Paged<T> {
  data: T[]
  meta: { current_page: number; last_page: number; total: number }
}
type ReportTarget = { type: 'system' } | { type: 'thread'; thread_id: number } | { type: 'post'; post_id: number } | { type: 'user'; user_id: number }

function Avatar({ a }: { a: Author }) {
  return (
    <span className={cn('grid size-9 shrink-0 place-items-center rounded-full text-sm font-semibold', a.is_admin ? 'bg-primary text-primary-contrast' : 'bg-surface-2 text-fg')} aria-hidden>
      {a.is_admin ? <ShieldCheck className="size-4" /> : a.initials}
    </span>
  )
}

function AuthorLine({ a, at, edited }: { a: Author; at: string; edited?: string | null }) {
  const { t } = useTranslation()
  return (
    <p className="t-small flex flex-wrap items-center gap-x-2 text-muted">
      <span className="font-semibold text-fg">{a.name}</span>
      {a.is_admin && <Badge tone="info">{t('forum.admin_badge')}</Badge>}
      <time dateTime={at} title={dateTime(at)}>{relative(at)}</time>
      {edited && <span>· {t('forum.edited')}</span>}
    </p>
  )
}

/** Gerbang akses: paket tanpa forum.access melihat ajakan upgrade, bukan isi forum. */
function useForumGate() {
  const { me } = useAuth()
  return !!me?.entitlements['forum.access']?.enabled
}

function LockedForum() {
  const { t } = useTranslation()
  const { openLocked } = useLocked()
  return (
    <>
      <PageHeader title={t('forum.title')} subtitle={t('forum.subtitle')} />
      <EmptyState title={t('forum.locked_title')} body={t('forum.locked_body')} action={<Button icon={<Lock className="size-4" aria-hidden />} onClick={() => openLocked('forum.access')}>{t('forum.upgrade')}</Button>} />
    </>
  )
}

/* ---------------- Beranda forum & daftar topik per kategori ---------------- */
export function ForumPage() {
  const { slug } = useParams()
  // key: ganti kategori = state daftar (halaman, pencarian) mulai dari awal
  return <ForumList key={slug ?? 'semua'} slug={slug} />
}

function ForumList({ slug }: { slug?: string }) {
  const { t } = useTranslation()
  const allowed = useForumGate()
  const nav = useNavigate()
  const cats = useForumCategories(allowed)
  const [q, setQ] = useState('')
  const [search, setSearch] = useState('')
  const [mine, setMine] = useState(false)
  const [page, setPage] = useState(1)
  const [items, setItems] = useState<Thread[]>([])
  const [newOpen, setNewOpen] = useState(false)
  const [report, setReport] = useState<ReportTarget | null>(null)
  const current = cats.data?.data.find((c) => c.slug === slug) ?? null
  useDocTitle(current?.name ?? t('forum.title'))
  const [meta, setMeta] = useState<Paged<Thread>['meta'] | null>(null)
  const [listError, setListError] = useState<string | null>(null)

  // Daftar topik berhalaman: halaman 1 mengganti isi, halaman berikutnya ditambahkan ("Muat lebih banyak")
  useEffect(() => {
    if (!allowed) return
    let live = true
    http.get<Paged<Thread>>('/app/forum/threads', { category: slug ?? '', q: search, mine: mine ? 1 : '', page })
      .then((r) => {
        if (!live) return
        setListError(null)
        setMeta(r.meta)
        setItems((prev) => (page === 1 ? r.data : [...prev, ...r.data.filter((x) => !prev.some((p) => p.id === x.id))]))
      })
      .catch((e) => live && setListError(e instanceof ApiError ? e.message : (e as Error).message))
    return () => {
      live = false
    }
  }, [allowed, slug, search, mine, page])

  if (!allowed) return <LockedForum />
  if (cats.error) return <EmptyState title={cats.error.message} />
  const muted = cats.data?.meta.muted
  const writable = (cats.data?.data ?? []).filter((c) => !c.admin_only_posting)

  return (
    <>
      <PageHeader
        title={current?.name ?? t('forum.title')}
        subtitle={current?.description ?? t('forum.subtitle')}
        crumbs={current ? [{ label: t('forum.title'), to: '/app/forum' }, { label: current.name }] : undefined}
        actions={
          <>
            <Button variant="ghost" icon={<Flag className="size-4" aria-hidden />} onClick={() => setReport({ type: 'system' })}>{t('forum.report_issue')}</Button>
            {!(current?.admin_only_posting) && <Button icon={<Plus className="size-4" aria-hidden />} disabled={muted || writable.length === 0} onClick={() => setNewOpen(true)}>{t('forum.new_thread')}</Button>}
          </>
        }
      />
      {muted && (
        <p role="status" className="t-small mb-4 rounded-md bg-warning/14 px-3 py-2 text-fg">
          {t('forum.muted', { until: cats.data?.meta.muted_until ? dateTime(cats.data.meta.muted_until) : t('forum.forever'), reason: cats.data?.meta.muted_reason })}
        </p>
      )}
      <div className="grid gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">
        <nav aria-label={t('forum.categories')} className="snap-row -mx-4 gap-1 px-4 lg:mx-0 lg:grid lg:content-start lg:px-0">
          <Link to="/app/forum" className={cn('shrink-0 whitespace-nowrap rounded-md px-3 py-2 text-sm', !slug ? 'bg-primary-soft font-semibold text-fg' : 'text-muted hover:bg-surface-2')}>{t('forum.all_topics')}</Link>
          {!cats.data
            ? Array.from({ length: 5 }).map((_, i) => <Skeleton key={i} className="h-9 w-28 shrink-0 lg:w-auto" />)
            : cats.data.data.map((c) => (
                <Link key={c.id} to={`/app/forum/c/${c.slug}`} className={cn('flex shrink-0 items-center justify-between gap-2 whitespace-nowrap rounded-md px-3 py-2 text-sm', slug === c.slug ? 'bg-primary-soft font-semibold text-fg' : 'text-muted hover:bg-surface-2')}>
                  <span className="truncate">{c.name}</span>
                  <span className="t-small tabular-nums">{c.threads_count}</span>
                </Link>
              ))}
          <Link to="/app/forum/reports" className="t-small shrink-0 self-center whitespace-nowrap px-3 text-primary hover:underline lg:mt-2 lg:self-auto">{t('forum.my_reports')}</Link>
        </nav>
        <div className="grid content-start gap-4">
          <form
            className="flex flex-wrap gap-2"
            onSubmit={(e) => {
              e.preventDefault()
              setPage(1)
              setSearch(q.trim())
            }}
          >
            <div className="min-w-0 flex-1">
              <TextField aria-label={t('forum.search')} placeholder={t('forum.search')} value={q} onChange={(e) => setQ(e.target.value)} />
            </div>
            <Button type="submit" variant="secondary" icon={<Search className="size-4" aria-hidden />}>{t('forum.search_btn')}</Button>
            <Button type="button" variant={mine ? 'primary' : 'ghost'} onClick={() => { setPage(1); setMine(!mine) }}>{t('forum.mine')}</Button>
          </form>
          {listError ? (
            <EmptyState title={listError} />
          ) : !meta ? (
            <Skeleton className="h-60" />
          ) : items.length === 0 ? (
            <EmptyState title={t('forum.empty')} body={current?.admin_only_posting ? t('forum.empty_announcements') : t('forum.empty_body')} />
          ) : (
            <ul className="divide-y divide-line rounded-lg border border-line bg-surface">
              {items.map((th) => (
                <li key={th.id}>
                  <Link to={`/app/forum/t/${th.id}`} className="flex gap-3 p-4 hover:bg-surface-2">
                    <Avatar a={th.author} />
                    <div className="grid min-w-0 flex-1 gap-1">
                      <p className="flex flex-wrap items-center gap-2 font-semibold text-fg">
                        {th.is_pinned && <Pin className="size-4 text-primary" aria-label={t('forum.pinned')} />}
                        {th.is_locked && <Lock className="size-4 text-muted" aria-label={t('forum.locked')} />}
                        <span className="min-w-0 break-words">{th.title}</span>
                      </p>
                      <p className="t-small line-clamp-2 text-muted">{th.excerpt}</p>
                      <p className="t-small flex flex-wrap gap-x-3 text-muted">
                        <span>{th.author.name}</span>
                        {!slug && <span>· {th.category.name}</span>}
                        <span>· {relative(th.last_reply_at ?? th.created_at)}</span>
                      </p>
                    </div>
                    <span className="t-small flex shrink-0 items-center gap-1 self-center text-muted" aria-label={t('forum.replies', { n: th.replies_count })}>
                      <MessageSquare className="size-4" aria-hidden />
                      {th.replies_count}
                    </span>
                  </Link>
                </li>
              ))}
            </ul>
          )}
          {meta && meta.current_page < meta.last_page && (
            <Button variant="secondary" className="justify-self-center" onClick={() => setPage(page + 1)}>{t('forum.load_more')}</Button>
          )}
        </div>
      </div>
      <NewThreadModal open={newOpen} onClose={() => setNewOpen(false)} categories={writable} initial={current && !current.admin_only_posting ? current.slug : writable[0]?.slug} onCreated={(id) => nav(`/app/forum/t/${id}`)} />
      <ReportModal target={report} onClose={() => setReport(null)} reasons={cats.data?.meta.report_reasons ?? []} />
    </>
  )
}

function NewThreadModal({ open, onClose, categories, initial, onCreated }: { open: boolean; onClose: () => void; categories: ForumCategory[]; initial?: string; onCreated: (id: number) => void }) {
  const { t } = useTranslation()
  const [f, setF] = useState({ category: initial ?? '', title: '', body: '' })
  const [errs, setErrs] = useState<Record<string, string[]>>({})
  const [busy, setBusy] = useState(false)
  const category = f.category || initial || categories[0]?.slug || ''
  const submit = async () => {
    setBusy(true)
    setErrs({})
    try {
      const r = await http.post<{ data: Thread }>('/app/forum/threads', { ...f, category })
      setF({ category: '', title: '', body: '' })
      onClose()
      onCreated(r.data.id)
    } catch (e) {
      if (e instanceof ApiError) setErrs(Object.keys(e.fields).length ? e.fields : { body: [e.message] })
    } finally {
      setBusy(false)
    }
  }
  return (
    <Modal open={open} onClose={onClose} size="form" title={t('forum.new_thread')} dirty={!!(f.title || f.body)} footer={<><Button variant="secondary" onClick={onClose}>{t('common.cancel')}</Button><Button loading={busy} onClick={submit}>{t('forum.publish')}</Button></>}>
      <div className="grid gap-4">
        <SelectField label={t('forum.category')} value={category} onChange={(e) => setF({ ...f, category: e.target.value })} error={errs.category?.[0]}>
          {categories.map((c) => <option key={c.slug} value={c.slug}>{c.name}</option>)}
        </SelectField>
        <TextField label={t('forum.thread_title')} required maxLength={150} value={f.title} onChange={(e) => setF({ ...f, title: e.target.value })} error={errs.title?.[0]} hint={t('forum.chars', { n: f.title.length, max: 150 })} />
        <TextArea label={t('forum.thread_body')} required rows={8} maxLength={10000} value={f.body} onChange={(e) => setF({ ...f, body: e.target.value })} error={errs.body?.[0]} hint={t('forum.body_hint')} />
      </div>
    </Modal>
  )
}

function ReportModal({ target, onClose, reasons }: { target: ReportTarget | null; onClose: () => void; reasons: string[] }) {
  const { t } = useTranslation()
  const toast = useToast()
  const [reason, setReason] = useState('')
  const [message, setMessage] = useState('')
  const [errs, setErrs] = useState<Record<string, string[]>>({})
  const [busy, setBusy] = useState(false)
  const isSystem = target?.type === 'system'
  const options = reasons.filter((r) => (isSystem ? ['bug', 'error', 'other'] : ['spam', 'abuse', 'harassment', 'scam', 'off_topic', 'other']).includes(r))
  const chosen = reason && options.includes(reason) ? reason : options[0] ?? 'other'
  const submit = async () => {
    if (!target) return
    setBusy(true)
    setErrs({})
    try {
      await http.post('/app/forum/reports', { ...target, reason: chosen, message, page_url: window.location.pathname })
      toast.push('success', t('forum.report_sent'))
      setMessage('')
      onClose()
    } catch (e) {
      if (e instanceof ApiError) setErrs(Object.keys(e.fields).length ? e.fields : { message: [e.message] })
    } finally {
      setBusy(false)
    }
  }
  return (
    <Modal open={!!target} onClose={onClose} size="form" title={isSystem ? t('forum.report_issue') : t('forum.report_content')} dirty={!!message} footer={<><Button variant="secondary" onClick={onClose}>{t('common.cancel')}</Button><Button loading={busy} onClick={submit}>{t('forum.send_report')}</Button></>}>
      <div className="grid gap-4">
        <p className="t-small text-muted">{isSystem ? t('forum.report_system_hint') : t('forum.report_content_hint')}</p>
        <SelectField label={t('forum.reason')} value={chosen} onChange={(e) => setReason(e.target.value)}>
          {options.map((r) => <option key={r} value={r}>{t(`forum.reasons.${r}`)}</option>)}
        </SelectField>
        <TextArea label={t('forum.report_message')} required rows={5} maxLength={3000} value={message} onChange={(e) => setMessage(e.target.value)} error={errs.message?.[0]} />
      </div>
    </Modal>
  )
}

/* ---------------- Detail topik ---------------- */
export function ForumThreadPage() {
  const { t } = useTranslation()
  const allowed = useForumGate()
  const { id } = useParams()
  const nav = useNavigate()
  const toast = useToast()
  const cats = useForumCategories(allowed)
  const th = useApi<Thread & { can_reply: boolean; posts: Paged<Post> }>(allowed ? `/app/forum/threads/${id}` : null)
  useDocTitle(th.data?.title ?? t('forum.title'))
  const [reply, setReply] = useState('')
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState<string | null>(null)
  const [report, setReport] = useState<ReportTarget | null>(null)
  const [edit, setEdit] = useState<{ kind: 'thread' | 'post'; id: number; title?: string; body: string } | null>(null)
  const [del, setDel] = useState<{ kind: 'thread' | 'post'; id: number } | null>(null)

  if (!allowed) return <LockedForum />
  if (th.error)
    return (
      <>
        <PageHeader title={t('forum.not_found')} crumbs={[{ label: t('forum.title'), to: '/app/forum' }, { label: t('forum.not_found') }]} />
        <EmptyState title={th.error.message} action={<ButtonLink to="/app/forum" variant="secondary">{t('forum.back')}</ButtonLink>} />
      </>
    )
  if (!th.data) return <Skeleton className="h-96" />
  const d = th.data
  const send = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setErr(null)
    try {
      await http.post(`/app/forum/threads/${d.id}/posts`, { body: reply })
      setReply('')
      th.reload()
    } catch (er) {
      if (er instanceof ApiError) setErr(er.fields.body?.[0] ?? er.message)
    } finally {
      setBusy(false)
    }
  }
  const saveEdit = async () => {
    if (!edit) return
    try {
      if (edit.kind === 'thread') await http.patch(`/app/forum/threads/${edit.id}`, { title: edit.title, body: edit.body })
      else await http.patch(`/app/forum/posts/${edit.id}`, { body: edit.body })
      setEdit(null)
      th.reload()
    } catch (e) {
      toast.push('error', e instanceof ApiError ? e.message : (e as Error).message)
    }
  }
  const actions = (kind: 'thread' | 'post', item: Thread | Post) => (
    <div className="flex flex-wrap gap-1">
      {item.can_edit && <Button size="sm" variant="ghost" icon={<Pencil className="size-3.5" aria-hidden />} onClick={() => setEdit({ kind, id: item.id, title: kind === 'thread' ? (item as Thread).title : undefined, body: (item as Thread).body ?? (item as Post).body })}>{t('forum.edit')}</Button>}
      {item.is_mine && <Button size="sm" variant="ghost" icon={<Trash2 className="size-3.5" aria-hidden />} onClick={() => setDel({ kind, id: item.id })}>{t('forum.delete')}</Button>}
      {!item.is_mine && !item.author.is_admin && <Button size="sm" variant="ghost" icon={<Flag className="size-3.5" aria-hidden />} onClick={() => setReport(kind === 'thread' ? { type: 'thread', thread_id: item.id } : { type: 'post', post_id: item.id })}>{t('forum.report')}</Button>}
    </div>
  )

  return (
    <>
      <PageHeader title={d.title} crumbs={[{ label: t('forum.title'), to: '/app/forum' }, { label: d.category.name, to: `/app/forum/c/${d.category.slug}` }, { label: d.title }]} />
      <div className="grid max-w-3xl gap-4">
        <Card>
          <div className="flex gap-3">
            <Avatar a={d.author} />
            <div className="grid min-w-0 flex-1 gap-2">
              <AuthorLine a={d.author} at={d.created_at} edited={d.edited_at} />
              <div className="flex flex-wrap gap-2">
                {d.is_pinned && <Badge tone="info">{t('forum.pinned')}</Badge>}
                {d.is_locked && <Badge>{t('forum.locked')}</Badge>}
              </div>
              <p className="whitespace-pre-wrap break-words text-fg">{d.body}</p>
              {actions('thread', d)}
            </div>
          </div>
        </Card>
        <h2 className="t-h3 flex items-center gap-2 text-fg"><MessagesSquare className="size-5" aria-hidden />{t('forum.replies', { n: d.replies_count })}</h2>
        {d.posts.data.length === 0 ? (
          <EmptyState title={t('forum.no_replies')} />
        ) : (
          <ul className="grid gap-3">
            {d.posts.data.map((p) => (
              <li key={p.id} className={cn('flex gap-3 rounded-lg border border-line bg-surface p-4', p.author.is_admin && 'border-primary/40')}>
                <Avatar a={p.author} />
                <div className="grid min-w-0 flex-1 gap-2">
                  <AuthorLine a={p.author} at={p.created_at} edited={p.edited_at} />
                  <p className="whitespace-pre-wrap break-words text-fg">{p.body}</p>
                  {actions('post', p)}
                </div>
              </li>
            ))}
          </ul>
        )}
        {d.can_reply ? (
          <Card>
            <form onSubmit={send} className="grid gap-3">
              <TextArea label={t('forum.your_reply')} rows={4} maxLength={5000} value={reply} onChange={(e) => setReply(e.target.value)} error={err} />
              <Button type="submit" loading={busy} disabled={reply.trim().length < 2} className="justify-self-start">{t('forum.send_reply')}</Button>
            </form>
          </Card>
        ) : (
          <p className="t-small rounded-md bg-surface-2 px-3 py-2 text-muted">{d.is_locked ? t('forum.locked_reply') : t('forum.muted_short')}</p>
        )}
      </div>
      <Modal open={!!edit} onClose={() => setEdit(null)} size="form" title={t('forum.edit')} footer={<><Button variant="secondary" onClick={() => setEdit(null)}>{t('common.cancel')}</Button><Button onClick={saveEdit}>{t('common.save')}</Button></>}>
        {edit && (
          <div className="grid gap-4">
            {edit.kind === 'thread' && <TextField label={t('forum.thread_title')} maxLength={150} value={edit.title ?? ''} onChange={(e) => setEdit({ ...edit, title: e.target.value })} />}
            <TextArea label={t('forum.thread_body')} rows={8} value={edit.body} onChange={(e) => setEdit({ ...edit, body: e.target.value })} hint={t('forum.edit_window', { n: cats.data?.meta.edit_minutes ?? 30 })} />
          </div>
        )}
      </Modal>
      <ConfirmDialog
        open={!!del}
        onClose={() => setDel(null)}
        destructive
        title={t('forum.delete_title')}
        body={t('forum.delete_body')}
        confirmLabel={t('forum.delete')}
        onConfirm={async () => {
          if (!del) return
          await http.del(del.kind === 'thread' ? `/app/forum/threads/${del.id}` : `/app/forum/posts/${del.id}`)
          setDel(null)
          if (del.kind === 'thread') nav('/app/forum')
          else th.reload()
        }}
      />
      <ReportModal target={report} onClose={() => setReport(null)} reasons={cats.data?.meta.report_reasons ?? []} />
    </>
  )
}

/* ---------------- Laporan saya ---------------- */
export function ForumReportsPage() {
  const { t } = useTranslation()
  const allowed = useForumGate()
  useDocTitle(t('forum.my_reports'))
  const list = useApi<{ id: number; type: string; reason: string; message: string; status: string; admin_note: string | null; created_at: string; thread_id: number | null }[]>(allowed ? '/app/forum/reports' : null)
  if (!allowed) return <LockedForum />
  return (
    <>
      <PageHeader title={t('forum.my_reports')} subtitle={t('forum.my_reports_sub')} crumbs={[{ label: t('forum.title'), to: '/app/forum' }, { label: t('forum.my_reports') }]} />
      {!list.data ? (
        <Skeleton className="h-40" />
      ) : list.data.length === 0 ? (
        <EmptyState title={t('forum.no_reports')} />
      ) : (
        <ul className="grid max-w-3xl gap-3">
          {list.data.map((r) => (
            <li key={r.id} className="grid gap-2 rounded-lg border border-line bg-surface p-4">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="font-semibold text-fg">{t(`forum.types.${r.type}`)} · {t(`forum.reasons.${r.reason}`)}</span>
                <StatusChip status={r.status === 'in_review' ? 'processing' : r.status} label={t(`forum.report_status.${r.status}`)} />
              </div>
              <p className="whitespace-pre-wrap break-words text-sm text-fg">{r.message}</p>
              {r.admin_note && <p className="t-small rounded-md bg-surface-2 px-3 py-2 text-fg"><span className="font-semibold">{t('forum.admin_reply')}:</span> {r.admin_note}</p>}
              <p className="t-small text-muted">{dateTime(r.created_at)}{r.thread_id ? <> · <Link className="text-primary hover:underline" to={`/app/forum/t/${r.thread_id}`}>{t('forum.open_thread')}</Link></> : null}</p>
            </li>
          ))}
        </ul>
      )}
    </>
  )
}
