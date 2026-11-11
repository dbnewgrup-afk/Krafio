import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { SlidersHorizontal, ShieldCheck, Undo2 } from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import { useApi } from '@/shared/hooks/useApi'
import { useToast } from '@/shared/context/ToastContext'
import { Card, CardTitle } from '@/shared/ui/Misc'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Status'
import { Modal } from '@/shared/ui/Modal'
import { ConfirmDialog } from '@/shared/ui/ConfirmDialog'
import { SelectField, TextArea, TextField } from '@/shared/ui/Field'
import { Skeleton } from '@/shared/ui/States'
import { dateTime, number } from '@/shared/lib/format'
import { cn } from '@/shared/lib/cn'

type Dict = Record<string, any>
interface PlanOpt { code: string; name: string; features: string[]; quotas: Dict; limits: Dict }
interface FeatureData {
  current: null | { plan_code: string; plan_name: string; source: string; price: number; starts_at: string | null; ends_at: string | null; granted_by_admin: boolean }
  features: string[]
  quotas: Dict
  limits: Dict
  upcoming: number
  catalog: { feature_keys: string[]; quota_keys: string[]; limit_keys: string[]; plans: PlanOpt[] }
}

/** Kartu "Fitur & paket" di detail user. Akun admin (pengelola) tidak pernah punya paket/fitur. */
export function UserFeaturesCard({ userId, isAdmin, onChanged }: { userId: string | number; isAdmin: boolean; onChanged: () => void }) {
  const { t } = useTranslation()
  if (isAdmin) {
    return (
      <Card className="mb-4 border-info/30 bg-info/5">
        <p className="t-label inline-flex items-center gap-2 text-fg"><ShieldCheck className="size-4 text-info" aria-hidden />{t('admin.uf.admin_title')}</p>
        <p className="t-small mt-1 text-muted">{t('admin.uf.admin_body')}</p>
      </Card>
    )
  }
  return <FeaturesInner userId={userId} onChanged={onChanged} />
}

function FeaturesInner({ userId, onChanged }: { userId: string | number; onChanged: () => void }) {
  const { t } = useTranslation()
  const toast = useToast()
  const f = useApi<FeatureData>(`/admin/users/${userId}/features`)
  const [open, setOpen] = useState(false)
  const [revoke, setRevoke] = useState(false)
  if (!f.data) return <Skeleton className="mb-4 h-40" />
  const d = f.data
  const reload = () => { f.reload(); onChanged() }
  return (
    <Card className="mb-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <CardTitle>{t('admin.uf.title')}</CardTitle>
          <p className="t-small -mt-2 text-muted">
            {d.current ? (
              <>
                <span className="font-medium text-fg">{d.current.plan_name}</span>
                {d.current.granted_by_admin && <span className="ml-2"><Badge tone="info">{t('admin.uf.by_admin')}</Badge></span>}
                {' · '}
                {d.current.ends_at ? t('admin.uf.until', { date: dateTime(d.current.ends_at, false) }) : t('admin.uf.no_end')}
              </>
            ) : t('admin.uf.none')}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          {d.current?.granted_by_admin && <Button variant="secondary" size="sm" icon={<Undo2 className="size-4" aria-hidden />} onClick={() => setRevoke(true)}>{t('admin.uf.revoke')}</Button>}
          <Button size="sm" icon={<SlidersHorizontal className="size-4" aria-hidden />} onClick={() => setOpen(true)}>{t('admin.uf.configure')}</Button>
        </div>
      </div>
      <div className="mt-3 flex flex-wrap gap-1.5">
        {d.catalog.feature_keys.map((k) => (
          <span key={k} className={cn('t-mono inline-flex h-6 items-center rounded-full px-2.5 text-[12px]', d.features.includes(k) ? 'bg-success/14 text-success' : 'bg-surface-2 text-muted line-through')}>{k}</span>
        ))}
      </div>
      <dl className="mt-4 grid grid-cols-2 gap-x-6 gap-y-1.5 text-sm sm:grid-cols-4">
        {[...d.catalog.quota_keys.map((k) => [k, d.quotas[k]] as const), ...d.catalog.limit_keys.map((k) => [k, d.limits[k]] as const)].map(([k, v]) => (
          <div key={k} className="flex justify-between gap-2 border-b border-line py-1">
            <dt className="t-small truncate text-muted">{t(`admin.uf.k.${k}`, { defaultValue: k })}</dt>
            <dd className="tabular-nums">{v === undefined || v === null ? '—' : number(Number(v))}</dd>
          </div>
        ))}
      </dl>
      {open && <ConfigureModal data={d} userId={userId} onClose={() => setOpen(false)} onSaved={() => { setOpen(false); toast.push('success', t('common.saved')); reload() }} />}
      <ConfirmDialog
        open={revoke}
        onClose={() => setRevoke(false)}
        destructive
        requireReason
        title={t('admin.uf.revoke')}
        body={t('admin.uf.revoke_body')}
        confirmLabel={t('admin.uf.revoke')}
        onConfirm={async (reason) => {
          await http.del(`/admin/users/${userId}/features`, { reason })
          toast.push('success', t('common.saved'))
          reload()
        }}
      />
    </Card>
  )
}

