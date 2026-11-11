import type { CSSProperties } from 'react'
import type { AiJob } from '@/api-client/types'

/** Aset gambar statis (public/assets/img). */
export const IMG = '/assets/img'
export const img = (path: string): string => `${IMG}/${path}`

/**
 * Foto & mockup UI resmi Krafio (sumber: assets/krafio_website_assets, dioptimalkan ke public/assets/img).
 * Satu foto boleh dipakai di beberapa halaman, tetapi tidak berulang di halaman yang sama.
 */
export const PHOTO = {
  heroGenerate: img('photos/hero-generate.jpg'),
  heroSeo: img('photos/hero-seo.jpg'),
  heroTemplates: img('photos/hero-templates.jpg'),
  loginCredit: img('photos/login-credit.jpg'),
  walletCards: img('photos/wallet-cards.jpg'),
  studioPlaceholder: img('photos/studio-placeholder.jpg'),
  memberHeroPhoto: img('photos/member-hero-photo.jpg'),
  memberHeroTemplates: img('photos/member-hero-templates.jpg'),
  memberHeroWebsite: img('photos/member-hero-website.jpg'),
  adminLoginControl: img('photos/admin-login-control.jpg'),
  adminLoginWallet: img('photos/admin-login-wallet.jpg'),
  adminLoginMfa: img('photos/admin-login-mfa.jpg'),
  adminHeroInbox: img('photos/admin-hero-inbox.jpg'),
  adminHeroTemplates: img('photos/admin-hero-templates.jpg'),
  pricing: img('pages/pricing.jpg'),
  affiliate: img('pages/affiliate.jpg'),
  faq: img('pages/faq.jpg'),
} as const

/** Mockup UI per fitur (slug = FEATURES[].slug). */
export const featurePhoto = (slug: string): string => img(`features/${slug}.jpg`)

/** Desain template katalog per slug `art` (24 template). */
export const templatePhoto = (art: string): string => img(`templates/${art}.jpg`)

/** Gaya latar pratinjau hasil job: gambar bila ada, gradien palet simulasi bila tidak. */
export function previewStyle(result: AiJob['result'] | null | undefined, index = 0): CSSProperties | undefined {
  const image = result?.preview_images?.[index]
  if (image) return { backgroundImage: `url(${image})`, backgroundSize: 'cover', backgroundPosition: 'center' }
  const p = result?.preview_palette?.slice(index * 3, index * 3 + 3)
  if (p && p.length === 3) return { background: `linear-gradient(150deg, ${p[0]}, ${p[1]} 55%, ${p[2]})` }
  return undefined
}
