import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { Bell, ChevronRight, ChevronDown, HelpCircle, Lock, LogOut, Menu as MenuIcon, PanelLeftClose, PanelLeftOpen, Search, Settings, ShieldCheck, User as UserIcon, X, type LucideIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/shared/lib/cn'
import { getPref, setPref } from '@/shared/lib/prefs'
import { useIsDesktop, useIsMobile, useMediaQuery } from '@/shared/hooks/useMediaQuery'
import { useBackClose, useFocusTrap, useScrollLock } from '@/shared/hooks/useOverlay'
import { useAuth } from '@/shared/context/AuthContext'
import { Logo, LangMenu, ThemeMenu } from './Utilities'
import { ContentLoader, useLoader } from './PageLoader'
import { Menu, MenuItem } from '@/shared/ui/Misc'
import { IconButton } from '@/shared/ui/Button'
import { http } from '@/api-client/http'
import type { NotificationItem } from '@/api-client/types'
import { relative } from '@/shared/lib/format'
import { useLocked } from './Locked'
import { Modal } from '@/shared/ui/Modal'
import { motion } from 'framer-motion'
import { IntroProvider, useIntro, TIMELINE } from '@/shared/motion/intro'
import { LoginCurtain } from '@/shared/motion/LoginCurtain'

export interface NavLeaf {
  key: string
  label: string
  to: string
  icon?: LucideIcon
  badge?: number
  lockedFeature?: string
  end?: boolean
}
export interface NavGroup {
  key: string
  label: string
  icon: LucideIcon
  to?: string
  badge?: number
  lockedFeature?: string
  children?: NavLeaf[]
}
export interface NavSection {
  key: string
  title?: string
  groups: NavGroup[]
}

export interface ShellConfig {
  area: 'user' | 'admin'
  home: string
  sections: NavSection[]
  sidebarFooter: ReactNode
  bottomNav?: { key: string; label: string; to: string; icon: LucideIcon; emphasis?: boolean }[]
  topBanner?: ReactNode
  navbarExtra?: ReactNode
  profileLinks: { label: string; to: string; icon: LucideIcon }[]
  footer: ReactNode
  fab?: ReactNode
}

function isActive(pathname: string, to: string, end?: boolean) {
  return end ? pathname === to : pathname === to || pathname.startsWith(to + '/')
}
function groupActive(pathname: string, g: NavGroup) {
  if (g.children?.length) return g.children.some((c) => isActive(pathname, c.to, c.end))
  return g.to ? isActive(pathname, g.to, g.to === '/app' || g.to === '/admin') : false
}

/**
 * Kerangka panel (Design Spec §6, disempurnakan): navbar sticky + sidebar bergaya modern
 * (grup berlabel, sub-menu inline dengan garis panduan, pill aktif, kartu akun di bawah).
 * Desktop: penuh 272px ↔ rail ikon 76px (flyout sub-menu saat hover/fokus). Tablet: rail. Mobile: drawer.
 */
export function AppShell({ config }: { config: ShellConfig }) {
  return (
    <IntroProvider area={config.area}>
      <ShellInner config={config} />
    </IntroProvider>
  )
}

function ShellInner({ config }: { config: ShellConfig }) {
  const { intro } = useIntro()
  const { t } = useTranslation()
  const isDesktop = useIsDesktop()
  const isMobile = useIsMobile()
  const isTablet = !isDesktop && !isMobile
  const [compactPref, setCompactPref] = useState(() => getPref('krafio.sidebar') === 'compact')
  const [drawer, setDrawer] = useState(false)
  // Tinggi header aktual (navbar + banner lingkungan/peringatan) agar sidebar fixed tidak tertutup banner
  const headerRef = useRef<HTMLElement>(null)
  const [headerH, setHeaderH] = useState<number | null>(null)
  useEffect(() => {
    const el = headerRef.current
    if (!el) return
    const ro = new ResizeObserver(() => setHeaderH(el.offsetHeight))
    ro.observe(el)
    return () => ro.disconnect()
  }, [])
  const [search, setSearch] = useState(false)
  const [scrolled, setScrolled] = useState(false)
  const menuBtn = useRef<HTMLButtonElement>(null)
  const groups = useMemo(() => config.sections.flatMap((s) => s.groups), [config.sections])
  const compact = isTablet || (isDesktop && compactPref)

  useEffect(() => {
    const f = () => setScrolled(window.scrollY > 8)
    f()
    window.addEventListener('scroll', f, { passive: true })
    return () => window.removeEventListener('scroll', f)
  }, [])

  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault()
        setSearch(true)
      }
    }
    window.addEventListener('keydown', k)
    return () => window.removeEventListener('keydown', k)
  }, [])

  // Swipe kanan dari tepi kiri (≤24 px) membuka drawer di mobile (§6.3 butir 3)
  useEffect(() => {
    if (!isMobile) return
    let sx = -1
    let sy = 0
    const start = (e: TouchEvent) => {
      const x = e.touches[0].clientX
      sx = x <= 24 ? x : -1
      sy = e.touches[0].clientY
    }
    const end = (e: TouchEvent) => {
      if (sx < 0) return
      const dx = e.changedTouches[0].clientX - sx
      const dy = Math.abs(e.changedTouches[0].clientY - sy)
      if (dx > 90 && dy < 60) setDrawer(true)
      sx = -1
    }
    window.addEventListener('touchstart', start, { passive: true })
    window.addEventListener('touchend', end, { passive: true })
    return () => {
      window.removeEventListener('touchstart', start)
      window.removeEventListener('touchend', end)
    }
  }, [isMobile])

  const toggle = () => {
    if (isDesktop) {
      const next = !compactPref
      setCompactPref(next)
      setPref('krafio.sidebar', next ? 'compact' : 'full')
    } else setDrawer((d) => !d)
  }

  const sidebarW = isMobile ? 0 : compact ? 76 : 272

  return (
    <div className="min-h-[100dvh] bg-bg-subtle">
      <a href="#main-content" className="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-toast focus:rounded-md focus:bg-primary focus:px-4 focus:py-2 focus:text-primary-contrast">
        {t('common.skip_to_content')}
      </a>

      {/* NAVBAR — sticky, tidak auto-hide di panel (§6.2) */}
      <header ref={headerRef} className={cn('sticky top-0 z-navbar border-b border-line bg-surface pt-[var(--safe-t)] transition-[box-shadow] duration-[120ms]', scrolled && 'shadow-k-sm')}>
        <motion.div className="flex h-[var(--nav-h)] items-center gap-1 px-2 md:gap-2 md:px-3" initial={intro ? { opacity: 0, y: 8 } : false} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.35, ease: TIMELINE.easeOut, delay: TIMELINE.header }}>
          <button
            ref={menuBtn}
            type="button"
            onClick={toggle}
            aria-label={isDesktop ? (compactPref ? t('nav.expand_sidebar') : t('nav.collapse_sidebar')) : t('nav.open_menu')}
            aria-expanded={isDesktop ? !compactPref : drawer}
            aria-controls="krafio-sidebar"
            className="grid size-11 place-items-center rounded-md text-fg hover:bg-surface-2 lg:size-10"
          >
            {isDesktop ? compactPref ? <PanelLeftOpen className="size-5" aria-hidden /> : <PanelLeftClose className="size-5" aria-hidden /> : <MenuIcon className="size-5" aria-hidden />}
          </button>
          <Logo to={config.home} />
          {config.area === 'admin' && <span className="ml-1 rounded-sm bg-danger px-1.5 py-0.5 text-[11px] font-bold text-white dark:text-bg">ADMIN</span>}
          <div className="ml-4 hidden min-w-0 flex-1 lg:block">
            <button type="button" onClick={() => setSearch(true)} className="flex h-10 w-full max-w-md items-center gap-2 rounded-lg border border-line bg-bg-subtle px-3 text-left text-sm text-muted transition-[border-color] duration-[120ms] hover:border-line-strong">
              <Search className="size-4" aria-hidden />
              <span className="flex-1">{t('nav.search')}</span>
              <kbd className="rounded-sm border border-line bg-surface px-1.5 font-mono text-[11px]">Ctrl K</kbd>
            </button>
          </div>
          <div className="flex-1 lg:hidden" />
          <IconButton label={t('nav.search')} onClick={() => setSearch(true)} className="lg:hidden">
            <Search className="size-5" aria-hidden />
          </IconButton>
          {config.navbarExtra}
          <LangMenu className="hidden md:block" />
          <ThemeMenu className="hidden md:grid" />
          <NotificationsMenu area={config.area} />
          <ProfileMenu config={config} />
        </motion.div>
        {config.topBanner}
      </header>

      {/* SIDEBAR desktop/tablet */}
      {!isMobile && (
        <aside id="krafio-sidebar" aria-label={t('nav.main')} className="fixed bottom-0 left-0 z-sidebar flex flex-col border-r border-line bg-surface" style={{ top: headerH ?? 'calc(var(--nav-h) + var(--safe-t))', width: sidebarW }}>
          <SidebarNav config={config} compact={compact} />
          {!compact && <div className="border-t border-line p-3">{config.sidebarFooter}</div>}
        </aside>
      )}

      {(isMobile || isTablet) && <MobileDrawer open={drawer} onClose={() => { setDrawer(false); menuBtn.current?.focus() }} config={config} />}

      <div style={{ paddingLeft: sidebarW }}>
        <main id="main-content" tabIndex={-1} className={cn('relative mx-auto min-h-[calc(100dvh-var(--nav-h))] px-4 pb-24 pt-5 outline-none md:px-6 md:pt-6 lg:px-8', config.area === 'admin' ? 'max-w-[1600px]' : 'max-w-[1360px]')}>
          <ContentLoader />
          <Outlet />
        </main>
        <div className={cn(config.bottomNav && isMobile && 'pb-[calc(64px+var(--safe-b))]')}>{config.footer}</div>
      </div>

      {config.bottomNav && isMobile && <BottomNav items={config.bottomNav} />}
      {config.fab}
      {search && <SearchOverlay groups={groups} onClose={() => setSearch(false)} />}
      <LoginCurtain />
    </div>
  )
}

