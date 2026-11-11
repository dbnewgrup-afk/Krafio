import { useEffect, useId, useRef, useState, type ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { ChevronLeft, ChevronRight, ChevronDown } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/shared/lib/cn'
import { useIsMobile } from '@/shared/hooks/useMediaQuery'

/* ---------- Tabs / segmented control (§1.3, §7.2 butir 10) ---------- */
export function Tabs<T extends string>({ items, value, onChange, className, label }: { items: { value: T; label: ReactNode; count?: number }[]; value: T; onChange: (v: T) => void; className?: string; label?: string }) {
  const refs = useRef<Array<HTMLButtonElement | null>>([])
  return (
    <div role="tablist" aria-label={label} className={cn('snap-row -mx-4 gap-1 border-b border-line px-4 md:mx-0 md:px-0', className)}>
      {items.map((it, i) => {
        const active = it.value === value
        return (
          <button
            key={it.value}
            ref={(el) => {
              refs.current[i] = el
            }}
            role="tab"
            type="button"
            aria-selected={active}
            tabIndex={active ? 0 : -1}
            onClick={() => onChange(it.value)}
            onKeyDown={(e) => {
              if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                e.preventDefault()
                const n = (i + (e.key === 'ArrowRight' ? 1 : items.length - 1)) % items.length
                onChange(items[n].value)
                refs.current[n]?.focus()
              }
            }}
            className={cn(
              't-label relative flex h-11 items-center gap-2 whitespace-nowrap px-3 transition-[color] duration-[120ms]',
              active ? 'text-primary after:absolute after:inset-x-2 after:-bottom-px after:h-0.5 after:rounded-full after:bg-primary' : 'text-muted hover:text-fg',
            )}
          >
            {it.label}
            {it.count !== undefined && <span className={cn('rounded-full px-1.5 text-[11px] leading-5', active ? 'bg-primary-soft' : 'bg-surface-2')}>{it.count}</span>}
          </button>
        )
      })}
    </div>
  )
}

export function Segmented<T extends string>({ items, value, onChange, label }: { items: { value: T; label: ReactNode }[]; value: T; onChange: (v: T) => void; label?: string }) {
  return (
    <div role="radiogroup" aria-label={label} className="inline-flex rounded-md border border-line bg-surface-2 p-1">
      {items.map((it) => (
        <button
          key={it.value}
          type="button"
          role="radio"
          aria-checked={it.value === value}
          onClick={() => onChange(it.value)}
          className={cn('t-label h-9 rounded-sm px-3 transition-[background-color,color] duration-[120ms]', it.value === value ? 'bg-surface text-fg shadow-k-sm' : 'text-muted hover:text-fg')}
        >
          {it.label}
        </button>
      ))}
    </div>
  )
}

/* ---------- Chips filter horizontal (§1.3) ---------- */
export function ChipRow<T extends string>({ items, value, onChange, label }: { items: { value: T; label: ReactNode }[]; value: T; onChange: (v: T) => void; label?: string }) {
  return (
    <div className="snap-row -mx-4 gap-2 px-4 md:mx-0 md:flex-wrap md:px-0" role="radiogroup" aria-label={label}>
      {items.map((it) => (
        <button
          key={it.value}
          type="button"
          role="radio"
          aria-checked={it.value === value}
          onClick={() => onChange(it.value)}
          className={cn(
            't-label h-9 rounded-full border px-3.5 transition-[background-color,color,border-color] duration-[120ms]',
            it.value === value ? 'border-primary bg-primary text-primary-contrast' : 'border-line bg-surface text-fg hover:border-primary/50',
          )}
        >
          {it.label}
        </button>
      ))}
    </div>
  )
}

/* ---------- Dropdown / popover menu ---------- */
export function Menu({ trigger, children, align = 'right', label, className, width = 'w-64' }: { trigger: (p: { open: boolean; toggle: () => void; id: string }) => ReactNode; children: (close: () => void) => ReactNode; align?: 'left' | 'right'; label: string; className?: string; width?: string }) {
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)
  const id = useId()
  useEffect(() => {
    if (!open) return
    const onDoc = (e: MouseEvent) => {
      if (!ref.current?.contains(e.target as Node)) setOpen(false)
    }
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false)
    document.addEventListener('mousedown', onDoc)
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('mousedown', onDoc)
      document.removeEventListener('keydown', onKey)
    }
  }, [open])
  return (
    <div ref={ref} className={cn('relative', className)}>
      {trigger({ open, toggle: () => setOpen((o) => !o), id })}
      {open && (
        <div id={id} role="menu" aria-label={label} className={cn('anim-pop absolute top-full z-popover mt-2 overflow-hidden rounded-lg border border-line bg-surface py-1.5 shadow-k-md', width, align === 'right' ? 'right-0' : 'left-0')}>
          {children(() => setOpen(false))}
        </div>
      )}
    </div>
  )
}

export function MenuItem({ children, onClick, to, icon, active, danger }: { children: ReactNode; onClick?: () => void; to?: string; icon?: ReactNode; active?: boolean; danger?: boolean }) {
  const cls = cn('flex min-h-10 w-full items-center gap-3 px-3.5 py-2 text-left text-sm hover:bg-surface-2', danger ? 'text-danger' : 'text-fg', active && 'font-semibold')
  if (to)
    return (
      <Link role="menuitem" to={to} onClick={onClick} className={cls}>
        {icon}
        <span className="flex-1">{children}</span>
      </Link>
    )
  return (
    <button role="menuitemradio" aria-checked={active} type="button" onClick={onClick} className={cls}>
      {icon}
      <span className="flex-1">{children}</span>
    </button>
  )
}

