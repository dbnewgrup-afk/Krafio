import { useEffect, useRef, useState } from 'react'
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom'
import { Menu as MenuIcon, X, ArrowRight } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Logo, LangMenu, ThemeMenu } from '@/shared/layout/Utilities'
import { ContentLoader } from '@/shared/layout/PageLoader'
import { FabStack } from '@/shared/layout/Fab'
import { CookieConsent, openCookieSettings } from '@/shared/layout/Consent'
import { TopBanner } from '@/shared/layout/Banners'
import { ButtonLink } from '@/shared/ui/Button'
import { useAuth } from '@/shared/context/AuthContext'
import { useIsMobile } from '@/shared/hooks/useMediaQuery'
import { useBackClose, useFocusTrap, useScrollLock } from '@/shared/hooks/useOverlay'
import { cn } from '@/shared/lib/cn'

const NAV = [
  { to: '/features', key: 'features' },
  { to: '/templates', key: 'templates' },
  { to: '/pricing', key: 'pricing' },
  { to: '/affiliate', key: 'affiliate' },
  { to: '/faq', key: 'faq' },
]

export function PublicLayout() {
  const { t } = useTranslation()
  const { me } = useAuth()
  const isMobile = useIsMobile()
  const loc = useLocation()
  const [hidden, setHidden] = useState(false)
  const [scrolled, setScrolled] = useState(false)
  const [menu, setMenu] = useState(false)
  const last = useRef(0)

  // Frontpage mobile: navbar boleh auto-hide saat scroll ke bawah (§6.2)
  useEffect(() => {
    const f = () => {
      const y = window.scrollY
      setScrolled(y > 8)
      if (isMobile) setHidden(y > 120 && y > last.current)
      else setHidden(false)
      last.current = y
    }
    window.addEventListener('scroll', f, { passive: true })
    return () => window.removeEventListener('scroll', f)
  }, [isMobile])

  useEffect(() => setMenu(false), [loc.pathname])
  const panelHref = me ? (me.role === 'admin' ? '/admin' : '/app') : null

  return (
    <div className="flex min-h-[100dvh] flex-col bg-bg">
      <a href="#main-content" className="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-toast focus:rounded-md focus:bg-primary focus:px-4 focus:py-2 focus:text-primary-contrast">
        {t('common.skip_to_content')}
      </a>
      <header className={cn('sticky top-0 z-navbar border-b bg-surface/95 pt-[var(--safe-t)] backdrop-blur transition-[transform,box-shadow,border-color] duration-[200ms]', scrolled ? 'border-line shadow-k-sm' : 'border-transparent', hidden && '-translate-y-full')}>
        <div className="mx-auto flex h-[var(--nav-h)] max-w-[1360px] items-center gap-2 px-4 md:px-6 lg:px-8">
          <Logo />
          <nav aria-label={t('nav.main')} className="ml-6 hidden lg:block">
            <ul className="flex items-center gap-1">
              {NAV.map((n) => (
                <li key={n.to}>
                  <NavLink to={n.to} className={({ isActive }) => cn('inline-flex h-10 items-center rounded-md px-3 text-[15px] font-medium', isActive ? 'text-primary' : 'text-fg hover:bg-surface-2')}>
                    {t(`public.nav.${n.key}`)}
                  </NavLink>
                </li>
              ))}
            </ul>
          </nav>
          <div className="flex-1" />
          <LangMenu className="hidden md:block" />
          <ThemeMenu className="hidden md:grid" />
          {panelHref ? (
            <ButtonLink to={panelHref} size="sm" className="ml-1">
              {t('public.open_panel')}
            </ButtonLink>
          ) : (
            <>
              <Link to="/login" className="hidden h-10 items-center rounded-md px-3 text-[15px] font-semibold text-fg hover:bg-surface-2 sm:inline-flex">
                {t('auth.login')}
              </Link>
              <ButtonLink to="/register" size="sm" className="ml-1">
                {t('public.start_free')}
              </ButtonLink>
            </>
          )}
          <button type="button" onClick={() => setMenu(true)} aria-label={t('nav.open_menu')} aria-expanded={menu} className="grid size-11 place-items-center rounded-md hover:bg-surface-2 lg:hidden">
            <MenuIcon className="size-5" aria-hidden />
          </button>
        </div>
        <TopBanner />
      </header>

      {menu && <PublicMenu onClose={() => setMenu(false)} />}

      <main id="main-content" tabIndex={-1} className="relative flex-1 outline-none">
        <ContentLoader />
        <Outlet />
      </main>

      <PublicFooter />
      <FabStack />
      <CookieConsent />
    </div>
  )
}

