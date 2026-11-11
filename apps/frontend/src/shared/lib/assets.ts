import type { CSSProperties } from 'react'
import type { AiJob } from '@/api-client/types'

/** Aset gambar statis (public/assets/img). Vektor datar mengikuti token design system. */
export const IMG = '/assets/img'
export const img = (path: string): string => `${IMG}/${path}`

/** Gaya latar pratinjau hasil job: gambar bila ada, gradien palet simulasi bila tidak. */
export function previewStyle(result: AiJob['result'] | null | undefined, index = 0): CSSProperties | undefined {
  const image = result?.preview_images?.[index]
  if (image) return { backgroundImage: `url(${image})`, backgroundSize: 'cover', backgroundPosition: 'center' }
  const p = result?.preview_palette?.slice(index * 3, index * 3 + 3)
  if (p && p.length === 3) return { background: `linear-gradient(150deg, ${p[0]}, ${p[1]} 55%, ${p[2]})` }
  return undefined
}
