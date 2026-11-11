import { Check, Globe, Moon, Sun } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { Menu, MenuItem } from '@/shared/ui/Misc'
import { useTheme } from '@/shared/context/ThemeContext'
import { changeLanguage } from '@/shared/i18n'
import { cn } from '@/shared/lib/cn'
import { http } from '@/api-client/http'
import { useAuth } from '@/shared/context/AuthContext'

/** Mark Krafio: K geometris dengan lengan atas berujung sparkle (sumber: public/assets/img/brand/logo-mark.svg). */
export function LogoMark({ size = 28, bg = 'var(--primary)', fg = 'var(--primary-contrast)', className }: { size?: number; bg?: string; fg?: string; className?: string }) {
  return (
    <svg width={size} height={size} viewBox="0 0 64 64" aria-hidden className={className}>
      <rect width="64" height="64" rx="16" fill={bg} />
      <rect x="17" y="15" width="8" height="34" rx="4" fill={fg} />
      <path d="M23 33L40 49M23 31L31 23" stroke={fg} strokeWidth="8" strokeLinecap="round" />
      <path d="M40.5 10C41.71 17.48 44.02 19.79 51.5 21 44.02 22.21 41.71 24.52 40.5 32 39.29 24.52 36.98 22.21 29.5 21 36.98 19.79 39.29 17.48 40.5 10Z" fill={fg} />
    </svg>
  )
}

export function Logo({ to = '/', compact, className, onDark }: { to?: string; compact?: boolean; className?: string; onDark?: boolean }) {
  return (
    <Link to={to} className={cn('inline-flex shrink-0 items-center gap-2 rounded-md', onDark ? 'text-[var(--on-dark)]' : 'text-fg', className)} aria-label="Krafio">
      <LogoMark size={28} />
      {!compact && <span className="text-[19px] font-bold tracking-tight">Krafio</span>}
    </Link>
  )
}

// Tombol tema: satu toggle Terang ⇄ Gelap. Ikon menunjukkan mode aktif; aria-label menyebut mode aktif dan aksinya.
export function ThemeMenu({ className }: { className?: string }) {
  const { t } = useTranslation()
  const { mode, toggle } = useTheme()
  const { me } = useAuth()
  const Icon = mode === 'dark' ? Moon : Sun
  return (
    <button
      type="button"
      onClick={() => {
        const next = mode === 'dark' ? 'light' : 'dark'
        toggle()
        if (me) void http.patch('/me/preferences', { theme: next }).catch(() => undefined)
      }}
      aria-label={t('theme.aria', { mode: t(`theme.${mode}`), next: t(`theme.${mode === 'dark' ? 'light' : 'dark'}`) })}
      title={t(`theme.switch_to_${mode === 'dark' ? 'light' : 'dark'}`)}
      className={cn('grid size-11 place-items-center rounded-md text-fg transition-[background-color] duration-[120ms] hover:bg-surface-2 lg:size-10', className)}
    >
      <Icon className="size-5" aria-hidden />
    </button>
  )
}

// Tombol bahasa (§5.2): globe + kode, label dalam bahasa aslinya, tanpa reload.
export function LangMenu({ className, align = 'right', up }: { className?: string; align?: 'left' | 'right'; up?: boolean }) {
  const { t, i18n } = useTranslation()
  const { me } = useAuth()
  const cur = i18n.language === 'en' ? 'en' : 'id'
  const choose = (l: 'id' | 'en') => {
    void changeLanguage(l)
    if (me) void http.patch('/me/preferences', { locale: l }).catch(() => undefined)
  }
  return (
    <Menu
      label={t('lang.label')}
      width="w-52"
      align={align}
      className={cn(className, up && '[&>[role=menu]]:bottom-full [&>[role=menu]]:top-auto [&>[role=menu]]:mb-2')}
      trigger={({ toggle, open, id }) => (
        <button type="button" onClick={toggle} aria-expanded={open} aria-controls={id} aria-haspopup="menu" aria-label={t('lang.aria')} className="inline-flex h-11 items-center gap-1.5 rounded-md px-2.5 text-sm font-semibold text-fg hover:bg-surface-2 lg:h-10">
          <Globe className="size-4.5" aria-hidden />
          {cur.toUpperCase()}
        </button>
      )}
    >
      {(close) =>
        (
          [
            ['id', 'Bahasa Indonesia'],
            ['en', 'English'],
          ] as const
        ).map(([code, name]) => (
          <MenuItem key={code} active={cur === code} onClick={() => { choose(code); close() }}>
            <span lang={code} className="flex items-center justify-between">
              {name}
              {cur === code && <Check className="size-4 text-primary" aria-hidden />}
            </span>
          </MenuItem>
        ))
      }
    </Menu>
  )
}
