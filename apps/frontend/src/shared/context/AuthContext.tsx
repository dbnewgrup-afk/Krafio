import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react'
import { http, ApiError } from '@/api-client/http'
import type { Me, PublicSettings } from '@/api-client/types'

interface AuthCtx {
  me: Me | null
  loading: boolean
  settings: PublicSettings | null
  refresh: () => Promise<Me | null>
  setMe: (m: Me | null) => void
  logout: () => Promise<void>
  sessionExpired: boolean
  setSessionExpired: (v: boolean) => void
}
const Ctx = createContext<AuthCtx | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [me, setMe] = useState<Me | null>(null)
  const [loading, setLoading] = useState(true)
  const [settings, setSettings] = useState<PublicSettings | null>(null)
  const [sessionExpired, setSessionExpired] = useState(false)

  const refresh = useCallback(async () => {
    try {
      const r = await http.get<{ data: Me }>('/me')
      setMe(r.data)
      return r.data
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) setMe(null)
      return null
    }
  }, [])

  useEffect(() => {
    let alive = true
    Promise.allSettled([refresh(), http.get<{ data: PublicSettings }>('/public/settings').then((r) => alive && setSettings(r.data))]).finally(() => {
      if (alive) setLoading(false)
    })
    return () => {
      alive = false
    }
  }, [refresh])

  const logout = useCallback(async () => {
    try {
      await http.post('/auth/logout')
    } finally {
      setMe(null)
    }
  }, [])

  return <Ctx.Provider value={{ me, loading, settings, refresh, setMe, logout, sessionExpired, setSessionExpired }}>{children}</Ctx.Provider>
}

export function useAuth(): AuthCtx {
  const c = useContext(Ctx)
  if (!c) throw new Error('AuthProvider missing')
  return c
}
