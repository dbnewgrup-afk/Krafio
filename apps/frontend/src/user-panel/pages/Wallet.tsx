import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Plus, ArrowDownLeft, ArrowUpRight, Check, Banknote, Trash2 } from 'lucide-react'
import { http, ApiError, newIdempotencyKey } from '@/api-client/http'
import type { LedgerEntry, Withdrawal } from '@/api-client/types'
import { useAuth } from '@/shared/context/AuthContext'
import { useToast } from '@/shared/context/ToastContext'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { useApi } from '@/shared/hooks/useApi'
import { Card, CardTitle, ChipRow, PageHeader, Segmented } from '@/shared/ui/Misc'
import { Button, ButtonLink } from '@/shared/ui/Button'
import { DataTable, useServerList } from '@/shared/ui/DataTable'
import { StatusChip } from '@/shared/ui/Status'
import { Modal } from '@/shared/ui/Modal'
import { ConfirmDialog } from '@/shared/ui/ConfirmDialog'
import { SelectField, TextField } from '@/shared/ui/Field'
import { EmptyState, Skeleton } from '@/shared/ui/States'
import { LegalModal } from '@/shared/layout/Legal'
import { useLoader } from '@/shared/layout/PageLoader'
import { dateTime, rupiah } from '@/shared/lib/format'
import { cn } from '@/shared/lib/cn'
import { WalletShowcase } from '@/shared/ui/card-showcase/WalletShowcase'

function BalanceCards() {
  const { t } = useTranslation()
  const { me } = useAuth()
  if (!me) return null
  return (
    <div className="grid gap-3 sm:grid-cols-2">
      <div className="grid gap-1 rounded-lg border border-line border-l-4 border-l-wallet-topup bg-surface p-4 md:p-5">
        <span className="t-small text-muted">{t('wallet.topup')}</span>
        <span className="text-[28px] font-bold leading-9 tabular-nums text-fg">{rupiah(me.balances.topup)}</span>
        <span className="t-small text-muted">{t('wallet.not_withdrawable')}</span>
        <div className="mt-2">
          <ButtonLink to="/app/wallet/topup" size="sm" icon={<Plus className="size-4" aria-hidden />}>
            {t('user.nav.topup')}
          </ButtonLink>
        </div>
      </div>
      <div className="grid gap-1 rounded-lg border border-line border-l-4 border-l-wallet-commission bg-surface p-4 md:p-5">
        <span className="t-small text-muted">{t('wallet.commission')}</span>
        <span className="text-[28px] font-bold leading-9 tabular-nums text-fg">{rupiah(me.balances.commission)}</span>
        <span className="t-small text-muted">
          {t('wallet.withdrawable_min', { min: rupiah(100000) })}
          {me.balances.commission_reserved > 0 && ` — ${t('wallet.reserved', { amount: rupiah(me.balances.commission_reserved) })}`}
        </span>
        <div className="mt-2">
          <ButtonLink to="/app/wallet/withdrawals" size="sm" variant="secondary" icon={<Banknote className="size-4" aria-hidden />}>
            {t('wallet.withdraw')}
          </ButtonLink>
        </div>
      </div>
    </div>
  )
}

export function WalletPage() {
  const { t } = useTranslation()
  useDocTitle(t('user.nav.balance'))
  const [wallet, setWallet] = useState<'all' | 'topup' | 'commission'>('all')
  const list = useServerList<LedgerEntry>('/app/wallet/ledger', { wallet: '' })
  return (
    <>
      <PageHeader title={t('user.nav.balance')} subtitle={t('wallet.subtitle')} crumbs={[{ label: t('user.nav.wallet') }, { label: t('user.nav.balance') }]} />
      <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
        <WalletShowcase area="user" />
        <div className="grid content-start gap-4"><BalanceCards /></div>
      </div>
      <section className="mt-6 grid gap-3">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h2 className="t-h2 text-fg">{t('wallet.history')}</h2>
          <Segmented
            label={t('wallet.history')}
            value={wallet}
            onChange={(v) => {
              setWallet(v)
              list.update({ wallet: v === 'all' ? '' : v })
            }}
            items={[
              { value: 'all', label: t('table.all') },
              { value: 'topup', label: t('wallet.topup_short') },
              { value: 'commission', label: t('wallet.commission_short') },
            ]}
          />
        </div>
        <DataTable<LedgerEntry>
          list={list}
          exportName="ledger"
          emptyTitle={t('wallet.empty')}
          columns={[
            { key: 'description', header: t('wallet.col_desc'), primary: true, render: (r) => <span className="inline-flex items-center gap-2">{r.direction === 'credit' ? <ArrowDownLeft className="size-4 text-success" aria-label={t('wallet.credit')} /> : <ArrowUpRight className="size-4 text-danger" aria-label={t('wallet.debit')} />}{r.description}</span> },
            { key: 'wallet', header: t('wallet.col_wallet'), mobile: true, render: (r) => (r.wallet === 'topup' ? t('wallet.topup_short') : t('wallet.commission_short')) },
            { key: 'amount', header: t('wallet.col_amount'), align: 'right', sortable: true, mobile: true, render: (r) => <span className={r.direction === 'credit' ? 'text-success' : 'text-fg'}>{r.direction === 'credit' ? '+' : '−'}{rupiah(r.amount)}</span> },
            { key: 'balance_after', header: t('wallet.col_balance'), align: 'right', render: (r) => rupiah(r.balance_after) },
            { key: 'created_at', header: t('wallet.col_date'), sortable: true, mobile: true, render: (r) => dateTime(r.created_at) },
          ]}
        />
      </section>
    </>
  )
}

