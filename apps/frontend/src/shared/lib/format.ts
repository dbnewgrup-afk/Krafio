import i18n from '@/shared/i18n'

const TZ = 'Asia/Jakarta'

function locale(): string {
  return i18n.language === 'en' ? 'en-US' : 'id-ID'
}

/** Rupiah penuh, satu aturan konsisten: "Rp50.000" (id) / "Rp50,000" (en). */
export function rupiah(value: number | string | null | undefined): string {
  const n = Number(value ?? 0)
  const num = new Intl.NumberFormat(locale(), { maximumFractionDigits: 0 }).format(Math.round(n))
  return `Rp${num}`
}

export function number(value: number | null | undefined): string {
  return new Intl.NumberFormat(locale()).format(Number(value ?? 0))
}

export function dateTime(value: string | null | undefined, withTime = true): string {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '—'
  return new Intl.DateTimeFormat(locale(), {
    timeZone: TZ,
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
  }).format(d)
}

export function relative(value: string | null | undefined): string {
  if (!value) return '—'
  const diff = (new Date(value).getTime() - Date.now()) / 1000
  const rtf = new Intl.RelativeTimeFormat(locale(), { numeric: 'auto' })
  const abs = Math.abs(diff)
  if (abs < 60) return rtf.format(Math.round(diff), 'second')
  if (abs < 3600) return rtf.format(Math.round(diff / 60), 'minute')
  if (abs < 86400) return rtf.format(Math.round(diff / 3600), 'hour')
  return rtf.format(Math.round(diff / 86400), 'day')
}

export function maskEmail(email: string): string {
  const [u, d] = email.split('@')
  if (!d) return email
  return `${u.slice(0, 1)}***@${d}`
}

export function digitsOnly(v: string): string {
  return v.replace(/\D+/g, '')
}
