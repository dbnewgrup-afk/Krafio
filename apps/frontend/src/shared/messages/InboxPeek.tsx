import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Clock } from 'lucide-react'
import { relative } from '@/shared/lib/format'
import { StaggerList, StaggerItem } from '@/shared/motion/Reveal'
import { useMessages } from './MessagesProvider'
import { Avatar } from './MessageToasts'
import { inboxRoute } from './types'

/** Ringkasan percakapan belum dibaca untuk kartu dashboard (data dari feed global, tanpa request tambahan). */
export function InboxPeek() {
  const { t } = useTranslation()
  const { recent, area } = useMessages()
  if (!recent.length) return <p className="t-small py-6 text-center text-muted">{t('inbox.all_caught_up')}</p>
  return (
    <StaggerList className="grid divide-y divide-line">
      {recent.slice(0, 5).map((c) => (
        <StaggerItem key={c.id}>
          <Link to={inboxRoute(area, c.id)} className="flex items-center gap-3 rounded-md px-1 py-2.5 hover:bg-surface-2">
            <Avatar name={area === 'admin' ? c.requester.name : c.assignee?.name ?? t('inbox.team')} size={34} online={area === 'admin' ? c.requester.online : undefined} />
            <span className="grid min-w-0 flex-1">
              <span className="flex items-baseline gap-2">
                <span className="truncate text-sm font-semibold text-fg">{area === 'admin' ? c.requester.name : c.subject}</span>
                <span className="t-small ml-auto shrink-0 text-muted">{relative(c.last_message_at)}</span>
              </span>
              <span className="t-small truncate text-muted">{c.preview}</span>
            </span>
            {c.sla_breached && <Clock className="size-4 shrink-0 text-danger" aria-label={t('inbox.sla_late')} />}
            {c.unread > 0 && <span className="min-w-5 rounded-full bg-primary px-1.5 text-center text-[11px] font-semibold leading-5 text-primary-contrast">{c.unread}</span>}
          </Link>
        </StaggerItem>
      ))}
    </StaggerList>
  )
}
