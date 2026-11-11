import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { PlugZap, Save, Send, RefreshCw, Download, Trash2, DatabaseBackup, Copy } from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import { useApi } from '@/shared/hooks/useApi'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { useToast } from '@/shared/context/ToastContext'
import { Card, CardTitle, KpiCard, PageHeader, Segmented } from '@/shared/ui/Misc'
import { Button } from '@/shared/ui/Button'
import { StatusChip } from '@/shared/ui/Status'
import { SelectField, Switch, TextField } from '@/shared/ui/Field'
import { Skeleton, EmptyState } from '@/shared/ui/States'
import { ConfirmDialog } from '@/shared/ui/ConfirmDialog'
import { dateTime, number, rupiah } from '@/shared/lib/format'
import { InfoCard } from './ResourcePage'
import { useAdmin } from './AdminContext'

interface FieldDef {
  key: string
  type: 'text' | 'secret' | 'number' | 'bool' | 'select'
  required?: boolean
  options?: string[]
}
interface IntegrationData {
  kind: 'payment' | 'smtp' | 'whatsapp'
  driver: string
  drivers: { key: string; label: string; fields: FieldDef[] }[]
  values: Record<string, any>
  is_active: boolean
  last_test_status: string | null
  last_test_message: string | null
  last_tested_at: string | null
  webhook_urls?: Record<string, string>
}

/**
 * Integrasi dari Admin Panel: pilih driver, isi credential (disimpan terenkripsi, secret tidak pernah
 * ditampilkan ulang), aktif/nonaktif, dan "Uji koneksi" (§7.2 butir 13: Draft/Aktif, Uji, Simpan saat dirty).
 */
