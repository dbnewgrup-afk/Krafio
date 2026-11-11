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
export function useBackClose(active: boolean, onClose: () => void): void {
  const cb = useRef(onClose)
  cb.current = onClose
  useEffect(() => {
    if (!active) return
    const token = `ov-${Math.random().toString(36).slice(2)}`
    window.history.pushState({ ...(window.history.state ?? {}), overlay: token }, '')
    let closedByPop = false
    const onPop = () => {
      closedByPop = true
      cb.current()
    }
    window.addEventListener('popstate', onPop)
    return () => {
      window.removeEventListener('popstate', onPop)
      if (!closedByPop && window.history.state?.overlay === token) window.history.back()
    }
  }, [active])
}
