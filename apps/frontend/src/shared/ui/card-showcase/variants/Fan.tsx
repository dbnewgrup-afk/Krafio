import { motion } from 'framer-motion'
import { CardFace, radiusCls, skinForTone } from '../CardFace'
import type { VariantProps } from '../types'
import { cn } from '@/shared/lib/cn'

/** "fan": kartu menyebar seperti kipas; kartu aktif di depan dan tegak, klik kartu lain untuk memilih. */
export default function Fan(p: VariantProps) {
  const { cards, state, config } = p
  const n = cards.length
  return (
    <div className="relative mx-auto h-[min(360px,80vw)] w-full max-w-[460px] overflow-hidden [container-type:inline-size]">
      {cards.map((c, i) => {
        const rel = i - state.index
        const active = rel === 0
        const angle = active ? 0 : (rel / Math.max(1, n - 1)) * 28 + (rel > 0 ? 6 : -6)
        return (
          <motion.button
            key={c.key}
            type="button"
            aria-label={c.label}
            aria-pressed={active}
            onClick={() => (active ? p.flip() : p.select(i, rel > 0 ? 1 : -1))}
            className={cn('absolute bottom-[22%] left-1/2 w-[60%] origin-[50%_120%] outline-none focus-visible:ring-2 focus-visible:ring-primary', radiusCls(config.radius))}
            style={{ zIndex: active ? n + 1 : n - Math.abs(rel), x: '-50%' }}
            initial={false}
            animate={{ rotate: angle, y: active ? -14 : 0, scale: active ? 1 : 0.94 }}
            whileHover={!active ? { y: -10 } : undefined}
            transition={{ type: 'spring', stiffness: 200, damping: 20 }}
          >
            <div className={cn(radiusCls(config.radius), 'shadow-[0_18px_36px_-14px_rgb(15_23_42/.45)]')}>
              <CardFace card={c} skin={active ? skinForTone(c.tone) : 'ink'} config={config} state={active ? state : { ...state, flipped: false, revealed: false }} money={p.money} labels={p.labels} flipped={active && state.flipped} />
            </div>
          </motion.button>
        )
      })}
    </div>
  )
}
