import type { ReactNode } from 'react'
import { Link, Outlet, useLocation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ArrowLeft, ShieldCheck, Wallet, Sparkles } from 'lucide-react'
import { Logo, LangMenu, ThemeMenu } from '@/shared/layout/Utilities'
import { Carousel } from '@/shared/ui/Carousel'
import { ContentLoader } from '@/shared/layout/PageLoader'
import { PHOTO } from '@/shared/lib/assets'

// Layout halaman auth (§9.1): desktop split 2 kolom (panel visual carousel + kartu form ≤440px),
// tablet kartu tengah ≤480px, mobile form layar penuh tanpa kartu.
export function AuthLayout() {
  const { t } = useTranslation()
  // Panel admin memakai mockup panel admin; area user memakai mockup sisi pengguna (tidak berulang dalam satu halaman).
  const isAdmin = useLocation().pathname.startsWith('/admin')
  const slides = [
    { icon: Sparkles, key: 'a', src: isAdmin ? PHOTO.adminLoginControl : PHOTO.loginCredit },
    { icon: Wallet, key: 'b', src: isAdmin ? PHOTO.adminLoginWallet : PHOTO.walletCards },
    { icon: ShieldCheck, key: 'c', src: PHOTO.adminLoginMfa },
  ]
  return (
    <div className="grid min-h-[100dvh] bg-bg md:bg-bg-subtle lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:bg-bg">
      <aside className="relative hidden flex-col justify-between overflow-hidden bg-[var(--loader-solid)] p-10 text-[var(--on-dark)] lg:flex">
        <Logo onDark />
        <Carousel label={t('auth.visual_label')} itemClass="basis-full" autoplay interval={6000} loop gap="gap-0" variant="hero" tone="dark">
          {slides.map((s) => (
            <div key={s.key} className="grid max-w-md gap-4 pb-2">
              <img src={s.src} alt="" width={1200} height={896} className="aspect-[4/3] w-full max-w-md rounded-xl bg-white/4 object-cover object-left shadow-k-md" />
              <span className="grid size-12 place-items-center rounded-lg bg-white/8 text-[var(--on-dark-accent)]">
                <s.icon className="size-6" aria-hidden />
              </span>
              <p className="text-[28px] font-bold leading-9">{t(`auth.visual.${s.key}_title`)}</p>
              <p className="text-base leading-7 text-[var(--on-dark-muted)]">{t(`auth.visual.${s.key}_body`)}</p>
            </div>
          ))}
        </Carousel>
        <p className="text-sm text-[var(--on-dark-muted)]">© {new Date().getFullYear()} Krafio</p>
      </aside>

      <div className="relative flex min-h-[100dvh] flex-col">
        <div className="flex h-14 items-center justify-between gap-2 px-4 pt-[var(--safe-t)] md:h-16 md:px-6">
          <Link to="/" className="t-small inline-flex h-10 items-center gap-1.5 rounded-md px-2 text-muted hover:text-fg">
            <ArrowLeft className="size-4" aria-hidden />
            {t('auth.back_home')}
          </Link>
          <div className="flex items-center">
            <LangMenu />
            <ThemeMenu />
          </div>
        </div>
        <main id="main-content" className="relative flex flex-1 items-start justify-center px-4 pb-10 md:items-center md:px-6">
          <ContentLoader />
          <div className="w-full max-w-[480px] md:rounded-xl md:border md:border-line md:bg-surface md:p-8 md:shadow-k-md lg:max-w-[440px] lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none">
            <div className="mb-5 lg:hidden">
              <Logo />
            </div>
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  )
}

export function AuthHeading({ title, sub }: { title: string; sub?: ReactNode }) {
  return (
    <div className="mb-6 grid gap-1.5">
      <h1 className="t-h1 text-fg" data-page-title tabIndex={-1}>
        {title}
      </h1>
      {sub && <p className="t-body text-muted">{sub}</p>}
    </div>
  )
}

export function FormAlert({ message, tone = 'danger' }: { message: string | null; tone?: 'danger' | 'success' | 'info' }) {
  if (!message) return null
  return (
    <div role={tone === 'danger' ? 'alert' : 'status'} className={tone === 'danger' ? 'mb-4 rounded-md border border-danger/30 bg-danger/10 p-3 text-sm text-danger' : tone === 'success' ? 'mb-4 rounded-md border border-success/30 bg-success/10 p-3 text-sm text-success' : 'mb-4 rounded-md border border-info/30 bg-info/10 p-3 text-sm text-info'}>
      {message}
    </div>
  )
}
