import { forwardRef, useCallback, useEffect, useId, useImperativeHandle, useRef, useState, type InputHTMLAttributes, type ReactNode } from 'react'
import { Eye, EyeOff, Check, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { FieldWrap, inputCls } from './Field'
import { cn } from '@/shared/lib/cn'

type Props = Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> & {
  label: ReactNode
  error?: string | null
  hint?: ReactNode
  autoComplete: 'current-password' | 'new-password'
  labelAside?: ReactNode
  /** Kontrol eksternal (opsi "Tampilkan kedua" pada register) */
  revealed?: boolean
  onRevealChange?: (v: boolean) => void
}

// Show/Hide Password sesuai Design Spec §9.3 (satu komponen untuk semua form kata sandi).
export const PasswordField = forwardRef<HTMLInputElement, Props>(function PasswordField(
  { label, error, hint, labelAside, id, revealed, onRevealChange, onKeyDown, onKeyUp, onFocus, className, required, ...rest },
  ref,
) {
  const { t } = useTranslation()
  const auto = useId()
  const fid = id ?? auto
  const inputRef = useRef<HTMLInputElement>(null)
  useImperativeHandle(ref, () => inputRef.current as HTMLInputElement)
  const [inner, setInner] = useState(false)
  const shown = revealed ?? inner
  const [caps, setCaps] = useState(false)
  const [announce, setAnnounce] = useState('')
  const timer = useRef<number | undefined>(undefined)

  const setShown = useCallback(
    (v: boolean) => {
      if (onRevealChange) onRevealChange(v)
      else setInner(v)
      setAnnounce(v ? t('auth.password_shown') : t('auth.password_hidden'))
    },
    [onRevealChange, t],
  )

  // Auto-hide 30 dtk, saat tab kehilangan fokus, dan saat form dikirim
  useEffect(() => {
    window.clearTimeout(timer.current)
    if (!shown) return
    timer.current = window.setTimeout(() => setShown(false), 30_000)
    const onVis = () => document.hidden && setShown(false)
    document.addEventListener('visibilitychange', onVis)
    const form = inputRef.current?.form
    const onSubmit = () => setShown(false)
    form?.addEventListener('submit', onSubmit)
    return () => {
      window.clearTimeout(timer.current)
      document.removeEventListener('visibilitychange', onVis)
      form?.removeEventListener('submit', onSubmit)
    }
  }, [shown, setShown])

  const toggle = (viaKeyboard: boolean) => {
    const el = inputRef.current
    const s = el?.selectionStart ?? null
    const e = el?.selectionEnd ?? null
    setShown(!shown)
    // Pertahankan posisi kursor & seleksi; fokus tetap di input kecuali pengguna memakai keyboard pada tombol
    requestAnimationFrame(() => {
      if (!el) return
      if (!viaKeyboard) el.focus()
      if (s !== null && e !== null) {
        try {
          el.setSelectionRange(s, e)
        } catch {
          /* noop */
        }
      }
    })
  }

  return (
    <FieldWrap id={fid} label={label} error={error} hint={hint} required={required} labelAside={labelAside}>
      <div className="relative">
        <input
          ref={inputRef}
          id={fid}
          type={shown ? 'text' : 'password'}
          required={required}
          data-sensitive="true"
          spellCheck={false}
          autoCapitalize="none"
          aria-invalid={error ? true : undefined}
          aria-describedby={[error ? `${fid}-err` : hint ? `${fid}-hint` : '', caps ? `${fid}-caps` : ''].filter(Boolean).join(' ') || undefined}
          className={cn(inputCls, 'pr-12', className)}
          onKeyDown={(e) => {
            setCaps(e.getModifierState?.('CapsLock') ?? false)
            onKeyDown?.(e)
          }}
          onKeyUp={(e) => {
            setCaps(e.getModifierState?.('CapsLock') ?? false)
            onKeyUp?.(e)
          }}
          onFocus={(e) => {
            if (window.matchMedia('(pointer: coarse)').matches) window.setTimeout(() => e.target.scrollIntoView({ block: 'center' }), 250)
            onFocus?.(e)
          }}
          {...rest}
        />
        <button
          type="button"
          aria-label={shown ? t('auth.hide_password') : t('auth.show_password')}
          aria-pressed={shown}
          aria-controls={fid}
          onMouseDown={(e) => e.preventDefault()}
          onClick={(e) => toggle(e.detail === 0)}
          className="absolute inset-y-0 right-0 grid w-11 place-items-center rounded-r-md text-muted hover:text-fg"
        >
          {shown ? <EyeOff className="size-5" aria-hidden /> : <Eye className="size-5" aria-hidden />}
        </button>
      </div>
      {caps && (
        <p id={`${fid}-caps`} className="t-small text-warning">
          {t('auth.caps_lock')}
        </p>
      )}
      <span className="sr-only" aria-live="polite">
        {announce}
      </span>
    </FieldWrap>
  )
})

// Indikator kekuatan (§9.4): meter 4 segmen + label teks + checklist hidup; muncul saat fokus/terisi.
export function passwordScore(pw: string, ctx: string[] = []): { score: number; rules: { key: string; ok: boolean }[] } {
  const low = pw.toLowerCase()
  const rules = [
    { key: 'min8', ok: pw.length >= 8 },
    { key: 'mix', ok: /[a-zA-Z]/.test(pw) && /\d/.test(pw) },
    { key: 'not_personal', ok: pw.length > 0 && !ctx.filter((c) => c && c.length >= 3).some((c) => low.includes(c.toLowerCase())) },
  ]
  let score = 0
  if (pw.length >= 8) score++
  if (pw.length >= 10) score++
  if (/[a-zA-Z]/.test(pw) && /\d/.test(pw)) score++
  if (pw.length >= 14 || /[^a-zA-Z0-9]/.test(pw)) score++
  if (!rules[2].ok) score = Math.min(score, 1)
  return { score: Math.min(score, 4), rules }
}

export function PasswordStrength({ value, context = [], visible }: { value: string; context?: string[]; visible: boolean }) {
  const { t } = useTranslation()
  if (!visible) return null
  const { score, rules } = passwordScore(value, context)
  const labels = [t('auth.strength.weak'), t('auth.strength.weak'), t('auth.strength.fair'), t('auth.strength.strong'), t('auth.strength.very_strong')]
  const color = score <= 1 ? 'bg-danger' : score === 2 ? 'bg-warning' : 'bg-success'
  return (
    <div className="grid gap-2" aria-live="polite">
      <div className="flex items-center gap-3">
        <div className="grid flex-1 grid-cols-4 gap-1" aria-hidden>
          {[0, 1, 2, 3].map((i) => (
            <span key={i} className={cn('h-1.5 rounded-full', i < score ? color : 'bg-line')} />
          ))}
        </div>
        <span className="t-small min-w-20 text-right text-muted">{labels[score]}</span>
      </div>
      <ul className="grid gap-1">
        {rules.map((r) => (
          <li key={r.key} className={cn('t-small flex items-center gap-1.5', r.ok ? 'text-success' : 'text-muted')}>
            {r.ok ? <Check className="size-3.5" aria-hidden /> : <X className="size-3.5" aria-hidden />}
            {t(`auth.rules.${r.key}`)}
          </li>
        ))}
      </ul>
    </div>
  )
}
