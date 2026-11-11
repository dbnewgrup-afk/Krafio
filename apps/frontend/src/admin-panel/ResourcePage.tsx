import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Plus, MoreHorizontal, Pencil, Trash2, PlugZap, KeyRound, Eye } from 'lucide-react'
import { http, ApiError, type Query } from '@/api-client/http'
import { DataTable, useServerList, type Column } from '@/shared/ui/DataTable'
import { PageHeader, Menu, MenuItem, Card } from '@/shared/ui/Misc'
import { Button } from '@/shared/ui/Button'
import { StatusChip, Badge } from '@/shared/ui/Status'
import { Modal } from '@/shared/ui/Modal'
import { ConfirmDialog } from '@/shared/ui/ConfirmDialog'
import { TextField, TextArea, SelectField, Switch } from '@/shared/ui/Field'
import { useToast } from '@/shared/context/ToastContext'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { useIsDesktop } from '@/shared/hooks/useMediaQuery'
import { dateTime, number, rupiah } from '@/shared/lib/format'
import { RESOURCES, abilities, type ColDef, type FieldDef } from './resources'
import { useAdmin } from './AdminContext'

type Row = Record<string, any> & { id: number | string }

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function renderCell(row: Row, c: ColDef, t: any) {
  const v = row[c.key]
  if (v === null || v === undefined || v === '') return <span className="text-muted">—</span>
  switch (c.type) {
    case 'money':
      return rupiah(v)
    case 'number':
      return number(v)
    case 'date':
      return dateTime(v)
    case 'status':
      return <StatusChip status={String(v)} label={t(`status.${v}`, { defaultValue: String(v) })} />
    case 'bool':
      return v ? <Badge tone="success">{t('common.yes')}</Badge> : <Badge>{t('common.no')}</Badge>
    case 'user':
      return <span className="inline-grid">{v.name ?? '—'}{v.email && <span className="t-small text-muted">{v.email}</span>}</span>
    case 'mono':
      return <span className="t-mono">{String(v)}</span>
    case 'json':
      return <span className="t-mono line-clamp-1 max-w-[280px] text-muted">{JSON.stringify(v)}</span>
    case 'palette':
      return (
        <span className="inline-flex gap-0.5" aria-label={(v as string[]).join(', ')}>
          {(v as string[]).map((c2) => <span key={c2} className="size-4 rounded-sm border border-line" style={{ background: c2 }} />)}
        </span>
      )
    default:
      return <span className="line-clamp-2">{String(v)}</span>
  }
}

