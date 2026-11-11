import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Check, Copy, Clock, ArrowRight, Sparkles } from 'lucide-react'
import { useAuth } from '@/shared/context/AuthContext'
import { useToast } from '@/shared/context/ToastContext'
import { Button } from '@/shared/ui/Button'
import { cn } from '@/shared/lib/cn'
import { dateTime } from '@/shared/lib/format'
import { promoTerms, promoValue, rememberPromo, type Promo } from '@/shared/lib/promo'

/**
 * Kupon sobek: blok nilai (pasir hangat) | garis sobek putus-putus | isi & aksi.
 * `notch` = warna latar halaman agar lubang sobekan menyatu dengan section.
 */
export function PromoCard({ p, now, notch = 'bg-bg', featured, className }: { p: Promo; now: number; notch?: string; featured?: boolean; className?: string }) {
  const { t } = useTranslation()
  const { me } = useAuth()
  const toast = useToast()
  const nav = useNavigate()
  const [copied, setCopied] = useState(false)
  const v = promoValue(p)
  const terms = promoTerms(p, t)
  const daysLeft = p.ends_at ? Math.max(0, Math.ceil((new Date(p.ends_at).getTime() - now) / 864e5)) : null
  const urgent = daysLeft !== null && daysLeft <= 7

  const copy = async () => {
    if (!p.code) return
    try {
      await navigator.clipboard.writeText(p.code)
      setCopied(true)
      toast.push('success', t('promo.copied', { code: p.code }))
      window.setTimeout(() => setCopied(false), 2000)
    } catch {
      toast.push('info', t('promo.copy_manual', { code: p.code }))
    }
  }
  const use = () => {
    if (p.code) rememberPromo(p.code)
    nav(me ? '/app/subscription' : '/register?next=/app/subscription')
  }

  return (
    <article className={cn('grid overflow-hidden rounded-lg border border-line bg-surface', featured ? 'grid-cols-[minmax(0,128px)_minmax(0,1fr)] sm:grid-cols-[minmax(0,168px)_minmax(0,1fr)]' : 'grid-cols-[minmax(0,104px)_minmax(0,1fr)] sm:grid-cols-[minmax(0,128px)_minmax(0,1fr)]', className)}>
      <div className="relative grid content-center justify-items-center gap-1 bg-warm-sand px-2 py-6 text-center text-warm-coffee">
        <span className={cn('font-bold leading-none tracking-[-0.02em] tabular-nums', featured ? 'text-[40px] sm:text-[48px]' : 'text-[28px] sm:text-[32px]')}>{v.big}</span>
        <span className="text-[11px] font-semibold uppercase tracking-[0.08em]">{t(`promo.unit.${v.unit}`)}</span>
        {/* Lubang sobekan di garis potong */}
        <span aria-hidden className={cn('absolute -right-2 -top-2 size-4 rounded-full border border-line', notch)} />
        <span aria-hidden className={cn('absolute -bottom-2 -right-2 size-4 rounded-full border border-line', notch)} />
      </div>

      <div className="grid content-start gap-3 border-l-2 border-dashed border-line p-4 md:p-5">
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
          {p.campaign && <span className="text-[11px] font-semibold uppercase tracking-[0.08em] text-primary">{t(`promo.campaign.${p.campaign}`, { defaultValue: p.campaign })}</span>}
          {daysLeft !== null && (
            <span className={cn('t-small inline-flex items-center gap-1', urgent ? 'font-semibold text-warning' : 'text-muted')}>
              <Clock className="size-3.5" aria-hidden />
              {urgent ? t('promo.days_left', { count: daysLeft }) : t('promo.until', { date: dateTime(p.ends_at, false) })}
            </span>
          )}
          {p.remaining !== null && <span className="t-small font-semibold text-warning">{t('promo.remaining', { count: p.remaining })}</span>}
        </div>
        <div className="grid gap-1">
          <h3 className={featured ? 't-h2 text-fg' : 't-h3 text-fg'}>{p.headline}</h3>
          {p.description && <p className={cn('t-small text-muted', !featured && 'line-clamp-2')}>{p.description}</p>}
        </div>
        {terms.length > 0 && (
          <ul className="flex flex-wrap gap-1.5" aria-label={t('promo.terms_label')}>
            {terms.map((x) => (
              <li key={x} className="rounded-full bg-surface-2 px-2.5 py-1 text-[12px] font-medium text-fg">{x}</li>
            ))}
          </ul>
        )}
        <div className="mt-1 flex flex-wrap items-center gap-2">
          {p.code ? (
            <button
              type="button"
              onClick={copy}
              aria-label={t('promo.copy_aria', { code: p.code })}
              className="inline-flex h-11 items-center gap-2 rounded-md border-2 border-dashed border-line-strong px-3 font-mono text-sm font-semibold tracking-[0.06em] text-fg transition-colors duration-[120ms] hover:border-primary hover:text-primary"
            >
              {p.code}
              {copied ? <Check className="size-4 text-success" aria-hidden /> : <Copy className="size-4 text-muted" aria-hidden />}
            </button>
          ) : (
            <span className="t-small inline-flex h-11 items-center gap-1.5 rounded-md bg-success/10 px-3 font-semibold text-success">
              <Sparkles className="size-4" aria-hidden />
              {t('promo.auto')}
            </span>
          )}
          <Button variant={featured ? 'primary' : 'secondary'} onClick={use} className="h-11">
            {p.code ? t('promo.use') : t('promo.see_plans')}
            <ArrowRight className="size-4" aria-hidden />
          </Button>
        </div>
      </div>
    </article>
  )
}