export function IntegrationPage({ kind }: { kind: 'payment' | 'smtp' | 'whatsapp' }) {
  const { t } = useTranslation()
  useDocTitle(t(`ops.${kind}_title`))
  const toast = useToast()
  const { withReauth } = useAdmin()
  const { data, reload } = useApi<IntegrationData>(`/admin/integrations/${kind}`)
  const [driver, setDriver] = useState('')
  const [values, setValues] = useState<Record<string, any>>({})
  const [active, setActive] = useState(false)
  const [busy, setBusy] = useState(false)
  const [testing, setTesting] = useState(false)
  const [errs, setErrs] = useState<Record<string, string[]>>({})
  const [testTo, setTestTo] = useState('')
  const [waEvent, setWaEvent] = useState('test')

  useEffect(() => {
    if (!data) return
    setDriver(data.driver)
    setValues({ ...data.values })
    setActive(data.is_active)
  }, [data])

  const def = data?.drivers.find((d) => d.key === driver)
  const initial = useMemo(() => (data ? JSON.stringify({ d: data.driver, v: data.values, a: data.is_active }) : ''), [data])
  const dirty = !!data && JSON.stringify({ d: driver, v: values, a: active }) !== initial
  if (!data || !def) return <Skeleton className="h-96" />

  const save = async () => {
    setBusy(true)
    setErrs({})
    try {
      await withReauth(() => http.put(`/admin/integrations/${kind}`, { driver, values, is_active: active }))
      toast.push('success', t('common.saved'))
      reload()
    } catch (e) {
      if (e instanceof ApiError) {
        setErrs(e.fields)
        toast.push('error', e.message)
      }
    } finally {
      setBusy(false)
    }
  }
  const test = async () => {
    setTesting(true)
    try {
      const r = await http.post<{ data: { ok: boolean; message: string } }>(`/admin/integrations/${kind}/test`, testTo ? { to: testTo } : {})
      toast.push(r.data.ok ? 'success' : 'error', r.data.message)
      reload()
    } catch (e) {
      toast.push('error', e instanceof ApiError ? e.message : t('errors.generic'))
    } finally {
      setTesting(false)
    }
  }

  return (
    <>
      <PageHeader
        title={t(`ops.${kind}_title`)}
        subtitle={t(`ops.${kind}_sub`)}
        actions={
          <>
            <Button variant="secondary" loading={testing} disabled={dirty} onClick={test} icon={<PlugZap className="size-4" aria-hidden />}>{t('admin.test_connection')}</Button>
            <Button loading={busy} disabled={!dirty} onClick={save} icon={<Save className="size-4" aria-hidden />}>{t('common.save')}</Button>
          </>
        }
      />
      <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
        <Card>
          <div className="grid gap-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <StatusChip status={active ? 'active' : 'draft'} label={active ? t('ops.active') : t('ops.inactive')} />
              <Switch label={t('ops.enable')} description={t(`ops.${kind}_enable_desc`)} checked={active} onChange={setActive} />
            </div>
            {data.drivers.length > 1 && (
              <SelectField label={t('ops.driver')} value={driver} onChange={(e) => { setDriver(e.target.value); setValues({}) }}>
                {data.drivers.map((d) => <option key={d.key} value={d.key}>{t(`ops.driver_${d.key}`, { defaultValue: d.label })}</option>)}
              </SelectField>
            )}
            {def.fields.length === 0 && <p className="t-small rounded-md bg-surface-2 p-3 text-muted">{t('ops.manual_note')}</p>}
            <div className="grid gap-4 md:grid-cols-2">
              {def.fields.map((f) => {
                const label = t(`ops.f.${f.key}`, { defaultValue: f.key })
                const err = errs[`values.${f.key}`]?.[0]
                const set = (v: any) => setValues((s) => ({ ...s, [f.key]: v }))
                if (f.type === 'bool') return <div key={f.key} className="md:col-span-2"><Switch label={label} checked={!!values[f.key]} onChange={set} /></div>
                if (f.type === 'select')
                  return (
                    <SelectField key={f.key} label={label} required={f.required} value={values[f.key] ?? f.options?.[0] ?? ''} onChange={(e) => set(e.target.value)} error={err}>
                      {f.options?.map((o) => <option key={o} value={o}>{o.toUpperCase()}</option>)}
                    </SelectField>
                  )
                if (f.type === 'secret')
                  return (
                    <TextField key={f.key} label={label} required={f.required} type="password" autoComplete="new-password" value={values[f.key] === '__set__' ? '' : values[f.key] ?? ''} placeholder={values[f.key] === '__set__' || data.values[f.key] === '__set__' ? '••••••••••••' : ''} onChange={(e) => set(e.target.value || (data.values[f.key] === '__set__' ? '__set__' : ''))} hint={data.values[f.key] === '__set__' ? t('admin.secret_set') : t('admin.secret_never_shown')} error={err} />
                  )
                return <TextField key={f.key} label={label} required={f.required} inputMode={f.type === 'number' ? 'numeric' : undefined} value={values[f.key] ?? ''} onChange={(e) => set(f.type === 'number' ? e.target.value.replace(/\D/g, '') : e.target.value)} error={err} />
              })}
            </div>
            {kind === 'smtp' && <TextField label={t('ops.test_to')} type="email" value={testTo} onChange={(e) => setTestTo(e.target.value)} hint={t('ops.test_to_hint')} optional />}
          </div>
        </Card>
        <div className="grid min-w-0 content-start gap-4">
          <Card>
            <CardTitle>{t('ops.last_test')}</CardTitle>
            {data.last_tested_at ? (
              <div className="grid gap-2">
                <StatusChip status={data.last_test_status === 'success' ? 'success' : 'failed'} label={data.last_test_status === 'success' ? t('ops.test_ok') : t('ops.test_fail')} />
                <p className="t-small text-fg">{data.last_test_message}</p>
                <p className="t-small text-muted">{dateTime(data.last_tested_at)}</p>
              </div>
            ) : (
              <p className="t-small text-muted">{t('ops.never_tested')}</p>
            )}
          </Card>
          {kind === 'payment' && data.webhook_urls && (
            <Card>
              <CardTitle>{t('ops.webhook_urls')}</CardTitle>
              <p className="t-small mb-3 text-muted">{t('ops.webhook_hint')}</p>
              <ul className="grid min-w-0 grid-cols-1 gap-2">
                {Object.entries(data.webhook_urls).map(([k, u]) => (
                  <li key={k} className="grid min-w-0 gap-1">
                    <span className="t-label capitalize">{k}</span>
                    <span className="flex min-w-0 items-center gap-2">
                      <code className="t-mono min-w-0 flex-1 break-all rounded-md bg-surface-2 px-2 py-1.5">{u}</code>
                      <button type="button" aria-label={t('common.copy')} onClick={() => navigator.clipboard?.writeText(u)} className="grid size-9 place-items-center rounded-md hover:bg-surface-2">
                        <Copy className="size-4" aria-hidden />
                      </button>
                    </span>
                  </li>
                ))}
              </ul>
            </Card>
          )}
          {kind === 'whatsapp' && (
            <Card>
              <CardTitle>{t('ops.wa_test_title')}</CardTitle>
              <div className="grid gap-3">
                <TextField label={t('fields.whatsapp')} value={testTo} onChange={(e) => setTestTo(e.target.value)} placeholder="+6281234567890" />
                <SelectField label={t('ops.event')} value={waEvent} onChange={(e) => setWaEvent(e.target.value)}>
                  {['test', 'otp', 'topup_success', 'subscription_active', 'withdrawal_paid'].map((x) => <option key={x} value={x}>{t(`ops.events.${x}`)}</option>)}
                </SelectField>
                <Button variant="secondary" icon={<Send className="size-4" aria-hidden />} disabled={!testTo} onClick={async () => { const r = await http.post<{ data: { ok: boolean; message: string } }>('/admin/whatsapp/test-send', { to: testTo, event: waEvent }); toast.push(r.data.ok ? 'success' : 'error', r.data.message) }}>{t('ops.send_test')}</Button>
                <Button variant="ghost" icon={<RefreshCw className="size-4" aria-hidden />} onClick={async () => { const r = await http.post<{ data: { ok: boolean; message: string } }>('/admin/whatsapp/sync-templates'); toast.push(r.data.ok ? 'success' : 'error', r.data.message) }}>{t('ops.sync_templates')}</Button>
              </div>
            </Card>
          )}
          <InfoCard title={t('admin.note')}>{t(`ops.${kind}_note`)}</InfoCard>
        </div>
      </div>
    </>
  )
}

