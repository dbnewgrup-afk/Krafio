import { forwardRef, useId, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react'
import { AlertCircle } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/shared/lib/cn'

export const inputCls =
  'w-full rounded-md border border-line bg-surface px-3 h-12 lg:h-10 text-fg placeholder:text-muted/70 transition-[border-color,box-shadow] duration-[120ms] focus:border-primary focus:outline-none focus-visible:outline-none focus:ring-2 focus:ring-primary/25 disabled:bg-surface-2 disabled:text-muted aria-[invalid=true]:border-danger'

interface WrapProps {
  id: string
  label?: ReactNode
  hint?: ReactNode
  error?: string | null
  required?: boolean
  optional?: boolean
  labelAside?: ReactNode
  children: ReactNode
  className?: string
}

// Form (§18.1): label di atas, (wajib)/(opsional), error di bawah field dengan ikon + teks, aria-describedby.
export function FieldWrap({ id, label, hint, error, required, optional, labelAside, children, className }: WrapProps) {
  const { t } = useTranslation()
  return (
    <div className={cn('grid gap-1.5', className)}>
      {label && (
        <div className="flex items-baseline justify-between gap-3">
          <label htmlFor={id} className="t-label text-fg">
            {label}
            {required && <span className="text-muted"> ({t('common.required')})</span>}
            {optional && <span className="text-muted"> ({t('common.optional')})</span>}
          </label>
          {labelAside}
        </div>
      )}
      {children}
      {error ? (
        <p id={`${id}-err`} className="t-small flex items-start gap-1.5 text-danger">
          <AlertCircle className="mt-0.5 size-3.5 shrink-0" aria-hidden />
          {error}
        </p>
      ) : hint ? (
        <p id={`${id}-hint`} className="t-small text-muted">
          {hint}
        </p>
      ) : null}
    </div>
  )
}

type TextFieldProps = InputHTMLAttributes<HTMLInputElement> & {
  label?: ReactNode
  hint?: ReactNode
  error?: string | null
  optional?: boolean
  prefix?: ReactNode
  labelAside?: ReactNode
  wrapClassName?: string
}

export const TextField = forwardRef<HTMLInputElement, TextFieldProps>(function TextField(
  { label, hint, error, optional, prefix, labelAside, wrapClassName, id, className, required, ...rest },
  ref,
) {
  const auto = useId()
  const fid = id ?? auto
  return (
    <FieldWrap id={fid} label={label} hint={hint} error={error} required={required} optional={optional} labelAside={labelAside} className={wrapClassName}>
      <div className="relative">
        {prefix && <span className="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-muted">{prefix}</span>}
        <input
          ref={ref}
          id={fid}
          required={required}
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? `${fid}-err` : hint ? `${fid}-hint` : undefined}
          className={cn(inputCls, prefix ? 'pl-10' : '', className)}
          {...rest}
        />
      </div>
    </FieldWrap>
  )
})

export function TextArea({ label, hint, error, optional, id, className, required, ...rest }: TextareaHTMLAttributes<HTMLTextAreaElement> & { label?: ReactNode; hint?: ReactNode; error?: string | null; optional?: boolean }) {
  const auto = useId()
  const fid = id ?? auto
  return (
    <FieldWrap id={fid} label={label} hint={hint} error={error} required={required} optional={optional}>
      <textarea
        id={fid}
        required={required}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? `${fid}-err` : hint ? `${fid}-hint` : undefined}
        className={cn(inputCls, 'h-auto min-h-28 py-2.5 lg:h-auto', className)}
        {...rest}
      />
    </FieldWrap>
  )
}

export function SelectField({ label, hint, error, optional, id, className, required, children, wrapClassName, ...rest }: SelectHTMLAttributes<HTMLSelectElement> & { label?: ReactNode; hint?: ReactNode; error?: string | null; optional?: boolean; wrapClassName?: string }) {
  const auto = useId()
  const fid = id ?? auto
  return (
    <FieldWrap id={fid} label={label} hint={hint} error={error} required={required} optional={optional} className={wrapClassName}>
      <select id={fid} required={required} aria-invalid={error ? true : undefined} className={cn(inputCls, 'pr-8', className)} {...rest}>
        {children}
      </select>
    </FieldWrap>
  )
}

export function Checkbox({ label, className, ...rest }: InputHTMLAttributes<HTMLInputElement> & { label: ReactNode }) {
  const id = useId()
  return (
    <div className={cn('flex items-start gap-2.5', className)}>
      <input id={rest.id ?? id} type="checkbox" className="mt-0.5 size-5 shrink-0 rounded-sm border-line accent-[var(--primary)]" {...rest} />
      <label htmlFor={rest.id ?? id} className="t-small text-fg">
        {label}
      </label>
    </div>
  )
}

export function Switch({ checked, onChange, label, disabled, description }: { checked: boolean; onChange: (v: boolean) => void; label: ReactNode; disabled?: boolean; description?: ReactNode }) {
  const id = useId()
  return (
    <div className="flex items-start justify-between gap-4">
      <div className="grid gap-0.5">
        <span id={`${id}-l`} className="t-label text-fg">
          {label}
        </span>
        {description && <span className="t-small text-muted">{description}</span>}
      </div>
      <button
        type="button"
        role="switch"
        aria-checked={checked}
        aria-labelledby={`${id}-l`}
        disabled={disabled}
        onClick={() => onChange(!checked)}
        className={cn('relative block h-7 w-12 shrink-0 rounded-full transition-[background-color] duration-[120ms]', checked ? 'bg-primary' : 'bg-line-strong', disabled && 'opacity-50')}
      >
        <span className={cn('absolute left-0 top-1 size-5 rounded-full bg-surface shadow-k-sm transition-transform duration-[120ms]', checked ? 'translate-x-6' : 'translate-x-1')} />
      </button>
    </div>
  )
}
