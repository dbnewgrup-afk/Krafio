import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react'
import { getPref, setPref } from '@/shared/lib/prefs'

// Tema hanya Terang / Gelap (keputusan pemilik produk, menggantikan opsi "Ikuti sistem" di Design Spec §4.1).
// Kunjungan pertama memakai preferensi OS sebagai nilai awal, setelah itu pilihan pengguna yang berlaku.
export type ThemeMode = 'light' | 'dark'
interface ThemeCtx {
  mode: ThemeMode
  resolved: ThemeMode
  setMode: (m: ThemeMode) => void
  toggle: () => void
}
const Ctx = createContext<ThemeCtx | null>(null)

function initialMode(): ThemeMode {
  const saved = getPref('krafio.theme')
  if (saved === 'light' || saved === 'dark') return saved
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [mode, setModeState] = useState<ThemeMode>(initialMode)

  useEffect(() => {
    document.documentElement.setAttribute('data-theme', mode)
  }, [mode])

  const setMode = useCallback((m: ThemeMode) => {
    setPref('krafio.theme', m)
    setModeState(m)
  }, [])

  const toggle = useCallback(() => setMode(mode === 'dark' ? 'light' : 'dark'), [mode, setMode])

  return <Ctx.Provider value={{ mode, resolved: mode, setMode, toggle }}>{children}</Ctx.Provider>
}

export function useTheme(): ThemeCtx {
  const c = useContext(Ctx)
  if (!c) throw new Error('ThemeProvider missing')
  return c
}
