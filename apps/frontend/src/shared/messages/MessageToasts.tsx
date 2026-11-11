import { useEffect, useRef, useState } from 'react'
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion'
import { useTranslation } from 'react-i18next'
import { Check, CornerDownLeft, ExternalLink, Loader2, MessageSquare, X } from 'lucide-react'
import { relative } from '@/shared/lib/format'
import { cn } from '@/shared/lib/cn'
import { useMessages, useQuickReply } from './MessagesProvider'
import { initials, type FeedItem } from './types'

export function Avatar({ name, online, size = 36, className }: { name: string; online?: boolean; size?: number; className?: string }) {
  // Warna stabil per nama dari palet hangat + aksen (bukan warna acak)
  const tones = ['bg-[var(--preview-4)] text-white', 'bg-[var(--preview-2)] text-white', 'bg-primary text-primary-contrast', 'bg-[var(--preview-5)] text-white', 'bg-[var(--preview-1)] text-[var(--preview-2)]']
  const h = [...name].reduce((a, c) => a + c.charCodeAt(0), 0)
  return (
    <span className={cn('relative inline-grid shrink-0 place-items-center rounded-full font-semibold', tones[h % tones.length], className)} style={{ width: size, height: size, fontSize: Math.round(size * 0.36) }} aria-hidden>
      {initials(name) || '?'}
      {online !== undefined && <span className={cn('absolute -bottom-0.5 -right-0.5 size-3 rounded-full border-2 border-surface', online ? 'bg-success' : 'bg-line-strong')} />}
    </span>
  )
}

const VISIBLE = 3
const LIFETIME = 8000

/**
 * Toast pesan masuk (pojok kanan bawah, semua halaman): avatar, pengirim, cuplikan, waktu, dan kolom balas
 * cepat. Enter = kirim. Dikelompokkan per pengirim, maks 3 terlihat ("+N lainnya"). Hilang otomatis 8 dtk,
 * berhenti saat di-hover atau saat kolom balas difokus.
 */
export function MessageToasts({ items, onDismiss }: { items: FeedItem[]; onDismiss: (conversationId: number) => void }) {
  const { t } = useTranslation()
  const visible = items.slice(-VISIBLE).reverse()
  const more = items.length - visible.length
  return (
    <div className="pointer-events-none fixed bottom-[calc(16px+var(--safe-b))] right-3 z-toast flex w-[min(380px,calc(100vw-24px))] flex-col-reverse gap-2 md:right-5 max-md:bottom-[calc(72px+var(--safe-b))]" aria-live="polite" aria-label={t('inbox.toasts')}>
      <AnimatePresence initial={false}>
        {visible.map((m) => (
          <MessageToast key={m.conversation_id} item={m} onDismiss={() => onDismiss(m.conversation_id)} />
        ))}
      </AnimatePresence>
      {more > 0 && (
        <motion.p layout className="pointer-events-auto self-end rounded-full bg-fg px-3 py-1 text-[12px] font-semibold text-bg shadow-k-md">
          {t('inbox.more', { n: more })}
        </motion.p>
      )}
    </div>
  )
}

