import { useState } from 'react'
import { motion, useReducedMotion } from 'framer-motion'
import { CardFace, radiusCls, skinForTone, type Skin } from '../CardFace'
import { useCardTilt } from '../useCardTilt'
import type { VariantProps } from '../types'
import { cn } from '@/shared/lib/cn'

/**
 * "stack3d": kartu bertumpuk miring dengan perspektif isometrik (rotateX 55°, rotateZ −35°),
 * saling offset ke bawah-kanan. Kartu teratas terang (perak), di bawahnya lebih gelap.
 * Hover merenggangkan tumpukan; klik kartu bawah menaikkannya ke atas (animasi swap).
 */
export default function Stack3D(p: VariantProps) {
  const reduced = useReducedMotion()
  const [spread, setSpread] = useState(false)
  const { cards, state, config } = p
  const order = cards.map((_, i) => (i - state.index + cards.length) % cards.length) // 0 = paling atas
  const tilt = useCardTilt({ intensity: config.tiltIntensity / 2, enabled: config.interactive })
  const gap = spread ? 46 : 30
  const iso = reduced ? 'none' : `rotateX(55deg) rotateZ(-35deg)`
  return (
    <div className="relative mx-auto grid w-full max-w-[460px] place-items-center rounded-[28px] bg-[radial-gradient(120%_90%_at_30%_10%,var(--surface),var(--surface-2))] px-6 pb-10 pt-6" style={{ perspective: config.perspective }} onMouseEnter={() => setSpread(true)} onMouseLeave={() => setSpread(false)}>
      <motion.div className="relative w-[78%] [container-type:inline-size]" style={{ rotateX: tilt.rotateX, rotateY: tilt.rotateY, transformStyle: 'preserve-3d' }} {...tilt.handlers}>
        {/* ruang tinggi untuk tumpukan */}
        <div className="aspect-[1.586] w-full" style={{ marginBottom: (cards.length - 1) * (gap * 0.55) }} aria-hidden />
        {cards.map((c, i) => {
          const depth = order[i]
          const top = depth === 0
          const skin: Skin = top ? 'silver' : depth === 1 ? (c.tone === 'commission' ? 'emerald' : 'ink') : skinForTone(c.tone) === 'emerald' ? 'emerald' : 'ink'
          return (
            <motion.button
              key={c.key}
              type="button"
              aria-label={top ? `${c.label} — ${p.labels.back}` : c.label}
              onClick={() => (top ? p.flip() : p.select(i, 1))}
              className={cn('absolute left-0 top-0 w-full outline-none focus-visible:ring-2 focus-visible:ring-primary', radiusCls(config.radius))}
              style={{ zIndex: cards.length - depth, transform: iso, transformStyle: 'preserve-3d' }}
              initial={false}
              animate={{ x: depth * gap * 0.62, y: depth * gap, scale: 1 - depth * 0.02, filter: top ? 'brightness(1)' : `brightness(${1 - depth * 0.08})` }}
              transition={{ type: 'spring', stiffness: 200, damping: 22 }}
              whileHover={!top && config.interactive ? { y: depth * gap - 8 } : undefined}
            >
              <div className={cn(radiusCls(config.radius), config.shadow !== 'none' && 'shadow-[18px_26px_36px_-12px_rgb(15_23_42/.45)]')}>
                <CardFace card={c} skin={skin} config={config} state={top ? state : { ...state, flipped: false, revealed: false }} money={p.money} labels={p.labels} flipped={top && state.flipped && !reduced} glareBg={top ? tilt.glareBg : undefined} glareOpacity={top ? tilt.glareOpacity : undefined} />
              </div>
            </motion.button>
          )
        })}
      </motion.div>
      {/* bayangan lembut di bawah tumpukan */}
      <div className="pointer-events-none absolute inset-x-[18%] bottom-6 h-8 rounded-[50%] bg-black/20 blur-2xl" aria-hidden />
    </div>
  )
}
