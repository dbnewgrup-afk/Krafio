import { useTranslation } from 'react-i18next'
import { Loader2, AlertCircle } from 'lucide-react'
import type { PaymentStatus } from './usePaymentStatus'

/** Keterangan di bawah tombol bayar: menunggu konfirmasi, atau gagal/kedaluwarsa. */
export function PaymentWaitNote({ status }: { status: PaymentStatus | null }) {
  const { t } = useTranslation()
  if (status === 'failed' || status === 'expired')
    return (
      <p role="alert" className="t-small flex items-start gap-2 rounded-md bg-danger/10 p-3 text-danger">
        <AlertCircle className="mt-0.5 size-4 shrink-0" aria-hidden />
        {t(status === 'expired' ? 'billing.payment_expired' : 'billing.payment_failed')}
      </p>
    )
  return (
    <p aria-live="polite" className="t-small flex items-start gap-2 text-muted">
      <Loader2 className="mt-0.5 size-4 shrink-0 animate-spin motion-reduce:animate-none" aria-hidden />
      {t('billing.waiting_payment')}
    </p>
  )
}