function readFolded(): string[] {
  try {
    const raw = localStorage.getItem('krafio.sidebar.folded')
    return raw ? (JSON.parse(raw) as string[]) : []
  } catch {
    return []
  }
}
function saveFolded(v: string[]) {
  try {
    localStorage.setItem('krafio.sidebar.folded', JSON.stringify(v))
  } catch {
    /* noop */
  }
}

function Count({ n }: { n?: number }) {
  if (!n) return null
  return <span className="ml-auto min-w-5 rounded-full bg-danger/12 px-1.5 text-center text-[11px] font-semibold leading-5 text-danger tabular-nums">{n > 99 ? '99+' : n}</span>
}

/** Sidebar: grup berlabel yang dapat dilipat; item induk membuka sub-menu inline (tree dengan garis panduan). */
function SidebarNav({ config, compact, onNavigate }: { config: ShellConfig; compact: boolean; onNavigate?: () => void }) {
  const { t } = useTranslation()
  const { intro } = useIntro()
  // Urutan item level 1 untuk stagger intro (dihitung sekali, tanpa mutasi saat render)
  const order = useMemo(() => new Map(config.sections.flatMap((x) => x.groups).map((g, i) => [g.key, i])), [config.sections])
  const loc = useLocation()
  const { openLocked } = useLocked()
  const [folded, setFolded] = useState<string[]>(() => readFolded())
  const [open, setOpen] = useState<string[]>(() => config.sections.flatMap((s) => s.groups).filter((g) => groupActive(loc.pathname, g) && g.children?.length).map((g) => g.key))
  const listRef = useRef<HTMLElement>(null)

  // Rute aktif: buka induknya & gulir item ke area terlihat (§6.5)
  useEffect(() => {
    const g = config.sections.flatMap((s) => s.groups).find((x) => groupActive(loc.pathname, x) && x.children?.length)
    if (g) setOpen((o) => (o.includes(g.key) ? o : [...o, g.key]))
    window.setTimeout(() => {
      const nav = listRef.current
      const el = nav?.querySelector<HTMLElement>('[aria-current="page"]')
      if (!nav || !el) return
      // Hanya gulir bila item aktif berada di luar area terlihat sidebar (koordinat layar, tidak bergantung offsetParent)
      const r = el.getBoundingClientRect()
      const n = nav.getBoundingClientRect()
      if (r.top < n.top || r.bottom > n.bottom) nav.scrollTo({ top: Math.max(0, nav.scrollTop + (r.top - n.top) - 96) })
    }, 50)
  }, [loc.pathname, config.sections])

  const toggleSection = (k: string) => {
    const next = folded.includes(k) ? folded.filter((x) => x !== k) : [...folded, k]
    setFolded(next)
    saveFolded(next)
  }

  const itemCls = (act: boolean) =>
    cn(
      'group/item relative flex min-h-10 w-full items-center gap-3 rounded-lg px-2.5 text-[14px] transition-[background-color,color] duration-[120ms] max-lg:min-h-11',
      act ? 'bg-primary-soft font-semibold text-primary' : 'font-medium text-fg/85 hover:bg-surface-2 hover:text-fg',
    )

  return (
    <nav ref={listRef} className={cn('min-h-0 flex-1 overflow-y-auto overscroll-contain py-3', compact ? 'px-2.5' : 'px-3')} aria-label={t('nav.main')}>
      {config.sections.map((s) => {
        const isFolded = !!s.title && folded.includes(s.key) && !compact
        return (
          <div key={s.key} className="mb-3 last:mb-0">
            {s.title && !compact && (
              <button type="button" onClick={() => toggleSection(s.key)} aria-expanded={!isFolded} className="mb-1 flex w-full items-center justify-between rounded-md px-2.5 py-1 text-[12px] font-semibold text-muted hover:text-fg">
                {s.title}
                <ChevronDown className={cn('size-3.5 transition-transform duration-[120ms]', isFolded && '-rotate-90')} aria-hidden />
              </button>
            )}
            {s.title && compact && <div className="mx-auto my-2 h-px w-8 bg-line" aria-hidden />}
            {!isFolded && (
              <ul className="grid gap-0.5">
                {s.groups.map((g) => {
                  const act = groupActive(loc.pathname, g)
                  const Icon = g.icon
                  const hasKids = !!g.children?.length
                  const expanded = open.includes(g.key)
                  const total = g.badge ?? g.children?.reduce((a, c) => a + (c.badge ?? 0), 0)

                  if (compact)
                    return (
                      <NavLi key={g.key} i={order.get(g.key) ?? 0} intro={intro} className="group/rail relative">
                        {hasKids ? (
                          <button type="button" aria-label={g.label} aria-haspopup="true" className={cn(itemCls(act), 'justify-center px-0')}>
                            <Icon className="size-[18px] shrink-0" aria-hidden />
                            {!!total && <span className="absolute right-2 top-2 size-2 rounded-full bg-danger" aria-hidden />}
                          </button>
                        ) : g.lockedFeature ? (
                          <button type="button" aria-label={`${g.label} — ${t('nav.locked')}`} onClick={() => openLocked(g.lockedFeature!)} className={cn(itemCls(false), 'justify-center px-0')}>
                            <Icon className="size-[18px] shrink-0" aria-hidden />
                          </button>
                        ) : (
                          <Link to={g.to ?? '#'} aria-label={g.label} aria-current={act ? 'page' : undefined} className={cn(itemCls(act), 'justify-center px-0')}>
                            <Icon className="size-[18px] shrink-0" aria-hidden />
                            {!!total && <span className="absolute right-2 top-2 size-2 rounded-full bg-danger" aria-hidden />}
                          </Link>
                        )}
                        {/* Flyout: label + sub-menu muncul saat hover/fokus (delay pendek) */}
                        <div className="pointer-events-none invisible absolute left-full top-0 z-popover pl-2 opacity-0 transition-[opacity] delay-150 duration-[120ms] group-focus-within/rail:pointer-events-auto group-focus-within/rail:visible group-focus-within/rail:opacity-100 group-hover/rail:pointer-events-auto group-hover/rail:visible group-hover/rail:opacity-100">
                          <div className="min-w-52 rounded-xl border border-line bg-surface p-1.5 shadow-k-lg">
                            <p className="px-2.5 py-1.5 text-[12px] font-semibold text-muted">{g.label}</p>
                            {hasKids ? <SubList group={g} onNavigate={onNavigate} /> : null}
                          </div>
                        </div>
                      </NavLi>
                    )

                  return (
                    <NavLi key={g.key} i={order.get(g.key) ?? 0} intro={intro}>
                      {hasKids ? (
                        <button type="button" aria-expanded={expanded} onClick={() => setOpen((o) => (expanded ? o.filter((x) => x !== g.key) : [...o, g.key]))} className={cn(itemCls(act && !expanded), act && expanded && 'text-fg')}>
                          <Icon className={cn('size-[18px] shrink-0', act ? 'text-primary' : 'text-muted group-hover/item:text-fg')} aria-hidden />
                          <span className="min-w-0 flex-1 truncate text-left">{g.label}</span>
                          {!expanded && <Count n={total} />}
                          <ChevronRight className={cn('size-4 shrink-0 text-muted transition-transform duration-[150ms]', expanded && 'rotate-90')} aria-hidden />
                        </button>
                      ) : g.lockedFeature ? (
                        <button type="button" onClick={() => openLocked(g.lockedFeature!)} className={itemCls(false)}>
                          <Icon className="size-[18px] shrink-0 text-muted" aria-hidden />
                          <span className="min-w-0 flex-1 truncate text-left text-muted">{g.label}</span>
                          <Lock className="size-3.5 text-muted" aria-label={t('nav.locked')} />
                        </button>
                      ) : (
                        <Link to={g.to ?? '#'} onClick={onNavigate} aria-current={act ? 'page' : undefined} className={itemCls(act)}>
                          <Icon className={cn('size-[18px] shrink-0', act ? 'text-primary' : 'text-muted group-hover/item:text-fg')} aria-hidden />
                          <span className="min-w-0 flex-1 truncate">{g.label}</span>
                          <Count n={g.badge} />
                        </Link>
                      )}
                      {hasKids && expanded && (
                        <div className="mb-1 ml-[21px] mt-0.5 border-l border-line pl-2">
                          <SubList group={g} onNavigate={onNavigate} />
                        </div>
                      )}
                    </NavLi>
                  )
                })}
              </ul>
            )}
          </div>
        )
      })}
    </nav>
  )
}