/* ---------------- Top-up (§19.3, §10.6, PRD 5.3) ---------------- */
export function TopupPage() {
  const { t } = useTranslation()
  useDocTitle(t('user.nav.topup'))
  const { settings, refresh } = useAuth()
  const toast = useToast()
  const nav = useNavigate()
  const { block, unblock } = useLoader()
  const min = settings?.topup_min ?? 50000
  const max = settings?.topup_max ?? 1000000
  const [amount, setAmount] = useState(100000)
  const [raw, setRaw] = useState('100.000')
  const [method, setMethod] = useState('va')
  const [confirm, setConfirm] = useState(false)
  const [busy, setBusy] = useState(false)
  const [invoice, setInvoice] = useState<{ id: number; reference: string; amount: number; status: string; simulate: boolean; redirect_url?: string | null } | null>(null)
  const quick = [50000, 100000, 250000, 500000, 1000000].filter((v) => v >= min && v <= max)
  const invalid = amount < min || amount > max
  const key = useState(() => newIdempotencyKey())[0]

  const setFromRaw = (v: string) => {
    const digits = v.replace(/\D/g, '').slice(0, 9)
    const n = Number(digits || 0)
    setAmount(n)
    setRaw(n ? new Intl.NumberFormat('id-ID').format(n) : '')
  }

  const create = async () => {
    setBusy(true)
    block(t('wallet.creating_invoice'))
    try {
      const r = await http.post<{ data: { id: number; reference: string; amount: number; status: string; simulate: boolean; redirect_url?: string | null } }>('/app/wallet/topups', { amount, method }, { idempotencyKey: key })
      setInvoice(r.data)
      setConfirm(false)
    } catch (e) {
      toast.push('error', e instanceof ApiError ? e.fields.amount?.[0] ?? e.message : t('errors.server_body'))
    } finally {
      setBusy(false)
      unblock()
    }
  }

  return (
    <>
      <PageHeader title={t('user.nav.topup')} subtitle={t('wallet.topup_sub', { min: rupiah(min), max: rupiah(max) })} crumbs={[{ label: t('user.nav.wallet'), to: '/app/wallet' }, { label: t('user.nav.topup') }]} />
      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
        <Card>
          {invoice ? (
            <div className="grid gap-4">
              <div className="flex items-center gap-3">
                <StatusChip status={invoice.status} />
                <span className="t-mono text-muted">{invoice.reference}</span>
              </div>
              <h2 className="t-h2">{t('wallet.invoice_created', { amount: rupiah(invoice.amount) })}</h2>
              <p className="t-body text-muted">{t('wallet.invoice_body')}</p>
              {invoice.redirect_url && (
                <a href={invoice.redirect_url} className="inline-flex h-11 items-center justify-center rounded-md bg-primary px-4 font-semibold text-primary-contrast hover:bg-primary-hover">
                  {t('billing.go_to_payment')}
                </a>
              )}
              {invoice.simulate && (
                <div className="grid gap-2 rounded-md border border-warning/40 bg-warning/10 p-3">
                  <p className="t-small text-fg">{t('wallet.sim_note')}</p>
                  <Button
                    variant="secondary"
                    size="sm"
                    onClick={async () => {
                      await http.post(`/app/wallet/topups/${invoice.id}/simulate-paid`)
                      await refresh()
                      toast.push('success', t('wallet.topup_success'))
                      nav('/app/wallet')
                    }}
                  >
                    {t('wallet.sim_pay')}
                  </Button>
                </div>
              )}
              <ButtonLink to="/app/wallet" variant="secondary">
                {t('wallet.back_to_balance')}
              </ButtonLink>
            </div>
          ) : (
            <div className="grid gap-5">
              <div className="grid gap-2">
                <span className="t-label text-fg">{t('wallet.quick_amount')}</span>
                <ChipRow label={t('wallet.quick_amount')} value={String(amount)} onChange={(v) => setFromRaw(v)} items={quick.map((q) => ({ value: String(q), label: rupiah(q) }))} />
              </div>
              <TextField
                label={t('wallet.amount')}
                required
                inputMode="numeric"
                prefix="Rp"
                value={raw}
                onChange={(e) => setFromRaw(e.target.value)}
                error={raw && invalid ? t('wallet.amount_range', { min: rupiah(min), max: rupiah(max) }) : null}
                hint={t('wallet.amount_range', { min: rupiah(min), max: rupiah(max) })}
              />
              <SelectField label={t('wallet.method')} value={method} onChange={(e) => setMethod(e.target.value)}>
                <option value="va">{t('wallet.methods.va')}</option>
                <option value="ewallet">{t('wallet.methods.ewallet')}</option>
                <option value="qris">QRIS</option>
              </SelectField>
              <Button size="lg" disabled={invalid} onClick={() => setConfirm(true)} className="max-md:w-full">
                {t('wallet.continue')}
              </Button>
            </div>
          )}
        </Card>
        <aside className="grid content-start gap-3">
          <div className="rounded-lg border border-line bg-bg-subtle p-4">
            <p className="t-label text-fg">{t('wallet.rules_title')}</p>
            <ul className="mt-2 grid gap-1.5">
              {['r1', 'r2', 'r3'].map((r) => (
                <li key={r} className="t-small flex items-start gap-2 text-muted">
                  <Check className="mt-0.5 size-4 shrink-0 text-success" aria-hidden />
                  {t(`wallet.topup_rules.${r}`)}
                </li>
              ))}
            </ul>
          </div>
        </aside>
      </div>
      <Modal
        open={confirm}
        onClose={() => setConfirm(false)}
        critical={busy}
        size="confirm"
        title={t('wallet.confirm_topup')}
        footer={
          <>
            <Button variant="secondary" onClick={() => setConfirm(false)} disabled={busy}>
              {t('common.cancel')}
            </Button>
            <Button loading={busy} onClick={create}>
              {t('wallet.pay_amount', { amount: rupiah(amount) })}
            </Button>
          </>
        }
      >
        <dl className="grid gap-2 text-sm">
          <Row k={t('wallet.item')} v={t('wallet.topup')} />
          <Row k={t('wallet.amount')} v={rupiah(amount)} />
          <Row k={t('wallet.fee')} v={rupiah(0)} />
          <Row k={t('wallet.total')} v={rupiah(amount)} strong />
          <Row k={t('wallet.source')} v={t('wallet.gateway')} />
        </dl>
      </Modal>
    </>
  )
}

