import { AnimatePresence, motion, type MotionValue } from 'framer-motion'
import { Snowflake } from 'lucide-react'
import { LogoMark } from '@/shared/layout/Utilities'
import { cn } from '@/shared/lib/cn'
import type { CardConfig, ShowcaseCard, VariantProps } from './types'

/** Skema warna permukaan kartu. "silver" = kartu terang paling atas pada stack3d. */
export type Skin = 'silver' | 'ink' | 'emerald' | 'indigo' | 'amber' | 'glass' | 'flat'

const SKIN: Record<Skin, { bg: string; fg: string; sub: string; chip: string }> = {
  silver: { bg: 'linear-gradient(135deg,#F8FAFC 0%,#E2E8F0 45%,#CBD5E1 100%)', fg: '#0F172A', sub: '#475569', chip: 'linear-gradient(135deg,#E9D5B7,#C7A27C)' },
  ink: { bg: 'linear-gradient(135deg,#1E293B 0%,#0B1020 70%)', fg: '#E5E9F5', sub: '#A3ADC8', chip: 'linear-gradient(135deg,#E9D5B7,#C7A27C)' },
  emerald: { bg: 'linear-gradient(135deg,#1E4A2E 0%,#0F2A1C 75%)', fg: '#E8F3E8', sub: '#9CC7A8', chip: 'linear-gradient(135deg,#E9D5B7,#C7A27C)' },
  indigo: { bg: 'linear-gradient(135deg,#3730A3 0%,#1E1B4B 80%)', fg: '#EEF2FF', sub: '#C7D2FE', chip: 'linear-gradient(135deg,#E9D5B7,#C7A27C)' },
  amber: { bg: 'linear-gradient(135deg,#4A3200 0%,#1F1500 80%)', fg: '#FFF4D6', sub: '#F2D28B', chip: 'linear-gradient(135deg,#F2B84B,#C7A27C)' },
  glass: { bg: 'linear-gradient(135deg,rgba(255,255,255,.55),rgba(255,255,255,.18))', fg: 'var(--text)', sub: 'var(--text-muted)', chip: 'linear-gradient(135deg,#E9D5B7,#C7A27C)' },
  flat: { bg: 'var(--surface)', fg: 'var(--text)', sub: 'var(--text-muted)', chip: 'linear-gradient(135deg,#E9D5B7,#C7A27C)' },
}

export function skinForTone(tone: ShowcaseCard['tone']): Skin {
  return tone === 'commission' ? 'emerald' : tone === 'warning' ? 'amber' : tone === 'topup' ? 'indigo' : 'ink'
}

export const radiusCls = (r: CardConfig['radius']) => (r === '3xl' ? 'rounded-3xl' : r === 'xl' ? 'rounded-xl' : 'rounded-2xl')
export const shadowCls = (s: CardConfig['shadow']) => (s === 'none' ? '' : s === 'deep' ? 'shadow-[0_30px_60px_-12px_rgb(15_23_42/.45)]' : 'shadow-[0_18px_40px_-14px_rgb(15_23_42/.35)]')

function mask(ref: string) {
  const parts = ref.split(' ')
  return parts.map((p, i) => (i === 0 || i === parts.length - 1 ? p : '••••')).join(' ')
}

