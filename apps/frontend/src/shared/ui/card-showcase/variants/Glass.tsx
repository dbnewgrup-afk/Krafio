import { AnimatePresence, motion } from 'framer-motion'
import { TiltCard } from '../TiltCard'
import type { VariantProps } from '../types'

/** "glass": kartu kaca buram di atas dua bidang warna — warna nada kartu muncul di belakang kaca. */
export default function Glass(p: VariantProps) {
  const card = p.cards[p.state.index]
  const hue = card.tone === 'commission' ? 'bg-wallet-commission' : card.tone === 'warning' ? 'bg-warning' : 'bg-primary'
  return (
    <div className="relative mx-auto w-full max-w-[380px] py-4">
      <div className="pointer-events-none absolute inset-0" aria-hidden>
        <motion.div className={`absolute left-[6%] top-[8%] size-32 rounded-full ${hue} opacity-60 blur-2xl`} animate={{ scale: [1, 1.08, 1] }} transition={{ duration: 6, repeat: Infinity, ease: 'easeInOut' }} />
        <div className="absolute bottom-[4%] right-[8%] size-28 rounded-full bg-[var(--preview-1)] opacity-70 blur-2xl" />
      </div>
      <AnimatePresence mode="popLayout" initial={false}>
        <motion.div key={card.key} initial={{ x: 60 * p.direction, opacity: 0 }} animate={{ x: 0, opacity: 1 }} exit={{ opacity: 0, scale: 0.92 }} transition={{ type: 'spring', stiffness: 220, damping: 24 }}>
          <TiltCard card={card} skin="glass" p={p} />
        </motion.div>
      </AnimatePresence>
    </div>
  )
}
