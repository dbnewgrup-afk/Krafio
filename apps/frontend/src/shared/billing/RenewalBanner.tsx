import { useTranslation } from 'react-i18next'
import { AlertTriangle, CalendarClock, RefreshCw } from 'lucide-react'
import { useAuth } from '@/shared/context/AuthContext'
import { ButtonLink } from '@/shared/ui/Button'
import { cn } from '@/shared/lib/cn'
import { dateTime, rupiah } from '@/shared/lib/format'

/**
 * Banner siklus langganan (blueprint "Siklus Langganan & Pemantauan Perpanjangan"):
 * - lewat tempo (past_due) → merah, batas masa tenggang + isi saldo / bayar;
 * - paket berakhir ≤ 7 hari → kuning (saldo kurang / auto-renew mati / pemberian admin) atau biru (akan diperpanjang otomatis).
 */
export function RenewalBanner({ className }: { className?: string }) {
  const { t } = useTranslation()
  const { me } = useAuth()
  const r = me?.renewal
  if (!me || !r) return null
  const balance = me.balances.topup

  let tone: 'danger' | 'warning' | 'info'
  let title: string
  let body: string
  let primary: { to: string; label: string } | null = null
  let secondary: { to: string; label: string } | null = null

  if (r.state === 'past_due') {
    tone = 'danger'
    title = t('renewal.past_due_title', { plan: r.plan })
    body = t('renewal.past_due_body', { date: dateTime(r.grace_until, false) })
    primary = { to: '/app/wallet/topup', label: t('renewal.topup') }
    secondary = { to: '/app/subscription', label: t('renewal.pay_now') }
  } else {
    const when = t('renewal.ends', { count: r.days ?? 0, date: dateTime(r.ends_at, false) })
    title = t('renewal.expiring_title', { plan: r.plan, when })
    if (r.granted_by_admin) {
      tone = 'warning'
      body = t('renewal.admin_body')
      primary = { to: '/app/subscription', label: t('renewal.choose_plan') }
    } else if (!r.auto) {
      tone = 'warning'
      body = t('renewal.manual_body')
      primary = { to: '/app/subscription', label: t('renewal.renew_now') }
    } else if (r.renew_price == null) {
      tone = 'warning'
      body = t('renewal.unavailable_body')
      primary = { to: '/app/subscription', label: t('renewal.choose_plan') }
    } else if (balance < r.renew_price) {
      tone = 'warning'
      body = t('renewal.short_body', { balance: rupiah(balance), price: rupiah(r.renew_price) })
      primary = { to: '/app/wallet/topup', label: t('renewal.topup') }
    } else {
      tone = 'info'
      body = t('renewal.auto_body', { price: rupiah(r.renew_price) })
      secondary = { to: '/app/subscription', label: t('renewal.manage') }
    }
  }

  const Icon = tone === 'danger' ? AlertTriangle : tone === 'info' ? RefreshCw : CalendarClock
  return (
    <div
      role={tone === 'danger' ? 'alert' : 'status'}
      className={cn(
        'mb-4 grid gap-3 rounded-lg border px-4 py-3 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center',
        tone === 'danger' ? 'border-danger/40 bg-danger/8' : tone === 'warning' ? 'border-warning/40 bg-warning/10' : 'border-info/30 bg-info/8',
        className,
      )}
    >
      <Icon className={cn('size-5 shrink-0', tone === 'danger' ? 'text-danger' : tone === 'warning' ? 'text-warning' : 'text-info')} aria-hidden />
      <div className="grid gap-0.5">
        <p className="text-sm font-semibold text-fg">{title}</p>
        <p className="t-small text-muted">{body}</p>
      </div>
      {(primary || secondary) && (
        <div className="flex flex-wrap gap-2">
          {secondary && <ButtonLink to={secondary.to} size="sm" variant="secondary">{secondary.label}</ButtonLink>}
          {primary && <ButtonLink to={primary.to} size="sm">{primary.label}</ButtonLink>}
        </div>
      )}
    </div>
  )
}