function PublicMenu({ onClose }: { onClose: () => void }) {
  const { t } = useTranslation()
  const { me } = useAuth()
  const ref = useRef<HTMLDivElement>(null)
  useScrollLock(true)
  useFocusTrap(ref, true)
  useBackClose(true, onClose)
  useEffect(() => {
    const k = (e: KeyboardEvent) => e.key === 'Escape' && onClose()
    document.addEventListener('keydown', k)
    return () => document.removeEventListener('keydown', k)
  }, [onClose])
  return (
    <div className="fixed inset-0 z-drawer lg:hidden">
      <div className="anim-fade-in absolute inset-0 bg-[var(--overlay-dark)]" onClick={onClose} aria-hidden />
      <div ref={ref} role="dialog" aria-modal="true" aria-label={t('nav.main')} className="absolute inset-y-0 right-0 flex w-[min(86vw,340px)] flex-col bg-surface pt-[var(--safe-t)] shadow-k-lg [animation:k-fade_200ms_ease_both]">
        <div className="flex h-14 items-center justify-between border-b border-line px-4">
          <Logo />
          <button type="button" onClick={onClose} aria-label={t('common.close')} className="grid size-11 place-items-center rounded-md hover:bg-surface-2">
            <X className="size-5" aria-hidden />
          </button>
        </div>
        <nav className="flex-1 overflow-y-auto p-3">
          <ul className="grid gap-1">
            {NAV.map((n) => (
              <li key={n.to}>
                <NavLink to={n.to} onClick={onClose} className={({ isActive }) => cn('flex min-h-12 items-center justify-between rounded-md px-3 text-base font-semibold', isActive ? 'bg-primary-soft text-primary' : 'text-fg hover:bg-surface-2')}>
                  {t(`public.nav.${n.key}`)}
                  <ArrowRight className="size-4 text-muted" aria-hidden />
                </NavLink>
              </li>
            ))}
          </ul>
        </nav>
        <div className="grid gap-2 border-t border-line p-4 pb-[calc(16px+var(--safe-b))]">
          <div className="flex items-center gap-1">
            <LangMenu up align="left" />
            <ThemeMenu />
          </div>
          {me ? (
            <ButtonLink to={me.role === 'admin' ? '/admin' : '/app'} block>
              {t('public.open_panel')}
            </ButtonLink>
          ) : (
            <>
              <ButtonLink to="/register" block>
                {t('public.start_free')}
              </ButtonLink>
              <ButtonLink to="/login" variant="secondary" block>
                {t('auth.login')}
              </ButtonLink>
            </>
          )}
        </div>
      </div>
    </div>
  )
}