function MessageToast({ item, onDismiss }: { item: FeedItem; onDismiss: () => void }) {
  const { t } = useTranslation()
  const reduced = useReducedMotion()
  const { open } = useMessages()
  const q = useQuickReply(item.conversation_id)
  const [hold, setHold] = useState(false)
  const [replying, setReplying] = useState(false)
  const input = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (hold || replying || q.text) return
    const id = window.setTimeout(onDismiss, LIFETIME)
    return () => window.clearTimeout(id)
  }, [hold, replying, q.text, onDismiss, item.id])

  useEffect(() => {
    if (q.state === 'sent') {
      const id = window.setTimeout(onDismiss, 1100)
      return () => window.clearTimeout(id)
    }
  }, [q.state, onDismiss])

  return (
    <motion.div
      layout={!reduced}
      role="alert"
      aria-label={t('inbox.new_from', { name: item.sender })}
      initial={reduced ? { opacity: 0 } : { opacity: 0, x: 60 }}
      animate={{ opacity: 1, x: 0 }}
      exit={reduced ? { opacity: 0 } : { opacity: 0, scale: 0.94 }}
      transition={{ type: 'spring', stiffness: 320, damping: 28 }}
      onMouseEnter={() => setHold(true)}
      onMouseLeave={() => setHold(false)}
      onKeyDown={(e) => e.key === 'Escape' && onDismiss()}
      className="pointer-events-auto overflow-hidden rounded-xl border border-line bg-surface shadow-k-lg"
    >
      <div className="flex items-start gap-3 p-3 pb-2">
        <Avatar name={item.sender} size={38} />
        <div className="min-w-0 flex-1">
          <p className="flex items-baseline gap-2">
            <span className="truncate text-sm font-semibold text-fg">{item.sender}</span>
            <span className="t-small shrink-0 text-muted">{relative(item.created_at)}</span>
          </p>
          <p className="t-small truncate text-muted">{item.number} · {item.subject}</p>
          <p className="mt-1 line-clamp-2 text-sm text-fg">{item.body}</p>
        </div>
        <button type="button" onClick={onDismiss} aria-label={t('inbox.dismiss')} className="-m-1 grid size-8 shrink-0 place-items-center rounded-md text-muted hover:bg-surface-2 hover:text-fg">
          <X className="size-4" aria-hidden />
        </button>
      </div>
      <form
        className="flex items-center gap-2 px-3 pb-2"
        onSubmit={async (e) => {
          e.preventDefault()
          await q.submit()
        }}
      >
        <input
          ref={input}
          value={q.text}
          onChange={(e) => q.setText(e.target.value)}
          onFocus={() => setReplying(true)}
          onBlur={() => setReplying(false)}
          placeholder={t('inbox.quick_reply_ph')}
          aria-label={t('inbox.quick_reply_to', { name: item.sender })}
          className="h-10 min-w-0 flex-1 rounded-md border border-line bg-bg-subtle px-3 text-sm text-fg placeholder:text-muted/70 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/25"
          disabled={q.state === 'sending' || q.state === 'sent'}
          maxLength={5000}
        />
        <button type="submit" aria-label={t('inbox.send')} disabled={!q.text.trim() || q.state === 'sending'} className="grid size-10 shrink-0 place-items-center rounded-md bg-primary text-primary-contrast disabled:opacity-50">
          {q.state === 'sending' ? <Loader2 className="size-4 animate-spin" aria-hidden /> : q.state === 'sent' ? <Check className="size-4" aria-hidden /> : <CornerDownLeft className="size-4" aria-hidden />}
        </button>
      </form>
      {q.state === 'failed' && <p className="t-small px-3 pb-2 text-danger">{t('inbox.send_failed')}</p>}
      <div className="flex border-t border-line text-[12.5px] font-medium">
        <button type="button" onClick={() => input.current?.focus()} className="flex flex-1 items-center justify-center gap-1.5 py-2 text-fg hover:bg-surface-2">
          <MessageSquare className="size-3.5" aria-hidden />
          {t('inbox.reply')}
        </button>
        <button type="button" onClick={() => open(item.conversation_id)} className="flex flex-1 items-center justify-center gap-1.5 border-l border-line py-2 text-fg hover:bg-surface-2">
          <ExternalLink className="size-3.5" aria-hidden />
          {t('inbox.open')}
        </button>
        <button type="button" onClick={() => void q.markRead()} className="flex flex-1 items-center justify-center gap-1.5 border-l border-line py-2 text-fg hover:bg-surface-2">
          <Check className="size-3.5" aria-hidden />
          {t('inbox.mark_read')}
        </button>
      </div>
    </motion.div>
  )
}
