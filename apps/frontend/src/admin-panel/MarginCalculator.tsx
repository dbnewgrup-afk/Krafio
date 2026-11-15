import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Calculator, Save, RotateCcw } from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import { useApi } from '@/shared/hooks/useApi'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { useToast } from '@/shared/context/ToastContext'
import { PageHeader, Card, CardTitle } from '@/shared/ui/Misc'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Status'
import { Skeleton } from '@/shared/ui/States'
import { SelectField, Switch } from '@/shared/ui/Field'
import { cn } from '@/shared/lib/cn'

interface TextModel {
  label: string
  in: number
  out: number
}
interface TokenEst {
  in: number
  out: number
  model: string
}
interface Assumptions {
  usd_idr: number
  fee_pct: number
  ppn_pct: number
  target_margin_pct: number
  affiliate_pct: number
  affiliate_active: boolean
  media: Record<'image_1k' | 'image_2k' | 'remove_bg' | 'inpaint' | 'video_720_per_s' | 'video_1080_per_s', number>
  text_models: Record<string, TextModel>
  text_tools: Record<string, TokenEst>
  chat: TokenEst
}
interface Data {
  assumptions: Assumptions
  credit_costs: Record<string, number>
  chat_messages_per_credit: number
  credit_value_idr: number
  text_tools: { key: string; label: string; credits_per_unit: number; mode: string; enabled: boolean }[]
  packs: { code: string; name: string; price: number; credits: number }[]
  plans: { code: string; name: string; price: number; credits: number; chat_per_day: number }[]
  saved_at: string | null
}

const rp = (n: number) => `Rp${Math.round(n).toLocaleString('id-ID')}`
const pct = (n: number) => `${Number.isFinite(n) ? Math.round(n) : 0}%`

/**
 * Kalkulator margin (Admin → Keuangan). Semua angka dihitung di browser dari asumsi yang bisa diubah;
 * "Simpan asumsi" hanya menyimpan asumsi, tidak mengubah harga jual. Acuan: report/BIAYA_AI_DAN_MARGIN_2026-11-14.md.
 */
