import { motion, useReducedMotion } from 'framer-motion'
import type { ReactNode } from 'react'
import { cn } from '@/shared/lib/cn'
import { useCardTilt } from './useCardTilt'
import { CardFace, shadowCls, radiusCls, type Skin } from './CardFace'
import type { ShowcaseCard, VariantProps } from './types'

/** Kartu tunggal interaktif: tilt + glare + flip + floating idle. Dipakai variant single/flat/glass. */
export function TiltCard({ card, skin, p, className, floatDelay = 0, extra }: { card: ShowcaseCard; skin: Skin; p: VariantProps; className?: string; floatDelay?: number; extra?: ReactNode }) {
  const reduced = useReducedMotion()
  const { config, state, flip, money, labels } = p
  const tilt = useCardTilt({ intensity: config.tiltIntensity, enabled: config.interactive && !state.flipped })
  const speed = config.animationSpeed
  const floating = config.float && speed > 0 && !reduced
  return (
    <motion.div
      className={cn('relative [container-type:inline-size]', className)}
      style={{ perspective: config.perspective }}
      animate={floating ? { y: [0, -6, 0] } : undefined}
      transition={floating ? { duration: 4 / speed, ease: 'easeInOut', repeat: Infinity, delay: floatDelay } : undefined}
    >
      <motion.div style={{ rotateX: tilt.rotateX, rotateY: tilt.rotateY, transformStyle: 'preserve-3d' }} {...tilt.handlers} onClick={() => config.interactive && flip()} className={cn('cursor-pointer', radiusCls(config.radius), shadowCls(config.shadow))}>
        <CardFace card={card} skin={skin} config={config} state={state} money={money} labels={labels} flipped={state.flipped && !reduced} glareBg={tilt.glareBg} glareOpacity={tilt.glareOpacity} />
      </motion.div>
      {/* bayangan lembut di bawah kartu */}
      {config.shadow !== 'none' && <div className="pointer-events-none absolute inset-x-[10%] -bottom-4 h-6 rounded-[50%] bg-black/25 blur-xl dark:bg-black/60" aria-hidden />}
      {extra}
    </motion.div>
  )
}
