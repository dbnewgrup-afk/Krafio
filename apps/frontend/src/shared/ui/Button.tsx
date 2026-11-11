import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from 'react'
import { Link, type LinkProps } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { cn } from '@/shared/lib/cn'

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'link'
type Size = 'sm' | 'md' | 'lg'

const base =
  'relative inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md font-semibold transition-[background-color,color,border-color,opacity] duration-[120ms] ease-out disabled:opacity-55 select-none'
const variants: Record<Variant, string> = {
  primary: 'bg-primary text-primary-contrast hover:bg-primary-hover',
  secondary: 'border border-line bg-surface text-fg hover:bg-surface-2',
  ghost: 'text-fg hover:bg-surface-2',
  danger: 'bg-danger text-white hover:opacity-90 dark:text-bg',
  link: 'text-primary underline-offset-4 hover:underline px-0! h-auto!',
}
const sizes: Record<Size, string> = {
  sm: 'h-9 px-3 text-[13px] max-lg:h-10',
  md: 'h-11 px-4 text-[15px] lg:h-10 lg:text-sm',
  lg: 'h-12 px-5 text-base',
}

export function Spinner({ className }: { className?: string }) {
  return <span aria-hidden className={cn('inline-block size-4 rounded-full border-2 border-current border-r-transparent [animation:k-spin_.7s_linear_infinite]', className)} />
}

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant
  size?: Size
  loading?: boolean
  icon?: ReactNode
  block?: boolean
}

// Loading di tombol (§12.6): spinner menggantikan ikon kiri, teks "Memproses…", lebar tetap, aria-busy, disabled.
export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
  { variant = 'primary', size = 'md', loading, icon, block, className, children, disabled, type = 'button', ...rest },
  ref,
) {
  const { t } = useTranslation()
  return (
    <button
      ref={ref}
      type={type}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      className={cn(base, variants[variant], sizes[size], block && 'w-full', className)}
      {...rest}
    >
      {loading ? <Spinner /> : icon}
      {loading ? (
        <span className="grid">
          <span className="invisible col-start-1 row-start-1">{children}</span>
          <span className="col-start-1 row-start-1">{t('common.processing')}</span>
        </span>
      ) : (
        children
      )}
    </button>
  )
})

export function ButtonLink({ variant = 'primary', size = 'md', block, className, children, icon, ...rest }: LinkProps & { variant?: Variant; size?: Size; block?: boolean; icon?: ReactNode }) {
  return (
    <Link className={cn(base, variants[variant], sizes[size], block && 'w-full', className)} {...rest}>
      {icon}
      {children}
    </Link>
  )
}

export function IconButton({ label, className, children, badge, ...rest }: ButtonHTMLAttributes<HTMLButtonElement> & { label: string; badge?: number }) {
  return (
    <button type="button" aria-label={label} title={label} className={cn('relative grid size-11 place-items-center rounded-md text-fg transition-[background-color] duration-[120ms] hover:bg-surface-2 lg:size-10', className)} {...rest}>
      {children}
      {badge ? (
        <span className="absolute right-1 top-1 min-w-[18px] rounded-full bg-danger px-1 text-center text-[11px] font-semibold leading-[18px] text-white dark:text-bg">
          {badge > 99 ? '99+' : badge}
        </span>
      ) : null}
    </button>
  )
}