/** Item sidebar level 1: saat intro login muncul satu per satu dari atas (stagger 0.05 dtk). */
function NavLi({ i, intro, className, children }: { i: number; intro: boolean; className?: string; children: ReactNode }) {
  if (!intro) return <li className={className}>{children}</li>
  return (
    <motion.li className={className} initial={{ opacity: 0, y: -8 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.35, ease: TIMELINE.easeOut, delay: TIMELINE.sidebar + i * TIMELINE.sidebarStagger }}>
      {children}
    </motion.li>
  )
}

function SubList({ group, onNavigate }: { group: NavGroup; onNavigate?: () => void }) {
  const loc = useLocation()
  const { openLocked } = useLocked()
  const { t } = useTranslation()
  return (
    <ul className="grid gap-0.5">
      {group.children?.map((c) => {
        const act = isActive(loc.pathname, c.to, c.end)
        const cls = cn(
          'flex min-h-9 w-full items-center gap-2 rounded-md px-2.5 text-[13.5px] transition-[background-color,color] duration-[120ms] max-lg:min-h-11',
          act ? 'bg-primary-soft font-semibold text-primary' : 'text-muted hover:bg-surface-2 hover:text-fg',
        )
        return (
          <li key={c.key}>
            {c.lockedFeature ? (
              <button type="button" className={cls} onClick={() => openLocked(c.lockedFeature!)}>
                <span className="flex-1 truncate text-left">{c.label}</span>
                <Lock className="size-3.5" aria-label={t('nav.locked')} />
              </button>
            ) : (
              <NavLink to={c.to} end={c.end} onClick={onNavigate} className={cls} aria-current={act ? 'page' : undefined}>
                <span className="flex-1 truncate">{c.label}</span>
                <Count n={c.badge} />
              </NavLink>
            )}
          </li>
        )
      })}
    </ul>
  )
}

