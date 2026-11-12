import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Undo2 } from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import { useApi } from '@/shared/hooks/useApi'
import { useToast } from '@/shared/context/ToastContext'
import { Card, CardTitle } from '@/shared/ui/Misc'
import { Button } from '@/shared/ui/Button'
import { StatusChip } from '@/shared/ui/Status'
import { Modal } from '@/shared/ui/Modal'
import { TextArea } from '@/shared/ui/Field'
import { Skeleton } from '@/shared/ui/States'
import { dateTime, rupiah } from '@/shared/lib/format'

interface Row {
  id: number
  reference: string
  purpose: 'subscription' | 'addon' | 'custom' | 'topup'
  item: string | null
  cycle: string | null
  amount: number
  gateway: string
  status: 'paid' | 'refunded'
  paid_at: string | null
  refunded_at: string | null
  refund_method: 'balance' | 'transfer' | null
  refund_pending: boolean
  refundable: boolean
}

/** Riwayat pembayaran + pengajuan refund (diputuskan admin di Approval › Refund). */
export function PaymentHistory() {
  const { t } = useTranslation()
  const toast = useToast()
  const list = useApi<Row[]>('/app/payments-history')
  const [target, setTarget] = useState<Row | null>(null)
  const [reason, setReason] = useState('')
  const [err, setErr] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  if (!list.data) return <Skeleton className="mt-8 h-40" />
  const rows = list.data

  const submit = async () => {
    if (!target) return
    setBusy(true)
    setErr(null)
    try {
      await http.post(`/app/payments/${target.id}/refund`, { reason })
      toast.push('success', t('refund.requested'))
      setTarget(null)
      setReason('')
      list.reload()
    } catch (e) {
      setErr(e instanceof ApiError ? (e.fields.reason?.[0] ?? e.message) : t('errors.server_body'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <Card className="mt-8">
      <CardTitle>{t('refund.history_title')}</CardTitle>
      {rows.length === 0 ? (
        <p className="t-small text-muted">{t('refund.history_empty')}</p>
      ) : (
        <ul className="divide-y divide-line">
          {rows.map((r) => (
            <li key={r.id} className="grid gap-2 py-3 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center sm:gap-4">
              <div className="grid gap-0.5">
                <span className="text-sm font-medium text-fg">
                  {t(`refund.purpose.${r.purpose}`)}{r.item && r.purpose !== 'topup' ? ` · ${r.item}` : ''}{r.cycle ? ` · ${t(`pricing.${r.cycle}`, { defaultValue: r.cycle })}` : ''}
                </span>
                <span className="t-small text-muted">
                  <span className="t-mono">{r.reference}</span> · {r.paid_at ? dateTime(r.paid_at, false) : '—'} · {t(`refund.gateway.${r.gateway}`, { defaultValue: r.gateway })}
                </span>
              </div>
              <span className="text-sm font-semibold tabular-nums text-fg">{rupiah(r.amount)}</span>
              <div className="flex flex-wrap items-center gap-2 sm:justify-end">
                {r.status === 'refunded' ? (
                  <StatusChip status="refunded" label={r.refund_method === 'transfer' ? t('refund.refunded_transfer') : t('refund.refunded_balance')} />
                ) : r.refund_pending ? (
                  <StatusChip status="pending" label={t('refund.pending')} />
                ) : r.refundable ? (
                  <Button size="sm" variant="secondary" icon={<Undo2 className="size-4" aria-hidden />} onClick={() => { setTarget(r); setErr(null) }}>{t('refund.request')}</Button>
                ) : (
                  <StatusChip status="paid" label={t('status.paid', { defaultValue: 'Dibayar' })} />
                )}
              </div>
            </li>
          ))}
        </ul>
      )}
      <p className="t-small mt-3 text-muted">{t('refund.policy_note')}</p>

      <Modal
        open={!!target}
        onClose={() => setTarget(null)}
        size="form"
        dirty={!!reason}
        title={t('refund.request_title')}
        footer={
          <>
            <Button variant="secondary" onClick={() => setTarget(null)}>{t('common.cancel')}</Button>
            <Button loading={busy} disabled={reason.trim().length < 10} onClick={submit}>{t('refund.submit', { amount: rupiah(target?.amount ?? 0) })}</Button>
          </>
        }
      >
        {target && (
          <div className="grid gap-4">
            <p className="t-small rounded-md bg-warning/12 p-3 text-fg">{target.purpose === 'topup' ? t('refund.topup_note') : t('refund.item_note')}</p>
            <TextArea label={t('refund.reason')} required rows={4} value={reason} onChange={(e) => setReason(e.target.value)} hint={t('refund.reason_hint')} error={err ?? undefined} />
          </div>
        )}
      </Modal>
    </Card>
  )
}
