import { useEffect, useState } from 'react'
import { http } from '@/api-client/http'

export type PaymentStatus = 'pending' | 'paid' | 'failed' | 'expired' | string

/**
 * Polling status pembayaran gateway (GET /app/payments/{id}) setiap 5 detik selama tab terlihat.
 * Dipakai untuk gateway tanpa webhook (JualKirim): backend ikut mengecek invoice saat endpoint dipanggil.
 * Berhenti otomatis begitu status bukan `pending`.
 */
export function usePaymentStatus(paymentId: number | null | undefined, enabled = true): PaymentStatus | null {
  const [status, setStatus] = useState<PaymentStatus | null>(null)

  useEffect(() => {
    setStatus(null)
    if (!paymentId || !enabled) return
    let stopped = false
    let timer: ReturnType<typeof setTimeout> | undefined
    const tick = async () => {
      if (stopped) return
      if (document.visibilityState === 'visible') {
        try {
          const r = await http.get<{ data: { status: PaymentStatus } }>(`/app/payments/${paymentId}`)
          if (stopped) return
          setStatus(r.data.status)
          if (r.data.status !== 'pending') return
        } catch {
          // jaringan putus sesaat: coba lagi di tick berikutnya
        }
      }
      timer = setTimeout(tick, 5000)
    }
    timer = setTimeout(tick, 3000)
    const onVisible = () => {
      if (document.visibilityState === 'visible' && timer) {
        clearTimeout(timer)
        timer = setTimeout(tick, 300)
      }
    }
    document.addEventListener('visibilitychange', onVisible)
    return () => {
      stopped = true
      if (timer) clearTimeout(timer)
      document.removeEventListener('visibilitychange', onVisible)
    }
  }, [paymentId, enabled])

  return status
}