/** Drawer mobile/tablet (§6.3): min(86vw,320px), scrim, kunci scroll, trap fokus, Back/Esc/swipe kiri menutup. */
function MobileDrawer({ open, onClose, config }: { open: boolean; onClose: () => void; config: ShellConfig }) {
  const { t } = useTranslation()
  const ref = useRef<HTMLDivElement>(null)
  const [dx, setDx] = useState(0)
  const startX = useRef<number | null>(null)
  useScrollLock(open)
  useFocusTrap(ref, open)
  useBackClose(open, onClose)

  useEffect(() => {
    if (!open) return
    const k = (e: KeyboardEvent) => e.key === 'Escape' && onClose()
    document.addEventListener('keydown', k)
    const h = document.documentElement
    h.dataset.overlays = String(Number(h.dataset.overlays ?? 0) + 1)
    return () => {
      document.removeEventListener('keydown', k)
      h.dataset.overlays = String(Math.max(0, Number(h.dataset.overlays ?? 1) - 1))
    }
  }, [open, onClose])

  if (!open) return null
  return (
    <div className="fixed inset-0 z-drawer">
      <div className="anim-fade-in absolute inset-0 bg-[var(--overlay-dark)]" onClick={onClose} aria-hidden />
      <div
        ref={ref}
        id="krafio-sidebar"
        role="dialog"
        aria-modal="true"
        aria-label={t('nav.main')}
        style={{ transform: dx < 0 ? `translateX(${dx}px)` : undefined }}
        className="relative flex h-[100dvh] w-[min(86vw,320px)] flex-col overflow-hidden bg-surface pt-[var(--safe-t)] shadow-k-lg [animation:k-drawer_240ms_ease-out_both]"
        onTouchStart={(e) => (startX.current = e.touches[0].clientX)}
        onTouchMove={(e) => {
          if (startX.current === null) return
          setDx(Math.min(0, e.touches[0].clientX - startX.current))
        }}
        onTouchEnd={() => {
          const w = ref.current?.offsetWidth ?? 300
          if (-dx > w * 0.3) onClose()
          setDx(0)
          startX.current = null
        }}
      >
        <div className="flex h-14 shrink-0 items-center justify-between border-b border-line px-3">
          <Logo to={config.home} />
          <button type="button" onClick={onClose} aria-label={t('common.close')} className="grid size-11 place-items-center rounded-md hover:bg-surface-2">
            <X className="size-5" aria-hidden />
          </button>
        </div>
        <SidebarNav config={config} compact={false} onNavigate={onClose} />
        <div className="flex items-center gap-1 border-t border-line px-3 py-2">
          <LangMenu up align="left" />
          <ThemeMenu />
        </div>
        <div className="border-t border-line p-3 pb-[calc(12px+var(--safe-b))]">{config.sidebarFooter}</div>
      </div>
    </div>
  )
}