/* ---------------- Laporan keuangan ---------------- */
interface FinRow {
  period: string
  topup: number
  sales: number
  withdrawals_paid: number
  commissions: number
}
export function FinanceReportPage() {
  const { t } = useTranslation()
  useDocTitle(t('ops.finance_title'))
  const today = new Date().toISOString().slice(0, 10)
  const monthAgo = new Date(Date.now() - 29 * 86400_000).toISOString().slice(0, 10)
  const [from, setFrom] = useState(monthAgo)
  const [to, setTo] = useState(today)
  const [group, setGroup] = useState<'day' | 'month'>('day')
  const { data, loading } = useApi<{ rows: FinRow[]; totals: Omit<FinRow, 'period'>; outstanding: Record<string, number> }>('/admin/reports/finance', { from, to, group })
  const msg = useApi<{ mail: Record<string, number>; whatsapp: Record<string, number> }>('/admin/reports/messaging')
  const max = Math.max(1, ...(data?.rows ?? []).map((r) => r.sales + r.topup))
  return (
    <>
      <PageHeader
        title={t('ops.finance_title')}
        subtitle={t('ops.finance_sub')}
        actions={<a href={`/api/admin/reports/finance?from=${from}&to=${to}&group=${group}&export=csv`} className="inline-flex h-10 items-center gap-2 rounded-md border border-line bg-surface px-3 text-sm font-semibold hover:bg-surface-2"><Download className="size-4" aria-hidden />{t('table.export')}</a>}
      />
      <Card className="mb-4">
        <div className="flex flex-wrap items-end gap-3">
          <TextField label={t('ops.from')} type="date" value={from} onChange={(e) => setFrom(e.target.value)} wrapClassName="w-44" />
          <TextField label={t('ops.to')} type="date" value={to} onChange={(e) => setTo(e.target.value)} wrapClassName="w-44" />
          <Segmented label={t('ops.group')} value={group} onChange={setGroup} items={[{ value: 'day', label: t('ops.daily') }, { value: 'month', label: t('ops.monthly') }]} />
        </div>
      </Card>
      {!data ? (
        <Skeleton className="h-80" />
      ) : (
        <>
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <KpiCard label={t('ops.sales')} value={rupiah(data.totals.sales)} tone="commission" />
            <KpiCard label={t('ops.topups')} value={rupiah(data.totals.topup)} tone="topup" />
            <KpiCard label={t('ops.withdrawals_paid')} value={rupiah(data.totals.withdrawals_paid)} tone="warning" />
            <KpiCard label={t('ops.commissions')} value={rupiah(data.totals.commissions)} />
          </div>
          <div className="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
            <Card>
              <CardTitle>{t('ops.per_period')}</CardTitle>
              {data.rows.length === 0 ? (
                <EmptyState title={t('ops.no_transactions')} />
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full min-w-[560px] text-sm" aria-busy={loading}>
                    <thead>
                      <tr className="border-b border-line text-left text-muted">
                        <th className="t-label py-2">{t('ops.period')}</th>
                        <th className="t-label py-2 text-right">{t('ops.sales')}</th>
                        <th className="t-label py-2 text-right">{t('ops.topups')}</th>
                        <th className="t-label py-2 text-right">{t('ops.withdrawals_paid')}</th>
                        <th className="t-label py-2 text-right">{t('ops.commissions')}</th>
                        <th className="w-28 py-2"><span className="sr-only">{t('ops.volume')}</span></th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.rows.map((r) => (
                        <tr key={r.period} className="border-b border-line last:border-0">
                          <td className="py-2 tabular-nums">{r.period}</td>
                          <td className="py-2 text-right tabular-nums">{rupiah(r.sales)}</td>
                          <td className="py-2 text-right tabular-nums">{rupiah(r.topup)}</td>
                          <td className="py-2 text-right tabular-nums">{rupiah(r.withdrawals_paid)}</td>
                          <td className="py-2 text-right tabular-nums">{rupiah(r.commissions)}</td>
                          <td className="py-2 pl-3"><div className="h-2 rounded-full bg-primary/70" style={{ width: `${Math.max(4, ((r.sales + r.topup) / max) * 100)}%` }} /></td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </Card>
            <div className="grid content-start gap-4">
              <Card>
                <CardTitle>{t('ops.outstanding')}</CardTitle>
                <dl className="grid gap-2 text-sm">
                  <div className="flex justify-between"><dt className="text-muted">{t('ops.topup_liability')}</dt><dd className="font-semibold tabular-nums">{rupiah(data.outstanding.topup_balance)}</dd></div>
                  <div className="flex justify-between"><dt className="text-muted">{t('ops.commission_liability')}</dt><dd className="font-semibold tabular-nums">{rupiah(data.outstanding.commission_balance)}</dd></div>
                  <div className="flex justify-between"><dt className="text-muted">{t('ops.withdraw_pending')}</dt><dd className="font-semibold tabular-nums">{rupiah(data.outstanding.withdraw_pending)}</dd></div>
                </dl>
              </Card>
              <Card>
                <CardTitle>{t('ops.messaging_30d')}</CardTitle>
                {!msg.data ? (
                  <Skeleton className="h-20" />
                ) : (
                  <dl className="grid gap-2 text-sm">
                    {(['mail', 'whatsapp'] as const).map((ch) => (
                      <div key={ch} className="flex flex-wrap items-center justify-between gap-2">
                        <dt className="text-muted">{t(`ops.ch_${ch}`)}</dt>
                        <dd className="flex gap-1.5">
                          {['sent', 'failed', 'skipped'].map((s) => <StatusChip key={s} status={s === 'sent' ? 'success' : s === 'failed' ? 'failed' : 'draft'} label={`${t(`ops.st_${s}`)} ${number(msg.data![ch][s] ?? 0)}`} />)}
                        </dd>
                      </div>
                    ))}
                  </dl>
                )}
              </Card>
            </div>
          </div>
        </>
      )}
    </>
  )
}

/* ---------------- Backup & retensi ---------------- */
interface BackupItem {
  id: number
  type: string
  file: string
  size: number
  status: string
  message: string | null
  trigger: string
  created_at: string
}
export function BackupPage() {
  const { t } = useTranslation()
  useDocTitle(t('ops.backup_title'))
  const toast = useToast()
  const { data, reload } = useApi<{ items: BackupItem[]; settings: Record<string, any>; directory: string; disk_free_gb: number }>('/admin/backups')
  const [running, setRunning] = useState(false)
  const [withFiles, setWithFiles] = useState(true)
  const [del, setDel] = useState<BackupItem | null>(null)
  const [ret, setRet] = useState<Record<string, any> | null>(null)
  const [pruneOpen, setPruneOpen] = useState(false)
  useEffect(() => {
    if (data) setRet(data.settings)
  }, [data])
  if (!data || !ret) return <Skeleton className="h-96" />
  const size = (b: number) => (b > 1024 * 1024 ? `${(b / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`)
  return (
    <>
      <PageHeader
        title={t('ops.backup_title')}
        subtitle={t('ops.backup_sub')}
        actions={
          <Button loading={running} icon={<DatabaseBackup className="size-4" aria-hidden />} onClick={async () => { setRunning(true); try { const r = await http.post<{ data: { database: BackupItem } }>('/admin/backups/run', { files: withFiles }); toast.push(r.data.database.status === 'success' ? 'success' : 'error', r.data.database.status === 'success' ? t('ops.backup_done') : r.data.database.message ?? t('errors.generic')); reload() } finally { setRunning(false) } }}>
            {t('ops.backup_now')}
          </Button>
        }
      />
      <div className="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        <Card>
          <CardTitle action={<Switch label={t('ops.include_files')} checked={withFiles} onChange={setWithFiles} />}>{t('ops.backup_list')}</CardTitle>
          {data.items.length === 0 ? (
            <EmptyState title={t('ops.no_backups')} />
          ) : (
            <ul className="divide-y divide-line">
              {data.items.map((b) => (
                <li key={b.id} className="flex flex-wrap items-center gap-3 py-2.5">
                  <StatusChip status={b.status === 'success' ? 'success' : 'failed'} label={t(`ops.type_${b.type}`)} />
                  <div className="min-w-0 flex-1">
                    <p className="t-mono break-all text-fg">{b.file}</p>
                    <p className="t-small text-muted">{dateTime(b.created_at)} · {size(b.size)} · {t(`ops.trigger_${b.trigger}`)}{b.message ? ` · ${b.type === 'files' && /^\d+$/.test(b.message) ? t('ops.file_count', { count: Number(b.message) }) : b.message}` : ''}</p>
                  </div>
                  {b.status === 'success' && (
                    <a href={`/api/admin/backups/${b.id}/download`} aria-label={t('ops.download')} className="grid size-9 place-items-center rounded-md hover:bg-surface-2">
                      <Download className="size-4" aria-hidden />
                    </a>
                  )}
                  <button type="button" aria-label={t('common.delete')} onClick={() => setDel(b)} className="grid size-9 place-items-center rounded-md text-muted hover:bg-surface-2 hover:text-danger">
                    <Trash2 className="size-4" aria-hidden />
                  </button>
                </li>
              ))}
            </ul>
          )}
        </Card>
        <div className="grid content-start gap-4">
          <Card>
            <CardTitle>{t('ops.retention')}</CardTitle>
            <div className="grid gap-3">
              {Object.keys(ret).map((k) =>
                typeof ret[k] === 'boolean' ? (
                  <Switch key={k} label={t(`ops.r.${k.replace(/\./g, '_')}`)} checked={!!ret[k]} onChange={(v) => setRet({ ...ret, [k]: v })} />
                ) : (
                  <TextField key={k} label={t(`ops.r.${k.replace(/\./g, '_')}`)} inputMode="numeric" value={String(ret[k] ?? '')} onChange={(e) => setRet({ ...ret, [k]: e.target.value.replace(/\D/g, '') })} />
                ),
              )}
              <div className="flex flex-wrap gap-2">
                <Button onClick={async () => { await http.put('/admin/settings', { values: Object.fromEntries(Object.entries(ret).map(([k, v]) => [k, typeof v === 'boolean' ? v : Number(v)])) }); toast.push('success', t('common.saved')); reload() }}>{t('common.save')}</Button>
                <Button variant="secondary" onClick={() => setPruneOpen(true)}>{t('ops.prune_now')}</Button>
              </div>
            </div>
          </Card>
          <InfoCard title={t('admin.note')}>{t('ops.backup_note', { dir: data.directory, free: data.disk_free_gb })}</InfoCard>
        </div>
      </div>
      <ConfirmDialog open={!!del} onClose={() => setDel(null)} destructive title={t('ops.delete_backup')} body={del?.file} confirmLabel={t('common.delete')} onConfirm={async () => { await http.del(`/admin/backups/${del!.id}`); reload() }} />
      <ConfirmDialog
        open={pruneOpen}
        onClose={() => setPruneOpen(false)}
        title={t('ops.prune_now')}
        body={t('ops.prune_body')}
        confirmLabel={t('ops.prune_now')}
        onConfirm={async () => {
          const r = await http.post<{ data: Record<string, number> }>('/admin/maintenance/prune')
          toast.push('success', t('ops.pruned', { n: Object.values(r.data).reduce((a, b) => a + b, 0) }))
        }}
      />
    </>
  )
}
