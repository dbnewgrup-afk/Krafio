import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { CheckCheck, MessageSquarePlus, Send, Monitor, LogOut } from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import type { AiJob, NotificationItem } from '@/api-client/types'
import { useAuth } from '@/shared/context/AuthContext'
import { useTheme, type ThemeMode } from '@/shared/context/ThemeContext'
import { useToast } from '@/shared/context/ToastContext'
import { useApi } from '@/shared/hooks/useApi'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { Card, CardTitle, PageHeader, Segmented, Tabs } from '@/shared/ui/Misc'
import { Button } from '@/shared/ui/Button'
import { Switch, TextArea, TextField, SelectField } from '@/shared/ui/Field'
import { PasswordField, PasswordStrength, passwordScore } from '@/shared/ui/PasswordField'
import { StatusChip } from '@/shared/ui/Status'
import { DataTable, useServerList } from '@/shared/ui/DataTable'
import { EmptyState, Skeleton } from '@/shared/ui/States'
import { Modal } from '@/shared/ui/Modal'
import { ConfirmDialog } from '@/shared/ui/ConfirmDialog'
import { changeLanguage } from '@/shared/i18n'
import { setPref, getPref } from '@/shared/lib/prefs'
import { dateTime, relative } from '@/shared/lib/format'
import { QuotaBar } from './Dashboard'
import { cn } from '@/shared/lib/cn'

/* ---------------- Penggunaan & aktivitas ---------------- */
export function UsagePage() {
  const { t } = useTranslation()
  useDocTitle(t('user.nav.usage'))
  const { me } = useAuth()
  const [tab, setTab] = useState<'quota' | 'jobs' | 'activity'>('quota')
  const jobs = useServerList<AiJob>('/app/jobs/history')
  const acts = useServerList<{ id: number; action: string; ip: string; created_at: string }>('/app/activity')
  const ents = Object.values(me?.entitlements ?? {})
  return (
    <>
      <PageHeader title={t('user.nav.usage')} subtitle={t('usage.subtitle')} />
      <Tabs
        label={t('user.nav.usage')}
        value={tab}
        onChange={setTab}
        items={[
          { value: 'quota', label: t('usage.tab_quota') },
          { value: 'jobs', label: t('usage.tab_jobs') },
          { value: 'activity', label: t('usage.tab_activity') },
        ]}
      />
      <div className="mt-5">
        {tab === 'quota' && (
          <Card>
            {ents.length === 0 ? (
              <p className="t-small text-muted">{t('user.dash.no_quota')}</p>
            ) : (
              <ul className="grid gap-4 md:grid-cols-2">
                {ents.map((e) => (
                  <li key={e.key} className="grid gap-1">
                    {e.enabled ? <QuotaBar e={e} /> : <p className="t-small flex justify-between text-muted"><span>{t(`features.${e.key.replace('.', '_')}`)}</span><span>{t('nav.locked')}</span></p>}
                    {e.reset_at && <span className="t-small text-muted">{t('locked.reset_at', { date: dateTime(e.reset_at, false) })}</span>}
                  </li>
                ))}
              </ul>
            )}
          </Card>
        )}
        {tab === 'jobs' && (
          <DataTable<AiJob>
            list={jobs}
            exportName="jobs"
            filters={[{ key: 'status', label: t('common.status'), options: ['queued', 'processing', 'completed', 'failed', 'cancelled'].map((s) => ({ value: s, label: t(`status.${s}`) })) }]}
            columns={[
              { key: 'prompt', header: t('studio.prompt'), primary: true, render: (r) => <span className="line-clamp-1">{r.prompt}</span> },
              { key: 'type', header: t('usage.type'), mobile: true, render: (r) => t(`job_types.${r.type}`) },
              { key: 'cost', header: t('usage.cost'), align: 'right', render: (r) => t('studio.credits', { count: r.cost }) },
              { key: 'status', header: t('common.status'), mobile: true, render: (r) => <StatusChip status={r.status} /> },
              { key: 'created_at', header: t('wallet.col_date'), sortable: true, render: (r) => dateTime(r.created_at) },
            ]}
          />
        )}
        {tab === 'activity' && (
          <DataTable
            list={acts}
            columns={[
              { key: 'action', header: t('usage.action'), primary: true, render: (r) => t(`activity.${r.action}`, { defaultValue: r.action }) },
              { key: 'ip', header: 'IP', mobile: true },
              { key: 'created_at', header: t('wallet.col_date'), mobile: true, render: (r) => dateTime(r.created_at) },
            ]}
          />
        )}
      </div>
    </>
  )
}

