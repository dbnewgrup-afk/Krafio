import { useEffect, useRef, type PointerEvent as RPointerEvent } from 'react'
import { useMotionTemplate, useMotionValue, useReducedMotion, useSpring, useTransform } from 'framer-motion'

/**
 * Tilt 3D + glare yang dipakai ulang semua variant. Memakai motion value + spring (tanpa re-render).
 * - Pointer halus: mengikuti kursor, kembali dengan spring saat kursor keluar.
 * - Sentuh: giroskop bila tersedia (deviceorientation), selain itu mengikuti jari saat ditekan.
 */
export function useCardTilt({ intensity = 10, enabled = true }: { intensity?: number; enabled?: boolean } = {}) {
  const reduced = useReducedMotion()
  const active = enabled && !reduced
  const px = useMotionValue(0.5) // 0..1 posisi relatif
  const py = useMotionValue(0.5)
  const spring = { stiffness: 180, damping: 18, mass: 0.6 }
  const rx = useSpring(useTransform(py, [0, 1], [intensity, -intensity]), spring)
  const ry = useSpring(useTransform(px, [0, 1], [-intensity, intensity]), spring)
  const gx = useTransform(px, [0, 1], ['0%', '100%'])
  const gy = useTransform(py, [0, 1], ['0%', '100%'])
  const glareBg = useMotionTemplate`radial-gradient(circle at ${gx} ${gy}, rgba(255,255,255,0.38), rgba(255,255,255,0) 55%)`
  const glareOpacity = useSpring(0, { stiffness: 200, damping: 24 })
  const pressing = useRef(false)

  const set = (el: HTMLElement, clientX: number, clientY: number) => {
    const r = el.getBoundingClientRect()
    px.set(Math.min(1, Math.max(0, (clientX - r.left) / r.width)))
    py.set(Math.min(1, Math.max(0, (clientY - r.top) / r.height)))
  }
  const reset = () => {
    px.set(0.5)
    py.set(0.5)
    glareOpacity.set(0)
  }

  // Giroskop di perangkat sentuh (tanpa meminta izin eksplisit — iOS butuh gesture; bila tidak ada data, drag tetap jalan)
  useEffect(() => {
    if (!active || !window.matchMedia('(pointer: coarse)').matches) return
    const on = (e: DeviceOrientationEvent) => {
      if (e.beta == null || e.gamma == null || pressing.current) return
      px.set(Math.min(1, Math.max(0, 0.5 + e.gamma / 60)))
      py.set(Math.min(1, Math.max(0, 0.5 + (e.beta - 45) / 60)))
    }
    window.addEventListener('deviceorientation', on)
    return () => window.removeEventListener('deviceorientation', on)
  }, [active, px, py])

  const handlers = active
    ? {
        onPointerMove: (e: RPointerEvent<HTMLElement>) => {
          if (e.pointerType === 'touch' && !pressing.current) return
          set(e.currentTarget, e.clientX, e.clientY)
          glareOpacity.set(1)
        },
        onPointerDown: (e: RPointerEvent<HTMLElement>) => {
          pressing.current = true
          set(e.currentTarget, e.clientX, e.clientY)
        },
        onPointerUp: () => {
          pressing.current = false
        },
        onPointerLeave: () => {
          pressing.current = false
          reset()
        },
      }
    : {}

  return { rotateX: active ? rx : 0, rotateY: active ? ry : 0, glareBg, glareOpacity, handlers, active }
}
