import { useEffect, useState, type ReactNode } from 'react'
import { motion, useReducedMotion, type HTMLMotionProps } from 'framer-motion'
import { useIntro, TIMELINE } from './intro'
import { Skeleton } from '@/shared/ui/States'
import { cn } from '@/shared/lib/cn'

/**
 * Kartu dashboard masuk berurutan. `order` menentukan giliran (0 = kartu utama).
 * Saat intro login: placeholder skeleton tampil dulu → berubah jadi kartu asli (scale 0.96→1)
 * → isi kartu fade-in. Tanpa intro: fade + naik halus singkat.
 */
export function RevealCard({ order = 0, children, className, skeletonClass, as = 'div' }: { order?: number; children: ReactNode; className?: string; skeletonClass?: string; as?: 'div' | 'section' | 'li' }) {
  const reduced = useReducedMotion()
  const { intro } = useIntro()
  const delay = intro ? TIMELINE.cards + order * TIMELINE.cardStagger : Math.min(order, 8) * 0.04
  const [ready, setReady] = useState(!intro || !!reduced)
  useEffect(() => {
    if (ready) return
    const id = window.setTimeout(() => setReady(true), delay * 1000)
    return () => window.clearTimeout(id)
  }, [ready, delay])

  if (reduced) {
    const Tag = as
    return <Tag className={className}>{children}</Tag>
  }
  const M = as === 'section' ? motion.section : as === 'li' ? motion.li : motion.div
  if (!ready) return <Skeleton className={cn('min-h-28 rounded-lg', skeletonClass, className)} />
  return (
    <M
      className={className}
      initial={intro ? { opacity: 0, scale: 0.96 } : { opacity: 0, y: 12 }}
      animate={intro ? { opacity: 1, scale: 1 } : { opacity: 1, y: 0 }}
      transition={{ duration: intro ? 0.4 : 0.35, ease: TIMELINE.easeOut, delay: intro ? 0 : delay }}
    >
      <motion.div className="contents" initial={intro ? { opacity: 0 } : false} animate={{ opacity: 1 }} transition={{ duration: 0.2, delay: intro ? 0.25 : 0 }}>
        {children}
      </motion.div>
    </M>
  )
}

/** Daftar/tabel: baris muncul berurutan (opacity + translateY 6px). */
export function StaggerList({ children, className, as = 'ul', step = 0.05, delay = 0 }: { children: ReactNode; className?: string; as?: 'ul' | 'ol' | 'div'; step?: number; delay?: number }) {
  const reduced = useReducedMotion()
  const { base } = useIntro()
  const M = as === 'ol' ? motion.ol : as === 'div' ? motion.div : motion.ul
  if (reduced) {
    const Tag = as
    return <Tag className={className}>{children}</Tag>
  }
  return (
    <M className={className} initial="hidden" animate="show" variants={{ hidden: {}, show: { transition: { staggerChildren: step, delayChildren: base + delay } } }}>
      {children}
    </M>
  )
}

export const staggerItem = {
  hidden: { opacity: 0, y: 6 },
  show: { opacity: 1, y: 0, transition: { duration: 0.3, ease: TIMELINE.easeOut } },
}

export function StaggerItem({ children, className, as = 'li', ...rest }: { children: ReactNode; className?: string; as?: 'li' | 'div' } & Omit<HTMLMotionProps<'li'>, 'children' | 'className'>) {
  const M = as === 'div' ? motion.div : motion.li
  return (
    <M className={className} variants={staggerItem} {...(rest as object)}>
      {children}
    </M>
  )
}

/** Bar chart: tiap bar tumbuh dari bawah (scaleY) dengan spring ringan, stagger kiri → kanan. */
export function GrowBar({ index, pct, className, children }: { index: number; pct: number; className?: string; children?: ReactNode }) {
  const reduced = useReducedMotion()
  const { intro } = useIntro()
  return (
    <motion.div
      className={className}
      style={{ height: `${pct}%`, transformOrigin: 'bottom' }}
      initial={reduced ? false : { scaleY: 0 }}
      animate={{ scaleY: 1 }}
      transition={{ type: 'spring', stiffness: 120, damping: 14, delay: (intro ? TIMELINE.cards + 0.2 : 0.1) + index * 0.06 }}
    >
      {children}
    </motion.div>
  )
}

/** Elemen bulat/orb: naik dari bawah lalu melayang halus tanpa henti. */
export function FloatOrb({ children, className, delay = 0 }: { children: ReactNode; className?: string; delay?: number }) {
  const reduced = useReducedMotion()
  const { base } = useIntro()
  if (reduced) return <div className={className}>{children}</div>
  return (
    <motion.div className={className} initial={{ y: 40, scale: 0.8, opacity: 0 }} animate={{ y: 0, scale: 1, opacity: 1 }} transition={{ duration: 0.6, ease: TIMELINE.easeOut, delay: base + delay }}>
      <motion.div animate={{ y: [0, -6, 0] }} transition={{ duration: 3, ease: 'easeInOut', repeat: Infinity, delay: base + delay + 0.6 }}>
        {children}
      </motion.div>
    </motion.div>
  )
}
