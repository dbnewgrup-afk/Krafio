import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Check, ChevronDown, Minus, Plus, SlidersHorizontal } from 'lucide-react'
import type { AiTool, AiToolField } from '@/api-client/types'
import { isVisible, type ToolValues } from './toolValues'
import { FieldWrap, SelectField, Switch, TextArea, TextField } from '@/shared/ui/Field'
import { Segmented } from '@/shared/ui/Misc'
import { cn } from '@/shared/lib/cn'

/**
 * Form AI Tool berbasis skema dari backend (registry AiTools): field dasar tampil langsung,
 * opsi lanjutan dilipat. Field bersyarat (show_if) muncul/hilang otomatis.
 */
export function ToolForm({ tool, values, onChange, errors }: { tool: AiTool; values: ToolValues; onChange: (v: ToolValues) => void; errors?: Record<string, string[]> }) {
  const { t } = useTranslation()
  const [advanced, setAdvanced] = useState(false)
  const set = (k: string, val: unknown) => onChange({ ...values, [k]: val })
  const basic = tool.fields.filter((f) => f.group !== 'advanced' && isVisible(f, values))
  const adv = tool.fields.filter((f) => f.group === 'advanced' && isVisible(f, values))
  const advFilled = adv.filter((f) => {
    const v = values[f.key]
    return Array.isArray(v) ? v.length > 0 && JSON.stringify(v) !== JSON.stringify(f.default ?? []) : v !== '' && v !== undefined && v !== f.default
  }).length

  return (
    <div className="grid gap-4">
      {basic.map((f) => (
        <FieldInput key={f.key} f={f} value={values[f.key]} onChange={(v) => set(f.key, v)} error={errors?.[f.key]?.[0]} />
      ))}
      {adv.length > 0 && (
        <div className="rounded-lg border border-line">
          <button type="button" onClick={() => setAdvanced(!advanced)} aria-expanded={advanced} className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left">
            <span className="flex items-center gap-2">
              <SlidersHorizontal className="size-4 text-primary" aria-hidden />
              <span className="t-label text-fg">{t('tools.advanced')}</span>
              {advFilled > 0 && <span className="t-small rounded-full bg-primary/10 px-2 text-primary">{t('tools.advanced_set', { count: advFilled })}</span>}
            </span>
            <ChevronDown className={cn('size-4 text-muted transition-transform', advanced && 'rotate-180')} aria-hidden />
          </button>
          {advanced && (
            <div className="grid gap-4 border-t border-line px-4 py-4">
              {adv.map((f) => (
                <FieldInput key={f.key} f={f} value={values[f.key]} onChange={(v) => set(f.key, v)} error={errors?.[f.key]?.[0]} />
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  )
}

function FieldInput({ f, value, onChange, error }: { f: AiToolField; value: unknown; onChange: (v: unknown) => void; error?: string }) {
  const req = !!f.required
  switch (f.type) {
    case 'textarea':
      return <TextArea label={f.label} required={req} optional={!req} rows={f.rows ?? 3} maxLength={f.max} value={String(value ?? '')} onChange={(e) => onChange(e.target.value)} placeholder={f.placeholder} hint={f.hint} error={error} />
    case 'text':
      return <TextField label={f.label} required={req} optional={!req} maxLength={f.max} value={String(value ?? '')} onChange={(e) => onChange(e.target.value)} placeholder={f.placeholder} hint={f.hint} error={error} />
    case 'select':
      return (
        <SelectField label={f.label} value={String(value ?? '')} onChange={(e) => onChange(e.target.value)} hint={f.hint} error={error}>
          {(f.options ?? []).map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))}
        </SelectField>
      )
    case 'segmented':
      return (
        <FieldWrap id={`f-${f.key}`} label={f.label} hint={f.hint} error={error}>
          <div className="max-w-full overflow-x-auto">
            <Segmented label={f.label} value={String(value ?? '')} onChange={(v) => onChange(v)} items={(f.options ?? []).map((o) => ({ value: o.value, label: o.label }))} />
          </div>
        </FieldWrap>
      )
    case 'multiselect': {
      const cur = Array.isArray(value) ? (value as string[]) : []
      return (
        <FieldWrap id={`f-${f.key}`} label={f.label} hint={f.hint} error={error}>
          <div className="flex flex-wrap gap-2" role="group" aria-label={f.label}>
            {(f.options ?? []).map((o) => {
              const on = cur.includes(o.value)
              return (
                <button
                  key={o.value}
                  type="button"
                  aria-pressed={on}
                  onClick={() => onChange(on ? cur.filter((x) => x !== o.value) : [...cur, o.value])}
                  className={cn('t-label inline-flex h-9 items-center gap-1.5 rounded-full border px-3.5 transition-colors', on ? 'border-primary bg-primary/10 text-primary' : 'border-line bg-surface text-fg hover:border-primary/50')}
                >
                  {on && <Check className="size-3.5" aria-hidden />}
                  {o.label}
                </button>
              )
            })}
          </div>
        </FieldWrap>
      )
    }
    case 'number': {
      const n = Number(value ?? f.min ?? 0)
      const clamp = (x: number) => Math.max(f.min ?? 0, Math.min(f.max ?? 1000, x))
      return (
        <FieldWrap id={`f-${f.key}`} label={f.label} hint={f.hint ?? (f.max ? `${f.min ?? 0}–${f.max}` : undefined)} error={error}>
          <div className="inline-flex items-center gap-2">
            <button type="button" aria-label="−" onClick={() => onChange(clamp(n - 1))} disabled={n <= (f.min ?? 0)} className="grid size-10 place-items-center rounded-md border border-line hover:bg-surface-2 disabled:opacity-40">
              <Minus className="size-4" aria-hidden />
            </button>
            <input id={`f-${f.key}`} type="number" inputMode="numeric" min={f.min} max={f.max} value={n} onChange={(e) => onChange(clamp(Number(e.target.value) || 0))} className="h-10 w-20 rounded-md border border-line bg-surface text-center tabular-nums text-fg" />
            <button type="button" aria-label="+" onClick={() => onChange(clamp(n + 1))} disabled={n >= (f.max ?? 1000)} className="grid size-10 place-items-center rounded-md border border-line hover:bg-surface-2 disabled:opacity-40">
              <Plus className="size-4" aria-hidden />
            </button>
          </div>
        </FieldWrap>
      )
    }
    case 'toggle':
      return <Switch checked={Boolean(value)} onChange={(v) => onChange(v)} label={f.label} description={f.hint} />
    default:
      return null
  }
}