function BottomNav({ items }: { items: NonNullable<ShellConfig['bottomNav']> }) {
  const loc = useLocation()
  return (
    <nav className="fixed inset-x-0 bottom-0 z-navbar border-t border-line bg-surface pb-[var(--safe-b)]" aria-label="Bottom navigation">
      <ul className="grid h-16 grid-cols-5">
        {items.map((it) => {
          const act = it.to === '/app' ? loc.pathname === '/app' : loc.pathname.startsWith(it.to)
          return (
            <li key={it.key}>
              <NavLink to={it.to} end={it.to === '/app'} className={cn('flex h-full flex-col items-center justify-center gap-0.5 text-[11px] font-semibold', act ? 'text-primary' : 'text-muted')}>
                {it.emphasis ? (
                  <span className="grid size-10 place-items-center rounded-full bg-primary text-primary-contrast shadow-k-md">
                    <it.icon className="size-5" aria-hidden />
                  </span>
                ) : (
                  <it.icon className="size-5" aria-hidden />
                )}
                {!it.emphasis && it.label}
                {it.emphasis && <span className="sr-only">{it.label}</span>}
              </NavLink>
            </li>
          )
        })}
      </ul>
    </nav>
  )
}

function NotificationsMenu({ area }: { area: 'user' | 'admin' }) {
  const { t } = useTranslation()
  const { me, refresh } = useAuth()
  const isMobile = useIsMobile()
  const [items, setItems] = useState<NotificationItem[] | null>(null)
  const [sheet, setSheet] = useState(false)
  const base = area === 'admin' ? '/admin/notifications' : '/app/notifications'
  const load = useCallback(() => {
    http.get<{ data: NotificationItem[] }>('/me/notifications', { per_page: 10 }).then((r) => setItems(r.data)).catch(() => setItems([]))
  }, [])
  const count = me?.unread_notifications ?? 0
  const markAll = async () => {
    await http.post('/me/notifications/read-all').catch(() => undefined)
    await refresh()
    load()
  }
  const list = (close: () => void) => (
    <div className="grid">
      <div className="flex items-center justify-between border-b border-line px-4 py-2.5">
        <p className="font-semibold">{t('notif.title')}</p>
        {count > 0 && (
          <button type="button" onClick={markAll} className="t-small text-primary hover:underline">
            {t('notif.mark_all')}
          </button>
        )}
      </div>
      <ul className="max-h-[60dvh] overflow-y-auto">
        {items === null && <li className="t-small px-4 py-6 text-muted">{t('loader.loading')}</li>}
        {items?.length === 0 && <li className="t-small px-4 py-6 text-center text-muted">{t('notif.empty')}</li>}
        {items?.map((n) => (
          <li key={n.id} className={cn('border-b border-line px-4 py-3 last:border-b-0', !n.read_at && 'bg-primary-soft/50')}>
            <p className="text-sm font-semibold text-fg">{n.title}</p>
            <p className="t-small text-muted">{n.body}</p>
            <p className="t-small mt-0.5 text-muted">{relative(n.created_at)}</p>
          </li>
        ))}
      </ul>
      <Link to={base} onClick={close} className="t-label border-t border-line px-4 py-3 text-center text-primary hover:bg-surface-2">
        {t('notif.see_all')}
      </Link>
    </div>
  )
  if (isMobile)
    return (
      <>
        <IconButton label={t('notif.aria', { count })} badge={count} onClick={() => { setSheet(true); load() }}>
          <Bell className="size-5" aria-hidden />
        </IconButton>
        <Modal open={sheet} onClose={() => setSheet(false)} title={t('notif.title')} size="info" bodyClassName="p-0!">
          {list(() => setSheet(false))}
        </Modal>
      </>
    )
  return (
    <Menu
      label={t('notif.title')}
      width="w-[360px]"
      trigger={({ toggle, open, id }) => (
        <IconButton label={t('notif.aria', { count })} badge={count} aria-expanded={open} aria-controls={id} onClick={() => { toggle(); if (!open) load() }}>
          <Bell className="size-5" aria-hidden />
        </IconButton>
      )}
    >
      {(close) => list(close)}
    </Menu>
  )
}

