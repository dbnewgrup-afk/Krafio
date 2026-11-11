import { AnimatePresence, motion } from 'framer-motion'
import { TiltCard } from '../TiltCard'
import { skinForTone } from '../CardFace'
import type { VariantProps } from '../types'

/** "flat": kartu warna-nada (top-up biru, komisi hijau) tanpa efek kaca — paling ringan. */
export default function Flat(p: VariantProps) {
  const card = p.cards[p.state.index]
  return (
    <div className="relative mx-auto w-full max-w-[380px] py-4">
      <AnimatePresence mode="popLayout" initial={false}>
        <motion.div key={card.key} initial={{ x: 60 * p.direction, opacity: 0 }} animate={{ x: 0, opacity: 1 }} exit={{ opacity: 0, scale: 0.92 }} transition={{ type: 'spring', stiffness: 240, damping: 26 }}>
          <TiltCard card={card} skin={skinForTone(card.tone)} p={{ ...p, config: { ...p.config, glare: false } }} />
        </motion.div>
      </AnimatePresence>
    </div>
  )
}