// Footer horizontal di semua perangkat (§13): desktop 5 kolom sejajar, tablet 3 kolom, mobile baris chip geser.
function PublicFooter() {
  const { t } = useTranslation()
  const { settings } = useAuth()
  const groups: { key: string; links: { to: string; label: string }[] }[] = [
    { key: 'product', links: [{ to: '/features', label: t('public.nav.features') }, { to: '/pricing', label: t('public.nav.pricing') }, { to: '/templates', label: t('public.nav.templates') }, { to: '/addons', label: t('footer.addons') }] },
    { key: 'resources', links: [{ to: '/faq', label: t('public.nav.faq') }, { to: '/affiliate', label: t('public.nav.affiliate') }, { to: '/status', label: t('footer.status') }] },
    { key: 'company', links: [{ to: '/about', label: t('footer.about') }, { to: '/contact', label: t('footer.contact') }] },
    { key: 'legal', links: [{ to: '/terms', label: t('legal.terms_short') }, { to: '/privacy', label: t('legal.privacy_short') }, { to: '/refund', label: t('legal.refund_short') }, { to: '/affiliate-terms', label: t('legal.affiliate_terms_short') }, { to: '/cookies', label: t('legal.cookies') }] },
  ]
  const social = settings?.social ?? []
  return (
    <footer className="border-t border-line bg-bg-subtle">
      <div className="mx-auto max-w-[1360px] px-4 pt-8 md:px-6 lg:px-8 lg:pt-12">
        {/* Desktop & tablet: kolom sejajar */}
        <div className="hidden gap-8 md:grid md:grid-cols-3 lg:grid-cols-[1.4fr_repeat(4,1fr)]">
          <div className="grid content-start gap-3 md:col-span-3 lg:col-span-1">
            <Logo />
            <p className="t-small max-w-xs text-muted">{t('footer.tagline')}</p>
          </div>
          {groups.map((g) => (
            <nav key={g.key} aria-label={t(`footer.${g.key}`)} className="grid content-start gap-2">
              <p className="t-label text-fg">{t(`footer.${g.key}`)}</p>
              {g.links.map((l) => (
                <Link key={l.to} to={l.to} className="t-small text-muted hover:text-fg">
                  {l.label}
                </Link>
              ))}
            </nav>
          ))}
        </div>

        {/* Mobile: tetap horizontal — baris chip yang dapat digeser */}
        <div className="grid gap-3 md:hidden">
          <div className="flex items-center justify-between gap-3">
            <Logo />
            <div className="flex items-center">
              <LangMenu up />
              <ThemeMenu />
            </div>
          </div>
          <p className="t-small text-muted">{t('footer.tagline')}</p>
          {groups.slice(0, 3).map((g) => (
            <nav key={g.key} aria-label={t(`footer.${g.key}`)} className="snap-row -mx-4 gap-2 px-4">
              {g.links.map((l) => (
                <Link key={l.to} to={l.to} className="t-small inline-flex h-9 items-center rounded-full border border-line bg-surface px-3 text-fg">
                  {l.label}
                </Link>
              ))}
            </nav>
          ))}
        </div>

        {social.length > 0 && (
          <ul className="mt-6 flex flex-wrap gap-2" aria-label={t('footer.social')}>
            {social.map((s) => (
              <li key={s.platform}>
                <a href={s.url} target="_blank" rel="noopener noreferrer" className="t-label inline-flex h-9 items-center rounded-full border border-line bg-surface px-3 text-fg hover:border-primary/50">
                  {s.platform}
                </a>
              </li>
            ))}
          </ul>
        )}

        {/* Baris bawah satu baris; boleh wrap bila tidak muat */}
        <div className="mt-8 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-line py-4 pr-20 md:pr-24">
          <p className="t-small text-muted">© {new Date().getFullYear()} Krafio</p>
          <Link to="/terms" className="t-small text-muted hover:text-fg">{t('legal.terms_short')}</Link>
          <Link to="/privacy" className="t-small text-muted hover:text-fg">{t('legal.privacy_short')}</Link>
          <Link to="/refund" className="t-small text-muted hover:text-fg">{t('legal.refund_short')}</Link>
          <button type="button" onClick={openCookieSettings} className="t-small text-muted hover:text-fg">
            {t('footer.cookie_settings')}
          </button>
          <div className="ml-auto hidden items-center md:flex">
            <LangMenu up />
            <ThemeMenu />
          </div>
        </div>
      </div>
    </footer>
  )
}
