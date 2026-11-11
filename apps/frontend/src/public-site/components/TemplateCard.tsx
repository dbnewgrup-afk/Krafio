import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Crown } from 'lucide-react'
import type { TemplateItem } from '@/api-client/types'
import { cn } from '@/shared/lib/cn'
import { img } from '@/shared/lib/assets'

const RATIO: Record<string, number> = { '1:1': 1, '4:5': 0.8, '9:16': 0.5625, '16:9': 1.7778, '4:3': 1.3333 }

/** Isi visual template (konten milik template, tidak ikut tema — §4.3): gambar pratinjau bila tersedia, bentuk palet bila tidak. */
function Art({ item }: { item: TemplateItem }) {
  const [broken, setBroken] = useState(false)
  const [a, b, c] = item.palette.length >= 3 ? item.palette : ['#E2E8F0', '#94A3B8', '#0F172A']
  const layout = item.id % 3
  if (item.art && !broken) return <img src={img(`templates/${item.art}.svg`)} alt="" draggable={false} loading="lazy" onError={() => setBroken(true)} className="h-full w-full object-cover" />
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
export function TemplatePreview({ item, className, watermark, frame = 0.8 }: { item: TemplateItem; className?: string; watermark?: boolean; frame?: number }) {
  const r = RATIO[item.ratio] ?? 1
  const tallerThanFrame = r < frame
  return (
    <div className={cn('relative grid place-items-center overflow-hidden rounded-lg border border-line bg-surface-2 p-[7%]', className)} style={{ aspectRatio: String(frame) }}>
      <div className="relative overflow-hidden rounded-[4px] shadow-k-sm" style={{ aspectRatio: String(r), ...(tallerThanFrame ? { height: '100%' } : { width: '100%' }) }}>
        <Art item={item} />
        {watermark && item.is_premium && <span className="absolute inset-0 grid place-items-center text-[10px] font-bold uppercase text-white/50 [transform:rotate(-24deg)]">Krafio Premium</span>}
      </div>
      <span className="absolute bottom-2 right-2 rounded-sm bg-surface/90 px-1.5 text-[11px] font-semibold tabular-nums text-muted">{item.ratio}</span>
    </div>
  )
}

export function TemplateCard({ item, to, onClick }: { item: TemplateItem; to?: string; onClick?: () => void }) {
  const { t } = useTranslation()
  const inner = (
    <>
      <TemplatePreview item={item} watermark />
      <div className="mt-2.5 grid gap-1 px-0.5">
        <p className="truncate text-sm font-semibold text-fg">{item.title}</p>
        <p className="t-small flex items-center gap-2 text-muted">
          {item.is_premium ? (
            <span className="inline-flex items-center gap-1 font-semibold text-warning">
              <Crown className="size-3.5" aria-hidden />
              {t('templates.premium')}
            </span>
          ) : (
            <span className="font-semibold text-success">{t('templates.free')}</span>
          )}
          <span className="truncate">{t(`templates.types.${item.type}`)}</span>
        </p>
      </div>
    </>
  )
  const cls = 'block h-full rounded-xl border border-transparent p-2 transition-[background-color,border-color] duration-[120ms] hover:border-line hover:bg-surface'
  if (to)
    return (
      <Link to={to} className={cls}>
        {inner}
      </Link>
    )
  return (
    <button type="button" onClick={onClick} className={cn(cls, 'w-full text-left')}>
      {inner}
    </button>
  )
}
