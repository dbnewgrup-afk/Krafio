import { useTranslation } from 'react-i18next'
import { Clock } from 'lucide-react'
import { useApi } from '@/shared/hooks/useApi'
import { dateTime, rupiah } from '@/shared/lib/format'
import { CancelInvoiceButton } from './CancelInvoiceButton'

interface PendingPayment {
  id: number
  reference: string
  purpose: string
  purpose_code: string | null
  amount: number
  redirect_url: string | null
  expires_at: string | null
}

/**
 * Daftar tagihan gateway yang belum dibayar (GET /app/payments). Pelanggan bisa kembali membayar
 * setelah menutup modal checkout, atau membatalkannya. Tidak tampil bila kosong.
 */
export function PendingInvoices({ onChange }: { onChange?: () => void }) {
  const { t } = useTranslation()
  const list = useApi<PendingPayment[]>('/app/payments')
  const items = list.data ?? []
  if (!items.length) return null
  return (
    <section aria-labelledby="pending-invoices" className="grid gap-2 rounded-lg border border-warning/40 bg-warning/10 p-4">
      <h2 id="pending-invoices" className="t-h3 flex items-center gap-2 text-fg">
        <Clock className="size-4" aria-hidden />
        {t('billing.pending_title')}
      </h2>
      <ul className="grid gap-2">
        {items.map((p) => (
          <li key={p.id} className="flex flex-wrap items-center justify-between gap-3 rounded-md bg-surface p-3">
            <div className="min-w-0">
              <p className="t-body font-semibold text-fg">
                {t(`billing.purpose_${p.purpose}`, { defaultValue: p.purpose })}
                {p.purpose_code ? ` · ${p.purpose_code}` : ''} — {rupiah(p.amount)}
              </p>
              <p className="t-small text-muted">
                <span className="t-mono">{p.reference}</span>
                {p.expires_at ? ` · ${t('billing.pay_before', { date: dateTime(p.expires_at) })}` : ''}
              </p>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              {p.redirect_url && (
                <a href={p.redirect_url} target="_blank" rel="noopener noreferrer" className="inline-flex h-9 items-center justify-center rounded-md bg-primary px-3 text-sm font-semibold text-primary-contrast hover:bg-primary-hover">
                  {t('billing.pay_now')}
                </a>
              )}
              <CancelInvoiceButton paymentId={p.id} onDone={() => { list.reload(); onChange?.() }} />
            </div>
          </li>
        ))}
      </ul>
    </section>
  )
}
