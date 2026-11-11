import { Children, useCallback, useEffect, useRef, useState, type ReactNode } from 'react'
import { ChevronLeft, ChevronRight, Pause, Play } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/shared/lib/cn'
import { useReducedMotion } from '@/shared/hooks/useMediaQuery'

// Carousel (Design Spec §14.2): swipe/drag + scroll-snap, panah (hover desktop), titik/penghitung, keyboard ← →,
// peek 1,15–1,3 di mobile, autoplay hanya bila diminta dengan tombol Pause/Play, berhenti saat hover/fokus/tab tersembunyi.
export function Carousel({
  children,
  label,
  itemClass = 'basis-[82%] sm:basis-[46%] lg:basis-[31.5%]',
  autoplay,
  interval = 6000,
  loop,
  dots = true,
  className,
  gap = 'gap-3 md:gap-4',
  variant = 'cards',
}: {
  children: ReactNode
  label: string
  itemClass?: string
  autoplay?: boolean
  interval?: number
  loop?: boolean
  dots?: boolean
  className?: string
  gap?: string
  variant?: 'cards' | 'hero' | 'strip'
}) {
  const { t } = useTranslation()
  const track = useRef<HTMLDivElement>(null)
  const items = Children.toArray(children)
  const [index, setIndex] = useState(0)
  const [playing, setPlaying] = useState(!!autoplay)
  const [hover, setHover] = useState(false)
  const reduced = useReducedMotion()
  const drag = useRef<{ x: number; left: number; moved: boolean } | null>(null)

  const goTo = useCallback(
    (i: number) => {
      const el = track.current
      if (!el) return
      const n = items.length
      const target = loop ? (i + n) % n : Math.max(0, Math.min(n - 1, i))
      const child = el.children[target] as HTMLElement | undefined
      if (child) el.scrollTo({ left: child.offsetLeft - el.offsetLeft, behavior: reduced ? 'auto' : 'smooth' })
    },
    [items.length, loop, reduced],
  )

  useEffect(() => {
    const el = track.current
    if (!el) return
    const onScroll = () => {
      const kids = Array.from(el.children) as HTMLElement[]
      let best = 0
      let dist = Infinity
      kids.forEach((k, i) => {
        const d = Math.abs(k.offsetLeft - el.offsetLeft - el.scrollLeft)
        if (d < dist) {
          dist = d
          best = i
        }
      })
      setIndex(best)
    }
    el.addEventListener('scroll', onScroll, { passive: true })
    return () => el.removeEventListener('scroll', onScroll)
  }, [])

  useEffect(() => {
    if (!autoplay || !playing || hover || reduced) return
    const id = window.setInterval(() => {
      if (!document.hidden) goTo(index + 1)
    }, interval)
    return () => window.clearInterval(id)
  }, [autoplay, playing, hover, reduced, index, interval, goTo])

  const showDots = dots && items.length > 1 && variant !== 'strip'
  return (
    <div
      role="region"
      aria-roledescription="carousel"
      aria-label={label}
      className={cn('group relative', className)}
      onMouseEnter={() => setHover(true)}
      onMouseLeave={() => setHover(false)}
      onFocus={() => setHover(true)}
      onBlur={() => setHover(false)}
      onKeyDown={(e) => {
        if (e.key === 'ArrowRight') {
          e.preventDefault()
          goTo(index + 1)
        } else if (e.key === 'ArrowLeft') {
          e.preventDefault()
          goTo(index - 1)
        }
      }}
    >
      <div
        ref={track}
        className={cn('snap-row -my-3.5 py-3.5', gap, 'cursor-grab active:cursor-grabbing')}
        aria-live={playing ? 'off' : 'polite'}
        onPointerDown={(e) => {
          if (e.pointerType !== 'mouse' || !track.current) return
          drag.current = { x: e.clientX, left: track.current.scrollLeft, moved: false }
        }}
        onPointerMove={(e) => {
          if (!drag.current || !track.current) return
          const dx = e.clientX - drag.current.x
          if (Math.abs(dx) > 8) drag.current.moved = true
          track.current.style.scrollSnapType = 'none'
          track.current.scrollLeft = drag.current.left - dx
        }}
        onPointerUp={() => {
          if (!track.current) return
          track.current.style.scrollSnapType = ''
          const moved = drag.current?.moved
          drag.current = null
          if (moved) goTo(index)
        }}
        onPointerLeave={() => {
          if (drag.current && track.current) {
            track.current.style.scrollSnapType = ''
            drag.current = null
          }
        }}
        onClickCapture={(e) => {
          // Klik setelah drag tidak memicu tautan
          if ((e.target as HTMLElement).closest('a,button') && drag.current?.moved) e.preventDefault()
        }}
      >
        {items.map((child, i) => (
          <div key={i} role="group" aria-roledescription="slide" aria-label={t('carousel.slide_of', { n: i + 1, total: items.length })} className={cn(itemClass, 'min-w-0')}>
            {child}
          </div>
        ))}
      </div>

      {items.length > 1 && variant !== 'strip' && (
        <>
          <button type="button" onClick={() => goTo(index - 1)} aria-label={t('carousel.prev')} disabled={!loop && index === 0} className="absolute left-2 top-1/2 hidden size-10 -translate-y-1/2 place-items-center rounded-full border border-line bg-surface text-fg opacity-0 shadow-k-md transition-opacity duration-[120ms] group-hover:opacity-100 focus-visible:opacity-100 disabled:hidden [@media(hover:hover)]:grid">
            <ChevronLeft className="size-5" aria-hidden />
          </button>
          <button type="button" onClick={() => goTo(index + 1)} aria-label={t('carousel.next')} disabled={!loop && index >= items.length - 1} className="absolute right-2 top-1/2 hidden size-10 -translate-y-1/2 place-items-center rounded-full border border-line bg-surface text-fg opacity-0 shadow-k-md transition-opacity duration-[120ms] group-hover:opacity-100 focus-visible:opacity-100 disabled:hidden [@media(hover:hover)]:grid">
            <ChevronRight className="size-5" aria-hidden />
          </button>
        </>
      )}

      {(showDots || (autoplay && !reduced)) && (
        <div className="mt-3 flex items-center justify-center gap-3">
          {showDots &&
            (items.length <= 7 ? (
              <div className="flex items-center gap-1.5">
                {items.map((_, i) => (
                  <button key={i} type="button" onClick={() => goTo(i)} aria-label={t('carousel.go_to', { n: i + 1 })} aria-current={i === index} className="grid h-6 place-items-center px-0.5">
                    <span className={cn('block h-2 rounded-full transition-[background-color] duration-[300ms]', i === index ? 'w-6 bg-primary' : 'w-2 bg-line')} />
                  </button>
                ))}
              </div>
            ) : (
              <span className="t-small tabular-nums text-muted">
                {index + 1} / {items.length}
              </span>
            ))}
          {autoplay && !reduced && (
            <button type="button" onClick={() => setPlaying((p) => !p)} className="t-label inline-flex h-8 items-center gap-1.5 rounded-full border border-line px-3 text-muted hover:text-fg" aria-label={playing ? t('carousel.pause') : t('carousel.play')}>
              {playing ? <Pause className="size-3.5" aria-hidden /> : <Play className="size-3.5" aria-hidden />}
              {playing ? t('carousel.pause') : t('carousel.play')}
            </button>
          )}
        </div>
      )}
    </div>
  )
}