/* ---------- Breadcrumb + page header (§6.5, §7.4) ---------- */
export interface Crumb {
  label: string
  to?: string
}
export function Breadcrumb({ items }: { items: Crumb[] }) {
  const { t } = useTranslation()
  const isMobile = useIsMobile()
  if (!items.length) return null
  if (isMobile) {
    const parent = [...items].reverse().find((c) => c.to)
    if (!parent) return null
    return (
      <Link to={parent.to!} className="t-small inline-flex items-center gap-1 text-muted hover:text-fg">
        <ChevronLeft className="size-4" aria-hidden />
        {parent.label}
      </Link>
    )
  }
  return (
    <nav aria-label={t('common.breadcrumb')}>
      <ol className="t-small flex flex-wrap items-center gap-1 text-muted">
        {items.map((c, i) => (
          <li key={i} className="flex items-center gap-1">
            {i > 0 && <ChevronRight className="size-3.5" aria-hidden />}
            {c.to && i < items.length - 1 ? (
              <Link to={c.to} className="hover:text-fg">
                {c.label}
              </Link>
            ) : (
              <span aria-current={i === items.length - 1 ? 'page' : undefined} className={i === items.length - 1 ? 'text-fg' : ''}>
                {c.label}
              </span>
            )}
          </li>
        ))}
      </ol>
    </nav>
  )
}

export function PageHeader({ title, subtitle, crumbs, actions }: { title: string; subtitle?: ReactNode; crumbs?: Crumb[]; actions?: ReactNode }) {
  return (
    <header className="mb-6 grid gap-3">
      {crumbs && <Breadcrumb items={crumbs} />}
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div className="grid min-w-0 gap-1">
          <h1 className="t-h1 text-fg" tabIndex={-1} data-page-title>
            {title}
          </h1>
          {subtitle && <p className="t-body text-muted">{subtitle}</p>}
        </div>
        {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
      </div>
    </header>
  )
}

/* ---------- Card ---------- */
export function Card({ children, className, as: As = 'section' }: { children: ReactNode; className?: string; as?: 'section' | 'div' | 'article' }) {
  return <As className={cn('rounded-lg border border-line bg-surface p-4 shadow-k-sm md:p-5', className)}>{children}</As>
}

export function CardTitle({ children, action }: { children: ReactNode; action?: ReactNode }) {
  return (
    <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
      <h2 className="t-h3 text-fg">{children}</h2>
      {action}
    </div>
  )
}

/* ---------- Accordion (§1.3) ---------- */
export function Accordion({ items }: { items: { id: string | number; title: ReactNode; body: ReactNode }[] }) {
  const [open, setOpen] = useState<string | number | null>(null)
  return (
    <div className="divide-y divide-line rounded-lg border border-line bg-surface">
      {items.map((it) => {
        const isOpen = open === it.id
        return (
          <div key={it.id}>
            <h3>
              <button
                type="button"
                aria-expanded={isOpen}
                aria-controls={`acc-${it.id}`}
                onClick={() => setOpen(isOpen ? null : it.id)}
                className="flex min-h-14 w-full items-center justify-between gap-4 px-4 py-3 text-left font-semibold text-fg hover:bg-surface-2 md:px-5"
              >
                {it.title}
                <ChevronDown className={cn('size-5 shrink-0 text-muted transition-transform duration-[180ms]', isOpen && 'rotate-180')} aria-hidden />
              </button>
            </h3>
            {isOpen && (
              <div id={`acc-${it.id}`} className="t-body measure px-4 pb-4 text-muted md:px-5">
                {it.body}
              </div>
            )}
          </div>
        )
      })}
    </div>
  )
}

/* ---------- Stat/KPI card (dapat diklik → daftar terfilter, §7.2 butir 11) ---------- */
export function KpiCard({ label, value, hint, to, tone }: { label: string; value: ReactNode; hint?: ReactNode; to?: string; tone?: 'primary' | 'topup' | 'commission' | 'warning' | 'danger' }) {
  const bar = tone === 'topup' ? 'before:bg-wallet-topup' : tone === 'commission' ? 'before:bg-wallet-commission' : tone === 'warning' ? 'before:bg-warning' : tone === 'danger' ? 'before:bg-danger' : 'before:bg-primary'
  const inner = (
    <>
      <span className="t-small text-muted">{label}</span>
      <span className="text-[26px] font-bold leading-8 tracking-tight text-fg tabular-nums">{value}</span>
      {hint && <span className="t-small text-muted">{hint}</span>}
    </>
  )
  const cls = cn('relative grid min-w-0 gap-1 overflow-hidden rounded-lg border border-line bg-surface p-4 pl-5 shadow-k-sm before:absolute before:inset-y-0 before:left-0 before:w-1', bar)
  if (to)
    return (
      <Link to={to} className={cn(cls, 'transition-[border-color] duration-[120ms] hover:border-primary/60')}>
        {inner}
      </Link>
    )
  return <div className={cls}>{inner}</div>
}
