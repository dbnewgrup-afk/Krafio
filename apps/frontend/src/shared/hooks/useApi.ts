import { useCallback, useEffect, useState } from 'react'
import { http, ApiError, type Query } from '@/api-client/http'
import { useAuth } from '@/shared/context/AuthContext'

/** GET sederhana dengan loading/error/reload; 401 memicu modal sesi berakhir (§10.7). */
export function useApi<T>(path: string | null, query?: Query) {
  const [data, setData] = useState<T | null>(null)
  const [loading, setLoading] = useState(!!path)
  const [error, setError] = useState<ApiError | null>(null)
  const [tick, setTick] = useState(0)
  const { setSessionExpired, me } = useAuth()
  const qkey = JSON.stringify(query ?? {})

  useEffect(() => {
    if (!path) return
    const ctrl = new AbortController()
    setLoading(true)
    setError(null)
    http
      .get<{ data: T }>(path, query, ctrl.signal)
      .then((r) => setData(r.data))
      .catch((e) => {
        if ((e as Error).name === 'AbortError') return
        if (e instanceof ApiError && e.status === 401 && me) setSessionExpired(true)
        setError(e as ApiError)
      })
      .finally(() => {
        if (!ctrl.signal.aborted) setLoading(false)
      })
    return () => ctrl.abort()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [path, qkey, tick])

  const reload = useCallback(() => setTick((x) => x + 1), [])
  return { data, setData, loading, error, reload }
}
