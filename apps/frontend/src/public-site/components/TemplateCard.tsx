import { useState, type ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Crown, Lock, Sparkles } from 'lucide-react'
import type { TemplateItem } from '@/api-client/types'
import { cn } from '@/shared/lib/cn'
import { img, templatePhoto } from '@/shared/lib/assets'

const RATIO: Record<string, number> = { '1:1': 1, '4:5': 0.8, '9:16': 0.5625, '16:9': 1.7778, '4:3': 1.3333 }

/** Setelan tampilan kartu — satu komponen untuk homepage, katalog, dan pratinjau langsung di Admin. */
export interface CardDisplay {
  variant: 'image-top' | 'overlay' | 'horizontal' | 'minimal'
  showBadge: boolean
  /** Tampilkan jenis/kategori (baris "pembuat/info") */
  showCreator: boolean
  showPrice: boolean
  /** Rasio bingkai pratinjau */
  imageRatio: '1/1' | '4/3' | '16/9' | '4/5'
  hoverEffect: 'none' | 'lift' | 'tilt' | 'zoom'
  radius: 'lg' | 'xl' | '2xl'
}
export const DEFAULT_CARD_DISPLAY: CardDisplay = { variant: 'image-top', showBadge: true, showCreator: true, showPrice: true, imageRatio: '4/5', hoverEffect: 'zoom', radius: 'xl' }
const FRAME: Record<CardDisplay['imageRatio'], number> = { '1/1': 1, '4/3': 4 / 3, '16/9': 16 / 9, '4/5': 0.8 }

/** Keadaan kartu yang dapat dipratinjau di editor Admin. */
export type CardPreviewState = 'default' | 'hover' | 'locked' | 'featured'

/** Bagian kartu yang bisa diklik di pratinjau untuk memfokuskan field form. */
export type CardField = 'image' | 'title' | 'badge' | 'price' | 'type'

/** Isi visual template (konten milik template, tidak ikut tema — §4.3): cover unggahan → foto desain resmi → ilustrasi vektor → bentuk palet. */
function Art({ item }: { item: TemplateItem }) {
  const [failed, setFailed] = useState(0)
  const [a, b, c] = item.palette.length >= 3 ? item.palette : ['#E2E8F0', '#94A3B8', '#0F172A']
  const layout = item.id % 3
  const sources = [item.cover_url, item.art ? templatePhoto(item.art) : null, item.art ? img(`templates/${item.art}.svg`) : null].filter((x): x is string => !!x)
  const src = sources[failed]
  if (src)
    return <img key={src} src={src} alt="" draggable={false} loading="lazy" onError={() => setFailed((n) => n + 1)} className="h-full w-full object-cover" style={item.focus ? { objectPosition: `${item.focus.x}% ${item.focus.y}%` } : undefined} />
  return (
    <div className="relative h-full w-full overflow-hidden" style={{ background: a }}>
      {layout === 0 && (
        <>
          <div className="absolute inset-x-[10%] top-[12%] h-[38%] rounded-[6%]" style={{ background: b }} />
          <div className="absolute bottom-[22%] left-[10%] h-[7%] w-[62%] rounded-full" style={{ background: c }} />
          <div className="absolute bottom-[12%] left-[10%] h-[5%] w-[40%] rounded-full opacity-60" style={{ background: c }} />
        </>
      )}
      {layout === 1 && (
        <>
          <div className="absolute inset-y-0 right-0 w-[46%]" style={{ background: b }} />
          <div className="absolute left-[8%] top-[18%] h-[8%] w-[38%] rounded-full" style={{ background: c }} />
          <div className="absolute left-[8%] top-[32%] h-[5%] w-[30%] rounded-full opacity-60" style={{ background: c }} />
          <div className="absolute bottom-[14%] left-[8%] h-[10%] w-[24%] rounded-[8%]" style={{ background: c }} />
        </>
      )}
      {layout === 2 && (
        <>
          <div className="absolute left-1/2 top-[16%] aspect-square w-[38%] -translate-x-1/2 rounded-full" style={{ background: b }} />
          <div className="absolute bottom-[20%] left-1/2 h-[7%] w-[58%] -translate-x-1/2 rounded-full" style={{ background: c }} />
        </>
      )}
    </div>
  )
}

