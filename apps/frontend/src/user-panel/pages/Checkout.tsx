import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Tag, Info } from 'lucide-react'
import { http, ApiError, newIdempotencyKey } from '@/api-client/http'
import type { Quote } from '@/api-client/types'
import { useAuth } from '@/shared/context/AuthContext'
import { useToast } from '@/shared/context/ToastContext'
import { useLoader } from '@/shared/layout/PageLoader'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Segmented } from '@/shared/ui/Misc'
import { Skeleton } from '@/shared/ui/States'
import { inputCls } from '@/shared/ui/Field'
import { dateTime, rupiah } from '@/shared/lib/format'
import { cn } from '@/shared/lib/cn'

export interface CheckoutItem {
  kind: 'plan' | 'addon' | 'custom'
  code: string
  name: string
  price: number
  cycle?: 'monthly' | 'yearly'
}

/**
 * Modal konfirmasi keuangan (Design Spec §10.6) + PRD 55.4–55.5:
 * ringkasan harga normal, prorata upgrade, diskon (1 kupon ATAU promo otomatis, harga terbaik), PPN, total;
 * sumber dana saldo top-up atau payment gateway (tanpa kombinasi); tombol final berlabel nominal; idempotency key.
 */
export function CheckoutModal({ item, onClose, onDone }: { item: CheckoutItem | null; onClose: () => void; onDone: () => void }) {
  const { t } = useTranslation()
  const { me, refresh } = useAuth()
  const toast = useToast()
  const { block, unblock } = useLoader()
  const [source, setSource] = useState<'balance' | 'gateway'>('balance')
  const [code, setCode] = useState('')
  const [applied, setApplied] = useState('')
  const [quote, setQuote] = useState<Quote | null>(null)
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState<string | null>(null)
  const [pending, setPending] = useState<{ id: number; reference: string; amount: number; simulate: boolean; redirect_url?: string | null } | null>(null)
  const [key] = useState(() => newIdempotencyKey())

  useEffect(() => {
    if (!item) return
    setQuote(null)
    http
      .post<{ data: Quote }>('/app/orders/quote', { kind: item.kind, code: item.code, cycle: item.cycle, coupon: applied || undefined })
      .then((r) => setQuote(r.data))
      .catch((e) => setErr(e instanceof ApiError ? e.message : t('errors.server_body')))
  }, [item, applied, t])

  if (!item) return null
  const final = quote?.final ?? item.price
  const insufficient = source === 'balance' && final > 0 && (me?.balances.topup ?? 0) < final

  const pay = async () => {
    setBusy(true)
    setErr(null)
    block(t('billing.processing'))
    try {
      const r = await http.post<{ data: { status: string; payment?: { id: number; reference: string; amount: number; redirect_url?: string | null }; simulate?: boolean; effective_at?: string } }>(
        '/app/orders',
        { kind: item.kind, code: item.code, cycle: item.cycle, source, coupon: applied || undefined },
        { idempotencyKey: key },
      )
      await refresh()
      if (r.data.status === 'scheduled') {
        toast.push('success', t('billing.downgrade_scheduled', { date: dateTime(r.data.effective_at, false) }))
      } else if (r.data.status === 'pending_payment' && r.data.payment) {
        setPending({ ...r.data.payment, simulate: !!r.data.simulate })
        return
      } else {
        toast.push('success', t('billing.activated', { name: item.name }))
      }
      onDone()
      onClose()
    } catch (e) {
      setErr(e instanceof ApiError ? (e.code === 'insufficient_balance' ? t('billing.insufficient') : e.fields.coupon?.[0] ?? e.message) : t('errors.server_body'))
    } finally {
      setBusy(false)
      unblock()
    }
  }

  if (pending)
    return (
      <Modal open onClose={() => { onDone(); onClose() }} size="confirm" title={t('billing.invoice_created')}>
        <div className="grid gap-3">
          <p className="t-body text-muted">{t('billing.invoice_body', { amount: rupiah(pending.amount) })}</p>
          <p className="t-mono text-fg">{pending.reference}</p>
          {pending.redirect_url && (
            <a href={pending.redirect_url} className="inline-flex h-11 items-center justify-center rounded-md bg-primary px-4 font-semibold text-primary-contrast hover:bg-primary-hover">
              {t('billing.go_to_payment')}
            </a>
          )}
          {pending.simulate && (
            <div className="grid gap-2 rounded-md border border-warning/40 bg-warning/10 p-3">
              <p className="t-small text-fg">{t('wallet.sim_note')}</p>
              <Button size="sm" variant="secondary" onClick={async () => { await http.post(`/app/wallet/topups/${pending.id}/simulate-paid`); await refresh(); toast.push('success', t('billing.activated', { name: item.name })); onDone(); onClose() }}>
                {t('wallet.sim_pay')}
              </Button>
            </div>
          )}
        </div>
      </Modal>
    )

  const isDowngrade = !!quote?.is_downgrade
  return (
    <Modal
      open
      onClose={onClose}
      critical={busy}
      size="form"
      title={t('billing.checkout_title')}
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={busy}>{t('common.cancel')}</Button>
          <Button loading={busy} disabled={!quote || (!isDowngrade && insufficient)} onClick={pay}>
            {isDowngrade ? t('billing.schedule_downgrade') : final > 0 ? t('wallet.pay_amount', { amount: rupiah(final) }) : t('billing.activate_free')}
          </Button>
        </>
      }
    >
      <div className="grid gap-4">
        {isDowngrade ? (
          <p className="t-small flex items-start gap-2 rounded-md bg-info/10 p-3 text-info">
            <Info className="mt-0.5 size-4 shrink-0" aria-hidden />
            {t('billing.downgrade_note', { date: dateTime(quote!.downgrade_at, false) })}
          </p>
        ) : null}
        {!quote ? (
          <Skeleton className="h-32" />
        ) : (
          <dl className="grid gap-2 text-sm">
            <Line k={t('wallet.item')} v={`${item.name}${item.cycle ? ` — ${t(`pricing.${item.cycle}`)}` : ''}`} />
            <Line k={t('billing.normal_price')} v={rupiah(quote.normal)} />
            {quote.prorata > 0 && <Line k={t('billing.prorata')} v={`−${rupiah(quote.prorata)}`} tone="success" />}
            {quote.discount > 0 && <Line k={quote.coupon?.auto ? t('billing.auto_promo', { name: quote.coupon.name }) : t('billing.coupon_discount', { code: quote.coupon?.code })} v={`−${rupiah(quote.discount)}`} tone="success" />}
            {quote.coupon && quote.discount === 0 && <Line k={t('billing.coupon_bonus')} v={quote.coupon.type === 'bonus_credit' ? t('billing.bonus_credits', { n: quote.coupon.value }) : t('billing.bonus_days', { n: quote.coupon.value })} tone="success" />}
            <Line k={quote.ppn_inclusive ? t('billing.ppn_included') : t('billing.ppn')} v={rupiah(quote.ppn)} />
            <Line k={t('wallet.total')} v={rupiah(quote.final)} strong />
          </dl>
        )}
        {item.kind !== 'custom' && !isDowngrade && (
          <div className="grid gap-1.5">
            <label htmlFor="coupon" className="t-label text-fg">{t('billing.coupon')} <span className="text-muted">({t('common.optional')})</span></label>
            <div className="flex gap-2">
              <div className="relative flex-1">
                <Tag className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" aria-hidden />
                <input id="coupon" value={code} onChange={(e) => setCode(e.target.value.toUpperCase())} className={cn(inputCls, 'pl-9 font-mono uppercase')} autoComplete="off" />
              </div>
              <Button variant="secondary" disabled={!code.trim()} onClick={() => setApplied(code.trim())}>{t('billing.apply')}</Button>
            </div>
            {applied && quote?.coupon_error && <p className="t-small text-danger">{quote.coupon_error}</p>}
            <p className="t-small text-muted">{t('billing.coupon_rules')}</p>
          </div>
        )}
        {!isDowngrade && final > 0 && (
          <div className="grid gap-1.5">
            <span className="t-label text-fg">{t('wallet.source')}</span>
            <Segmented label={t('wallet.source')} value={source} onChange={setSource} items={[{ value: 'balance', label: t('billing.from_balance') }, { value: 'gateway', label: t('wallet.gateway') }]} />
            {source === 'balance' && (
              <p className={cn('t-small', insufficient ? 'text-danger' : 'text-muted')}>
                {t('billing.balance_now', { amount: rupiah(me?.balances.topup ?? 0) })}
                {insufficient && ` — ${t('billing.insufficient')}`}
              </p>
            )}
          </div>
        )}
        <p className="t-small text-muted">{t('billing.snapshot_note')}</p>
        {err && <p role="alert" className="t-small rounded-md bg-danger/10 p-3 text-danger">{err}</p>}
      </div>
    </Modal>
  )
}

function Line({ k, v, strong, tone }: { k: string; v: string; strong?: boolean; tone?: 'success' }) {
  return (
    <div className={cn('flex justify-between gap-3', strong && 'border-t border-line pt-2')}>
      <dt className="text-muted">{k}</dt>
      <dd className={cn('text-right tabular-nums', strong ? 'font-bold text-fg' : tone === 'success' ? 'text-success' : 'text-fg')}>{v}</dd>
    </div>
  )
}
