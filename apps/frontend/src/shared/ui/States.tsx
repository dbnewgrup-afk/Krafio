import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { cn } from '@/shared/lib/cn'
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

export type IllustrationKind = 'empty' | 'filter' | 'locked' | 'error' | 'clock' | 'notfound'

/** Ilustrasi SVG ringan mengikuti token warna (§3.5, §17.3) */
export function Illustration({ kind }: { kind: IllustrationKind }) {
  return (
    <svg width="96" height="72" viewBox="0 0 96 72" aria-hidden className="text-primary">
      <rect x="8" y="14" width="80" height="50" rx="10" fill="currentColor" opacity=".1" />
      <rect x="18" y="24" width="38" height="6" rx="3" fill="currentColor" opacity=".35" />
      <rect x="18" y="36" width="56" height="5" rx="2.5" fill="currentColor" opacity=".18" />
      <rect x="18" y="46" width="44" height="5" rx="2.5" fill="currentColor" opacity=".18" />
      {kind === 'locked' && <path d="M66 6a8 8 0 0 1 8 8v4h2a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3H56a3 3 0 0 1-3-3V21a3 3 0 0 1 3-3h2v-4a8 8 0 0 1 8-8Zm0 5a3 3 0 0 0-3 3v4h6v-4a3 3 0 0 0-3-3Z" fill="currentColor" />}
      {kind === 'filter' && <path d="M60 4h28l-11 13v11l-6 4V17L60 4Z" fill="currentColor" opacity=".8" />}
      {kind === 'error' && <path d="M72 4 90 34H54L72 4Zm-2 11v9h4v-9h-4Zm0 12v4h4v-4h-4Z" fill="currentColor" opacity=".85" />}
      {kind === 'clock' && (
        <g>
          <circle cx="72" cy="20" r="14" fill="currentColor" opacity=".85" />
          <path d="M72 12v9l6 4" stroke="var(--surface)" strokeWidth="3" fill="none" strokeLinecap="round" />
        </g>
      )}
      {kind === 'notfound' && (
        <g>
          <circle cx="70" cy="18" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
          <path d="m77 25 9 9" stroke="currentColor" strokeWidth="4" strokeLinecap="round" />
        </g>
      )}
      {kind === 'empty' && <rect x="62" y="6" width="22" height="22" rx="5" fill="currentColor" opacity=".7" />}
    </svg>
  )
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
