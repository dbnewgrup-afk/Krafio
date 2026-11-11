import { createContext, useContext, useEffect, useRef, useState, type ReactNode } from 'react'
import { useLocation, useNavigate, useNavigation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { getPref } from '@/shared/lib/prefs'
import { cn } from '@/shared/lib/cn'

// Loading screen dua skema (Design Spec §12.2–12.3):
// A Transparan: navigasi dalam panel yang sama, hanya menutup area konten.
// B Dark: pindah panel (Front ↔ User ↔ Admin), login/logout, operasi blocking; menutup seluruh viewport.
type Scheme = 'A' | 'B'
interface LoaderState {
  visible: boolean
  scheme: Scheme
  elapsed: number
  message: string | null
}
interface LoaderCtx extends LoaderState {
  block: (message: string) => void
  unblock: () => void
}
const Ctx = createContext<LoaderCtx | null>(null)

export function areaOf(path: string): 'admin' | 'user' | 'auth' | 'public' {
  if (path.startsWith('/admin')) return 'admin'
  if (path.startsWith('/app')) return 'user'
  if (/^\/(login|register|forgot-password|reset-password|verify|account-status)/.test(path)) return 'auth'
  return 'public'
}

function pickScheme(from: string, to: string): Scheme {
  const pref = getPref('krafio.loader')
  if (pref === 'transparent') return 'A'
  if (pref === 'dark') return 'B'
  const a = areaOf(from)
  const b = areaOf(to)
  if (a !== b) return 'B'
  return 'A'
}

export function LoaderProvider({ children }: { children: ReactNode }) {
  const nav = useNavigation()
  const loc = useLocation()
  const navigate = useNavigate()
  const [state, setState] = useState<LoaderState>({ visible: false, scheme: 'A', elapsed: 0, message: null })
  const showTimer = useRef<number | undefined>(undefined)
  const tick = useRef<number | undefined>(undefined)
  const shownAt = useRef(0)
  const blocked = useRef(false)
  const busy = nav.state !== 'idle'

  useEffect(() => {
    if (blocked.current) return
    window.clearTimeout(showTimer.current)
    if (busy) {
      const scheme = pickScheme(loc.pathname, nav.location?.pathname ?? loc.pathname)
      // Ambang tampil 150 ms (§12.3 butir 1)
      showTimer.current = window.setTimeout(() => {
        shownAt.current = Date.now()
        setState({ visible: true, scheme, elapsed: 0, message: null })
        window.clearInterval(tick.current)
        tick.current = window.setInterval(() => setState((s) => ({ ...s, elapsed: Math.round((Date.now() - shownAt.current) / 1000) })), 1000)
      }, 150)
    } else {
      // Durasi minimum 400 ms setelah tampil (§12.3 butir 2)
      const left = Math.max(0, 400 - (Date.now() - shownAt.current))
      window.setTimeout(() => {
        if (blocked.current) return
        window.clearInterval(tick.current)
        setState((s) => ({ ...s, visible: false, elapsed: 0 }))
      }, state.visible ? left : 0)
    }
    return () => window.clearTimeout(showTimer.current)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [busy])

  // Fokus & pengumuman setelah halaman baru siap (§12.3 butir 8)
  const [announce, setAnnounce] = useState('')
  const firstLoad = useRef(true)
  useEffect(() => {
    if (firstLoad.current) {
      firstLoad.current = false
      return
    }
    const id = window.setTimeout(() => {
      const h = document.querySelector<HTMLElement>('[data-page-title]') ?? document.querySelector<HTMLElement>('main h1')
      if (h) {
        if (!h.hasAttribute('tabindex')) h.setAttribute('tabindex', '-1')
        h.focus({ preventScroll: true })
        setAnnounce(h.textContent ?? '')
      }
    }, 60)
    return () => window.clearTimeout(id)
  }, [loc.pathname])

  const ctx: LoaderCtx = {
    ...state,
    block: (message) => {
      blocked.current = true
      shownAt.current = Date.now()
      setState({ visible: true, scheme: 'B', elapsed: 0, message })
    },
    unblock: () => {
      blocked.current = false
      setState((s) => ({ ...s, visible: false }))
    },
  }

  return (
    <Ctx.Provider value={ctx}>
      {children}
      {state.visible && state.scheme === 'B' && <DarkLoader elapsed={state.elapsed} message={state.message} onRetry={() => window.location.reload()} onBack={() => navigate(-1)} />}
      <span className="sr-only" aria-live="polite">
        {announce}
      </span>
    </Ctx.Provider>
  )
}

export function useLoader(): LoaderCtx {
  const c = useContext(Ctx)
  if (!c) throw new Error('LoaderProvider missing')
  return c
}

function Stage({ elapsed, onRetry, onBack }: { elapsed: number; onRetry: () => void; onBack: () => void }) {
  const { t } = useTranslation()
  // Timeout bertahap (§12.3 butir 6)
  if (elapsed >= 30)
    return (
      <div className="grid justify-items-center gap-2 text-center">
        <p className="t-small">{t('loader.failed')}</p>
        <p className="t-mono opacity-70">ID: {`nav-${Date.now().toString(36)}`}</p>
        <div className="flex gap-2">
          <button type="button" onClick={onRetry} className="t-label h-9 rounded-md bg-primary px-3 text-primary-contrast">{t('common.retry')}</button>
          <button type="button" onClick={onBack} className="t-label h-9 rounded-md border border-current/30 px-3">{t('common.back')}</button>
        </div>
      </div>
    )
  if (elapsed >= 15)
    return (
      <div className="grid justify-items-center gap-2 text-center">
        <p className="t-small">{t('loader.slow')}</p>
        <div className="flex gap-2">
          <button type="button" onClick={onRetry} className="t-label h-9 rounded-md bg-primary px-3 text-primary-contrast">{t('common.retry')}</button>
          <button type="button" onClick={onBack} className="t-label h-9 rounded-md border border-current/30 px-3">{t('common.back')}</button>
        </div>
      </div>
    )
  if (elapsed >= 5) return <p className="t-small">{t('loader.still_loading')}</p>
  return null
}

function lightMode(): boolean {
  const c = (navigator as any).connection
  return !!(c && (c.saveData || /2g/.test(c.effectiveType ?? '')))
}

export function DarkLoader({ elapsed, message, onRetry, onBack }: { elapsed: number; message: string | null; onRetry: () => void; onBack: () => void }) {
  const { t } = useTranslation()
  return (
    <div className="anim-fade-in fixed inset-0 z-loader grid place-items-center bg-[var(--loader-solid)] text-[var(--on-dark)]" role="status" aria-busy="true" aria-live="polite">
      <div className="grid justify-items-center gap-4 px-6">
        <svg width="48" height="48" viewBox="0 0 32 32" aria-hidden className={lightMode() ? '' : 'motion-safe:[animation:k-pulse_1.4s_ease-in-out_infinite]'}>
          <rect width="32" height="32" rx="8" fill="var(--on-dark-accent)" />
          <path d="M10 8v16M10 16l9-8M13 13l7 11" stroke="var(--loader-solid)" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" fill="none" />
        </svg>
        <div className="h-[3px] w-40 overflow-hidden rounded-full bg-white/10" aria-hidden>
          <span className="block h-full w-2/5 rounded-full bg-[var(--on-dark-accent)] motion-safe:[animation:k-bar_1.1s_ease-in-out_infinite]" />
        </div>
        <p className="t-small text-[var(--on-dark-muted)]">{message ?? t('loader.loading')}</p>
        <Stage elapsed={elapsed} onRetry={onRetry} onBack={onBack} />
      </div>
    </div>
  )
}

/** Skema A — dipasang di dalam area konten (tidak menutup navbar/sidebar). */
export function ContentLoader() {
  const { t } = useTranslation()
  const { visible, scheme, elapsed } = useLoader()
  const navigate = useNavigate()
  if (!visible || scheme !== 'A') return null
  return (
    <div className={cn('anim-fade-in absolute inset-0 z-sticky grid place-items-center bg-[var(--overlay-clear)]', !lightMode() && 'backdrop-blur-[2px]')} role="status" aria-busy="true">
      <div className="sticky top-1/3 grid justify-items-center gap-3 text-fg">
        <span className="size-8 rounded-full border-[3px] border-primary border-r-transparent motion-safe:[animation:k-spin_.8s_linear_infinite]" aria-hidden />
        <span className="sr-only">{t('loader.loading_page')}</span>
        {elapsed >= 2 && elapsed < 5 && <span className="t-small text-muted">{t('loader.loading')}</span>}
        <Stage elapsed={elapsed} onRetry={() => window.location.reload()} onBack={() => navigate(-1)} />
      </div>
    </div>
  )
}