function ProfileMenu({ config }: { config: ShellConfig }) {
  const { t } = useTranslation()
  const { me, logout } = useAuth()
  const nav = useNavigate()
  const { block, unblock } = useLoader()
  const isMobile = useIsMobile()
  if (!me) return null
  const initials = me.name.split(' ').map((p) => p[0]).slice(0, 2).join('').toUpperCase()
  const doLogout = async () => {
    block(t('loader.logging_out'))
    await logout()
    nav(config.area === 'admin' ? '/admin/login' : '/login', { replace: true })
    window.setTimeout(unblock, 400)
  }
  return (
    <Menu
      label={t('nav.profile')}
      width="w-72"
      trigger={({ toggle, open, id }) => (
        <button type="button" onClick={toggle} aria-expanded={open} aria-controls={id} aria-haspopup="menu" aria-label={t('nav.profile_aria', { name: me.name })} className="ml-0.5 grid size-11 place-items-center rounded-full lg:size-10">
          <span className={cn('grid size-9 place-items-center rounded-full text-[13px] font-bold', config.area === 'admin' ? 'bg-danger text-white dark:text-bg' : 'bg-primary text-primary-contrast')}>{initials}</span>
        </button>
      )}
    >
      {(close) => (
        <div className="grid">
          <div className="border-b border-line px-4 py-3">
            <p className="truncate font-semibold text-fg">{me.name}</p>
            <p className="t-small truncate text-muted">{me.email}</p>
            {config.area === 'admin' ? (
              <p className="t-small mt-1 inline-flex items-center gap-1 text-success">
                <ShieldCheck className="size-3.5" aria-hidden />
                {me.mfa_enabled ? t('nav.mfa_active') : t('nav.mfa_inactive')}
              </p>
            ) : (
              <p className="t-small mt-1 text-muted">{t('nav.plan', { plan: me.plan?.name ?? t('locked.no_plan') })}</p>
            )}
          </div>
          {config.profileLinks.map((l) => (
            <MenuItem key={l.to} to={l.to} onClick={close} icon={<l.icon className="size-4" aria-hidden />}>
              {l.label}
            </MenuItem>
          ))}
          {isMobile && (
            <div className="flex items-center gap-1 border-t border-line px-2 py-1.5">
              <LangMenu align="left" />
              <ThemeMenu />
            </div>
          )}
          <div className="border-t border-line">
            <MenuItem danger onClick={() => { close(); void doLogout() }} icon={<LogOut className="size-4" aria-hidden />}>
              {t('nav.logout')}
            </MenuItem>
          </div>
        </div>
      )}
    </Menu>
  )
}

