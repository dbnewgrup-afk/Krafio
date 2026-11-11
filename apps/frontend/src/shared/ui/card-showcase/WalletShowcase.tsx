import { useTranslation } from 'react-i18next'
import { useApi } from '@/shared/hooks/useApi'
import { Card, CardTitle } from '@/shared/ui/Misc'
import { Skeleton } from '@/shared/ui/States'
import { RevealCard } from '@/shared/motion/Reveal'
import { CardShowcase } from './CardShowcase'
import type { ShowcaseCard } from './types'

/** Kartu dompet interaktif: user = saldo top-up & komisi; admin = kewajiban saldo platform. */
export function WalletShowcase({ area, order = 0, title }: { area: 'user' | 'admin'; order?: number; title?: string }) {
  const { t } = useTranslation()
  const { data, error } = useApi<ShowcaseCard[]>(area === 'admin' ? '/admin/wallet/cards' : '/app/wallet/cards')
  if (error) return null
  return (
    <RevealCard order={order}>
      <Card>
        <CardTitle>{title ?? t('cards.title')}</CardTitle>
        {!data ? <Skeleton className="h-80 rounded-2xl" /> : <CardShowcase cards={data} storageKey={`krafio.cards.${area}`} />}
      </Card>
    </RevealCard>
  )
}
