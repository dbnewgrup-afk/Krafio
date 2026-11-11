import { useCallback, useEffect, useRef, useState, type KeyboardEvent } from 'react'
import { motion, useReducedMotion } from 'framer-motion'
import { useTranslation } from 'react-i18next'
import { Snowflake, Eye, EyeOff, Copy, Check, RotateCcw, Layers, Square, PanelTop, Sparkle, Fan as FanIcon } from 'lucide-react'
import { useToast } from '@/shared/context/ToastContext'
import { rupiah } from '@/shared/lib/format'
import { cn } from '@/shared/lib/cn'
import { Segmented } from '@/shared/ui/Misc'
import Stack3D from './variants/Stack3D'
import Single from './variants/Single'
import Flat from './variants/Flat'
import Glass from './variants/Glass'
import Fan from './variants/Fan'
import { DEFAULT_CARD_CONFIG, type CardConfig, type CardState, type CardVariant, type ShowcaseCard, type VariantComponent } from './types'

/** Registry gaya: menambah gaya baru cukup menambah satu entri di sini. */
export const CARD_VARIANTS: Record<CardVariant, { component: VariantComponent; icon: typeof Layers }> = {
  stack3d: { component: Stack3D, icon: Layers },
  single: { component: Single, icon: Square },
  flat: { component: Flat, icon: PanelTop },
  glass: { component: Glass, icon: Sparkle },
  fan: { component: Fan, icon: FanIcon },
}

const read = (key: string, fallback: string) => {
  try {
    return localStorage.getItem(key) ?? fallback
  } catch {
    return fallback
  }
}
const write = (key: string, v: string) => {
  try {
    localStorage.setItem(key, v)
  } catch {
    /* noop */
  }
}

/**
 * CardShowcase — satu komponen, satu data kartu, banyak gaya visual.
 *   <CardShowcase cards={cards} />               // gaya tersimpan / default
 *   <CardShowcase cards={cards} variant="single" />
 * Interaksi: tilt + glare mengikuti kursor (giroskop/drag di sentuh), klik/Enter/Spasi = balik kartu,
 * panah kiri/kanan atau swipe = ganti kartu, aksi cepat (mode privasi, tampilkan, salin, balik).
 */
