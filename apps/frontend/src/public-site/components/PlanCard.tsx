import { useTranslation } from 'react-i18next'
import { Check, Minus } from 'lucide-react'
import type { Plan } from '@/api-client/types'
import { rupiah, number } from '@/shared/lib/format'
import { ButtonLink, Button } from '@/shared/ui/Button'
import { cn } from '@/shared/lib/cn'

/**
 * Kartu paket (Design Spec §18.3, PRD 55.2): satu "Direkomendasikan" ditonjolkan, harga & siklus jelas,
 * kredit/bulan sebagai satuan utama, limit teks & struktural, fitur ✓ / —. Enterprise → pengajuan custom.
 */
export function PlanCard({ plan, cycle, current, onChoose, choosing }: { plan: Plan; cycle: 'monthly' | 'yearly'; current?: boolean; onChoose?: (p: Plan) => void; choosing?: boolean }) {
  const { t } = useTranslation()
  const custom = !!(plan.limits as any).custom
  const price = cycle === 'monthly' ? plan.price_monthly : plan.price_yearly
  const q = plan.quotas as Record<string, number>
  const l = plan.limits as Record<string, any>
  const credits = Number(q.credits ?? 0)
  const yearlyMonths = cycle === 'yearly' && plan.price_monthly > 0 ? Math.round((plan.price_monthly * 12 - plan.price_yearly) / plan.price_monthly) : 0

  const rows: { on: boolean; text: string }[] = custom
    ? [{ on: true, text: t('pricing.enterprise_1') }, { on: true, text: t('pricing.enterprise_2') }, { on: true, text: t('pricing.enterprise_3') }]
    : [
        { on: credits > 0, text: t('pricing.r_credits', { n: number(credits), photos: number(Math.floor(credits / 8)) }) },
        { on: !!q['chatbot.basic'], text: t('pricing.r_chat', { n: number(q['chatbot.basic'] ?? 0) }) + (q['chatbot.premium'] ? t('pricing.r_chat_premium', { n: number(q['chatbot.premium']) }) : '') },
        { on: !!q['content.brainstorm'], text: t('pricing.r_brainstorm', { n: number(q['content.brainstorm'] ?? 0) }) },
        { on: Number(l.video_max_seconds ?? 0) > 0, text: Number(l.video_max_seconds ?? 0) > 0 ? t('pricing.r_video', { res: l.video_resolution ?? '720p', s: l.video_max_seconds }) : t('pricing.r_no_video') },
        { on: true, text: l.website_preview_only ? t('pricing.r_web_preview') : t('pricing.r_web', { n: l.websites ?? 0 }) },
        { on: !!q['seo.basic'], text: t('pricing.r_seo', { n: number(q['seo.basic'] ?? 0) }) + (plan.features.includes('seo.advanced') ? t('pricing.r_seo_adv') : '') },
        { on: Number(l.social_accounts ?? 0) > 0, text: Number(l.social_accounts ?? 0) > 0 ? t('pricing.r_social', { n: l.social_accounts }) : t('pricing.r_no_social') },
        { on: true, text: t('pricing.r_storage', { size: Number(l.storage_mb ?? 0) >= 1024 ? `${Math.round(Number(l.storage_mb) / 1024)} GB` : `${l.storage_mb ?? 0} MB`, p: l.projects ?? 0 }) },
        { on: !l.watermark, text: l.watermark ? t('pricing.r_watermark') : t('pricing.r_no_watermark') },
        { on: l.templates === 'all' || l.templates === 'partial', text: t(`pricing.r_templates_${l.templates ?? 'free'}`) },
      ]

  return (
    <article className={cn('relative flex h-full flex-col gap-4 rounded-lg border bg-surface p-5', plan.is_recommended ? 'border-2 border-primary shadow-k-md' : 'border-line')}>
      {plan.is_recommended && <span className="absolute -top-3 left-5 rounded-full bg-primary px-2.5 py-0.5 text-[12px] font-semibold text-primary-contrast">{t('pricing.recommended')}</span>}
      <div className="grid gap-1">
        <h3 className="t-h2 text-fg">{plan.name}</h3>
        <p className="t-small min-h-9 text-muted">{plan.description}</p>
      </div>
      <div className="grid gap-0.5">
        <p className="flex items-baseline gap-1">
          <span className="text-[28px] font-bold leading-9 tracking-tight text-fg tabular-nums">{custom ? t('pricing.custom') : price > 0 ? rupiah(price) : t('pricing.free')}</span>
          {!custom && price > 0 && <span className="t-small text-muted">{cycle === 'monthly' ? t('pricing.per_month') : t('pricing.per_year')}</span>}
        </p>
        {yearlyMonths > 0 && <p className="t-small font-semibold text-success">{t('pricing.yearly_save', { n: yearlyMonths })}</p>}
        {!custom && credits > 0 && <p className="t-small text-muted">{t('pricing.credits_note')}</p>}
      </div>
      <ul className="grid flex-1 content-start gap-2">
        {rows.map((r, i) => (
          <li key={i} className={cn('t-small flex items-start gap-2', r.on ? 'text-fg' : 'text-muted')}>
            {r.on ? <Check className="mt-0.5 size-4 shrink-0 text-success" aria-label={t('pricing.included')} /> : <Minus className="mt-0.5 size-4 shrink-0" aria-label={t('pricing.not_included')} />}
            <span>{r.text}</span>
          </li>
        ))}
      </ul>
      {custom ? (
        <ButtonLink to={onChoose ? '/app/custom-packages' : '/register?next=%2Fapp%2Fcustom-packages'} variant="secondary" block>{t('pricing.contact_sales')}</ButtonLink>
      ) : current ? (
        <Button variant="secondary" disabled block>{t('pricing.current')}</Button>
      ) : onChoose ? (
        <Button variant={plan.is_recommended ? 'primary' : 'secondary'} block onClick={() => onChoose(plan)} loading={choosing}>{t('pricing.choose', { name: plan.name })}</Button>
      ) : (
        <ButtonLink to={`/register?plan=${plan.code}`} variant={plan.is_recommended ? 'primary' : 'secondary'} block>{price > 0 ? t('pricing.choose', { name: plan.name }) : t('public.start_free')}</ButtonLink>
      )}
    </article>
  )
}