export function ResourcePage({ resource, title, subtitle, preset = {}, group, hideCreate }: { resource: string; title: string; subtitle?: string; preset?: Query; group?: string; hideCreate?: boolean }) {
  const { t } = useTranslation()
  useDocTitle(title)
  const def = RESOURCES[resource]
  const can = abilities(def)
  const toast = useToast()
  const nav = useNavigate()
  const { withReauth } = useAdmin()
  const list = useServerList<Row>(`/admin/r/${def.endpoint}`, preset)
  const [editing, setEditing] = useState<Row | 'new' | null>(null)
  const [del, setDel] = useState<Row | null>(null)
  const [bulkDel, setBulkDel] = useState<{ ids: Array<number | string>; clear: () => void } | null>(null)
  const [rotate, setRotate] = useState<Row | null>(null)
  const [newKey, setNewKey] = useState('')
  const [viewing, setViewing] = useState<Row | null>(null)

  const columns: Column<Row>[] = useMemo(
    () =>
      def.columns.map((c) => ({
        key: c.key,
        header: t(`admin.f.${c.key}`, { defaultValue: c.key }),
        sortable: c.sortable,
        primary: c.primary,
        mobile: c.mobile,
        align: c.type === 'money' || c.type === 'number' ? 'right' : undefined,
        render: (r: Row) => renderCell(r, c, t),
      })),
    [def, t],
  )

  const filters = (def.filters ?? []).filter((f) => !(f.key in preset)).map((f) => ({ key: f.key, label: t(`admin.f.${f.key}`, { defaultValue: f.key }), options: f.options.map((o) => ({ value: o, label: t(`status.${o}`, { defaultValue: o === 'true' ? t('common.yes') : o === 'false' ? t('common.no') : o }) })) }))

  return (
    <>
      <PageHeader
        title={title}
        subtitle={subtitle}
        crumbs={group ? [{ label: group }, { label: title }] : undefined}
        actions={can.includes('create') && def.fields && !hideCreate ? <Button icon={<Plus className="size-4" aria-hidden />} onClick={() => setEditing('new')}>{t('admin.add_new')}</Button> : undefined}
      />
      <DataTable<Row>
        list={list}
        columns={columns}
        filters={filters}
        exportName={def.endpoint}
        onRowClick={resource === 'tickets' ? (r) => nav(`/admin/support/tickets/${r.id}`) : (r) => setViewing(r)}
        bulkActions={
          (def.bulk && can.includes('update')) || can.includes('delete')
            ? (ids, clear) => (
                <>
                  {def.bulk && can.includes('update') && (
                    <>
                      <Button size="sm" variant="secondary" onClick={async () => { await http.post(`/admin/r/${def.endpoint}/bulk`, { ids, action: 'activate' }); clear(); list.reload() }}>{t('admin.activate')}</Button>
                      <Button size="sm" variant="secondary" onClick={async () => { await http.post(`/admin/r/${def.endpoint}/bulk`, { ids, action: 'deactivate' }); clear(); list.reload() }}>{t('admin.deactivate')}</Button>
                    </>
                  )}
                  {can.includes('delete') && <Button size="sm" variant="danger" onClick={() => setBulkDel({ ids, clear })}>{t('common.delete')}</Button>}
                </>
              )
            : undefined
        }
        rowActions={(r) => (
                <Menu
                  label={t('table.actions')}
                  width="w-52"
                  trigger={({ toggle, open, id }) => (
                    <button type="button" onClick={(e) => { e.stopPropagation(); toggle() }} aria-expanded={open} aria-controls={id} aria-label={t('table.actions')} className="grid size-10 place-items-center rounded-md hover:bg-surface-2">
                      <MoreHorizontal className="size-4" aria-hidden />
                    </button>
                  )}
                >
                  {(close) => (
                    <>
                      <MenuItem icon={<Eye className="size-4" aria-hidden />} onClick={() => { close(); setViewing(r) }}>{t('admin.view')}</MenuItem>
                      {can.includes('update') && def.fields && <MenuItem icon={<Pencil className="size-4" aria-hidden />} onClick={() => { close(); setEditing(r) }}>{t('common.edit')}</MenuItem>}
                      {def.testable && (
                        <>
                          <MenuItem icon={<PlugZap className="size-4" aria-hidden />} onClick={async () => { close(); try { const res = await http.post<{ data: { ok: boolean } }>(`/admin/ai-providers/${r.id}/test`); toast.push(res.data.ok ? 'success' : 'error', res.data.ok ? t('admin.test_ok') : t('admin.test_failed')); list.reload() } catch (e) { toast.push('error', (e as Error).message) } }}>{t('admin.test_connection')}</MenuItem>
                          <MenuItem icon={<KeyRound className="size-4" aria-hidden />} onClick={() => { close(); setNewKey(''); setRotate(r) }}>{t('admin.rotate_key')}</MenuItem>
                        </>
                      )}
                      {can.includes('delete') && <MenuItem danger icon={<Trash2 className="size-4" aria-hidden />} onClick={() => { close(); setDel(r) }}>{t('common.delete')}</MenuItem>}
                    </>
                  )}
                </Menu>
              )
        }
      />
      {editing && def.fields && <ResourceForm resource={resource} row={editing === 'new' ? null : editing} onClose={() => setEditing(null)} onSaved={() => { setEditing(null); list.reload(); toast.push('success', t('common.saved')) }} />}
      <ConfirmDialog open={!!del} onClose={() => setDel(null)} destructive title={t('admin.delete_title')} body={t('admin.delete_body')} confirmLabel={t('common.delete')} onConfirm={async () => { await http.del(`/admin/r/${def.endpoint}/${del!.id}`); list.reload() }} />
      <ConfirmDialog open={!!bulkDel} onClose={() => setBulkDel(null)} destructive keyword="HAPUS" title={t('admin.bulk_delete_title', { count: bulkDel?.ids.length ?? 0 })} body={t('admin.bulk_delete_body')} confirmLabel={t('common.delete')} onConfirm={async () => { await http.post(`/admin/r/${def.endpoint}/bulk`, { ids: bulkDel!.ids, action: 'delete', confirm: 'HAPUS' }); bulkDel!.clear(); list.reload() }} />
      <Modal
        open={!!rotate}
        onClose={() => setRotate(null)}
        size="form"
        title={t('admin.rotate_key')}
        footer={<Button disabled={newKey.length < 8} onClick={async () => { await withReauth(() => http.post(`/admin/ai-providers/${rotate!.id}/rotate`, { api_key: newKey })); setRotate(null); list.reload(); toast.push('success', t('admin.key_rotated')) }}>{t('admin.rotate_key')}</Button>}
      >
        <TextField label={t('admin.f.api_key')} type="password" autoComplete="off" value={newKey} onChange={(e) => setNewKey(e.target.value)} hint={t('admin.secret_never_shown')} />
      </Modal>
      <Modal open={!!viewing} onClose={() => setViewing(null)} size="form" title={t('admin.detail')} icon={<Eye className="size-5 text-primary" aria-hidden />}>
        {viewing && (
          <dl className="grid gap-2 text-sm">
            {Object.entries(viewing).map(([k, v]) => (
              <div key={k} className="grid grid-cols-[140px_minmax(0,1fr)] gap-3 border-b border-line pb-2">
                <dt className="text-muted">{t(`admin.f.${k}`, { defaultValue: k })}</dt>
                <dd className="t-mono break-words text-fg">{typeof v === 'object' ? JSON.stringify(v) : String(v ?? '—')}</dd>
              </div>
            ))}
          </dl>
        )}
      </Modal>
    </>
  )
}

