import { useCallback, useEffect, useRef, useState, type KeyboardEvent, type PointerEvent as RPointerEvent } from 'react'
import { createPortal } from 'react-dom'
import { Link } from 'react-router-dom'
import { AnimatePresence, motion, useMotionTemplate, useMotionValue, useReducedMotion, useSpring, useTransform } from 'framer-motion'
import { useTranslation } from 'react-i18next'
import { ArrowRight, ChevronLeft, ChevronRight, ImageIcon, Pause, Play, X } from 'lucide-react'
import { cn } from '@/shared/lib/cn'
import { CountUp } from '@/shared/motion/CountUp'
import { useIntro } from '@/shared/motion/intro'
import { useBackClose, useFocusTrap, useScrollLock } from '@/shared/hooks/useOverlay'

export interface HeroSlide {
  id: string
  enabled?: boolean
  badge?: string | null
  title: string
  body?: string | null
  cta_label?: string | null
  cta_url?: string | null
  image?: string | null
  alt?: string | null
}

export interface HeroConfig {
  imageEffect: 'none' | 'zoom' | 'parallax' | 'tilt'
  autoPlay: boolean
  interval: number
  float: boolean
  showDots: boolean
  showArrows: boolean
  lightbox: boolean
  /** 0 = matikan animasi */
  animationSpeed: number
}

export const DEFAULT_HERO_CONFIG: HeroConfig = { imageEffect: 'parallax', autoPlay: true, interval: 6000, float: true, showDots: true, showArrows: false, lightbox: true, animationSpeed: 1 }

const EASE = [0.22, 1, 0.36, 1] as const

/** Badge berisi angka (mis. "24 template") → angkanya count-up saat muncul. */
function BadgeText({ text }: { text: string }) {
  const m = text.match(/^(\D*)(\d[\d.]*)(.*)$/)
  if (!m) return <>{text}</>
  const n = Number(m[2].replace(/\./g, ''))
  return (
    <>
      {m[1]}
      <CountUp to={n} duration={1} />
      {m[3]}
    </>
  )
}

/**
 * HeroShowcase — area sorotan di tengah dashboard: gambar besar + teks + CTA, carousel dengan autoplay,
 * parallax/tilt/zoom mengikuti kursor, kilau cahaya, lightbox dengan transisi dari posisi gambar.
 */
