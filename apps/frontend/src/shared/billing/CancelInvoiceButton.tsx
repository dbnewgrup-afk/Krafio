import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { http, ApiError } from '@/api-client/http'
import { useToast } from '@/shared/context/ToastContext'
import { Button } from '@/shared/ui/Button'

/**
 * Batalkan tagihan gateway yang belum dibayar (POST /app/payments/{id}/cancel) dengan konfirmasi dua langkah.
 * Untuk JualKirim, backend juga menghapus invoice sehingga link bayar tidak bisa dipakai lagi.
 */
export function CancelInvoiceButton({ paymentId, onDone }: { paymentId: number; onDone: (status: string) => void }) {
  const { t } = useTranslation()
  const toast = useToast()
  const [ask, setAsk] = useState(false)
  const [busy, setBusy] = useState(false)

  const cancel = async () => {
    setBusy(true)
    try {
      const r = await http.post<{ data: { status: string } }>(`/app/payments/${paymentId}/cancel`)
      if (r.data.status === 'paid') toast.push('success', t('billing.already_paid'))
      else toast.push('success', t('billing.invoice_cancelled'))
      onDone(r.data.status)
    } catch (e) {
      toast.push('error', e instanceof ApiError ? e.message : t('errors.server_body'))
    } finally {
      setBusy(false)
      setAsk(false)
    }
  }

  if (!ask)
    return (
      <Button size="sm" variant="ghost" onClick={() => setAsk(true)}>
        {t('billing.cancel_invoice')}
      </Button>
    )
  return (
    <div className="flex flex-wrap items-center gap-2 rounded-md bg-surface-2 p-2" role="group" aria-label={t('billing.cancel_invoice')}>
      <span className="t-small text-fg">{t('billing.cancel_confirm')}</span>
      <Button size="sm" variant="danger" loading={busy} onClick={cancel}>
        {t('billing.cancel_yes')}
      </Button>
      <Button size="sm" variant="secondary" disabled={busy} onClick={() => setAsk(false)}>
        {t('common.cancel')}
      </Button>
    </div>
  )
}