function Row({ k, v, strong }: { k: string; v: string; strong?: boolean }) {
  return (
    <div className={cn('flex justify-between gap-3', strong && 'border-t border-line pt-2')}>
      <dt className="text-muted">{k}</dt>
      <dd className={cn('tabular-nums', strong ? 'font-bold text-fg' : 'text-fg')}>{v}</dd>
    </div>
  )
}

/* ---------------- Withdrawal (§19.3, PRD 5.5) ---------------- */
interface Destination {
  id: number
  type: 'bank' | 'ewallet'
  provider: string
  holder: string
  account_masked: string
}
const STEPS = ['requested', 'under_review', 'approved', 'processing', 'paid'] as const

export function WithdrawalsPage() {
  const { t } = useTranslation()
  useDocTitle(t('user.nav.withdrawals'))
  const { me, refresh, settings } = useAuth()
  const toast = useToast()
  const min = settings?.withdraw_min ?? 100000
  const dests = useApi<Destination[]>('/app/wallet/destinations')
  const wds = useApi<Withdrawal[]>('/app/wallet/withdrawals')
  const [addOpen, setAddOpen] = useState(false)
  const [newDest, setNewDest] = useState({ type: 'bank', provider: '', holder: '', account: '' })
  const [destErr, setDestErr] = useState<Record<string, string[]>>({})
  const [destId, setDestId] = useState<number | null>(null)
  const [raw, setRaw] = useState('')
  const [confirm, setConfirm] = useState(false)
  const [terms, setTerms] = useState(false)
  const [delDest, setDelDest] = useState<Destination | null>(null)
  const amount = Number(raw.replace(/\D/g, '') || 0)
  const available = me?.balances.commission ?? 0
  const err = raw ? (amount < min ? t('wallet.wd_min', { min: rupiah(min) }) : amount > available ? t('wallet.wd_exceeds') : null) : null
  const dest = dests.data?.find((d) => d.id === destId) ?? dests.data?.[0] ?? null
  const key = useState(() => newIdempotencyKey())[0]

  const submit = async () => {
    await http.post('/app/wallet/withdrawals', { amount, destination_id: dest!.id }, { idempotencyKey: key })
    toast.push('success', t('wallet.wd_submitted'))
    setRaw('')
    wds.reload()
    await refresh()
  }

  return (
    <>
      <PageHeader title={t('user.nav.withdrawals')} subtitle={t('wallet.wd_sub', { min: rupiah(min) })} crumbs={[{ label: t('user.nav.wallet'), to: '/app/wallet' }, { label: t('user.nav.withdrawals') }]} />
      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <Card>
          <CardTitle>{t('wallet.new_withdrawal')}</CardTitle>
          <div className="mb-4 rounded-md border-l-4 border-l-wallet-commission bg-surface-2 p-3">
            <p className="t-small text-muted">{t('wallet.available_commission')}</p>
            <p className="text-xl font-bold tabular-nums text-fg">{rupiah(available)}</p>
          </div>
          <div className="grid gap-4">
            <div className="grid gap-2">
              <span className="t-label text-fg">{t('wallet.destination')}</span>
              {!dests.data ? (
                <Skeleton className="h-14" />
              ) : dests.data.length === 0 ? (
                <p className="t-small text-muted">{t('wallet.no_destination')}</p>
              ) : (
                <ul className="grid gap-2" role="radiogroup" aria-label={t('wallet.destination')}>
                  {dests.data.map((d) => (
                    <li key={d.id} className="flex items-center gap-2">
                      <button type="button" role="radio" aria-checked={dest?.id === d.id} onClick={() => setDestId(d.id)} className={cn('flex min-h-14 flex-1 items-center justify-between gap-3 rounded-md border px-3 text-left', dest?.id === d.id ? 'border-primary bg-primary-soft' : 'border-line hover:bg-surface-2')}>
                        <span>
                          <span className="block text-sm font-semibold text-fg">
                            {d.provider} — {d.account_masked}
                          </span>
                          <span className="t-small text-muted">{d.holder}</span>
                        </span>
                        {dest?.id === d.id && <Check className="size-4 text-primary" aria-hidden />}
                      </button>
                      <button type="button" aria-label={t('common.delete')} onClick={() => setDelDest(d)} className="grid size-11 place-items-center rounded-md text-muted hover:bg-surface-2 hover:text-danger">
                        <Trash2 className="size-4" aria-hidden />
                      </button>
                    </li>
                  ))}
                </ul>
              )}
              <Button variant="secondary" size="sm" className="justify-self-start" icon={<Plus className="size-4" aria-hidden />} onClick={() => setAddOpen(true)}>
                {t('wallet.add_destination')}
              </Button>
            </div>
            <TextField label={t('wallet.amount')} required inputMode="numeric" prefix="Rp" value={raw} onChange={(e) => { const n = Number(e.target.value.replace(/\D/g, '').slice(0, 9) || 0); setRaw(n ? new Intl.NumberFormat('id-ID').format(n) : '') }} error={err} hint={t('wallet.wd_hint', { min: rupiah(min) })} />
            <p className="t-small text-muted">{t('wallet.wd_fee_note')}</p>
            <Button size="lg" disabled={!dest || !!err || !amount} onClick={() => setTerms(true)} className="max-md:w-full">
              {t('wallet.review_withdrawal')}
            </Button>
          </div>
        </Card>

        <section className="grid content-start gap-3">
          <h2 className="t-h2 text-fg">{t('wallet.wd_history')}</h2>
          {!wds.data ? (
            <Skeleton className="h-40" />
          ) : wds.data.length === 0 ? (
            <EmptyState title={t('wallet.wd_empty')} />
          ) : (
            <ul className="grid gap-3">
              {wds.data.map((w) => (
                <li key={w.id} className="grid gap-3 rounded-lg border border-line bg-surface p-4">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="text-lg font-bold tabular-nums text-fg">{rupiah(w.amount)}</span>
                    <StatusChip status={w.status} />
                  </div>
                  <p className="t-small text-muted">
                    {w.destination.provider} — {w.destination.account_masked} — {dateTime(w.created_at)}
                  </p>
                  {w.status !== 'rejected' && w.status !== 'cancelled' ? <Tracker status={w.status} /> : w.admin_note && <p className="t-small rounded-md bg-danger/10 p-2 text-danger">{w.admin_note}</p>}
                  {w.reference && <p className="t-mono text-muted">Ref: {w.reference}</p>}
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>

      {/* Ketentuan withdrawal pertama per versi (§11.2) lalu modal konfirmasi keuangan (§10.6) */}
      <LegalModal
        type="affiliate_terms"
        open={terms}
        mode="read"
        onClose={() => {
          setTerms(false)
          setConfirm(true)
        }}
      />
      <ConfirmDialog
        open={confirm}
        onClose={() => setConfirm(false)}
        title={t('wallet.confirm_withdrawal')}
        confirmLabel={t('wallet.submit_amount', { amount: rupiah(amount) })}
        critical
        onConfirm={submit}
        body={
          <dl className="grid gap-2 text-sm">
            <Row k={t('wallet.source')} v={t('wallet.commission')} />
            <Row k={t('wallet.destination')} v={dest ? `${dest.provider} — ${dest.account_masked}` : '—'} />
            <Row k={t('wallet.amount')} v={rupiah(amount)} />
            <Row k={t('wallet.estimate')} v={t('wallet.wd_time')} strong />
          </dl>
        }
      />
      <ConfirmDialog
        open={!!delDest}
        onClose={() => setDelDest(null)}
        destructive
        title={t('wallet.delete_destination')}
        confirmLabel={t('wallet.delete_destination')}
        onConfirm={async () => {
          await http.del(`/app/wallet/destinations/${delDest!.id}`)
          dests.reload()
        }}
      />
      <Modal
        open={addOpen}
        onClose={() => setAddOpen(false)}
        size="form"
        dirty={!!(newDest.provider || newDest.account)}
        title={t('wallet.add_destination')}
        footer={
          <>
            <Button variant="secondary" onClick={() => setAddOpen(false)}>
              {t('common.cancel')}
            </Button>
            <Button
              onClick={async () => {
                try {
                  await http.post('/app/wallet/destinations', newDest)
                  setAddOpen(false)
                  setNewDest({ type: 'bank', provider: '', holder: '', account: '' })
                  dests.reload()
                } catch (e) {
                  if (e instanceof ApiError) setDestErr(e.fields)
                }
              }}
            >
              {t('common.save')}
            </Button>
          </>
        }
      >
        <div className="grid gap-4">
          <SelectField label={t('wallet.dest_type')} value={newDest.type} onChange={(e) => setNewDest({ ...newDest, type: e.target.value })}>
            <option value="bank">{t('wallet.bank')}</option>
            <option value="ewallet">{t('wallet.ewallet')}</option>
          </SelectField>
          <TextField label={t('wallet.provider')} required value={newDest.provider} onChange={(e) => setNewDest({ ...newDest, provider: e.target.value })} error={destErr.provider?.[0]} placeholder="BCA / Mandiri / DANA" />
          <TextField label={t('wallet.holder')} required value={newDest.holder} onChange={(e) => setNewDest({ ...newDest, holder: e.target.value })} error={destErr.holder?.[0]} autoComplete="name" />
          <TextField label={t('wallet.account_number')} required inputMode="numeric" value={newDest.account} onChange={(e) => setNewDest({ ...newDest, account: e.target.value })} error={destErr.account?.[0]} />
        </div>
      </Modal>
      <Link to="/app/affiliate" className="sr-only">
        {t('user.nav.affiliate')}
      </Link>
    </>
  )
}

function Tracker({ status }: { status: Withdrawal['status'] }) {
  const { t } = useTranslation()
  const idx = STEPS.indexOf(status as (typeof STEPS)[number])
  return (
    <ol className="grid grid-cols-5 gap-1" aria-label={t('wallet.tracker')}>
      {STEPS.map((s, i) => (
        <li key={s} className="grid gap-1">
          <span className={cn('h-1.5 rounded-full', i <= idx ? 'bg-success' : 'bg-line')} />
          <span className={cn('text-[11px] leading-4', i === idx ? 'font-semibold text-fg' : 'text-muted')} aria-current={i === idx ? 'step' : undefined}>
            {t(`status.${s}`)}
          </span>
        </li>
      ))}
    </ol>
  )
}
