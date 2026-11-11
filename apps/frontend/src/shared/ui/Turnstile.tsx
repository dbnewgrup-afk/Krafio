import { useEffect, useRef } from 'react'
import { useAuth } from '@/shared/context/AuthContext'
import { useTheme } from '@/shared/context/ThemeContext'

declare global {
  interface Window {
    turnstile?: { render: (el: HTMLElement, o: Record<string, unknown>) => string; remove: (id: string) => void }
  }
}

let loading: Promise<void> | null = null
function loadScript(): Promise<void> {
  if (window.turnstile) return Promise.resolve()
  if (!loading)
    loading = new Promise((res, rej) => {
      const s = document.createElement('script')
      s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
      s.async = true
      s.onload = () => res()
      s.onerror = () => rej(new Error('turnstile'))
      document.head.appendChild(s)
    })
  return loading
}

/**
 * Cloudflare Turnstile (PRD Bab 43.1, Design Spec §9.2). Hanya dimuat bila site key dikonfigurasi Admin
 * dan `show` true (mis. setelah ≥3 gagal login). Token divalidasi di backend.
 */
export function Turnstile({ show, onToken }: { show: boolean; onToken: (t: string | null) => void }) {
  const { settings } = useAuth()
  const { resolved } = useTheme()
  const ref = useRef<HTMLDivElement>(null)
  const key = settings?.turnstile_site_key
  useEffect(() => {
    if (!show || !key || !ref.current) return
    let id: string | null = null
    loadScript()
      .then(() => {
        if (!ref.current || !window.turnstile) return
        id = window.turnstile.render(ref.current, {
          sitekey: key,
          theme: resolved,
          callback: (tk: string) => onToken(tk),
          'expired-callback': () => onToken(null),
        })
      })
      .catch(() => onToken(null))
    return () => {
      if (id && window.turnstile) window.turnstile.remove(id)
    }
  }, [show, key, resolved, onToken])
  if (!show || !key) return null
  return <div ref={ref} className="min-h-[65px]" />
}
