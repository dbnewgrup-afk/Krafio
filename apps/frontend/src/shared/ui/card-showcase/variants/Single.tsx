import { AnimatePresence, motion } from 'framer-motion'
import { TiltCard } from '../TiltCard'
import type { VariantProps } from '../types'

/** "single": satu kartu datar menghadap depan; kartu lama keluar (fade + scale-down), yang baru masuk dari samping. */
export default function Single(p: VariantProps) {
  const card = p.cards[p.state.index]
  const s = p.config.animationSpeed || 1
  return (
    <div className="relative mx-auto w-full max-w-[380px] py-4">
      <AnimatePresence mode="popLayout" initial={false} custom={p.direction}>
        <motion.div
          key={card.key}
          custom={p.direction}
          initial={{ x: 70 * p.direction, opacity: 0, scale: 0.96 }}
          animate={{ x: 0, opacity: 1, scale: 1 }}
          exit={{ opacity: 0, scale: 0.9, x: -30 * p.direction }}
          transition={{ type: 'spring', stiffness: 220, damping: 24, duration: 0.45 / s }}
          drag={p.config.interactive ? 'x' : false}
          dragConstraints={{ left: 0, right: 0 }}
          dragElastic={0.25}
          onDragEnd={(_, info) => {
            if (info.offset.x < -60) p.select(p.state.index + 1, 1)
            else if (info.offset.x > 60) p.select(p.state.index - 1, -1)
          }}
        >
          <TiltCard card={card} skin="ink" p={p} />
        </motion.div>
      </AnimatePresence>
    </div>
  )
}