export function MarginCalculator({ title }: { title: string }) {
  const { t } = useTranslation()
  useDocTitle(title)
  const toast = useToast()
  const { data, reload } = useApi<Data>('/admin/margin-calculator')
  const [a, setA] = useState<Assumptions | null>(null)
  const [packCode, setPackCode] = useState<string>('')
  const [viaAffiliate, setViaAffiliate] = useState(true)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    if (!data) return
    setA(structuredClone(data.assumptions))
    // Referensi bawaan: paket dengan harga per kredit terendah (skenario terburuk)
    const worst = [...data.packs].sort((x, y) => x.price / x.credits - y.price / y.credits)[0]
    setPackCode(worst?.code ?? '')
  }, [data])

  const calc = useMemo(() => {
    if (!data || !a) return null
    const idr = (usd: number) => usd * a.usd_idr
    const ppn = 1 + a.ppn_pct / 100
    const fee = a.fee_pct / 100
    const aff = a.affiliate_active ? a.affiliate_pct / 100 : 0
    const net = (pricePerCredit: number, withAff: boolean) => (pricePerCredit / ppn) * (1 - fee) * (withAff ? 1 - aff : 1)
    const pack = data.packs.find((p) => p.code === packCode) ?? data.packs[0]
    const netPerCredit = pack ? net(pack.price / pack.credits, viaAffiliate) : data.credit_value_idr
    const tokenCost = (e: TokenEst) => {
      const m = a.text_models[e.model] ?? { in: 0, out: 0 }
      return idr((e.in * m.in + e.out * m.out) / 1e6)
    }
    const c = data.credit_costs
    const rows: { group: string; name: string; credits: number; cost: number; note?: string }[] = [
      { group: 'media', name: t('admin.margin.f_photo'), credits: c.photo_standard, cost: idr(a.media.image_1k) },
      { group: 'media', name: t('admin.margin.f_photo_hd'), credits: c.photo_hd, cost: idr(a.media.image_2k) },
      { group: 'media', name: t('admin.margin.f_inpaint'), credits: c.inpaint, cost: idr(a.media.inpaint) },
      { group: 'media', name: t('admin.margin.f_remove_bg'), credits: c.remove_bg, cost: idr(a.media.remove_bg), note: a.media.remove_bg ? undefined : t('admin.margin.fill_price') },
      { group: 'media', name: t('admin.margin.f_video720'), credits: c.video_720_per_5s, cost: idr(a.media.video_720_per_s * 5) },
      { group: 'media', name: t('admin.margin.f_video1080'), credits: c.video_1080_per_5s, cost: idr(a.media.video_1080_per_s * 5) },
      { group: 'local', name: t('admin.margin.f_upscale'), credits: c.upscale, cost: 0 },
      { group: 'local', name: t('admin.margin.f_clipper'), credits: c.clipper_per_min, cost: 0 },
      { group: 'local', name: t('admin.margin.f_video_edit'), credits: c.video_edit_per_min, cost: 0 },
      { group: 'local', name: t('admin.margin.f_script_video'), credits: c.video_edit_per_min, cost: 0 },
      ...data.text_tools.map((tool) => ({
        group: 'text',
        name: `${tool.label} · ${t(tool.mode === 'list' ? 'admin.margin.per_10' : 'admin.margin.per_part')}`,
        credits: tool.credits_per_unit,
        cost: tokenCost(a.text_tools[tool.key] ?? { in: 0, out: 0, model: 'haiku' }),
      })),
      { group: 'text', name: t('admin.margin.f_chat', { n: data.chat_messages_per_credit }), credits: 1, cost: tokenCost(a.chat) * data.chat_messages_per_credit },
    ].map((r) => r)
    const target = a.target_margin_pct / 100
    const out = rows.map((r) => {
      const revenue = r.credits * netPerCredit
      const profit = revenue - r.cost
      return { ...r, revenue, profit, margin: revenue > 0 ? (profit / revenue) * 100 : 0, perCredit: r.credits > 0 ? r.cost / r.credits : 0, below: revenue > 0 && profit / revenue < target }
    })
    const worstPerCredit = Math.max(0, ...out.map((r) => r.perCredit))
    // harga per kredit (termasuk PPN) ≥ biaya/kredit ÷ (1 − margin) × (1 + PPN) ÷ ((1 − fee) × (1 − afiliasi))
    const minPrice = (worstPerCredit / (1 - target)) * ppn / ((1 - fee) * (1 - aff))
    const packs = data.packs.map((p) => {
      const per = p.price / p.credits
      return { ...p, per, net: net(per, false), netAff: net(per, true), ok: per >= minPrice }
    })
    const chatMsgCost = tokenCost(a.chat)
    const plans = data.plans.map((p) => {
      const netRev = (p.price / ppn) * (1 - fee)
      const cost = p.credits * worstPerCredit + p.chat_per_day * 30 * chatMsgCost
      return { ...p, netRev, cost, margin: netRev > 0 ? ((netRev - cost) / netRev) * 100 : 0 }
    })
    return { netPerCredit, rows: out, worstPerCredit, minPrice, packs, plans, target }
  }, [data, a, packCode, viaAffiliate, t])

  if (!data || !a || !calc) return <Skeleton className="h-96" />

  const num = (v: number, set: (n: number) => void, step = 'any') => (
    <input type="number" step={step} min={0} value={v} onChange={(e) => set(Number(e.target.value))} className="h-9 w-full min-w-0 rounded-md border border-line bg-surface px-2 text-right tabular-nums text-fg" />
  )
  const upd = (patch: Partial<Assumptions>) => setA({ ...a, ...patch })

  const save = async () => {
    setBusy(true)
    try {
      const { usd_idr, fee_pct, target_margin_pct, media, text_models, text_tools, chat } = a
      await http.put('/admin/margin-calculator', { usd_idr, fee_pct, target_margin_pct, media, text_models, text_tools, chat })
      toast.push('success', t('common.saved'))
      reload()
    } catch (e) {
      toast.push('error', e instanceof ApiError ? e.message : (e as Error).message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
      <PageHeader title={title} subtitle={t('admin.margin.subtitle')} />

      <div className="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Card>
          <p className="t-small text-muted">{t('admin.margin.net_per_credit')}</p>
          <p className="t-h2 tabular-nums text-fg">{rp(calc.netPerCredit)}</p>
        </Card>
        <Card>
          <p className="t-small text-muted">{t('admin.margin.worst_cost')}</p>
          <p className="t-h2 tabular-nums text-fg">{rp(calc.worstPerCredit)}</p>
        </Card>
        <Card>
          <p className="t-small text-muted">{t('admin.margin.min_price', { m: a.target_margin_pct })}</p>
          <p className="t-h2 tabular-nums text-fg">{rp(calc.minPrice)}</p>
        </Card>
        <Card>
          <p className="t-small text-muted">{t('admin.margin.profit_per_credit')}</p>
          <p className="t-h2 tabular-nums text-fg">{rp(calc.netPerCredit - calc.worstPerCredit)}</p>
        </Card>
      </div>

      <div className="grid gap-4 2xl:grid-cols-[minmax(0,340px)_minmax(0,1fr)]">
        <Card className="grid h-fit gap-4 md:grid-cols-2 2xl:grid-cols-1">
          <CardTitle>
            <span className="inline-flex items-center gap-2">
              <Calculator className="size-4 text-primary" aria-hidden />
              {t('admin.margin.assumptions')}
            </span>
          </CardTitle>
          <div className="grid grid-cols-2 gap-3">
            <label className="t-small grid gap-1 text-muted">{t('admin.margin.usd_idr')}{num(a.usd_idr, (n) => upd({ usd_idr: n }), '1')}</label>
            <label className="t-small grid gap-1 text-muted">{t('admin.margin.fee')}{num(a.fee_pct, (n) => upd({ fee_pct: n }))}</label>
            <label className="t-small grid gap-1 text-muted">{t('admin.margin.target')}{num(a.target_margin_pct, (n) => upd({ target_margin_pct: n }))}</label>
            <label className="t-small grid gap-1 text-muted">PPN %{num(a.ppn_pct, (n) => upd({ ppn_pct: n }))}</label>
          </div>
          <SelectField label={t('admin.margin.ref_pack')} value={packCode} onChange={(e) => setPackCode(e.target.value)}>
            {data.packs.map((p) => (
              <option key={p.code} value={p.code}>
                {p.name} — {rp(p.price / p.credits)}/{t('admin.margin.credit')}
              </option>
            ))}
          </SelectField>
          <Switch checked={viaAffiliate} onChange={setViaAffiliate} label={t('admin.margin.via_affiliate', { n: a.affiliate_pct })} disabled={!a.affiliate_active} />

          <div className="grid gap-2">
            <p className="t-label text-fg">{t('admin.margin.media_prices')}</p>
            {(Object.keys(a.media) as (keyof Assumptions['media'])[]).map((k) => (
              <label key={k} className="t-small grid grid-cols-[1fr_110px] items-center gap-2 text-muted">
                {t(`admin.margin.m_${k}`)}
                {num(a.media[k], (n) => upd({ media: { ...a.media, [k]: n } }))}
              </label>
            ))}
          </div>
          <div className="grid gap-2">
            <p className="t-label text-fg">{t('admin.margin.text_prices')}</p>
            {Object.entries(a.text_models).map(([k, m]) => (
              <div key={k} className="t-small grid grid-cols-[1fr_80px_80px] items-center gap-2 text-muted">
                <span>{m.label}</span>
                {num(m.in, (n) => upd({ text_models: { ...a.text_models, [k]: { ...m, in: n } } }))}
                {num(m.out, (n) => upd({ text_models: { ...a.text_models, [k]: { ...m, out: n } } }))}
              </div>
            ))}
            <p className="t-small text-muted">{t('admin.margin.text_prices_hint')}</p>
          </div>
          <div className="grid gap-2">
            <p className="t-label text-fg">{t('admin.margin.tokens')}</p>
            {[...data.text_tools.map((x) => ({ key: x.key, label: x.label })), { key: '__chat', label: t('admin.margin.chat_msg') }].map((tool) => {
              const e = tool.key === '__chat' ? a.chat : (a.text_tools[tool.key] ?? { in: 0, out: 0, model: 'haiku' })
              const set = (v: TokenEst) => (tool.key === '__chat' ? upd({ chat: v }) : upd({ text_tools: { ...a.text_tools, [tool.key]: v } }))
              return (
                <div key={tool.key} className="grid gap-1 rounded-md border border-line p-2">
                  <span className="t-small font-semibold text-fg">{tool.label}</span>
                  <div className="grid grid-cols-[1fr_64px_64px] items-center gap-1.5">
                    <select value={e.model} onChange={(ev) => set({ ...e, model: ev.target.value })} className="h-9 min-w-0 rounded-md border border-line bg-surface px-1 text-sm text-fg">
                      {Object.entries(a.text_models).map(([k, m]) => (
                        <option key={k} value={k}>
                          {m.label}
                        </option>
                      ))}
                    </select>
                    {num(e.in, (n) => set({ ...e, in: n }), '1')}
                    {num(e.out, (n) => set({ ...e, out: n }), '1')}
                  </div>
                </div>
              )
            })}
            <p className="t-small text-muted">{t('admin.margin.tokens_hint')}</p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button loading={busy} icon={<Save className="size-4" aria-hidden />} onClick={save}>
              {t('admin.margin.save')}
            </Button>
            <Button variant="ghost" icon={<RotateCcw className="size-4" aria-hidden />} onClick={() => setA(structuredClone(data.assumptions))}>
              {t('admin.margin.reset')}
            </Button>
          </div>
          {data.saved_at && <p className="t-small text-muted">{t('admin.margin.saved_at', { when: new Date(data.saved_at).toLocaleString('id-ID') })}</p>}
        </Card>

        <div className="grid min-w-0 gap-4">
          <Card className="min-w-0">
            <CardTitle>{t('admin.margin.per_feature')}</CardTitle>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[640px] text-sm">
                <thead>
                  <tr className="t-small text-left text-muted">
                    <th className="py-2 pr-2 font-medium">{t('admin.margin.feature')}</th>
                    <th className="px-2 text-right font-medium">{t('admin.margin.credits')}</th>
                    <th className="px-2 text-right font-medium">{t('admin.margin.revenue')}</th>
                    <th className="px-2 text-right font-medium">{t('admin.margin.cost')}</th>
                    <th className="px-2 text-right font-medium">{t('admin.margin.profit')}</th>
                    <th className="pl-2 text-right font-medium">{t('admin.margin.margin')}</th>
                  </tr>
                </thead>
                <tbody>
                  {calc.rows.map((r) => (
                    <tr key={r.name} className="border-t border-line">
                      <td className="py-2 pr-2 text-fg">
                        {r.name}
                        {r.group === 'local' && <span className="t-small ml-1 text-muted">({t('admin.margin.local')})</span>}
                        {r.note && <span className="t-small ml-1 text-warning">· {r.note}</span>}
                      </td>
                      <td className="px-2 text-right tabular-nums">{r.credits}</td>
                      <td className="px-2 text-right tabular-nums">{rp(r.revenue)}</td>
                      <td className="px-2 text-right tabular-nums">{rp(r.cost)}</td>
                      <td className={cn('px-2 text-right font-semibold tabular-nums', r.profit < 0 ? 'text-danger' : 'text-fg')}>{rp(r.profit)}</td>
                      <td className="pl-2 text-right">
                        <Badge tone={r.profit < 0 ? 'danger' : r.below ? 'warning' : 'success'}>{pct(r.margin)}</Badge>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>

          <Card className="min-w-0">
            <CardTitle>{t('admin.margin.packs')}</CardTitle>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[560px] text-sm">
                <thead>
                  <tr className="t-small text-left text-muted">
                    <th className="py-2 pr-2 font-medium">{t('admin.margin.pack')}</th>
                    <th className="px-2 text-right font-medium">{t('admin.margin.price')}</th>
                    <th className="px-2 text-right font-medium">{t('admin.margin.per_credit')}</th>
                    <th className="px-2 text-right font-medium">{t('admin.margin.net')}</th>
                    <th className="px-2 text-right font-medium">{t('admin.margin.net_aff')}</th>
                    <th className="pl-2 text-right font-medium">{t('admin.margin.status')}</th>
                  </tr>
                </thead>
                <tbody>
                  {calc.packs.map((p) => (
                    <tr key={p.code} className="border-t border-line">
                      <td className="py-2 pr-2 text-fg">{p.name}</td>
                      <td className="px-2 text-right tabular-nums">{rp(p.price)}</td>
                      <td className="px-2 text-right tabular-nums">{rp(p.per)}</td>
                      <td className="px-2 text-right tabular-nums">{rp(p.net)}</td>
                      <td className="px-2 text-right tabular-nums">{rp(p.netAff)}</td>
                      <td className="pl-2 text-right">
                        <Badge tone={p.ok ? 'success' : 'danger'}>{p.ok ? t('admin.margin.ok') : t('admin.margin.raise_to', { price: rp(Math.ceil((calc.minPrice * p.credits) / 1000) * 1000) })}</Badge>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>

          <Card className="min-w-0">
            <CardTitle>{t('admin.margin.plans')}</CardTitle>
            <p className="t-small mb-2 text-muted">{t('admin.margin.plans_hint')}</p>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[520px] text-sm">
                <thead>
                  <tr className="t-small text-left text-muted">
                    <th className="py-2 pr-2 font-medium">{t('admin.margin.plan')}</th>
                    <th className="px-2 text-right font-medium">{t('admin.margin.net_revenue')}</th>
                    <th className="px-2 text-right font-medium">{t('admin.margin.worst_api')}</th>
                    <th className="pl-2 text-right font-medium">{t('admin.margin.margin')}</th>
                  </tr>
                </thead>
                <tbody>
                  {calc.plans.map((p) => (
                    <tr key={p.code} className="border-t border-line">
                      <td className="py-2 pr-2 text-fg">
                        {p.name} <span className="t-small text-muted">· {p.credits} {t('admin.margin.credit')}</span>
                      </td>
                      <td className="px-2 text-right tabular-nums">{rp(p.netRev)}</td>
                      <td className="px-2 text-right tabular-nums">{rp(p.cost)}</td>
                      <td className="pl-2 text-right">
                        <Badge tone={p.margin < 0 ? 'danger' : p.margin < a.target_margin_pct ? 'warning' : 'success'}>{pct(p.margin)}</Badge>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>
        </div>
      </div>
    </>
  )
}
