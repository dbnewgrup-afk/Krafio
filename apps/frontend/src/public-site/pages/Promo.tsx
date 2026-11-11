import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ArrowRight, TicketPercent } from 'lucide-react'
import { http } from '@/api-client/http'
import { ButtonLink } from '@/shared/ui/Button'
import { EmptyState, Skeleton } from '@/shared/ui/States'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { PHOTO } from '@/shared/lib/assets'
import type { Promo } from '@/shared/lib/promo'
import { PageIntro } from './Catalog'
import { PromoCard } from '../components/PromoCard'

/** Ambil promo publik sekali; null = memuat, [] = kosong/gagal. */
function usePublicPromos(): { promos: Promo[] | null; failed: boolean; reload: () => void } {
  const [promos, setPromos] = useState<Promo[] | null>(null)
  const [failed, setFailed] = useState(false)
  const [n, setN] = useState(0)
  useEffect(() => {
    let alive = true
    http
      .get<{ data: Promo[] }>('/public/promos')
      .then((r) => alive && (setPromos(r.data), setFailed(false)))
      .catch(() => alive && (setPromos([]), setFailed(true)))
    return () => {
      alive = false
    }
  }, [n])
  return { promos, failed, reload: () => { setPromos(null); setN((x) => x + 1) } }
}

export default function PromoPage() {
  const { t } = useTranslation()
  useDocTitle(t('promo.title'))
  const { promos, failed, reload } = usePublicPromos()
  const [now] = useState(() => Date.now())
  const [first, ...rest] = promos ?? []
  return (
    <>
      <PageIntro title={t('promo.title')} body={t('promo.body')} art={first?.image || PHOTO.pricing}>
        {promos && promos.length > 0 && (
          <p className="t-small inline-flex items-center gap-2 pt-1 text-muted">
            <TicketPercent className="size-4 text-primary" aria-hidden />
            {t('promo.count', { count: promos.length })}
          </p>
        )}
      </PageIntro>

      <div className="mx-auto max-w-[1360px] px-4 py-8 md:px-6 md:py-12 lg:px-8">
        {!promos ? (
          <div className="grid gap-4">
            <Skeleton className="h-48" />
            <div className="grid gap-4 md:grid-cols-2">
              <Skeleton className="h-44" />
              <Skeleton className="h-44" />
            </div>
          </div>
        ) : promos.length === 0 ? (
          <EmptyState
            title={failed ? t('promo.error_title') : t('promo.empty_title')}
            body={failed ? t('promo.error_body') : t('promo.empty_body')}
            action={failed ? <button type="button" onClick={reload} className="t-label text-primary hover:underline">{t('common.retry')}</button> : <ButtonLink to="/pricing" variant="secondary">{t('promo.see_plans')}</ButtonLink>}
          />
        ) : (
          <div className="grid gap-4 lg:gap-6">
            <PromoCard p={first} now={now} featured />
            {rest.length > 0 && (
              <ul className="grid gap-4 md:grid-cols-2 lg:gap-6">
                {rest.map((p) => (
                  <li key={p.id} className="grid">
                    <PromoCard p={p} now={now} />
                  </li>
                ))}
              </ul>
            )}
          </div>
        )}

        {/* Cara pakai — daftar bernomor besar (memecah ritme kartu) */}
        <section aria-labelledby="promo-how" className="mt-12 grid gap-6 border-t border-line pt-10 lg:grid-cols-[minmax(0,4fr)_minmax(0,8fr)] lg:gap-12">
          <div className="grid content-start gap-2">
            <h2 id="promo-how" className="t-h1 text-fg">{t('promo.how_title')}</h2>
            <p className="t-body text-muted">{t('promo.how_body')}</p>
          </div>
          <ol className="grid gap-5 sm:grid-cols-3">
            {(['copy', 'choose', 'apply'] as const).map((k, i) => (
              <li key={k} className="grid content-start gap-2">
                <span className="text-[40px] font-bold leading-none tracking-[-0.02em] text-primary tabular-nums">{String(i + 1).padStart(2, '0')}</span>
                <h3 className="t-h3 text-fg">{t(`promo.how.${k}_title`)}</h3>
                <p className="t-small text-muted">{t(`promo.how.${k}_body`)}</p>
              </li>
            ))}
          </ol>
        </section>
        <p className="t-small mt-8 text-muted">
          {t('promo.fine_print')}{' '}
          <Link to="/terms" className="text-primary hover:underline">{t('legal.terms_short')}</Link>
        </p>
      </div>
    </>
  )
}

/** Strip promo beranda — hanya muncul bila ada promo aktif (tidak menyisakan ruang kosong). */
export function PromoStrip() {
  const { t } = useTranslation()
  const { promos } = usePublicPromos()
  const [now] = useState(() => Date.now())
  if (!promos || promos.length === 0) return null
  return (
    <section className="mx-auto max-w-[1360px] px-4 pt-8 md:px-6 md:pt-12 lg:px-8" aria-labelledby="promo-strip-h">
      <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div className="grid gap-1">
          <p className="text-[11px] font-semibold uppercase tracking-[0.08em] text-primary">{t('promo.strip_overline')}</p>
          <h2 id="promo-strip-h" className="t-h2 text-fg">{t('promo.strip_title')}</h2>
        </div>
        <Link to="/promo" className="t-label inline-flex items-center gap-1 text-primary hover:underline">
          {t('promo.see_all', { count: promos.length })}
          <ArrowRight className="size-4" aria-hidden />
        </Link>
      </div>
      <ul className="grid gap-4 lg:grid-cols-2">
        {promos.slice(0, 2).map((p) => (
          <li key={p.id} className="grid">
            <PromoCard p={p} now={now} />
          </li>
        ))}
      </ul>
    </section>
  )
}