export function CardShowcase({ cards, variant, config: cfg, storageKey = 'krafio.cards.variant', showSwitcher = true, className }: { cards: ShowcaseCard[]; variant?: CardVariant; config?: Partial<CardConfig>; storageKey?: string; showSwitcher?: boolean; className?: string }) {
  const { t } = useTranslation()
  const toast = useToast()
  const reduced = useReducedMotion()
  const [v, setV] = useState<CardVariant>(() => variant ?? ((read(storageKey, DEFAULT_CARD_CONFIG.variant) as CardVariant) in CARD_VARIANTS ? (read(storageKey, DEFAULT_CARD_CONFIG.variant) as CardVariant) : 'stack3d'))
  const config: CardConfig = { ...DEFAULT_CARD_CONFIG, ...cfg, variant: v, ...(reduced ? { interactive: cfg?.interactive ?? true, float: false, tiltIntensity: 0, animationSpeed: 0 } : {}) }
  const [state, setState] = useState<CardState>(() => ({ index: 0, flipped: false, revealed: false, frozen: read(`${storageKey}.privacy`, '0') === '1' }))
  const [direction, setDirection] = useState<1 | -1>(1)
  const [copied, setCopied] = useState(false)
  const liveRef = useRef<HTMLParagraphElement>(null)

  useEffect(() => {
    if (variant) setV(variant)
  }, [variant])

  const select = useCallback(
    (i: number, dir: 1 | -1 = 1) => {
      const n = cards.length
      const next = ((i % n) + n) % n
      setDirection(dir)
      setState((s) => ({ ...s, index: next, flipped: false }))
    },
    [cards.length],
  )
  const flip = useCallback(() => setState((s) => ({ ...s, flipped: !s.flipped })), [])
  const card = cards[state.index] ?? cards[0]
  if (!card) return null

  const onKey = (e: KeyboardEvent) => {
    if (e.key === 'ArrowRight') { e.preventDefault(); select(state.index + 1, 1) }
    else if (e.key === 'ArrowLeft') { e.preventDefault(); select(state.index - 1, -1) }
    else if ((e.key === 'Enter' || e.key === ' ') && e.target === e.currentTarget) { e.preventDefault(); flip() }
  }

  const Variant = CARD_VARIANTS[v].component
  const labels = { back: t('cards.flip'), holder: t('cards.holder'), hidden: t('cards.hidden'), frozen: t('cards.privacy_on') }

  const actions = [
    {
      key: 'freeze',
      label: state.frozen ? t('cards.unfreeze') : t('cards.freeze'),
      icon: Snowflake,
      active: state.frozen,
      run: () => {
        const on = !state.frozen
        setState((s) => ({ ...s, frozen: on, revealed: on ? false : s.revealed }))
        write(`${storageKey}.privacy`, on ? '1' : '0')
        toast.push('info', on ? t('cards.privacy_on_toast') : t('cards.privacy_off_toast'))
      },
    },
    {
      key: 'show',
      label: state.revealed ? t('cards.hide_number') : t('cards.show_number'),
      icon: state.revealed ? EyeOff : Eye,
      active: state.revealed,
      disabled: state.frozen,
      run: () => setState((s) => ({ ...s, revealed: !s.revealed })),
    },
    {
      key: 'copy',
      label: t('cards.copy_number'),
      icon: copied ? Check : Copy,
      active: copied,
      run: async () => {
        try {
          await navigator.clipboard.writeText(card.ref.replace(/\s+/g, ' '))
          setCopied(true)
          toast.push('success', t('cards.copied', { ref: card.ref }))
          window.setTimeout(() => setCopied(false), 1600)
        } catch {
          toast.push('error', t('cards.copy_failed'))
        }
      },
    },
    { key: 'flip', label: t('cards.flip'), icon: RotateCcw, active: state.flipped, run: flip },
  ]

  return (
    <div className={cn('grid gap-4', className)}>
      {showSwitcher && (
        <div className="flex flex-wrap items-center justify-between gap-2">
          <Segmented
            label={t('cards.style')}
            value={v}
            onChange={(x) => {
              setV(x)
              write(storageKey, x)
            }}
            items={(Object.keys(CARD_VARIANTS) as CardVariant[]).map((k) => {
              const Icon = CARD_VARIANTS[k].icon
              return { value: k, label: <span className="inline-flex items-center gap-1.5"><Icon className="size-3.5" aria-hidden /><span className="max-sm:sr-only">{t(`cards.variant.${k}`)}</span></span> }
            })}
          />
        </div>
      )}

      <div role="group" aria-roledescription={t('cards.region')} aria-label={`${card.label} — ${state.index + 1} / ${cards.length}`} tabIndex={0} onKeyDown={onKey} className={cn('rounded-[28px] outline-none focus-visible:ring-2 focus-visible:ring-primary', state.frozen && 'grayscale')}>
        <Variant cards={cards} config={config} state={state} select={select} flip={flip} money={rupiah} labels={labels} direction={direction} />
      </div>
      <p ref={liveRef} className="sr-only" aria-live="polite">
        {card.label}. {state.revealed ? rupiah(card.amount) : t('cards.hidden')}
      </p>

      {/* Pilihan kartu (chip) */}
      {cards.length > 1 && (
        <div className="flex flex-wrap justify-center gap-2" role="tablist" aria-label={t('cards.choose')}>
          {cards.map((c, i) => (
            <button
              key={c.key}
              type="button"
              role="tab"
              aria-selected={i === state.index}
              onClick={() => select(i, i > state.index ? 1 : -1)}
              className={cn('t-label inline-flex h-9 items-center gap-2 rounded-full border px-3 transition-[background-color,border-color,color] duration-[120ms]', i === state.index ? 'border-primary bg-primary-soft text-primary' : 'border-line bg-surface text-muted hover:text-fg')}
            >
              <span className={cn('size-2 rounded-full', c.tone === 'commission' ? 'bg-wallet-commission' : c.tone === 'warning' ? 'bg-warning' : 'bg-wallet-topup')} aria-hidden />
              {c.label}
              <span className="font-mono text-[12px] text-muted">•• {c.ref.replace(/\D/g, '').slice(-4) || c.ref.slice(-4)}</span>
            </button>
          ))}
        </div>
      )}

      {/* Aksi cepat */}
      <div className="grid grid-cols-4 gap-2">
        {actions.map((a) => (
          <motion.button
            key={a.key}
            type="button"
            whileTap={reduced ? undefined : { scale: 0.96 }}
            onClick={a.run}
            disabled={a.disabled}
            aria-pressed={a.key !== 'copy' ? a.active : undefined}
            aria-label={a.label}
            className={cn('grid min-h-16 justify-items-center gap-1 rounded-xl border px-1 py-2.5 text-center transition-[background-color,border-color,color] duration-[120ms] disabled:opacity-45', a.active ? 'border-primary/40 bg-primary-soft text-primary' : 'border-line bg-surface text-fg hover:bg-surface-2')}
          >
            <motion.span key={String(a.active)} initial={reduced ? false : { scale: 0.6, rotate: -20, opacity: 0 }} animate={{ scale: 1, rotate: 0, opacity: 1 }} transition={{ type: 'spring', stiffness: 380, damping: 18 }}>
              <a.icon className="size-5" aria-hidden />
            </motion.span>
            <span className="text-[12px] font-medium leading-4">{a.label}</span>
          </motion.button>
        ))}
      </div>
    </div>
  )
}
