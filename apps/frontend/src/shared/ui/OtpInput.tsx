import { useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/shared/lib/cn'

// OTP 6 kotak (§9.7): numeric, one-time-code, auto-advance, backspace mundur, paste 6 digit, auto-submit.
export function OtpInput({ value, onChange, onComplete, disabled, invalid, shakeKey }: { value: string; onChange: (v: string) => void; onComplete?: (v: string) => void; disabled?: boolean; invalid?: boolean; shakeKey?: number }) {
  const { t } = useTranslation()
  const refs = useRef<Array<HTMLInputElement | null>>([])
  const digits = Array.from({ length: 6 }, (_, i) => value[i] ?? '')

  const setAt = (i: number, d: string) => {
    const arr = digits.slice()
    arr[i] = d
    const next = arr.join('').slice(0, 6)
    onChange(next)
    if (next.length === 6 && !next.includes('') && /^\d{6}$/.test(next)) onComplete?.(next)
  }

  return (
    <div key={shakeKey} className={cn('flex justify-between gap-2 sm:justify-start', invalid && shakeKey ? 'anim-shake' : '')} role="group" aria-label={t('auth.otp.group')}>
      {digits.map((d, i) => (
        <input
          key={i}
          ref={(el) => {
            refs.current[i] = el
          }}
          value={d}
          disabled={disabled}
          inputMode="numeric"
          autoComplete={i === 0 ? 'one-time-code' : 'off'}
          pattern="[0-9]*"
          maxLength={i === 0 ? 6 : 1}
          aria-label={t('auth.otp.digit', { n: i + 1 })}
          aria-invalid={invalid || undefined}
          className={cn(
            'size-12 rounded-md border bg-surface text-center font-mono text-xl text-fg focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/25 disabled:bg-surface-2 disabled:text-muted sm:size-13',
            invalid ? 'border-danger' : 'border-line',
          )}
          onChange={(e) => {
            const raw = e.target.value.replace(/\D/g, '')
            if (raw.length > 1) {
              // paste / autofill banyak digit
              const v = raw.slice(0, 6)
              onChange(v)
              refs.current[Math.min(v.length, 5)]?.focus()
              if (v.length === 6) onComplete?.(v)
              return
            }
            setAt(i, raw)
            if (raw && i < 5) refs.current[i + 1]?.focus()
          }}
          onKeyDown={(e) => {
            if (e.key === 'Backspace' && !digits[i] && i > 0) {
              e.preventDefault()
              setAt(i - 1, '')
              refs.current[i - 1]?.focus()
            } else if (e.key === 'ArrowLeft' && i > 0) refs.current[i - 1]?.focus()
            else if (e.key === 'ArrowRight' && i < 5) refs.current[i + 1]?.focus()
          }}
          onPaste={(e) => {
            const v = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6)
            if (!v) return
            e.preventDefault()
            onChange(v)
            refs.current[Math.min(v.length, 5)]?.focus()
            if (v.length === 6) onComplete?.(v)
          }}
          onFocus={(e) => e.target.select()}
        />
      ))}
    </div>
  )
}
