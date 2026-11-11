import { AnimatePresence, motion } from 'framer-motion'
import { useEffect, useState } from 'react'
import { useIntro, TIMELINE } from './intro'
import { LogoMark } from '@/shared/layout/Utilities'

/**
 * Efek "terbuka" setelah login (0–0.6 dtk): konten login memudar + blur, panel visual kiri
 * menyusut menjadi strip lalu keluar ke kiri, panel kanan melebar memenuhi layar seperti pintu,
 * lalu jeda kosong ±0.2 dtk sebelum dashboard masuk. Hanya transform/opacity (kecuali lebar panel).
 */
export function LoginCurtain() {
  const { intro } = useIntro()
  const [show, setShow] = useState(intro)
  useEffect(() => {
    if (!intro) return
    const id = window.setTimeout(() => setShow(false), (TIMELINE.curtain + TIMELINE.gap) * 1000)
    return () => window.clearTimeout(id)
  }, [intro])

  return (
    <AnimatePresence>
      {show && (
        <motion.div key="curtain" className="pointer-events-none fixed inset-0 z-loader flex" aria-hidden exit={{ opacity: 0 }} transition={{ duration: 0.18 }}>
          {/* Panel kiri (panel visual auth) — hanya di desktop, sama seperti layout login */}
          <motion.div
            className="relative hidden h-full overflow-hidden bg-[var(--loader-solid)] lg:block"
            initial={{ width: '50%', x: 0 }}
            animate={{ width: ['50%', '1.4%', '1.4%'], x: ['0%', '0%', '-110%'] }}
            transition={{ duration: TIMELINE.curtain, ease: TIMELINE.ease, times: [0, 0.62, 1] }}
          >
            <motion.div className="absolute left-10 top-10 flex items-center gap-2 text-[var(--on-dark)]" initial={{ opacity: 1 }} animate={{ opacity: 0 }} transition={{ duration: 0.2 }}>
              <LogoMark size={28} />
              <span className="text-[19px] font-bold tracking-tight">Krafio</span>
            </motion.div>
          </motion.div>
          {/* Panel kanan (form) melebar; isinya fade-out + blur 0→6px */}
          <motion.div className="relative h-full flex-1 bg-bg" initial={{ opacity: 1 }} animate={{ opacity: [1, 1, 0] }} transition={{ duration: TIMELINE.curtain + TIMELINE.gap, times: [0, 0.75, 1] }}>
            <motion.div
              className="absolute inset-0 grid place-items-center"
              initial={{ opacity: 1, filter: 'blur(0px)' }}
              animate={{ opacity: 0, filter: 'blur(6px)' }}
              transition={{ duration: 0.25, ease: 'easeOut' }}
            >
              <div className="grid w-full max-w-[440px] gap-4 px-6">
                <div className="h-8 w-2/3 rounded-md bg-surface-2" />
                <div className="h-12 rounded-md border border-line" />
                <div className="h-12 rounded-md border border-line" />
                <div className="h-12 rounded-md bg-primary" />
              </div>
            </motion.div>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  )
}
