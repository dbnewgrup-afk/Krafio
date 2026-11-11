import { useEffect, useRef, type RefObject } from 'react'

let lockCount = 0
/** Kunci scroll body dengan kompensasi lebar scrollbar (Design Spec §10.3 butir 5). */
export function useScrollLock(active: boolean): void {
  useEffect(() => {
    if (!active) return
    const html = document.documentElement
    if (lockCount === 0) {
      const sbw = window.innerWidth - html.clientWidth
      html.style.paddingRight = sbw > 0 ? `${sbw}px` : ''
      html.classList.add('scroll-locked')
    }
    lockCount++
    return () => {
      lockCount--
      if (lockCount === 0) {
        html.classList.remove('scroll-locked')
        html.style.paddingRight = ''
      }
    }
  }, [active])
}

const FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'

/** Focus trap + kembalikan fokus ke pemicu saat ditutup (§10.3 butir 4, §6.3 butir 7). */
export function useFocusTrap(ref: RefObject<HTMLElement | null>, active: boolean): void {
  useEffect(() => {
    if (!active) return
    const prev = document.activeElement as HTMLElement | null
    const el = ref.current
    const first = () => el?.querySelectorAll<HTMLElement>(FOCUSABLE)[0]
    window.setTimeout(() => {
      if (el && !el.contains(document.activeElement)) (first() ?? el).focus()
    }, 20)
    const onKey = (e: KeyboardEvent) => {
      if (e.key !== 'Tab' || !el) return
      const items = Array.from(el.querySelectorAll<HTMLElement>(FOCUSABLE)).filter((x) => x.offsetParent !== null)
      if (!items.length) return
      const a = items[0]
      const b = items[items.length - 1]
      if (e.shiftKey && document.activeElement === a) {
        e.preventDefault()
        b.focus()
      } else if (!e.shiftKey && document.activeElement === b) {
        e.preventDefault()
        a.focus()
      }
    }
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('keydown', onKey)
      prev?.focus?.()
    }
  }, [ref, active])
}

/** Tombol Back browser/Android menutup overlay teratas (push history state) — §6.3 butir 6, §10.3 butir 10. */
// popstate yang dipicu sendiri oleh history.back() saat overlay ditutup via tombol (bukan tombol Back browser)
let selfPops = 0
if (typeof window !== 'undefined') {
  // Listener global terdaftar paling awal: menurunkan penghitung setelah semua listener overlay memeriksanya
  window.addEventListener('popstate', () => {
    if (selfPops > 0) window.setTimeout(() => (selfPops = Math.max(0, selfPops - 1)), 0)
  })
}

export function useBackClose(active: boolean, onClose: () => void): void {
  const cb = useRef(onClose)
  cb.current = onClose
  useEffect(() => {
    if (!active) return
    const token = `ov-${Math.random().toString(36).slice(2)}`
    let pushed = false
    let closedByPop = false
    const onPop = () => {
      if (selfPops > 0) return
      closedByPop = true
      cb.current()
    }
    // Ditunda satu tick: StrictMode (dev) menjalankan efek dua kali; tanpa ini history.back() dari
    // cleanup pertama memicu popstate yang langsung menutup overlay yang baru dibuka.
    const timer = window.setTimeout(() => {
      window.history.pushState({ ...(window.history.state ?? {}), overlay: token }, '')
      pushed = true
      window.addEventListener('popstate', onPop)
    }, 0)
    return () => {
      window.clearTimeout(timer)
      window.removeEventListener('popstate', onPop)
      if (pushed && !closedByPop && window.history.state?.overlay === token) {
        selfPops++
        window.history.back()
      }
    }
  }, [active])
}
