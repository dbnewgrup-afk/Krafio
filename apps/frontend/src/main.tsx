import { StrictMode, useEffect } from 'react'
import { createRoot } from 'react-dom/client'
import { RouterProvider } from 'react-router-dom'
import './index.css'
import '@/shared/i18n'
import { router } from './router'
import { ThemeProvider } from '@/shared/context/ThemeContext'
import { ToastProvider } from '@/shared/context/ToastContext'
import { AuthProvider, useAuth } from '@/shared/context/AuthContext'

/** Hapus splash inline setelah sesi awal dimuat (Design Spec §12.4); "Menyambungkan…" bila > 3 dtk. */
function SplashGate() {
  const { loading } = useAuth()
  useEffect(() => {
    const el = document.getElementById('krafio-splash')
    if (!el) return
    if (!loading) {
      el.style.transition = 'opacity 180ms ease'
      el.style.opacity = '0'
      const id = window.setTimeout(() => el.remove(), 200)
      return () => window.clearTimeout(id)
    }
    const id = window.setTimeout(() => {
      const s = el.querySelector('small')
      if (s) s.textContent = document.documentElement.lang === 'en' ? 'Connecting…' : 'Menyambungkan…'
    }, 3000)
    return () => window.clearTimeout(id)
  }, [loading])
  return loading ? null : <RouterProvider router={router} />
}

if ('serviceWorker' in navigator && import.meta.env.PROD) {
  window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => undefined))
}

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <ThemeProvider>
      <ToastProvider>
        <AuthProvider>
          <SplashGate />
        </AuthProvider>
      </ToastProvider>
    </ThemeProvider>
  </StrictMode>,
)
