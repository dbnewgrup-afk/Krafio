import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion'
import { useTranslation } from 'react-i18next'
import { BellOff, CornerDownLeft, Loader2, MessagesSquare, Volume2, VolumeX, Monitor, Check } from 'lucide-react'
import { Menu } from '@/shared/ui/Misc'
import { relative } from '@/shared/lib/format'
import { cn } from '@/shared/lib/cn'
import { useMessages, useQuickReply } from './MessagesProvider'
import { Avatar } from './MessageToasts'
import { inboxRoute, type Conversation } from './types'

/** Ikon pesan di header: badge unread (pop saat bertambah) + dropdown pesan terbaru dengan balas cepat inline. */
export function MessagesMenu() {
  const { t } = useTranslation()
  const reduced = useReducedMotion()
  const { area, unread, recent, settings, setSettings, queued } = useMessages()
  const prev = useRef(unread)
  const [pop, setPop] = useState(0)
  useEffect(() => {
    if (unread > prev.current) setPop((p) => p + 1)
    prev.current = unread
  }, [unread])

  return (
    <Menu
      label={t('inbox.title')}
      width="w-[min(380px,calc(100vw-24px))]"
      trigger={({ open, toggle, id }) => (
        <button type="button" onClick={toggle} aria-expanded={open} aria-controls={id} aria-label={unread ? t('inbox.unread_label', { n: unread }) : t('inbox.title')} className="relative grid size-11 place-items-center rounded-md text-fg hover:bg-surface-2 lg:size-10">
          <MessagesSquare className="size-5" aria-hidden />
          <AnimatePresence>
            {unread > 0 && (
              <motion.span key={pop} initial={reduced ? false : { scale: 1 }} animate={reduced ? undefined : { scale: [1, 1.25, 1] }} transition={{ duration: 0.35 }} className="absolute right-1 top-1 min-w-[18px] rounded-full bg-danger px-1 text-center text-[11px] font-semibold leading-[18px] text-white tabular-nums dark:text-bg">
                {unread > 99 ? '99+' : unread}
              </motion.span>
            )}
          </AnimatePresence>
        </button>
      )}
    >
      {(close) => (
        <div className="grid">
          <div className="flex items-center justify-between gap-2 border-b border-line px-3.5 pb-2 pt-1">
            <p className="text-sm font-semibold text-fg">{t('inbox.title')}</p>
            <Link to={inboxRoute(area)} onClick={close} className="t-label text-primary hover:underline">{t('common.see_all')}</Link>
          </div>
          {queued > 0 && <p className="t-small bg-warning/10 px-3.5 py-2 text-warning">{t('inbox.queued', { n: queued })}</p>}
          <div className="max-h-[min(60dvh,420px)] overflow-y-auto">
            {recent.length === 0 ? (
              <p className="t-small px-3.5 py-6 text-center text-muted">{t('inbox.all_caught_up')}</p>
            ) : (
              <ul className="divide-y divide-line">
                {recent.map((c) => (
                  <RecentRow key={c.id} c={c} onOpen={close} />
                ))}
              </ul>
            )}
          </div>
          <div className="grid grid-cols-3 border-t border-line text-[12px]" role="group" aria-label={t('inbox.notify_settings')}>
            <Toggle on={settings.sound} onClick={() => setSettings({ sound: !settings.sound })} icon={settings.sound ? Volume2 : VolumeX} label={t('inbox.sound')} />
            <Toggle on={settings.desktop} onClick={() => setSettings({ desktop: !settings.desktop })} icon={Monitor} label={t('inbox.desktop')} />
            <Toggle on={settings.dnd} onClick={() => setSettings({ dnd: !settings.dnd })} icon={BellOff} label={t('inbox.dnd')} />
          </div>
        </div>
      )}
    </Menu>
  )
}

function Toggle({ on, onClick, icon: Icon, label }: { on: boolean; onClick: () => void; icon: typeof Volume2; label: string }) {
  return (
    <button type="button" role="switch" aria-checked={on} onClick={onClick} className={cn('flex items-center justify-center gap-1.5 py-2.5 font-medium hover:bg-surface-2', on ? 'text-primary' : 'text-muted')}>
      <Icon className="size-3.5" aria-hidden />
      {label}
    </button>
  )
}

function RecentRow({ c, onOpen }: { c: Conversation; onOpen: () => void }) {
  const { t } = useTranslation()
  const { area } = useMessages()
  const q = useQuickReply(c.id)
  const [reply, setReply] = useState(false)
  const name = area === 'admin' ? c.requester.name : c.assignee?.name ?? t('inbox.team')
  return (
    <li className="px-3.5 py-2.5">
      <div className="flex items-start gap-2.5">
        <Avatar name={name} size={32} online={area === 'admin' ? c.requester.online : undefined} />
        <Link to={inboxRoute(area, c.id)} onClick={onOpen} className="min-w-0 flex-1">
          <p className="flex items-baseline justify-between gap-2">
            <span className="truncate text-sm font-semibold text-fg">{name}</span>
            <span className="t-small shrink-0 text-muted">{relative(c.last_message_at)}</span>
          </p>
          <p className="t-small truncate text-muted">{c.subject}</p>
          <p className="truncate text-[13px] text-fg">{c.preview}</p>
        </Link>
        {c.unread > 0 && <span className="mt-1 min-w-5 rounded-full bg-primary px-1.5 text-center text-[11px] font-semibold leading-5 text-primary-contrast">{c.unread}</span>}
      </div>
      {reply || q.text ? (
        <form className="mt-2 flex gap-2 pl-[42px]" onSubmit={async (e) => { e.preventDefault(); if (await q.submit()) setReply(false) }}>
          <input autoFocus value={q.text} onChange={(e) => q.setText(e.target.value)} onKeyDown={(e) => e.key === 'Escape' && (e.stopPropagation(), setReply(false))} placeholder={t('inbox.quick_reply_ph')} aria-label={t('inbox.quick_reply_to', { name })} className="h-9 min-w-0 flex-1 rounded-md border border-line bg-bg-subtle px-2.5 text-sm focus:border-primary focus:outline-none" />
          <button type="submit" aria-label={t('inbox.send')} disabled={!q.text.trim() || q.state === 'sending'} className="grid size-9 place-items-center rounded-md bg-primary text-primary-contrast disabled:opacity-50">
            {q.state === 'sending' ? <Loader2 className="size-4 animate-spin" aria-hidden /> : <CornerDownLeft className="size-4" aria-hidden />}
          </button>
        </form>
      ) : (
        <div className="mt-1.5 flex gap-3 pl-[42px] text-[12.5px] font-medium">
          <button type="button" onClick={() => setReply(true)} className="text-primary hover:underline">{t('inbox.reply')}</button>
          <button type="button" onClick={() => void q.markRead()} className="inline-flex items-center gap-1 text-muted hover:text-fg"><Check className="size-3.5" aria-hidden />{t('inbox.mark_read')}</button>
        </div>
      )}
    </li>
  )
}
