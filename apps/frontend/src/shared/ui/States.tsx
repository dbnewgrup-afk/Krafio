import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { cn } from '@/shared/lib/cn'
import { img } from '@/shared/lib/assets'
import { Button } from './Button'

export function Skeleton({ className }: { className?: string }) {
  return <span aria-hidden className={cn('shimmer block rounded-md', className)} />
}

export function SkeletonRows({ rows = 8 }: { rows?: number }) {
  const { t } = useTranslation()
  return (
    <div className="grid gap-2" aria-busy="true" role="status">
      <span className="sr-only">{t('loader.loading')}</span>
      {Array.from({ length: rows }).map((_, i) => (
        <Skeleton key={i} className="h-12 w-full" />
      ))}
    </div>
  )
}

export type IllustrationKind = 'empty' | 'filter' | 'locked' | 'error' | 'clock' | 'notfound' | 'offline' | 'maintenance'

/** Ilustrasi vektor ringan (public/assets/img/states), warna mid-tone agar aman di light & dark (§3.5, §17.3) */
export function Illustration({ kind, className }: { kind: IllustrationKind; className?: string }) {
  // "Belum ada data": ilustrasi folder resmi — versi admin (penyimpanan media) di /admin, versi member di area lain.
  if (kind === 'empty') {
    const admin = typeof window !== 'undefined' && window.location.pathname.startsWith('/admin')
    return <img src={img(`states/empty-${admin ? 'admin' : 'user'}.jpg`)} alt="" width={120} height={120} draggable={false} loading="lazy" className={cn('size-28 select-none rounded-xl object-cover', className)} />
  }
  return <img src={img(`states/${kind}.svg`)} alt="" width={160} height={120} draggable={false} className={cn('select-none', className)} />
}

export function EmptyState({ kind = 'empty', title, body, action, className }: { kind?: IllustrationKind; title: string; body?: ReactNode; action?: ReactNode; className?: string }) {
  return (
    <div className={cn('grid justify-items-center gap-3 rounded-lg border border-dashed border-line px-6 py-10 text-center', className)}>
      <Illustration kind={kind} />
      <h3 className="t-h3 text-fg">{title}</h3>
      {body && <p className="t-small max-w-sm text-muted">{body}</p>}
      {action}
    </div>
  )
}

export function ErrorState({ status, requestId, onRetry, compact }: { status?: number; requestId?: string; onRetry?: () => void; compact?: boolean }) {
  const { t } = useTranslation()
  const key = status === 403 ? 'forbidden' : status === 404 ? 'not_found' : status === 0 ? 'network' : 'server'
  return (
    <div className={cn('grid justify-items-center gap-3 px-6 text-center', compact ? 'py-8' : 'py-16')} role="alert">
      <Illustration kind={key === 'not_found' ? 'notfound' : key === 'forbidden' ? 'locked' : 'error'} />
      <h2 className="t-h2 text-fg">{t(`errors.${key}_title`)}</h2>
      <p className="t-body max-w-md text-muted">{t(`errors.${key}_body`)}</p>
      {requestId && <p className="t-mono text-muted">ID: {requestId}</p>}
      <div className="flex flex-wrap justify-center gap-2">
        {onRetry && <Button onClick={onRetry}>{t('common.retry')}</Button>}
        <Link to="/" className="inline-flex h-11 items-center rounded-md border border-line px-4 font-semibold hover:bg-surface-2 lg:h-10">
          {t('errors.go_home')}
        </Link>
        <Link to="/contact" className="inline-flex h-11 items-center rounded-md px-4 font-semibold text-primary hover:bg-primary-soft lg:h-10">
          {t('errors.contact_support')}
        </Link>
      </div>
    </div>
  )
}