/* ---------------- Form resource dengan dirty state (§7.2 butir 13–14) ---------------- */
function initial(f: FieldDef, row: Row | null): any {
  const v = row?.[f.name]
  if (f.type === 'json') return v ? JSON.stringify(v, null, 2) : '{}'
  if (f.type === 'tags') return Array.isArray(v) ? v.join(', ') : ''
  if (f.type === 'features') return Array.isArray(v) ? v : []
  if (f.type === 'palette') return Array.isArray(v) ? v : ['#F4E7D3', '#C7A27C', '#3E2C23']
  if (f.type === 'sections') return v ? JSON.stringify(v, null, 2) : '[\n  { "id": "umum", "heading": "Ketentuan umum", "body": "" }\n]'
  if (f.type === 'bool') return v ?? f.default ?? true
  if (f.type === 'date') return v ? String(v).slice(0, 10) : ''
  if (f.type === 'secret') return ''
  return v ?? ''
}

function ResourceForm({ resource, row, onClose, onSaved }: { resource: string; row: Row | null; onClose: () => void; onSaved: () => void }) {
  const { t } = useTranslation()
  const def = RESOURCES[resource]
  const isDesktop = useIsDesktop()
  const fields = useMemo(() => def.fields!.filter((f) => !(row && f.createOnly)), [def, row])
  const init = useMemo(() => Object.fromEntries(fields.map((f) => [f.name, initial(f, row)])), [fields, row])
  const [vals, setVals] = useState<Record<string, any>>(init)
  const [errs, setErrs] = useState<Record<string, string[]>>({})
  const [busy, setBusy] = useState(false)
  const dirty = JSON.stringify(vals) !== JSON.stringify(init)
  const set = (k: string, v: any) => setVals((s) => ({ ...s, [k]: v }))

  const payload = () => {
    const out: Record<string, any> = {}
    for (const f of fields) {
      const v = vals[f.name]
      if (f.type === 'json' || f.type === 'sections') {
        try {
          out[f.name] = JSON.parse(v || (f.type === 'sections' ? '[]' : '{}'))
        } catch {
          throw { field: f.name }
        }
      } else if (f.type === 'tags') out[f.name] = String(v).split(',').map((x) => x.trim()).filter(Boolean)
      else if (f.type === 'number' || f.type === 'money') out[f.name] = v === '' ? null : Number(String(v).replace(/\D/g, ''))
      else if (f.type === 'secret') {
        if (v) out[f.name] = v
      } else if (f.type === 'date') out[f.name] = v || null
      else if (f.type === 'select' && v === '') out[f.name] = null
      else out[f.name] = v
    }
    return out
  }

  const save = async () => {
    setErrs({})
    let body: Record<string, any>
    try {
      body = payload()
    } catch (e: any) {
      setErrs({ [e.field]: [t('admin.invalid_json')] })
      return
    }
    setBusy(true)
    try {
      if (row) await http.put(`/admin/r/${def.endpoint}/${row.id}`, body)
      else await http.post(`/admin/r/${def.endpoint}`, body)
      onSaved()
    } catch (e) {
      if (e instanceof ApiError) setErrs(e.fields)
    } finally {
      setBusy(false)
    }
  }

  return (
    <Modal
      open
      onClose={onClose}
      dirty={dirty}
      size="form"
      title={row ? t('admin.edit_title') : t('admin.create_title')}
      headerExtra={!isDesktop && ['templates', 'legal-documents', 'plans'].includes(resource) ? <p className="t-small text-info">{t('common.wide_screen_hint')}</p> : undefined}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>{t('common.cancel')}</Button>
          <Button loading={busy} disabled={!dirty} onClick={save}>{t('common.save')}</Button>
        </>
      }
    >
      <div className="grid gap-4">
        {fields.map((f) => {
          const label = t(`admin.f.${f.name}`, { defaultValue: f.name })
          const hint = f.hint ? t(`admin.hint.${f.hint}`) : undefined
          const error = errs[f.name]?.[0] ?? Object.entries(errs).find(([k]) => k.startsWith(`${f.name}.`))?.[1][0]
          switch (f.type) {
            case 'textarea':
              return <TextArea key={f.name} label={label} required={f.required} rows={4} value={vals[f.name]} onChange={(e) => set(f.name, e.target.value)} error={error} hint={hint} />
            case 'json':
            case 'sections':
              return <TextArea key={f.name} label={label} required={f.required} rows={f.type === 'sections' ? 10 : 6} className="font-mono text-[13px]" value={vals[f.name]} onChange={(e) => set(f.name, e.target.value)} error={error} hint={hint ?? (f.type === 'sections' ? t('admin.hint.sections_hint') : undefined)} />
            case 'select':
              return (
                <SelectField key={f.name} label={label} required={f.required} value={vals[f.name] ?? ''} onChange={(e) => set(f.name, e.target.value)} error={error}>
                  {f.options!.map((o) => <option key={o} value={o}>{o === '' ? '—' : t(`status.${o}`, { defaultValue: o })}</option>)}
                </SelectField>
              )
            case 'bool':
              return <Switch key={f.name} label={label} description={hint} checked={!!vals[f.name]} onChange={(v) => set(f.name, v)} />
            case 'features':
              return (
                <fieldset key={f.name} className="grid gap-2">
                  <legend className="t-label mb-1 text-fg">{label}</legend>
                  <div className="grid grid-cols-2 gap-1.5">
                    {f.options!.map((o) => (
                      <label key={o} className="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-surface-2">
                        <input type="checkbox" className="size-4 accent-[var(--primary)]" checked={vals[f.name].includes(o)} onChange={(e) => set(f.name, e.target.checked ? [...vals[f.name], o] : vals[f.name].filter((x: string) => x !== o))} />
                        <span className="t-mono">{o}</span>
                      </label>
                    ))}
                  </div>
                  {error && <p className="t-small text-danger">{error}</p>}
                </fieldset>
              )
            case 'palette':
              return (
                <fieldset key={f.name} className="grid gap-2">
                  <legend className="t-label mb-1 text-fg">{label}</legend>
                  <div className="flex gap-3">
                    {vals[f.name].map((c: string, i: number) => (
                      <input key={i} type="color" aria-label={`${label} ${i + 1}`} value={c} onChange={(e) => { const p = [...vals[f.name]]; p[i] = e.target.value.toUpperCase(); set(f.name, p) }} className="h-11 w-16 cursor-pointer rounded-md border border-line bg-surface" />
                    ))}
                  </div>
                </fieldset>
              )
            case 'secret':
              return <TextField key={f.name} label={label} type="password" autoComplete="new-password" value={vals[f.name]} onChange={(e) => set(f.name, e.target.value)} hint={row?.[`${f.name}_set`] ? t('admin.secret_set') : hint} error={error} />
            case 'date':
              return <TextField key={f.name} label={label} type="date" required={f.required} value={vals[f.name]} onChange={(e) => set(f.name, e.target.value)} error={error} />
            case 'money':
              return <TextField key={f.name} label={label} required={f.required} inputMode="numeric" prefix="Rp" value={vals[f.name] === '' || vals[f.name] === null ? '' : new Intl.NumberFormat('id-ID').format(Number(String(vals[f.name]).replace(/\D/g, '') || 0))} onChange={(e) => set(f.name, e.target.value.replace(/\D/g, ''))} error={error} hint={hint} />
            case 'number':
              return <TextField key={f.name} label={label} required={f.required} inputMode="numeric" value={vals[f.name] ?? ''} onChange={(e) => set(f.name, e.target.value.replace(/[^\d]/g, ''))} error={error} hint={hint} />
            case 'tags':
              return <TextField key={f.name} label={label} value={vals[f.name]} onChange={(e) => set(f.name, e.target.value)} error={error} hint={hint ?? t('admin.hint.tags_hint')} />
            default:
              return <TextField key={f.name} label={label} required={f.required} value={vals[f.name] ?? ''} onChange={(e) => set(f.name, e.target.value)} error={error} hint={hint} />
          }
        })}
      </div>
    </Modal>
  )
}

export function InfoCard({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <Card className="mb-4 border-info/30 bg-info/5">
      <p className="t-label text-fg">{title}</p>
      <div className="t-small mt-1 text-muted">{children}</div>
    </Card>
  )
}