export function HeroShowcase({ slides: all, config: cfg, className, editingIndex, label }: { slides: HeroSlide[]; config?: Partial<HeroConfig>; className?: string; editingIndex?: number; label?: string }) {
  const { t } = useTranslation()
  const reducedPref = useReducedMotion()
  const { base } = useIntro()
  const config: HeroConfig = { ...DEFAULT_HERO_CONFIG, ...cfg }
  const reduced = !!reducedPref || config.animationSpeed === 0
  const speed = config.animationSpeed || 1
  const slides = all.filter((s) => s.enabled !== false)
  const [index, setIndex] = useState(0)
  const [dir, setDir] = useState<1 | -1>(1)
  const [hover, setHover] = useState(false)
  const [paused, setPaused] = useState(false)
  const [box, setBox] = useState<HeroSlide | null>(null)
  const n = slides.length
  const cur = slides[Math.min(index, Math.max(0, n - 1))]

  // Editor: ikuti slide yang sedang diedit
  useEffect(() => {
    if (editingIndex == null) return
    const id = all[editingIndex]?.id
    const i = slides.findIndex((s) => s.id === id)
    if (i >= 0) setIndex(i)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [editingIndex])
  useEffect(() => {
    if (index >= n && n > 0) setIndex(0)
  }, [n, index])

  const go = useCallback((i: number, d: 1 | -1) => {
    if (!n) return
    setDir(d)
    setIndex(((i % n) + n) % n)
  }, [n])

  // Autoplay 6 dtk, berhenti saat hover / fokus / tab tersembunyi / reduced-motion
  useEffect(() => {
    if (!config.autoPlay || reduced || hover || paused || n < 2 || box) return
    const id = window.setInterval(() => {
      if (!document.hidden) go(index + 1, 1)
    }, Math.max(3000, config.interval))
    return () => window.clearInterval(id)
  }, [config.autoPlay, config.interval, reduced, hover, paused, n, index, go, box])

  // Pointer: parallax / tilt / glare
  const px = useMotionValue(0)
  const py = useMotionValue(0)
  const sp = { stiffness: 120, damping: 18, mass: 0.5 }
  const sx = useSpring(px, sp)
  const sy = useSpring(py, sp)
  const imgX = useTransform(sx, (v) => (config.imageEffect === 'parallax' ? v * 18 : 0))
  const imgY = useTransform(sy, (v) => (config.imageEffect === 'parallax' ? v * 14 : 0))
  const txtX = useTransform(sx, (v) => (config.imageEffect === 'parallax' ? v * -8 : 0))
  const txtY = useTransform(sy, (v) => (config.imageEffect === 'parallax' ? v * -6 : 0))
  const rotX = useTransform(sy, (v) => (config.imageEffect === 'tilt' || config.imageEffect === 'parallax' ? v * -4 : 0))
  const rotY = useTransform(sx, (v) => (config.imageEffect === 'tilt' || config.imageEffect === 'parallax' ? v * 5 : 0))
  const gx = useTransform(sx, [-1, 1], ['0%', '100%'])
  const gy = useTransform(sy, [-1, 1], ['0%', '100%'])
  const glare = useMotionTemplate`radial-gradient(circle at ${gx} ${gy}, rgba(255,255,255,.22), transparent 50%)`
  const onMove = (e: RPointerEvent<HTMLElement>) => {
    if (reduced || e.pointerType === 'touch') return
    const r = e.currentTarget.getBoundingClientRect()
    px.set(((e.clientX - r.left) / r.width) * 2 - 1)
    py.set(((e.clientY - r.top) / r.height) * 2 - 1)
  }
  const onLeave = () => {
    px.set(0)
    py.set(0)
    setHover(false)
  }

  const onKey = (e: KeyboardEvent) => {
    if (e.key === 'ArrowRight') { e.preventDefault(); go(index + 1, 1) }
    if (e.key === 'ArrowLeft') { e.preventDefault(); go(index - 1, -1) }
  }

  if (!cur) return null
  const animDur = (d: number) => (reduced ? 0 : d / speed)
  const stagger = (i: number) => (reduced ? 0 : (0.08 * i) / speed)

  return (
    <section
      className={cn('relative', className)}
      role="region"
      aria-roledescription="carousel"
      aria-label={label ?? t('hero.label')}
      onKeyDown={onKey}
      onFocus={() => setHover(true)}
      onBlur={(e) => !e.currentTarget.contains(e.relatedTarget as Node) && setHover(false)}
    >
      <motion.div
        className="relative overflow-hidden rounded-2xl border border-line bg-surface shadow-k-md [perspective:1200px]"
        initial={reduced ? false : { opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: animDur(0.5), ease: EASE, delay: base }}
        onPointerMove={onMove}
        onPointerEnter={() => setHover(true)}
        onPointerLeave={onLeave}
      >
        <motion.div style={reduced ? undefined : { rotateX: rotX, rotateY: rotY, transformStyle: 'preserve-3d' }} className="relative">
          {/* Latar bertinta lembut */}
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(90%_120%_at_100%_0%,var(--primary-soft),transparent_60%)]" aria-hidden />
          <AnimatePresence mode="wait" initial={false} custom={dir}>
            <motion.div
              key={cur.id}
              custom={dir}
              className="relative grid items-center gap-5 p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] md:gap-8 md:p-7 lg:p-8"
              initial={reduced ? false : { opacity: 0, x: 24 * dir }}
              animate={{ opacity: 1, x: 0 }}
              exit={reduced ? { opacity: 0 } : { opacity: 0, x: -24 * dir }}
              transition={{ duration: animDur(0.4), ease: EASE }}
              drag={n > 1 && !reduced ? 'x' : false}
              dragConstraints={{ left: 0, right: 0 }}
              dragElastic={0.2}
              onDragEnd={(_, info) => {
                if (info.offset.x < -70) go(index + 1, 1)
                else if (info.offset.x > 70) go(index - 1, -1)
              }}
            >
              {/* Teks — mobile di bawah gambar */}
              <motion.div className="order-2 grid content-center gap-3 md:order-1" style={reduced ? undefined : { x: txtX, y: txtY }}>
                {cur.badge && (
                  <motion.span className="t-label inline-flex w-fit items-center gap-1.5 rounded-full bg-primary-soft px-2.5 py-1 text-primary" initial={reduced ? false : { opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: stagger(1), duration: animDur(0.35), ease: EASE }}>
                    <BadgeText text={cur.badge} />
                  </motion.span>
                )}
                <motion.h2 className="t-h1 text-fg" initial={reduced ? false : { opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: stagger(2), duration: animDur(0.4), ease: EASE }}>
                  {cur.title}
                </motion.h2>
                {cur.body && (
                  <motion.p className="t-body max-w-[52ch] text-muted" initial={reduced ? false : { opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: stagger(3), duration: animDur(0.4), ease: EASE }}>
                    {cur.body}
                  </motion.p>
                )}
                {cur.cta_label && cur.cta_url && (
                  <motion.div initial={reduced ? false : { opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: stagger(4), duration: animDur(0.4), ease: EASE }} className="pt-1">
                    <CtaLink to={cur.cta_url} label={cur.cta_label} reduced={reduced} />
                  </motion.div>
                )}
              </motion.div>

              {/* Gambar — fokus visual */}
              <motion.div className="relative order-1 md:order-2" initial={reduced ? false : { opacity: 0, scale: 0.94, clipPath: 'inset(8% 8% 8% 8% round 18px)' }} animate={{ opacity: 1, scale: 1, clipPath: 'inset(0% 0% 0% 0% round 18px)' }} transition={{ duration: animDur(0.55), ease: EASE, delay: stagger(1) }}>
                <motion.div animate={config.float && !reduced ? { y: [0, -6, 0] } : undefined} transition={config.float && !reduced ? { duration: 5 / speed, repeat: Infinity, ease: 'easeInOut' } : undefined}>
                  {cur.image ? (
                    <motion.button
                      type="button"
                      layoutId={config.lightbox && !reduced ? `hero-img-${cur.id}` : undefined}
                      onClick={() => config.lightbox && setBox(cur)}
                      aria-label={config.lightbox ? t('hero.open_image', { title: cur.title }) : cur.alt ?? cur.title}
                      className={cn('group relative block aspect-[4/3] w-full overflow-hidden rounded-[18px] bg-surface-2', config.lightbox ? 'cursor-zoom-in' : 'cursor-default')}
                    >
                      <motion.img
                        src={cur.image}
                        alt={cur.alt ?? ''}
                        loading="lazy"
                        decoding="async"
                        draggable={false}
                        className="absolute inset-0 h-full w-full object-cover"
                        style={reduced ? undefined : { x: imgX, y: imgY }}
                        animate={{ scale: hover && !reduced && config.imageEffect !== 'none' ? 1.06 : 1 }}
                        transition={{ duration: 0.6, ease: EASE }}
                      />
                      {!reduced && <motion.span className="pointer-events-none absolute inset-0" style={{ background: glare }} aria-hidden />}
                    </motion.button>
                  ) : (
                    <div className="grid aspect-[4/3] w-full place-items-center rounded-[18px] border border-dashed border-line-strong bg-surface-2 text-muted">
                      <span className="grid justify-items-center gap-2">
                        <ImageIcon className="size-8" aria-hidden />
                        <span className="t-small">{t('hero.no_image')}</span>
                      </span>
                    </div>
                  )}
                </motion.div>
              </motion.div>
            </motion.div>
          </AnimatePresence>
        </motion.div>

        {/* Kontrol: titik, panah, jeda */}
        {n > 1 && (
          <div className="flex items-center justify-between gap-2 border-t border-line px-4 py-2.5 md:px-7">
            <div className="flex items-center gap-1.5" role="tablist" aria-label={t('hero.slides')}>
              {config.showDots &&
                slides.map((s, i) => (
                  <button key={s.id} type="button" role="tab" aria-selected={i === index} aria-label={t('hero.go_to', { n: i + 1, total: n })} onClick={() => go(i, i > index ? 1 : -1)} className="grid h-6 place-items-center px-0.5">
                    <motion.span className={cn('block h-1.5 rounded-full', i === index ? 'bg-primary' : 'bg-line-strong')} animate={{ width: i === index ? 22 : 8 }} transition={{ duration: 0.3 }} />
                  </button>
                ))}
              <span className="t-small ml-2 tabular-nums text-muted">
                {index + 1} / {n}
              </span>
            </div>
            <div className="flex items-center gap-1">
              {config.autoPlay && !reduced && (
                <button type="button" onClick={() => setPaused((p) => !p)} aria-label={paused ? t('carousel.play') : t('carousel.pause')} className="grid size-9 place-items-center rounded-full text-muted hover:bg-surface-2 hover:text-fg">
                  {paused ? <Play className="size-4" aria-hidden /> : <Pause className="size-4" aria-hidden />}
                </button>
              )}
              {(
                <>
                  <button type="button" onClick={() => go(index - 1, -1)} aria-label={t('carousel.prev')} className={cn('size-9 place-items-center rounded-full border border-line text-fg hover:bg-surface-2', config.showArrows ? 'grid' : 'hidden md:grid')}>
                    <ChevronLeft className="size-4" aria-hidden />
                  </button>
                  <button type="button" onClick={() => go(index + 1, 1)} aria-label={t('carousel.next')} className={cn('size-9 place-items-center rounded-full border border-line text-fg hover:bg-surface-2', config.showArrows ? 'grid' : 'hidden md:grid')}>
                    <ChevronRight className="size-4" aria-hidden />
                  </button>
                </>
              )}
            </div>
          </div>
        )}
      </motion.div>

      <Lightbox slide={box} onClose={() => setBox(null)} reduced={reduced} />
    </section>
  )
}

