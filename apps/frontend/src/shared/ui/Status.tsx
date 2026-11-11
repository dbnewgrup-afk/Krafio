import type { ReactNode } from 'react'
import { Check, Clock, RefreshCw, X, Pause, type LucideIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/shared/lib/cn'

export type Tone = 'success' | 'warning' | 'info' | 'danger' | 'neutral'
const TONE: Record<Tone, { cls: string; icon: LucideIcon }> = {
  success: { cls: 'bg-success/12 text-success', icon: Check },
  warning: { cls: 'bg-warning/14 text-warning', icon: Clock },
  info: { cls: 'bg-info/12 text-info', icon: RefreshCw },
  danger: { cls: 'bg-danger/12 text-danger', icon: X },
  neutral: { cls: 'bg-surface-2 text-muted', icon: Pause },
}

// Pemetaan status → tone (§18.4). Status tidak hanya dibedakan warna: selalu ikon + teks.
const MAP: Record<string, Tone> = {
  active: 'success', paid: 'success', completed: 'success', approved: 'success', available: 'success', resolved: 'success', connected: 'success', published: 'success', healthy: 'success', success: 'success', verified: 'success', enabled: 'success',
  open: 'info', processing: 'info', in_progress: 'info', running: 'info',
  pending: 'warning', pending_payment: 'warning', queued: 'warning', requested: 'warning', under_review: 'warning', pending_verification: 'warning', pending_approval: 'warning', request_revision: 'warning', past_due: 'warning', degraded: 'warning', waiting: 'warning',
  failed: 'danger', rejected: 'danger', banned: 'danger', suspended: 'danger', reversed: 'danger', cancelled: 'danger', error: 'danger', down: 'danger', expired: 'danger', disconnected: 'danger',
  closed: 'neutral', inactive: 'neutral', draft: 'neutral', archived: 'neutral', deactivated: 'neutral', disabled: 'neutral', not_available: 'neutral', ignored: 'neutral',
}

export function toneOf(status: string): Tone {
  return MAP[status] ?? 'neutral'
}

export function StatusChip({ status, label, tone }: { status: string; label?: string; tone?: Tone }) {
  const { t } = useTranslation()
  const tn = tone ?? toneOf(status)
  const { cls, icon: Icon } = TONE[tn]
  return (
    <span className={cn('inline-flex h-6 items-center gap-1 whitespace-nowrap rounded-full px-2.5 text-[12px] font-semibold', cls)}>
      <Icon className="size-3.5" aria-hidden />
      {label ?? t(`status.${status}`)}
    </span>
  )
}

export function Badge({ children, tone = 'neutral' }: { children: ReactNode; tone?: Tone }) {
  return <span className={cn('inline-flex h-6 items-center whitespace-nowrap rounded-full px-2.5 text-[12px] font-semibold', TONE[tone].cls)}>{children}</span>
}