/**
 * Pratinjau template dalam bingkai seragam (default 4:5) agar grid rapi: rasio asli template
 * ditampilkan utuh di tengah bingkai netral (letterbox), bukan meregang/berbeda tinggi per kartu.
 */
export function TemplatePreview({ item, className, watermark, frame = 0.8, zoom }: { item: TemplateItem; className?: string; watermark?: boolean; frame?: number; zoom?: boolean }) {
  const r = RATIO[item.ratio] ?? 1
  const tallerThanFrame = r < frame
  return (
    <div className={cn('relative grid place-items-center overflow-hidden rounded-lg border border-line bg-surface-2 p-[7%]', className)} style={{ aspectRatio: String(frame) }}>
      <div className={cn('relative overflow-hidden rounded-[4px] shadow-k-sm transition-transform duration-500 ease-[cubic-bezier(.22,1,.36,1)]', zoom && 'scale-[1.04]')} style={{ aspectRatio: String(r), ...(tallerThanFrame ? { height: '100%' } : { width: '100%' }) }}>
        <Art item={item} />
        {watermark && item.is_premium && <span className="absolute inset-0 grid place-items-center text-[10px] font-bold uppercase text-white/50 [transform:rotate(-24deg)]">Krafio Premium</span>}
      </div>
      <span className="absolute bottom-2 right-2 rounded-sm bg-surface/90 px-1.5 text-[11px] font-semibold tabular-nums text-muted">{item.ratio}</span>
    </div>
  )
}

const BADGE_TONE: Record<string, string> = { new: 'bg-info text-white', promo: 'bg-danger text-white', featured: 'bg-primary text-primary-contrast' }