/** Permukaan kartu (depan + belakang) yang dipakai semua variant. Rasio kartu bank 1.586:1. */
export function CardFace({
  card,
  skin,
  config,
  state,
  money,
  labels,
  flipped = false,
  glareBg,
  glareOpacity,
  className,
}: {
  card: ShowcaseCard
  skin: Skin
  config: CardConfig
  state: VariantProps['state']
  money: VariantProps['money']
  labels: VariantProps['labels']
  flipped?: boolean
  glareBg?: MotionValue<string>
  glareOpacity?: MotionValue<number>
  className?: string
}) {
  const s = SKIN[skin]
  const r = radiusCls(config.radius)
  const face = cn('absolute inset-0 overflow-hidden [backface-visibility:hidden]', r)
  const flipDur = config.animationSpeed ? 0.6 / config.animationSpeed : 0
  return (
    <motion.div
      className={cn('relative aspect-[1.586] w-full select-none [transform-style:preserve-3d]', className)}
      animate={{ rotateY: flipped ? 180 : 0 }}
      transition={config.animationSpeed ? { type: 'spring', stiffness: 140, damping: 18, duration: flipDur } : { duration: 0 }}
    >
      {/* DEPAN */}
      <div className={cn(face, skin === 'glass' && 'border border-white/50 backdrop-blur-md', skin === 'flat' && 'border border-line')} style={{ background: s.bg, color: s.fg }}>
        {/* kilau tipis permukaan */}
        <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(115deg,transparent_30%,rgba(255,255,255,.22)_45%,transparent_60%)]" aria-hidden />
        <div className="relative flex h-full flex-col justify-between p-[6%]">
          <div className="flex items-start justify-between gap-2">
            <div className="min-w-0">
              <p className="truncate text-[clamp(10px,2.6cqw,13px)] font-semibold uppercase tracking-[0.08em]" style={{ color: s.sub }}>{card.label}</p>
              <div className="mt-1 h-[clamp(22px,7cqw,34px)] overflow-hidden">
                <AnimatePresence mode="wait" initial={false}>
                  <motion.p
                    key={state.revealed ? 'show' : 'hide'}
                    className="text-[clamp(17px,6cqw,28px)] font-bold leading-tight tracking-tight tabular-nums"
                    initial={{ y: 14, opacity: 0, filter: 'blur(4px)' }}
                    animate={{ y: 0, opacity: 1, filter: 'blur(0px)' }}
                    exit={{ y: -14, opacity: 0, filter: 'blur(4px)' }}
                    transition={{ duration: 0.25 }}
                  >
                    {state.revealed ? money(card.amount) : 'Rp ••••••'}
                  </motion.p>
                </AnimatePresence>
              </div>
            </div>
            {/* chip */}
            <span className="h-[18%] w-[13%] shrink-0 rounded-md border border-black/10" style={{ background: s.chip, aspectRatio: '1.3' }} aria-hidden />
          </div>
          <div className="flex items-end justify-between gap-3">
            <div className="min-w-0">
              <p className="font-mono text-[clamp(11px,3.4cqw,16px)] tracking-[0.12em] tabular-nums">{state.revealed ? card.ref : mask(card.ref)}</p>
              <p className="mt-1 truncate text-[clamp(10px,2.6cqw,12px)] uppercase tracking-wide" style={{ color: s.sub }}>{card.holder}</p>
            </div>
            <span className="flex shrink-0 items-center gap-1.5 text-[clamp(10px,2.8cqw,13px)] font-bold">
              <LogoMark size={18} bg={skin === 'silver' || skin === 'flat' || skin === 'glass' ? '#4338CA' : 'rgba(255,255,255,.92)'} fg={skin === 'silver' || skin === 'flat' || skin === 'glass' ? '#fff' : '#1E1B4B'} />
              Krafio
            </span>
          </div>
        </div>
        {glareBg && glareOpacity && config.glare && <motion.div className="pointer-events-none absolute inset-0 mix-blend-overlay" style={{ background: glareBg, opacity: glareOpacity }} aria-hidden />}
        <AnimatePresence>
          {state.frozen && (
            <motion.div className="absolute inset-0 grid place-items-center bg-white/35 backdrop-blur-[3px] dark:bg-slate-900/40" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
              <span className="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-3 py-1 text-[12px] font-semibold text-slate-800">
                <Snowflake className="size-3.5" aria-hidden />
                {labels.frozen}
              </span>
            </motion.div>
          )}
        </AnimatePresence>
      </div>
      {/* BELAKANG */}
      <div className={cn(face, '[transform:rotateY(180deg)]')} style={{ background: s.bg, color: s.fg }}>
        <div className="mt-[9%] h-[17%] w-full bg-black/80" aria-hidden />
        <div className="flex items-center gap-3 px-[6%] pt-[5%]">
          <div className="h-[clamp(20px,7cqw,30px)] flex-1 rounded-sm bg-[repeating-linear-gradient(45deg,#fff,#fff_6px,#f1f5f9_6px,#f1f5f9_12px)]" aria-hidden />
          <span className="rounded-sm bg-white px-2 py-0.5 font-mono text-[12px] font-bold text-slate-900">{state.revealed ? card.ref.slice(-3) : '•••'}</span>
        </div>
        <dl className="grid gap-1 px-[6%] pt-[4%] text-[clamp(10px,2.7cqw,13px)]">
          <dt className="sr-only">{labels.back}</dt>
          {Object.entries(card.meta ?? {}).slice(0, 3).map(([k, v]) => (
            <div key={k} className="flex justify-between gap-3">
              <span style={{ color: s.sub }}>{k}</span>
              <span className="font-semibold tabular-nums">{typeof v === 'number' ? money(v) : v}</span>
            </div>
          ))}
        </dl>
      </div>
    </motion.div>
  )
}
