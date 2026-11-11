import type { TFunction } from 'i18next'
import { rupiah } from './format'

/** Promo publik dari GET /public/promos (bentuk dari App\Services\Promos::present). */
export interface Promo {
  id: number
  code: string | null
  auto: boolean
  headline: string
  description: string | null
  image: string | null
  type: 'percent' | 'fixed' | 'bonus_credit' | 'bonus_days'
  value: number
  max_discount: number | null
  min_purchase: number
  plan_codes: string[]
  cycles: string[]
  applies_to_addons: boolean
  first_purchase_only: boolean
  duration: 'once' | 'first_n_months' | 'forever'
  duration_months: number | null
  campaign: string | null
  ends_at: string | null
  remaining: number | null
  /** Hanya pada daftar promo untuk item checkout */
  discount?: number
}

/** Nilai besar di sisi kiri kupon: "20%", "Rp50rb", "+100", "+7". */
export function promoValue(p: Promo): { big: string; unit: string } {
  switch (p.type) {
    case 'percent':
      return { big: `${p.value}%`, unit: 'off' }
    case 'fixed':
      return { big: p.value >= 1000 && p.value % 1000 === 0 ? `Rp${p.value / 1000}rb` : rupiah(p.value), unit: 'cut' }
    case 'bonus_credit':
      return { big: `+${p.value}`, unit: 'credits' }
    default:
      return { big: `+${p.value}`, unit: 'days' }
  }
}

/** Syarat singkat sebagai chip (urut dari yang paling menentukan). */
export function promoTerms(p: Promo, t: TFunction): string[] {
  const out: string[] = []
  if (p.plan_codes.length) out.push(t('promo.terms.plans', { plans: p.plan_codes.map((c) => t(`promo.plan.${c}`, { defaultValue: c })).join(', ') }))
  if (p.cycles.length === 1) out.push(t(`promo.terms.cycle_${p.cycles[0]}`))
  if (p.min_purchase > 0) out.push(t('promo.terms.min', { amount: rupiah(p.min_purchase) }))
  if (p.type === 'percent' && p.max_discount) out.push(t('promo.terms.max', { amount: rupiah(p.max_discount) }))
  if (p.duration === 'first_n_months' && p.duration_months) out.push(t('promo.terms.months', { n: p.duration_months }))
  if (p.duration === 'forever') out.push(t('promo.terms.forever'))
  if (p.first_purchase_only) out.push(t('promo.terms.first'))
  if (p.applies_to_addons) out.push(t('promo.terms.addons'))
  return out
}

/* ---- Serah-terima kode dari halaman Promo ke checkout (bertahan lintas login/daftar) ---- */
const KEY = 'krafio.promo'

export function rememberPromo(code: string): void {
  try {
    localStorage.setItem(KEY, JSON.stringify({ code, at: Date.now() }))
  } catch {
    /* storage tidak tersedia — kode tetap bisa diketik manual */
  }
}

/** Kode yang dipilih ≤ 7 hari terakhir. */
export function pendingPromo(): string | null {
  try {
    const v = JSON.parse(localStorage.getItem(KEY) ?? 'null') as { code?: string; at?: number } | null
    if (!v?.code || !v.at || Date.now() - v.at > 7 * 864e5) return null
    return v.code
  } catch {
    return null
  }
}

export function clearPromo(): void {
  try {
    localStorage.removeItem(KEY)
  } catch {
    /* abaikan */
  }
}