function ConfigureModal({ data, userId, onClose, onSaved }: { data: FeatureData; userId: string | number; onClose: () => void; onSaved: () => void }) {
  const { t } = useTranslation()
  const plans = data.catalog.plans
  const [mode, setMode] = useState<'plan' | 'custom'>('plan')
  const [planCode, setPlanCode] = useState(plans.find((p) => p.code !== 'free')?.code ?? plans[0]?.code ?? '')
  const [base, setBase] = useState(data.current?.plan_code && plans.some((p) => p.code === data.current!.plan_code) ? data.current.plan_code : planCode)
  const basePlan = plans.find((p) => p.code === base)
  const [name, setName] = useState('')
  const [features, setFeatures] = useState<string[]>(data.features)
  const [quotas, setQuotas] = useState<Dict>(() => Object.fromEntries(data.catalog.quota_keys.map((k) => [k, data.quotas[k] ?? ''])))
  const [limits, setLimits] = useState<Dict>(() => Object.fromEntries(data.catalog.limit_keys.map((k) => [k, data.limits[k] ?? ''])))
  const [days, setDays] = useState('30')
  const [reason, setReason] = useState('')
  const [busy, setBusy] = useState(false)
  const [errs, setErrs] = useState<Record<string, string[]>>({})

  const applyBase = (code: string) => {
    setBase(code)
    const p = plans.find((x) => x.code === code)
    if (!p) return
    setFeatures(p.features)
    setQuotas(Object.fromEntries(data.catalog.quota_keys.map((k) => [k, p.quotas[k] ?? ''])))
    setLimits(Object.fromEntries(data.catalog.limit_keys.map((k) => [k, p.limits[k] ?? ''])))
  }
  const preview = useMemo(() => plans.find((p) => p.code === planCode), [plans, planCode])
  const replacesPaid = !!data.current && data.current.price > 0
  const valid = reason.trim().length >= 5 && (mode === 'plan' ? !!planCode : name.trim().length >= 2)

  const save = async () => {
    setBusy(true)
    setErrs({})
    const num = (o: Dict) => Object.fromEntries(Object.entries(o).filter(([, v]) => v !== '' && v !== null).map(([k, v]) => [k, Number(v)]))
    try {
      await http.post(`/admin/users/${userId}/features`, mode === 'plan'
        ? { mode, plan_code: planCode, days: days ? Number(days) : null, reason }
        : { mode, name, base_plan: base || null, features, quotas: num(quotas), limits: num(limits), days: days ? Number(days) : null, reason })
      onSaved()
    } catch (e) {
      if (e instanceof ApiError) setErrs(e.fields)
    } finally {
      setBusy(false)
    }
  }

  const numInput = (label: string, value: any, onChange: (v: string) => void, error?: string) => (
    <TextField label={label} inputMode="numeric" value={value ?? ''} onChange={(e) => onChange(e.target.value.replace(/\D/g, ''))} error={error} />
  )

  return (
    <Modal
      open
      onClose={onClose}
      dirty={!!reason || !!name}
      size="form"
      title={t('admin.uf.configure')}
      icon={<SlidersHorizontal className="size-5 text-primary" aria-hidden />}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>{t('common.cancel')}</Button>
          <Button loading={busy} disabled={!valid} onClick={save}>{t('admin.uf.apply')}</Button>
        </>
      }
    >
      <div className="grid gap-4">
        <div role="radiogroup" aria-label={t('admin.uf.mode')} className="grid grid-cols-2 gap-2">
          {(['plan', 'custom'] as const).map((m) => (
            <button
              key={m}
              type="button"
              role="radio"
              aria-checked={mode === m}
              onClick={() => setMode(m)}
              className={cn('rounded-lg border p-3 text-left transition-colors', mode === m ? 'border-primary bg-primary/6' : 'border-line hover:bg-surface-2')}
            >
              <span className="block font-medium">{t(`admin.uf.mode_${m}`)}</span>
              <span className="t-small text-muted">{t(`admin.uf.mode_${m}_hint`)}</span>
            </button>
          ))}
        </div>

        {mode === 'plan' ? (
          <>
            <SelectField label={t('admin.uf.plan')} value={planCode} onChange={(e) => setPlanCode(e.target.value)} error={errs.plan_code?.[0]}>
              {plans.map((p) => <option key={p.code} value={p.code}>{p.name}</option>)}
            </SelectField>
            {preview && (
              <p className="t-small text-muted">
                {t('admin.uf.plan_preview', { features: preview.features.length, credits: number(Number(preview.quotas.credits ?? 0)), projects: preview.limits.projects ?? 0 })}
              </p>
            )}
          </>
        ) : (
          <>
            <TextField label={t('admin.uf.name')} required value={name} onChange={(e) => setName(e.target.value)} placeholder={t('admin.uf.name_ph')} error={errs.name?.[0]} />
            <SelectField label={t('admin.uf.base')} value={base} onChange={(e) => applyBase(e.target.value)} hint={t('admin.uf.base_hint')}>
              {plans.map((p) => <option key={p.code} value={p.code}>{p.name}</option>)}
            </SelectField>
            <fieldset className="grid gap-2">
              <legend className="t-label mb-1 text-fg">{t('admin.uf.features')}</legend>
              <div className="grid grid-cols-1 gap-1 sm:grid-cols-2">
                {data.catalog.feature_keys.map((k) => (
                  <label key={k} className="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-surface-2">
                    <input type="checkbox" className="size-4 accent-primary" checked={features.includes(k)} onChange={(e) => setFeatures((s) => (e.target.checked ? [...s, k] : s.filter((x) => x !== k)))} />
                    <span className="t-mono">{k}</span>
                  </label>
                ))}
              </div>
              {errs.features?.[0] && <p className="t-small text-danger">{errs.features[0]}</p>}
            </fieldset>
            <fieldset className="grid gap-2">
              <legend className="t-label mb-1 text-fg">{t('admin.uf.quotas')}</legend>
              <div className="grid grid-cols-2 gap-3">
                {data.catalog.quota_keys.map((k) => <div key={k}>{numInput(t(`admin.uf.k.${k}`, { defaultValue: k }), quotas[k], (v) => setQuotas((s) => ({ ...s, [k]: v })), errs[`quotas.${k}`]?.[0])}</div>)}
              </div>
            </fieldset>
            <fieldset className="grid gap-2">
              <legend className="t-label mb-1 text-fg">{t('admin.uf.limits')}</legend>
              <div className="grid grid-cols-2 gap-3">
                {data.catalog.limit_keys.map((k) => <div key={k}>{numInput(t(`admin.uf.k.${k}`, { defaultValue: k }), limits[k], (v) => setLimits((s) => ({ ...s, [k]: v })), errs[`limits.${k}`]?.[0])}</div>)}
              </div>
              {basePlan && <p className="t-small text-muted">{t('admin.uf.limits_hint', { plan: basePlan.name })}</p>}
            </fieldset>
          </>
        )}

        <TextField label={t('admin.uf.days')} inputMode="numeric" value={days} onChange={(e) => setDays(e.target.value.replace(/\D/g, ''))} hint={t('admin.uf.days_hint')} error={errs.days?.[0]} />
        {(replacesPaid || data.upcoming > 0) && <p className="t-small rounded-md bg-warning/14 p-3 text-fg">{t('admin.uf.replace_warning', { plan: data.current?.plan_name ?? '' })}</p>}
        <TextArea label={t('common.reason')} required rows={3} value={reason} onChange={(e) => setReason(e.target.value)} hint={t('common.reason_hint')} error={errs.reason?.[0]} />
      </div>
    </Modal>
  )
}