export const DEFAULT_PROFILE_ICONS = { UserIcon, Settings, HelpCircle }

/** Pencarian: desktop palette; mobile overlay layar penuh (§6.2) */
function SearchOverlay({ groups, onClose }: { groups: NavGroup[]; onClose: () => void }) {
  const { t } = useTranslation()
  const nav = useNavigate()
  const ref = useRef<HTMLDivElement>(null)
  const [q, setQ] = useState('')
  const [idx, setIdx] = useState(0)
  const full = !useMediaQuery('(min-width: 768px)')
  useScrollLock(true)
  useFocusTrap(ref, true)
  useBackClose(true, onClose)
  const items = useMemo(() => {
    const all = groups.flatMap((g) => (g.children?.length ? g.children.map((c) => ({ label: c.label, group: g.label, to: c.to })) : g.to ? [{ label: g.label, group: '', to: g.to }] : []))
    const s = q.trim().toLowerCase()
    return (s ? all.filter((x) => `${x.label} ${x.group}`.toLowerCase().includes(s)) : all).slice(0, 12)
  }, [groups, q])
  const go = (to: string) => {
    onClose()
    nav(to)
  }
  return (
    <div className="fixed inset-0 z-modal">
      <div className="absolute inset-0 bg-[var(--overlay-dark)]" onClick={onClose} aria-hidden />
      <div ref={ref} role="dialog" aria-modal="true" aria-label={t('nav.search')} className={cn('relative mx-auto flex flex-col overflow-hidden bg-surface', full ? 'anim-fade-in h-[100dvh]' : 'anim-pop mt-[12vh] max-h-[70dvh] w-full max-w-xl rounded-xl border border-line shadow-k-lg')}>
        <div className="flex items-center gap-2 border-b border-line px-3">
          <Search className="size-5 text-muted" aria-hidden />
          <input
            autoFocus
            value={q}
            onChange={(e) => {
              setQ(e.target.value)
              setIdx(0)
            }}
            onKeyDown={(e) => {
              if (e.key === 'ArrowDown') {
                e.preventDefault()
                setIdx((i) => Math.min(items.length - 1, i + 1))
              } else if (e.key === 'ArrowUp') {
                e.preventDefault()
                setIdx((i) => Math.max(0, i - 1))
              } else if (e.key === 'Enter' && items[idx]) go(items[idx].to)
              else if (e.key === 'Escape') onClose()
            }}
            placeholder={t('nav.search_placeholder')}
            aria-label={t('nav.search')}
            className="h-14 min-w-0 flex-1 bg-transparent text-fg outline-none placeholder:text-muted"
          />
          <button type="button" onClick={onClose} className="t-label h-9 rounded-md px-2 text-muted hover:bg-surface-2">
            {full ? t('common.close') : 'Esc'}
          </button>
        </div>
        <ul className="min-h-0 flex-1 overflow-y-auto p-2" role="listbox">
          {items.length === 0 && <li className="t-small px-3 py-6 text-center text-muted">{t('table.no_results')}</li>}
          {items.map((it, i) => (
            <li key={it.to} role="option" aria-selected={i === idx}>
              <button type="button" onMouseEnter={() => setIdx(i)} onClick={() => go(it.to)} className={cn('flex min-h-11 w-full items-center justify-between gap-3 rounded-md px-3 text-left', i === idx ? 'bg-primary-soft text-primary' : 'text-fg')}>
                <span className="font-medium">{it.label}</span>
                {it.group && <span className="t-small text-muted">{it.group}</span>}
              </button>
            </li>
          ))}
        </ul>
      </div>
    </div>
  )
}

/** Footer ringkas satu baris untuk panel (§13.3) */
export function PanelFooter({ version, statusTo = '/status' }: { version: string; statusTo?: string }) {
  const { t } = useTranslation()
  return (
    <footer className="border-t border-line px-4 py-3 md:px-8">
      <p className="t-small flex flex-wrap items-center gap-x-4 gap-y-1 pr-20 text-muted">
        <span>Krafio {version}</span>
        <Link to={statusTo} className="hover:text-fg">{t('footer.status')}</Link>
        <Link to="/faq" className="hover:text-fg">{t('footer.help')}</Link>
        <Link to="?modal=terms" className="hover:text-fg">{t('legal.terms_short')}</Link>
        <Link to="?modal=privacy" className="hover:text-fg">{t('legal.privacy_short')}</Link>
      </p>
    </footer>
  )
}
