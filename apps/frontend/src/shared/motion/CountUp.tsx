import { useEffect, useRef } from 'react'
import { useReducedMotion } from 'framer-motion'
import { useIntro, TIMELINE } from './intro'

const easeOutCubic = (t: number) => 1 - Math.pow(1 - t, 3)

interface CountUpProps {
  to: number
  /** Format angka akhir (mis. rupiah, number). Default: pemisah ribuan id-ID */
  format?: (n: number) => string
  prefix?: string
  suffix?: string
  decimals?: number
  /** Detik; default dari timeline */
  duration?: number
  /** Detik tambahan setelah waktu dasar halaman */
  delay?: number
  /** Naik sedikit melewati nilai akhir lalu kembali (hanya angka utama) */
  overshoot?: boolean
  className?: string
}

/**
 * Angka naik dari 0 ke nilai akhir (easeOutCubic), tabular-nums agar tidak goyang.
 * Contoh: <CountUp to={92} suffix="%" duration={1.4} delay={0.3} />
 * Nilai ditulis langsung ke DOM per frame (tanpa re-render React) agar puluhan angka tetap ringan di ponsel.
 * Saat prefers-reduced-motion: langsung menampilkan nilai akhir.
 */
export function CountUp({ to, format, prefix = '', suffix = '', decimals = 0, duration, delay = 0, overshoot, className }: CountUpProps) {
  const reduced = useReducedMotion()
  const { base, intro } = useIntro()
  const el = useRef<HTMLSpanElement>(null)
  const from = useRef(0)
  const fmtRef = useRef(format)
  fmtRef.current = format

  const render = (v: number) => {
    const n = decimals ? Number(v.toFixed(decimals)) : Math.round(v)
    const text = fmtRef.current ? fmtRef.current(n) : new Intl.NumberFormat('id-ID', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(n)
    return `${prefix}${text}${suffix}`
  }

  useEffect(() => {
    const node = el.current
    if (!node) return
    if (reduced) {
      node.textContent = render(to)
      from.current = to
      return
    }
    const start0 = from.current
    const dur = (duration ?? (intro ? TIMELINE.countDuration : 0.9)) * 1000
    const wait = ((intro ? TIMELINE.count : 0) + delay) * 1000 + (intro ? 0 : base * 1000)
    const peak = overshoot && to > 10 ? to * 1.022 : to
    let raf = 0
    let t0 = 0
    const tick = (now: number) => {
      if (!t0) t0 = now
      const p = Math.min(1, (now - t0) / dur)
      let v: number
      if (overshoot && p < 0.82) v = start0 + (peak - start0) * easeOutCubic(p / 0.82)
      else if (overshoot) v = peak + (to - peak) * easeOutCubic((p - 0.82) / 0.18)
      else v = start0 + (to - start0) * easeOutCubic(p)
      node.textContent = render(v)
      if (p < 1) raf = requestAnimationFrame(tick)
      else from.current = to
    }
    node.textContent = render(start0)
    const id = window.setTimeout(() => (raf = requestAnimationFrame(tick)), wait)
    return () => {
      window.clearTimeout(id)
      cancelAnimationFrame(raf)
      from.current = to
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [to, reduced])

  return (
    <span ref={el} className={className} style={{ fontVariantNumeric: 'tabular-nums' }} aria-label={render(to)}>
      {render(reduced ? to : from.current)}
    </span>
  )
}