/* ---------------- Pusat notifikasi (§19.8) ---------------- */
export function NotificationsPage() {
  const { t } = useTranslation()
  useDocTitle(t('user.nav.notifications'))
  const { refresh } = useAuth()
  const [filter, setFilter] = useState<'all' | 'unread'>('all')
  const [tab, setTab] = useState<'list' | 'prefs'>('list')
  const list = useApi<NotificationItem[]>('/me/notifications', { per_page: 100, unread: filter === 'unread' ? 1 : '' })
  const groups = useMemo(() => {
    const out: Record<string, NotificationItem[]> = { today: [], yesterday: [], older: [] }
    const now = new Date()
    const startToday = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime()
    ;(list.data ?? []).forEach((n) => {
      const ts = new Date(n.created_at).getTime()
      if (ts >= startToday) out.today.push(n)
      else if (ts >= startToday - 86400_000) out.yesterday.push(n)
      else out.older.push(n)
    })
    return out
  }, [list.data])
  const markRead = async (n: NotificationItem) => {
    await http.post(`/me/notifications/${n.id}/read`).catch(() => undefined)
    list.reload()
    void refresh()
  }
  return (
    <>
      <PageHeader
        title={t('user.nav.notifications')}
        actions={
          <Button variant="secondary" icon={<CheckCheck className="size-4" aria-hidden />} onClick={async () => { await http.post('/me/notifications/read-all'); list.reload(); void refresh() }}>
            {t('notif.mark_all')}
          </Button>
        }
      />
      <Tabs label={t('user.nav.notifications')} value={tab} onChange={setTab} items={[{ value: 'list', label: t('notif.tab_list') }, { value: 'prefs', label: t('notif.tab_prefs') }]} />
      <div className="mt-4">
        {tab === 'prefs' ? (
          <NotificationPrefs />
        ) : (
          <>
            <Segmented label={t('table.filter')} value={filter} onChange={setFilter} items={[{ value: 'all', label: t('table.all') }, { value: 'unread', label: t('notif.unread') }]} />
            <div className="mt-4 grid gap-6">
              {!list.data ? (
                <Skeleton className="h-48" />
              ) : list.data.length === 0 ? (
                <EmptyState title={t('notif.empty')} />
              ) : (
                (['today', 'yesterday', 'older'] as const).map((g) =>
                  groups[g].length ? (
                    <section key={g} className="grid gap-2">
                      <h2 className="t-label text-muted">{t(`notif.${g}`)}</h2>
                      <ul className="divide-y divide-line rounded-lg border border-line bg-surface">
                        {groups[g].map((n) => (
                          <li key={n.id} className={cn('flex items-start gap-3 px-4 py-3', !n.read_at && 'bg-primary-soft/40')}>
                            <span className={cn('mt-2 size-2 shrink-0 rounded-full', n.read_at ? 'bg-transparent' : 'bg-primary')} aria-hidden />
                            <div className="min-w-0 flex-1">
                              <p className="font-semibold text-fg">{n.title}</p>
                              <p className="t-small text-muted">{n.body}</p>
                              <p className="t-small mt-1 text-muted">
                                {relative(n.created_at)}
                                {!n.read_at && <span className="sr-only"> — {t('notif.unread')}</span>}
                              </p>
                            </div>
                            <div className="flex shrink-0 flex-col items-end gap-1">
                              {n.link && (
                                <Link to={n.link} onClick={() => markRead(n)} className="t-label text-primary hover:underline">
                                  {t('common.open')}
                                </Link>
                              )}
                              {!n.read_at && (
                                <button type="button" onClick={() => markRead(n)} className="t-small text-muted hover:text-fg">
                                  {t('notif.mark_read')}
                                </button>
                              )}
                            </div>
                          </li>
                        ))}
                      </ul>
                    </section>
                  ) : null,
                )
              )}
            </div>
          </>
        )}
      </div>
    </>
  )
}

