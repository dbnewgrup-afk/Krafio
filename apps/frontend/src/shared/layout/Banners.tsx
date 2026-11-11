import { useEffect, useState } from 'react'
import { AlertTriangle, Info, WifiOff, X, ShieldAlert } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/shared/lib/cn'
import { useAuth } from '@/shared/context/AuthContext'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { useNavigate, useLocation } from 'react-router-dom'

export function useOnline(): boolean {
  const [on, setOn] = useState(navigator.onLine)
  useEffect(() => {
    const a = () => setOn(true)
    const b = () => setOn(false)
    window.addEventListener('online', a)
    window.addEventListener('offline', b)
    return () => {
      window.removeEventListener('online', a)
      window.removeEventListener('offline', b)
    }
  }, [])
  return on
}

/**
 * Satu banner terlihat dengan prioritas: keamanan > sistem > langganan > promo (§17.2).
 * Offline: banner tipis yang hilang otomatis saat pulih (§10.7).
 */
export function TopBanner({ security, subscription }: { security?: string | null; subscription?: { message: string; cta: string; to: string } | null }) {
  const { t } = useTranslation()
  const { settings } = useAuth()
  const online = useOnline()
  const nav = useNavigate()
  const [dismissed, setDismissed] = useState<string | null>(() => sessionStorage.getItem('krafio.banner.dismissed'))

  if (!online)
    return (
      <div role="status" className="flex items-center justify-center gap-2 bg-fg px-4 py-1.5 text-[13px] font-semibold text-bg">
        <WifiOff className="size-4" aria-hidden />
        {t('banner.offline')}
      </div>
    )
  if (security)
    return (
      <div role="alert" className="flex items-center gap-2 bg-danger px-4 py-2 text-[13px] font-semibold text-white dark:text-bg">
        <ShieldAlert className="size-4 shrink-0" aria-hidden />
        <span className="flex-1">{security}</span>
      </div>
    )
  const ann = settings?.maintenance.active ? { level: 'warning' as const, message: settings.maintenance.message ?? t('banner.maintenance'), link: '/status', critical: true } : settings?.announcement ? { ...settings.announcement, critical: settings.announcement.level === 'danger' } : null
  if (ann && dismissed !== ann.message) {
    const Icon = ann.level === 'info' ? Info : AlertTriangle
    return (
      <div role="status" className={cn('flex items-center gap-2 px-4 py-2 text-[13px]', ann.level === 'danger' ? 'bg-danger/12 text-danger' : ann.level === 'warning' ? 'bg-warning/14 text-fg' : 'bg-info/10 text-fg')}>
        <Icon className="size-4 shrink-0" aria-hidden />
        <span className="min-w-0 flex-1">
          {ann.message}{' '}
          {ann.link && (
            <a href={ann.link} className="font-semibold underline underline-offset-2">
              {t('banner.more')}
            </a>
          )}
        </span>
        {!ann.critical && (
          <button
            type="button"
            aria-label={t('common.close')}
            className="grid size-8 place-items-center rounded-md hover:bg-black/5"
            onClick={() => {
              sessionStorage.setItem('krafio.banner.dismissed', ann.message)
              setDismissed(ann.message)
            }}
          >
            <X className="size-4" aria-hidden />
          </button>
        )}
      </div>
    )
  }
  if (subscription)
    return (
      <div role="status" className="flex flex-wrap items-center gap-2 bg-warning/14 px-4 py-2 text-[13px] text-fg">
        <AlertTriangle className="size-4 shrink-0 text-warning" aria-hidden />
        <span className="flex-1">{subscription.message}</span>
        <button type="button" onClick={() => nav(subscription.to)} className="font-semibold text-primary underline underline-offset-2">
          {subscription.cta}
        </button>
      </div>
    )
  return null
}

/** Modal sesi berakhir (§10.7): tidak dapat ditutup, "Masuk lagi" mempertahankan konteks next. */
export function SessionExpiredModal() {
  const { t } = useTranslation()
  const { sessionExpired, setSessionExpired } = useAuth()
  const nav = useNavigate()
  const loc = useLocation()
  const admin = loc.pathname.startsWith('/admin')
  return (
    <Modal open={sessionExpired} onClose={() => undefined} critical hideClose size="info" title={t('session.expired_title')} footer={<Button onClick={() => { setSessionExpired(false); nav(`${admin ? '/admin/login' : '/login'}?next=${encodeURIComponent(loc.pathname + loc.search)}`) }}>{t('session.login_again')}</Button>}>
      <p className="t-body text-muted">{t('session.expired_body')}</p>
    </Modal>
  )
}
