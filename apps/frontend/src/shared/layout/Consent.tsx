import { useEffect, useRef, useState } from 'react'
import { Cookie } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { getJsonPref, setPref } from '@/shared/lib/prefs'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Switch } from '@/shared/ui/Field'
import { useAuth } from '@/shared/context/AuthContext'
import { http } from '@/api-client/http'

const CONSENT_VERSION = '1.0'
interface ConsentValue {
  version: string
  at: string
  analytics: boolean
  functional: boolean
  marketing: boolean
}

function readConsent(): ConsentValue | null {
  const v = getJsonPref<ConsentValue | null>('krafio.consent', null)
  if (!v || v.version !== CONSENT_VERSION) return null
  // Kedaluwarsa 12 bulan (§11.3)
  if (Date.now() - new Date(v.at).getTime() > 365 * 86400_000) return null
  return v
}

export function openCookieSettings() {
  window.dispatchEvent(new CustomEvent('krafio:cookie-settings'))
}

/** Skrip non-esensial hanya dimuat setelah persetujuan (§11.3). */
export function hasConsent(cat: 'analytics' | 'functional' | 'marketing'): boolean {
  return !!readConsent()?.[cat]
}

// Banner cookie (§11.3): Terima semua / Tolak non-esensial / Atur pilihan — setara menonjol.
export function CookieConsent() {
  const { t } = useTranslation()
  const { me } = useAuth()
  const [visible, setVisible] = useState(() => readConsent() === null)
  const [settingsOpen, setSettingsOpen] = useState(false)
  const [prefs, setPrefs] = useState({ analytics: false, functional: false, marketing: false, ...(readConsent() ?? {}) })
  const bar = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const open = () => setSettingsOpen(true)
    window.addEventListener('krafio:cookie-settings', open)
    return () => window.removeEventListener('krafio:cookie-settings', open)
  }, [])

  // Tombol CS naik di atas banner selama banner terlihat
  useEffect(() => {
    const root = document.documentElement
    if (!visible || !bar.current) {
      root.style.removeProperty('--fab-lift')
      return
    }
    const ro = new ResizeObserver(() => root.style.setProperty('--fab-lift', `${(bar.current?.offsetHeight ?? 0) + 8}px`))
    ro.observe(bar.current)
    return () => {
      ro.disconnect()
      root.style.removeProperty('--fab-lift')
    }
  }, [visible])

  const save = (v: { analytics: boolean; functional: boolean; marketing: boolean }) => {
    const value: ConsentValue = { version: CONSENT_VERSION, at: new Date().toISOString(), ...v }
    setPref('krafio.consent', JSON.stringify(value))
    setPrefs(v)
    setVisible(false)
    setSettingsOpen(false)
    if (me) void http.post('/consents/cookie', value).catch(() => undefined)
  }

  return (
    <>
      {visible && (
        <div ref={bar} role="region" aria-label={t('consent.title')} className="anim-fade-in fixed inset-x-0 bottom-0 z-consent border-t border-line bg-surface px-4 pb-[calc(12px+var(--safe-b))] pt-3 shadow-k-lg max-md:rounded-t-xl md:px-6">
          <div className="mx-auto flex max-w-[1200px] flex-col gap-3 lg:flex-row lg:items-center lg:gap-6">
            <div className="flex min-w-0 flex-1 items-start gap-3">
              <Cookie className="mt-0.5 size-5 shrink-0 text-primary" aria-hidden />
              <p className="t-small text-fg">
                {t('consent.body')}{' '}
                <Link to="?modal=cookies" className="font-semibold text-primary underline-offset-2 hover:underline">
                  {t('legal.cookies')}
                </Link>
              </p>
            </div>
            <div className="grid grid-cols-3 gap-2 lg:flex lg:shrink-0">
              <Button size="sm" variant="secondary" onClick={() => save({ analytics: false, functional: false, marketing: false })} className="max-sm:px-2 max-sm:text-[12px]">
                {t('consent.reject')}
              </Button>
              <Button size="sm" variant="secondary" onClick={() => setSettingsOpen(true)} className="max-sm:px-2 max-sm:text-[12px]">
                {t('consent.settings')}
              </Button>
              <Button size="sm" variant="secondary" onClick={() => save({ analytics: true, functional: true, marketing: true })} className="max-sm:px-2 max-sm:text-[12px]">
                {t('consent.accept')}
              </Button>
            </div>
          </div>
        </div>
      )}
      <Modal
        open={settingsOpen}
        onClose={() => setSettingsOpen(false)}
        size="form"
        title={t('consent.settings_title')}
        footer={
          <>
            <Button variant="secondary" onClick={() => save({ analytics: false, functional: false, marketing: false })}>
              {t('consent.reject')}
            </Button>
            <Button onClick={() => save(prefs)}>{t('consent.save')}</Button>
          </>
        }
      >
        <div className="grid gap-5">
          <Switch checked disabled onChange={() => undefined} label={t('consent.essential')} description={t('consent.essential_desc')} />
          <Switch checked={prefs.analytics} onChange={(v) => setPrefs({ ...prefs, analytics: v })} label={t('consent.analytics')} description={t('consent.analytics_desc')} />
          <Switch checked={prefs.functional} onChange={(v) => setPrefs({ ...prefs, functional: v })} label={t('consent.functional')} description={t('consent.functional_desc')} />
          <Switch checked={prefs.marketing} onChange={(v) => setPrefs({ ...prefs, marketing: v })} label={t('consent.marketing')} description={t('consent.marketing_desc')} />
        </div>
      </Modal>
    </>
  )
}
