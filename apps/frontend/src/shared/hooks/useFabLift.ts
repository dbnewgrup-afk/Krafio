import { useEffect, type RefObject } from 'react'

/** Elemen tetap di bawah (sticky action bar, banner) mengangkat tumpukan FAB agar tidak saling menimpa (§15.3). */
export function useFabLift(ref: RefObject<HTMLElement | null>, active = true): void {
  useEffect(() => {
    const el = ref.current
    const root = document.documentElement
    if (!active || !el) return
    const ro = new ResizeObserver(() => root.style.setProperty('--fab-lift', `${el.offsetHeight + 8}px`))
    ro.observe(el)
    return () => {
      ro.disconnect()
      root.style.removeProperty('--fab-lift')
    }
  }, [ref, active])
}