/** Kartu template persis seperti di homepage. Admin memakai komponen yang sama untuk pratinjau langsung. */
export function TemplateCard({
  item, to, onClick, display, previewState = 'default', onField, highlight, footer, className,
}: {
  item: TemplateItem
  to?: string
  onClick?: () => void
  display?: Partial<CardDisplay>
  previewState?: CardPreviewState
  /** Pratinjau editor: klik bagian kartu memfokuskan field form */
  onField?: (f: CardField) => void
  /** Bagian yang baru berubah → kedip halus */
  highlight?: CardField | null
  footer?: ReactNode
  className?: string
}) {
  const { t } = useTranslation()
  const d = { ...DEFAULT_CARD_DISPLAY, ...display }
  const [hover, setHover] = useState(false)
  const hovering = hover || previewState === 'hover'
  const featured = item.is_featured || previewState === 'featured'
  const locked = previewState === 'locked'
  const flash = (f: CardField) => (highlight === f ? 'animate-[k-flash_0.9s_ease]' : '')
  const field = (f: CardField) => (onField ? { role: 'button' as const, tabIndex: 0, onClick: (e: React.MouseEvent) => { e.preventDefault(); e.stopPropagation(); onField(f) }, onKeyDown: (e: React.KeyboardEvent) => e.key === 'Enter' && onField(f), className: 'cursor-text rounded-sm outline-offset-2 hover:outline hover:outline-1 hover:outline-dashed hover:outline-primary/60' } : {})
  const badges = [...(featured ? ['featured'] : []), ...(item.badges ?? [])].slice(0, 3)
  const radius = d.radius === '2xl' ? 'rounded-2xl' : d.radius === 'lg' ? 'rounded-lg' : 'rounded-xl'

  const preview = (
    <div {...field('image')} className={cn('relative', onField && 'cursor-pointer')}>
      <TemplatePreview item={item} watermark={locked || !onField} frame={FRAME[d.imageRatio]} zoom={hovering && d.hoverEffect === 'zoom'} className={cn(flash('image'), d.variant === 'minimal' && 'border-0 bg-transparent')} />
      {d.showBadge && badges.length > 0 && (
        <span {...field('badge')} className={cn('absolute left-2 top-2 flex flex-wrap gap-1', flash('badge'))}>
          {badges.map((b) => (
            <span key={b} className={cn('rounded-full px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide shadow-k-sm', BADGE_TONE[b] ?? 'bg-surface text-fg')}>
              {b === 'featured' ? <Sparkles className="mr-0.5 inline size-3" aria-hidden /> : null}
              {t(`templates.badge.${b}`, { defaultValue: b })}
            </span>
          ))}
        </span>
      )}
      {locked && (
        <span className="absolute inset-0 grid place-items-center rounded-lg bg-black/35 backdrop-blur-[1px]">
          <span className="inline-flex items-center gap-1.5 rounded-full bg-surface px-3 py-1 text-[12px] font-semibold text-fg"><Lock className="size-3.5" aria-hidden />{t('templates.locked_preview')}</span>
        </span>
      )}
      {d.variant === 'overlay' && (
        <span className="absolute inset-x-0 bottom-0 grid gap-0.5 rounded-b-lg bg-gradient-to-t from-black/75 to-transparent p-3 pt-10 text-white">
          <span {...field('title')} className={cn('truncate text-sm font-semibold', flash('title'))}>{item.title}</span>
          {d.showPrice && <span className="text-[12px] font-semibold opacity-90">{item.is_premium ? t('templates.premium') : t('templates.free')}</span>}
        </span>
      )}
    </div>
  )

  const meta = d.variant !== 'overlay' && (
    <div className={cn('grid gap-1 px-0.5', d.variant === 'horizontal' ? 'content-center' : 'mt-2.5')}>
      <p {...field('title')} className={cn('truncate text-sm font-semibold text-fg', flash('title'))}>{item.title}</p>
      {d.variant !== 'minimal' && (
        <p className="t-small flex items-center gap-2 text-muted">
          {d.showPrice &&
            (item.is_premium ? (
              <span {...field('price')} className={cn('inline-flex items-center gap-1 font-semibold text-warning', flash('price'))}>
                <Crown className="size-3.5" aria-hidden />
                {t('templates.premium')}
              </span>
            ) : (
              <span {...field('price')} className={cn('font-semibold text-success', flash('price'))}>{t('templates.free')}</span>
            ))}
          {d.showCreator && <span {...field('type')} className={cn('truncate', flash('type'))}>{t(`templates.types.${item.type}`)}</span>}
        </p>
      )}
      {footer}
    </div>
  )

  const hoverFx = d.hoverEffect === 'lift' ? 'hover:-translate-y-1 hover:shadow-k-md' : d.hoverEffect === 'tilt' ? 'hover:[transform:perspective(800px)_rotateX(3deg)_rotateY(-3deg)]' : ''
  const cls = cn(
    'block h-full border border-transparent p-2 transition-[background-color,border-color,transform,box-shadow] duration-200 hover:border-line hover:bg-surface',
    radius,
    hoverFx,
    previewState === 'hover' && 'border-line bg-surface',
    previewState === 'hover' && d.hoverEffect === 'lift' && '-translate-y-1 shadow-k-md',
    d.variant === 'horizontal' && 'grid grid-cols-[42%_minmax(0,1fr)] gap-3',
    className,
  )
  const inner = (
    <>
      {preview}
      {meta}
    </>
  )
  const events = { onMouseEnter: () => setHover(true), onMouseLeave: () => setHover(false) }
  if (to)
    return (
      <Link to={to} className={cls} {...events}>
        {inner}
      </Link>
    )
  if (onField) return <div className={cls} {...events}>{inner}</div>
  return (
    <button type="button" onClick={onClick} className={cn(cls, 'w-full text-left')} {...events}>
      {inner}
    </button>
  )
}
