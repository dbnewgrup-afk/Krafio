import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import { useReducedMotion } from 'framer-motion'

/**
 * Orkestrasi animasi "Login → Dashboard" (satu tempat untuk semua delay/stagger).
 * Login yang sukses memanggil startIntro(); AppShell membaca flag sekali saat mount lalu
 * memutar urutan: tirai terbuka → sidebar → header → kartu (skeleton → konten) → count-up.
 */
const KEY = 'krafio.intro'

export const TIMELINE = {
  /** Efek tirai login (detik) */
  curtain: 0.6,
  /** Jeda layar kosong setelah tirai */
  gap: 0.2,
  /** Sidebar mulai muncul */
  sidebar: 0.8,
  sidebarStagger: 0.05,
  /** Header mulai 0.15 dtk setelah sidebar */
  header: 0.95,
  /** Kartu pertama */
  cards: 1.0,
  cardStagger: 0.13,
  /** Count-up angka */
  count: 1.2,
  countDuration: 1.4,
  ease: [0.76, 0, 0.24, 1] as [number, number, number, number],
  easeOut: [0.22, 1, 0.36, 1] as [number, number, number, number],
} as const

export function startIntro(area: 'user' | 'admin') {
  try {
    sessionStorage.setItem(KEY, area)
  } catch {
    /* sesi privat: lewati animasi */
  }
}

function takeIntro(): string | null {
  try {
    const v = sessionStorage.getItem(KEY)
    if (v) sessionStorage.removeItem(KEY)
    return v
  } catch {
    return null
  }
}

interface IntroCtx {
  /** Animasi intro sedang diputar (sekali setelah login) */
  intro: boolean
  /** Waktu dasar (detik) untuk elemen konten; 0 bila tidak ada intro */
  base: number
  /** Motion dimatikan pengguna (prefers-reduced-motion) */
  reduced: boolean
}
const Ctx = createContext<IntroCtx>({ intro: false, base: 0, reduced: false })

export function IntroProvider({ area, children }: { area: 'user' | 'admin'; children: ReactNode }) {
  const reduced = !!useReducedMotion()
  // Dibaca sekali per mount (StrictMode memanggil initializer dua kali; nilai disimpan di state pertama)
  const [intro] = useState(() => {
    const v = takeIntro()
    if (v) (window as unknown as { __krafioIntro?: string }).__krafioIntro = v
    const flagged = v ?? (window as unknown as { __krafioIntro?: string }).__krafioIntro
    return flagged === area
  })
  const [active, setActive] = useState(intro && !reduced)
  useEffect(() => {
    if (!active) return
    const id = window.setTimeout(() => {
      setActive(false)
      delete (window as unknown as { __krafioIntro?: string }).__krafioIntro
    }, 3200)
    return () => window.clearTimeout(id)
  }, [active])
  const value = useMemo(() => ({ intro: active, base: active ? TIMELINE.cards : 0, reduced }), [active, reduced])
  return <Ctx.Provider value={value}>{children}</Ctx.Provider>
}

export function useIntro(): IntroCtx {
  return useContext(Ctx)
}
