import { useApi } from '@/shared/hooks/useApi'
import { HeroShowcase, type HeroConfig, type HeroSlide } from './HeroShowcase'
import { Skeleton } from './States'
import { RevealCard } from '@/shared/motion/Reveal'

/** Hero Showcase di tengah dashboard; slide dikelola Admin › Konten › Hero dashboard. */
export function DashboardHero({ area }: { area: 'user' | 'admin' }) {
  const { data, error } = useApi<{ slides: HeroSlide[]; config: Partial<HeroConfig> }>(area === 'admin' ? '/admin/hero/live' : '/app/hero')
  if (error) return null
  if (!data) return <Skeleton className="mb-4 h-[300px] rounded-2xl md:h-[340px]" />
  if (!data.slides.length) return null
  return (
    <RevealCard order={0} className="mb-4">
      <HeroShowcase slides={data.slides} config={data.config} />
    </RevealCard>
  )
}