function CtaLink({ to, label, reduced }: { to: string; label: string; reduced: boolean }) {
  const external = /^https?:\/\//.test(to)
  const inner = (
    <motion.span className="inline-flex h-11 items-center gap-2 rounded-md bg-primary px-4 text-[15px] font-semibold text-primary-contrast lg:h-10 lg:text-sm" whileHover={reduced ? undefined : 'hover'} whileTap={reduced ? undefined : { scale: 0.97 }}>
      {label}
      <motion.span variants={{ hover: { x: 4 } }} transition={{ type: 'spring', stiffness: 400, damping: 20 }}>
        <ArrowRight className="size-4" aria-hidden />
      </motion.span>
    </motion.span>
  )
  return external ? (
    <a href={to} target="_blank" rel="noopener noreferrer" className="inline-block rounded-md">{inner}</a>
  ) : (
    <Link to={to} className="inline-block rounded-md">{inner}</Link>
  )
}

function Lightbox({ slide, onClose, reduced }: { slide: HeroSlide | null; onClose: () => void; reduced: boolean }) {
  const { t } = useTranslation()
  const ref = useRef<HTMLDivElement>(null)
  const open = !!slide
  useScrollLock(open)
  useFocusTrap(ref, open)
  useBackClose(open, onClose)
  useEffect(() => {
    if (!open) return
    const k = (e: globalThis.KeyboardEvent) => e.key === 'Escape' && onClose()
    window.addEventListener('keydown', k)
    return () => window.removeEventListener('keydown', k)
  }, [open, onClose])
  return createPortal(
    <AnimatePresence>
      {slide && (
        <motion.div ref={ref} key="lb" className="fixed inset-0 z-modal grid place-items-center bg-[var(--overlay-dark)] p-4 md:p-10" role="dialog" aria-modal="true" aria-label={slide.title} initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} onClick={onClose}>
          <motion.img layoutId={reduced ? undefined : `hero-img-${slide.id}`} src={slide.image ?? ''} alt={slide.alt ?? slide.title} className="max-h-[86dvh] max-w-full rounded-2xl bg-surface object-contain shadow-k-lg" onClick={(e) => e.stopPropagation()} transition={{ type: 'spring', stiffness: 220, damping: 28 }} />
          <button type="button" onClick={onClose} aria-label={t('common.close')} className="absolute right-4 top-4 grid size-11 place-items-center rounded-full bg-surface text-fg shadow-k-md">
            <X className="size-5" aria-hidden />
          </button>
          <p className="absolute inset-x-0 bottom-5 text-center text-sm font-medium text-white">{slide.title}</p>
        </motion.div>
      )}
    </AnimatePresence>,
    document.body,
  )
}
