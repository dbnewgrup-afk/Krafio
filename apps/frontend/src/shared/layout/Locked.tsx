import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react'
import { Lock } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Skeleton } from '@/shared/ui/States'
import { http } from '@/api-client/http'
import { rupiah, dateTime, number } from '@/shared/lib/format'
import { useAuth } from '@/shared/context/AuthContext'

type Variant = 'locked' | 'quota' | 'expired'
interface Unlocks {
  plans: { code: string; name: string; price_monthly: number }[]
  addons: { code: string; name: string; price: number }[]
}
interface Ctx {
  openLocked: (feature: string, variant?: Variant) => void
}
const LockedCtx = createContext<Ctx | null>(null)

// Modal Fitur Terkunci / Upsell (§10.5): harga dari API, varian kuota habis & langganan kedaluwarsa.
export function LockedFeatureProvider({ children }: { children: ReactNode }) {
  const { t } = useTranslation()
  const { me } = useAuth()
  const nav = useNavigate()
  const [state, setState] = useState<{ feature: string; variant: Variant } | null>(null)
  const [data, setData] = useState<Unlocks | null>(null)

  useEffect(() => {
    if (!state) return
    setData(null)
    http
      .get<{ data: Unlocks }>('/public/catalog/unlocks', { feature: state.feature })
      .then((r) => setData(r.data))
      .catch(() => setData({ plans: [], addons: [] }))
  }, [state])

  const openLocked = useCallback((feature: string, variant: Variant = 'locked') => setState({ feature, variant }), [])
  const ent = state && me?.entitlements[state.feature]
  const close = () => setState(null)

  return (
    <LockedCtx.Provider value={{ openLocked }}>
      {children}
      <Modal
        open={!!state}
        onClose={close}
        size="upsell"
        icon={<Lock className="size-5 text-primary" aria-hidden />}
        title={state ? t(`features.${state.feature.replace('.', '_')}`) : ''}
        footer={
          state && (
            <>
              <Button variant="ghost" onClick={close}>
                {t('locked.later')}
              </Button>
              {state.variant === 'expired' ? (
                <Button onClick={() => { close(); nav('/app/subscription') }}>{t('locked.renew')}</Button>
              ) : (
                <>
                  <Button variant="secondary" onClick={() => { close(); nav('/app/addons') }}>
                    {state.variant === 'quota' ? t('locked.buy_quota') : t('locked.buy_addon')}
                  </Button>
                  <Button onClick={() => { close(); nav('/app/subscription') }}>{t('locked.see_plans')}</Button>
                </>
              )}
            </>
          )
        }
      >
        {state && (
          <div className="grid gap-4">
            <p className="t-body text-fg">{t(`locked.${state.variant}_body`)}</p>
            {state.variant === 'quota' && ent && (
              <div className="rounded-md bg-surface-2 p-3">
                <p className="t-small text-muted">{t('locked.remaining')}</p>
                <p className="font-semibold tabular-nums">
                  {number(ent.remaining ?? 0)} / {number(ent.quota ?? 0)}
                </p>
                {ent.reset_at && <p className="t-small text-muted">{t('locked.reset_at', { date: dateTime(ent.reset_at, false) })}</p>}
              </div>
            )}
            {state.variant !== 'expired' && (
              <div className="grid gap-2">
                <p className="t-label text-muted">{t('locked.available_in')}</p>
                {!data ? (
                  <Skeleton className="h-16 w-full" />
                ) : data.plans.length + data.addons.length === 0 ? (
                  <p className="t-small text-muted">{t('locked.contact_admin')}</p>
                ) : (
                  <ul className="grid gap-1.5">
                    {data.plans.map((p) => (
                      <li key={p.code} className="flex justify-between gap-3 rounded-md border border-line px-3 py-2 text-sm">
                        <span>{t('locked.plan_name', { name: p.name })}</span>
                        <span className="font-semibold tabular-nums">{rupiah(p.price_monthly)}{t('pricing.per_month')}</span>
                      </li>
                    ))}
                    {data.addons.map((a) => (
                      <li key={a.code} className="flex justify-between gap-3 rounded-md border border-line px-3 py-2 text-sm">
                        <span>{t('locked.addon_name', { name: a.name })}</span>
                        <span className="font-semibold tabular-nums">{rupiah(a.price)}</span>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            )}
            <p className="t-small text-muted">{t('locked.your_plan', { plan: me?.plan?.name ?? t('locked.no_plan') })}</p>
          </div>
        )}
      </Modal>
    </LockedCtx.Provider>
  )
}

export function useLocked(): Ctx {
  const c = useContext(LockedCtx)
  if (!c) throw new Error('LockedFeatureProvider missing')
  return c
}