const CHANNELS = ['email', 'whatsapp', 'in_app'] as const
const CATEGORIES = ['security', 'transaction', 'job', 'subscription', 'affiliate', 'promo'] as const
const MANDATORY = ['security', 'transaction']
function NotificationPrefs() {
  const { t } = useTranslation()
  const toast = useToast()
  const prefs = useApi<Record<string, Record<string, boolean>>>('/me/notification-preferences')
  const [state, setState] = useState<Record<string, Record<string, boolean>> | null>(null)
  useEffect(() => {
    if (prefs.data) setState(prefs.data)
  }, [prefs.data])
  if (!state) return <Skeleton className="h-48" />
  return (
    <Card>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[480px] text-sm">
          <thead>
            <tr className="border-b border-line">
              <th className="t-label py-2 text-left text-muted">{t('notif.category')}</th>
              {CHANNELS.map((c) => (
                <th key={c} className="t-label py-2 text-center text-muted">
                  {t(`notif.ch_${c}`)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {CATEGORIES.map((cat) => (
              <tr key={cat} className="border-b border-line last:border-0">
                <td className="py-3">
                  <p className="font-medium text-fg">{t(`notif.cat_${cat}`)}</p>
                  {MANDATORY.includes(cat) && <p className="t-small text-muted">{t('notif.mandatory')}</p>}
                </td>
                {CHANNELS.map((ch) => (
                  <td key={ch} className="py-3 text-center">
                    <input
                      type="checkbox"
                      aria-label={`${t(`notif.cat_${cat}`)} — ${t(`notif.ch_${ch}`)}`}
                      className="size-5 accent-[var(--primary)]"
                      disabled={MANDATORY.includes(cat) && ch !== 'whatsapp'}
                      checked={MANDATORY.includes(cat) && ch !== 'whatsapp' ? true : !!state[cat]?.[ch]}
                      onChange={(e) => setState({ ...state, [cat]: { ...(state[cat] ?? {}), [ch]: e.target.checked } })}
                    />
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="mt-4">
        <Button
          onClick={async () => {
            await http.put('/me/notification-preferences', state)
            toast.push('success', t('common.saved'))
          }}
        >
          {t('common.save')}
        </Button>
      </div>
    </Card>
  )
}

/* ---------------- Bantuan & tiket ---------------- */
interface Ticket {
  id: number
  number: string
  subject: string
  status: string
  updated_at: string
  messages?: { id: number; from: 'user' | 'admin'; body: string; created_at: string; author: string }[]
}
export function SupportPage() {
  const { t } = useTranslation()
  useDocTitle(t('user.nav.support'))
  const list = useServerList<Ticket>('/app/tickets')
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState({ subject: '', message: '' })
  const toast = useToast()
  return (
    <>
      <PageHeader title={t('user.nav.support')} subtitle={t('support.subtitle')} actions={<Button icon={<MessageSquarePlus className="size-4" aria-hidden />} onClick={() => setOpen(true)}>{t('support.new')}</Button>} />
      <DataTable<Ticket>
        list={list}
        emptyTitle={t('support.empty')}
        emptyAction={<Button onClick={() => setOpen(true)}>{t('support.new')}</Button>}
        filters={[{ key: 'status', label: t('common.status'), options: ['open', 'pending', 'resolved', 'closed'].map((s) => ({ value: s, label: t(`status.${s}`) })) }]}
        columns={[
          { key: 'subject', header: t('cs.subject'), primary: true, render: (r) => <Link to={`/app/support/${r.id}`} className="font-semibold text-fg hover:text-primary">{r.subject}</Link> },
          { key: 'number', header: t('support.number'), mobile: true, render: (r) => <span className="t-mono">{r.number}</span> },
          { key: 'status', header: t('common.status'), mobile: true, render: (r) => <StatusChip status={r.status} /> },
          { key: 'updated_at', header: t('support.updated'), sortable: true, render: (r) => relative(r.updated_at) },
        ]}
      />
      <Modal
        open={open}
        onClose={() => setOpen(false)}
        size="form"
        dirty={!!(form.subject || form.message)}
        title={t('support.new')}
        footer={
          <Button
            onClick={async () => {
              await http.post('/support/tickets', { ...form, page: '/app/support' })
              toast.push('success', t('cs.ticket_sent'))
              setOpen(false)
              setForm({ subject: '', message: '' })
              list.reload()
            }}
          >
            {t('cs.send_ticket')}
          </Button>
        }
      >
        <div className="grid gap-4">
          <TextField label={t('cs.subject')} required value={form.subject} onChange={(e) => setForm({ ...form, subject: e.target.value })} />
          <TextArea label={t('cs.message')} required rows={5} value={form.message} onChange={(e) => setForm({ ...form, message: e.target.value })} />
        </div>
      </Modal>
    </>
  )
}

export function TicketDetailPage({ admin }: { admin?: boolean }) {
  const { id } = useParams()
  const { t } = useTranslation()
  const base = admin ? `/admin/tickets/${id}` : `/app/tickets/${id}`
  const tk = useApi<Ticket>(base)
  useDocTitle(tk.data?.subject ?? t('user.nav.support'))
  const [msg, setMsg] = useState('')
  const [busy, setBusy] = useState(false)
  const send = async (e: FormEvent) => {
    e.preventDefault()
    if (!msg.trim()) return
    setBusy(true)
    try {
      await http.post(`${base}/messages`, { body: msg })
      setMsg('')
      tk.reload()
    } finally {
      setBusy(false)
    }
  }
  return (
    <>
      <PageHeader
        title={tk.data?.subject ?? '…'}
        subtitle={tk.data && <span className="inline-flex items-center gap-2"><span className="t-mono">{tk.data.number}</span><StatusChip status={tk.data.status} /></span>}
        crumbs={[{ label: t('user.nav.support'), to: admin ? '/admin/support/tickets' : '/app/support' }, { label: tk.data?.number ?? '' }]}
        actions={
          admin && tk.data ? (
            <Button variant="secondary" onClick={async () => { await http.patch(base, { status: tk.data!.status === 'resolved' ? 'open' : 'resolved' }); tk.reload() }}>
              {tk.data.status === 'resolved' ? t('support.reopen') : t('support.resolve')}
            </Button>
          ) : undefined
        }
      />
      {!tk.data ? (
        <Skeleton className="h-60" />
      ) : (
        <div className="grid max-w-3xl gap-4">
          <ul className="grid gap-3">
            {tk.data.messages?.map((m) => (
              <li key={m.id} className={cn('grid gap-1 rounded-lg border p-4', m.from === 'admin' ? 'border-primary/30 bg-primary-soft/40' : 'border-line bg-surface')}>
                <p className="t-small text-muted">
                  <span className="font-semibold text-fg">{m.author}</span> — {dateTime(m.created_at)}
                </p>
                <p className="whitespace-pre-line text-sm text-fg">{m.body}</p>
              </li>
            ))}
          </ul>
          <form onSubmit={send} className="grid gap-2">
            <TextArea label={t('support.reply')} rows={4} value={msg} onChange={(e) => setMsg(e.target.value)} />
            <div>
              <Button type="submit" loading={busy} icon={<Send className="size-4" aria-hidden />}>
                {t('common.send')}
              </Button>
            </div>
          </form>
        </div>
      )}
    </>
  )
}

/* ---------------- Pengaturan ---------------- */
export function SettingsPage() {
  const { t } = useTranslation()
  useDocTitle(t('user.nav.settings'))
  const [params, setParams] = useSearchParams()
  const tab = (params.get('tab') as 'profile' | 'security' | 'preferences') ?? 'profile'
  return (
    <>
      <PageHeader title={t('user.nav.settings')} />
      <Tabs
        label={t('user.nav.settings')}
        value={tab}
        onChange={(v) => setParams({ tab: v }, { replace: true })}
        items={[
          { value: 'profile', label: t('settings.profile') },
          { value: 'security', label: t('settings.security') },
          { value: 'preferences', label: t('settings.preferences') },
        ]}
      />
      <div className="mt-5 max-w-3xl">
        {tab === 'profile' && <ProfileForm />}
        {tab === 'security' && <SecurityForm />}
        {tab === 'preferences' && <PreferencesForm />}
      </div>
    </>
  )
}

function ProfileForm() {
  const { t } = useTranslation()
  const { me, setMe } = useAuth()
  const toast = useToast()
  const [f, setF] = useState({ name: me?.name ?? '', phone: me?.phone ?? '' })
  const [errs, setErrs] = useState<Record<string, string[]>>({})
  const [busy, setBusy] = useState(false)
  const [del, setDel] = useState(false)
  const dirty = f.name !== me?.name || f.phone !== (me?.phone ?? '')
  return (
    <div className="grid gap-4">
      <Card>
        <form
          className="grid gap-4"
          onSubmit={async (e) => {
            e.preventDefault()
            setBusy(true)
            setErrs({})
            try {
              const r = await http.patch<{ data: any }>('/me', f)
              setMe(r.data)
              toast.push('success', t('common.saved'))
            } catch (er) {
              if (er instanceof ApiError) setErrs(er.fields)
            } finally {
              setBusy(false)
            }
          }}
        >
          <TextField label={t('fields.full_name')} required value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} error={errs.name?.[0]} autoComplete="name" />
          <TextField label={t('fields.email')} value={me?.email ?? ''} disabled hint={t('settings.email_change_hint')} />
          <TextField label={t('fields.whatsapp')} optional value={f.phone} onChange={(e) => setF({ ...f, phone: e.target.value })} error={errs.phone?.[0]} placeholder="+6281234567890" />
          <div>
            <Button type="submit" loading={busy} disabled={!dirty}>
              {t('common.save')}
            </Button>
          </div>
        </form>
      </Card>
      <Card className="border-danger/40">
        <CardTitle>{t('settings.delete_account')}</CardTitle>
        <p className="t-small mb-3 text-muted">{t('settings.delete_body')}</p>
        <Button variant="danger" onClick={() => setDel(true)}>
          {t('settings.delete_account')}
        </Button>
      </Card>
      <ConfirmDialog
        open={del}
        onClose={() => setDel(false)}
        destructive
        keyword={t('common.delete_keyword')}
        title={t('settings.delete_account')}
        body={t('settings.delete_confirm')}
        confirmLabel={t('settings.delete_account')}
        onConfirm={async () => {
          await http.post('/me/deactivate')
          window.location.href = '/'
        }}
      />
    </div>
  )
}

function SecurityForm() {
  const { t } = useTranslation()
  const { me } = useAuth()
  const toast = useToast()
  const sessions = useApi<{ id: string; device: string; ip: string; last_active: string; current: boolean }[]>('/me/sessions')
  const [cur, setCur] = useState('')
  const [pw, setPw] = useState('')
  const [confirm, setConfirm] = useState('')
  const [focus, setFocus] = useState(false)
  const [errs, setErrs] = useState<Record<string, string>>({})
  const [busy, setBusy] = useState(false)
  return (
    <div className="grid gap-4">
      <Card>
        <CardTitle>{t('settings.change_password')}</CardTitle>
        <form
          className="grid gap-4"
          onSubmit={async (e) => {
            e.preventDefault()
            const x: Record<string, string> = {}
            if (!passwordScore(pw, [me?.email.split('@')[0] ?? '']).rules.every((r) => r.ok)) x.pw = t('validation.password_rules')
            if (pw !== confirm) x.confirm = t('validation.password_match')
            setErrs(x)
            if (Object.keys(x).length) return
            setBusy(true)
            try {
              await http.put('/me/password', { current_password: cur, password: pw, password_confirmation: confirm })
              toast.push('success', t('settings.password_changed'))
              setCur('')
              setPw('')
              setConfirm('')
              sessions.reload()
            } catch (er) {
              if (er instanceof ApiError) setErrs({ cur: er.fields.current_password?.[0] ?? er.message })
            } finally {
              setBusy(false)
            }
          }}
        >
          <PasswordField label={t('settings.current_password')} required autoComplete="current-password" value={cur} onChange={(e) => setCur(e.target.value)} error={errs.cur} />
          <div className="grid gap-2">
            <PasswordField label={t('auth.new_password')} required autoComplete="new-password" value={pw} onChange={(e) => setPw(e.target.value)} onFocus={() => setFocus(true)} error={errs.pw} />
            <PasswordStrength value={pw} context={[me?.email.split('@')[0] ?? '', me?.name ?? '']} visible={focus || pw.length > 0} />
          </div>
          <PasswordField label={t('auth.password_confirm')} required autoComplete="new-password" value={confirm} onChange={(e) => setConfirm(e.target.value)} error={errs.confirm} />
          <div>
            <Button type="submit" loading={busy}>
              {t('auth.save_password')}
            </Button>
          </div>
        </form>
      </Card>
      <Card>
        <CardTitle
          action={
            <Button size="sm" variant="secondary" icon={<LogOut className="size-4" aria-hidden />} onClick={async () => { await http.post('/me/sessions/revoke-others'); sessions.reload(); toast.push('success', t('settings.sessions_revoked')) }}>
              {t('settings.revoke_others')}
            </Button>
          }
        >
          {t('settings.sessions')}
        </CardTitle>
        {!sessions.data ? (
          <Skeleton className="h-20" />
        ) : (
          <ul className="grid gap-2">
            {sessions.data.map((s) => (
              <li key={s.id} className="flex items-center gap-3 rounded-md bg-surface-2 px-3 py-2">
                <Monitor className="size-5 text-muted" aria-hidden />
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium text-fg">{s.device}</p>
                  <p className="t-small text-muted">
                    {s.ip} — {relative(s.last_active)}
                  </p>
                </div>
                {s.current && <StatusChip status="active" label={t('settings.this_device')} />}
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  )
}

function PreferencesForm() {
  const { t, i18n } = useTranslation()
  const { mode, setMode } = useTheme()
  const toast = useToast()
  const [loader, setLoader] = useState(getPref('krafio.loader') ?? 'auto')
  const save = (patch: Record<string, string>) => http.patch('/me/preferences', patch).catch(() => undefined)
  return (
    <Card>
      <div className="grid gap-5">
        <div className="grid gap-1.5">
          <span className="t-label text-fg">{t('theme.label')}</span>
          <Segmented<ThemeMode>
            label={t('theme.label')}
            value={mode}
            onChange={(v) => {
              setMode(v)
              void save({ theme: v })
            }}
            items={(['light', 'dark'] as ThemeMode[]).map((m) => ({ value: m, label: t(`theme.${m}`) }))}
          />
        </div>
        <SelectField
          label={t('lang.label')}
          value={i18n.language === 'en' ? 'en' : 'id'}
          onChange={(e) => {
            const l = e.target.value as 'id' | 'en'
            void changeLanguage(l)
            void save({ locale: l })
          }}
        >
          <option value="id">Bahasa Indonesia</option>
          <option value="en">English</option>
        </SelectField>
        <SelectField
          label={t('settings.loader_style')}
          hint={t('settings.loader_hint')}
          value={loader}
          onChange={(e) => {
            setLoader(e.target.value)
            setPref('krafio.loader', e.target.value)
            void save({ loader: e.target.value })
            toast.push('success', t('common.saved'))
          }}
        >
          <option value="auto">{t('settings.loader_auto')}</option>
          <option value="transparent">{t('settings.loader_transparent')}</option>
          <option value="dark">{t('settings.loader_dark')}</option>
        </SelectField>
        <Switch
          checked={getPref('krafio.cs.hidden') === null}
          onChange={(v) => {
            setPref('krafio.cs.hidden', v ? null : new Date().toISOString().slice(0, 10))
            toast.push('success', t('common.saved'))
          }}
          label={t('settings.show_cs')}
          description={t('settings.show_cs_desc')}
        />
      </div>
    </Card>
  )
}
