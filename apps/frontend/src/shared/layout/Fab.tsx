import { useEffect, useRef, useState, type FormEvent } from 'react'
import { ArrowUp, Headset, MessageCircle, Ticket, BookOpen, Send, X, MoreHorizontal, EyeOff } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link, useLocation } from 'react-router-dom'
import { cn } from '@/shared/lib/cn'
import { getPref, setPref } from '@/shared/lib/prefs'
import { useAuth } from '@/shared/context/AuthContext'
import { useIsMobile, useReducedMotion } from '@/shared/hooks/useMediaQuery'
import { useBackClose, useFocusTrap } from '@/shared/hooks/useOverlay'
import { http, ApiError } from '@/api-client/http'
import type { FaqItem } from '@/api-client/types'
import { Segmented } from '@/shared/ui/Misc'
import { TextField, TextArea } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'

/** Keyboard virtual terbuka → FAB disembunyikan (§15.3) */
function useKeyboardOpen(): boolean {
  const [open, setOpen] = useState(false)
  useEffect(() => {
    const vv = window.visualViewport
    if (!vv) return
    const f = () => setOpen(window.innerHeight - vv.height > 150)
    vv.addEventListener('resize', f)
    return () => vv.removeEventListener('resize', f)
  }, [])
  return open
}

/**
 * Tumpukan FAB kanan-bawah (§15, §16): Back-to-top di atas CS (jarak 12 px).
 * `scrollContainer` diisi bila panel memiliki area konten scroll sendiri.
 * `showCs` false di Admin Panel (tidak ada CS pelanggan).
 */
export function FabStack({ showCs = true, scrollContainer }: { showCs?: boolean; scrollContainer?: () => HTMLElement | null }) {
  const kb = useKeyboardOpen()
  const [csHidden, setCsHidden] = useState(() => getPref('krafio.cs.hidden') === new Date().toISOString().slice(0, 10))
  return (
    <div
      className={cn('fab-stack pointer-events-none fixed right-4 z-fab flex flex-col items-end gap-3 transition-opacity duration-[160ms] lg:right-6', kb && 'opacity-0')}
      style={{ bottom: 'calc(16px + var(--safe-b) + var(--fab-lift, 0px) + var(--fab-base, 0px))' }}
    >
      <div className="grid w-12 place-items-center md:w-[52px] lg:w-14">
        <BackToTop scrollContainer={scrollContainer} />
      </div>
      {showCs && !csHidden && <CsButton onHideToday={() => { setPref('krafio.cs.hidden', new Date().toISOString().slice(0, 10)); setCsHidden(true) }} />}
    </div>
  )
}

function BackToTop({ scrollContainer }: { scrollContainer?: () => HTMLElement | null }) {
  const { t } = useTranslation()
  const [show, setShow] = useState(false)
  const reduced = useReducedMotion()
  const loc = useLocation()

  useEffect(() => {
    const el = scrollContainer?.() ?? null
    const target: HTMLElement | Window = el ?? window
    const read = () => {
      const top = el ? el.scrollTop : window.scrollY
      const vh = el ? el.clientHeight : window.innerHeight
      const total = el ? el.scrollHeight : document.documentElement.scrollHeight
      if (total < vh * 2) return setShow(false) // halaman pendek
      const nearBottom = total - (top + vh) <= 400
      if (top <= 300) return setShow(false)
      setShow(top >= vh * 1.5 || nearBottom)
    }
    read()
    target.addEventListener('scroll', read, { passive: true })
    window.addEventListener('resize', read)
    return () => {
      target.removeEventListener('scroll', read)
      window.removeEventListener('resize', read)
    }
  }, [scrollContainer, loc.pathname])

  const toTop = () => {
    const el = scrollContainer?.() ?? null
    ;(el ?? window).scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' })
    window.setTimeout(() => {
      const h = document.querySelector<HTMLElement>('#main-content h1, [data-page-title]') ?? document.getElementById('main-content')
      h?.setAttribute('tabindex', '-1')
      h?.focus({ preventScroll: true })
    }, reduced ? 0 : 500)
  }

  return (
    <button
      type="button"
      onClick={toTop}
      aria-label={t('fab.back_to_top')}
      tabIndex={show ? 0 : -1}
      aria-hidden={!show}
      className={cn(
        'fab-hide pointer-events-auto grid size-10 place-items-center rounded-full border border-line bg-surface text-fg shadow-k-md transition-[opacity,transform] duration-[160ms] hover:bg-surface-2 lg:size-11',
        show ? 'translate-y-0 opacity-100' : 'pointer-events-none translate-y-2 opacity-0',
      )}
    >
      <ArrowUp className="size-5" aria-hidden />
    </button>
  )
}

function CsButton({ onHideToday }: { onHideToday: () => void }) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const [hint, setHint] = useState(false)
  const [menu, setMenu] = useState(false)
  const btn = useRef<HTMLButtonElement>(null)

  useEffect(() => {
    // Tooltip "Butuh bantuan?" sekali per sesi, hilang otomatis 6 dtk (§15.2 butir 7)
    if (sessionStorage.getItem('krafio.cs.hint')) return
    const a = window.setTimeout(() => setHint(true), 2500)
    const b = window.setTimeout(() => setHint(false), 8500)
    try {
      sessionStorage.setItem('krafio.cs.hint', '1')
    } catch {
      /* noop */
    }
    return () => {
      window.clearTimeout(a)
      window.clearTimeout(b)
    }
  }, [])

  return (
    <div className="fab-hide pointer-events-auto relative flex items-center gap-2">
      {hint && !open && (
        <div className="anim-fade-in flex items-center gap-1 rounded-full border border-line bg-surface py-1 pl-3 pr-1 text-sm font-semibold text-fg shadow-k-md">
          {t('cs.hint')}
          <button type="button" onClick={() => setHint(false)} aria-label={t('common.close')} className="grid size-7 place-items-center rounded-full hover:bg-surface-2">
            <X className="size-3.5" aria-hidden />
          </button>
        </div>
      )}
      <div className="relative">
        <button
          ref={btn}
          type="button"
          onClick={() => {
            setOpen((o) => !o)
            setHint(false)
          }}
          onContextMenu={(e) => {
            e.preventDefault()
            setMenu(true)
          }}
          aria-label={t('cs.aria')}
          aria-haspopup="dialog"
          aria-expanded={open}
          className="grid size-12 place-items-center rounded-full bg-primary text-primary-contrast shadow-k-fab transition-[background-color] duration-[120ms] hover:bg-primary-hover md:size-[52px] lg:size-14"
        >
          {open ? <X className="size-6" aria-hidden /> : <Headset className="size-6" aria-hidden />}
        </button>
        <button type="button" onClick={() => setMenu((m) => !m)} aria-label={t('cs.more')} className="absolute -left-2 -top-2 grid size-6 place-items-center rounded-full border border-line bg-surface text-muted shadow-k-sm hover:text-fg">
          <MoreHorizontal className="size-3.5" aria-hidden />
        </button>
        {menu && (
          <div className="anim-pop absolute bottom-full right-0 mb-2 w-56 rounded-lg border border-line bg-surface py-1 shadow-k-md" role="menu">
            <button type="button" role="menuitem" onClick={() => { setMenu(false); onHideToday() }} className="flex min-h-10 w-full items-center gap-2 px-3 text-left text-sm hover:bg-surface-2">
              <EyeOff className="size-4" aria-hidden />
              {t('cs.hide_today')}
            </button>
          </div>
        )}
      </div>
      {open && <CsPanel onClose={() => { setOpen(false); btn.current?.focus() }} />}
    </div>
  )
}

type CsTab = 'chat' | 'ticket' | 'help' | 'wa'

function CsPanel({ onClose }: { onClose: () => void }) {
  const { t } = useTranslation()
  const { settings, me } = useAuth()
  const isMobile = useIsMobile()
  const ref = useRef<HTMLDivElement>(null)
  const loc = useLocation()
  const [tab, setTab] = useState<CsTab>('chat')
  useFocusTrap(ref, true)
  useBackClose(true, onClose)
  useEffect(() => {
    const k = (e: KeyboardEvent) => e.key === 'Escape' && onClose()
    document.addEventListener('keydown', k)
    return () => document.removeEventListener('keydown', k)
  }, [onClose])

  const wa = settings?.cs.whatsapp
  const tabs: { value: CsTab; label: string }[] = [
    { value: 'chat', label: t('cs.tab_chat') },
    { value: 'ticket', label: t('cs.tab_ticket') },
    { value: 'help', label: t('cs.tab_help') },
    ...(wa ? [{ value: 'wa' as CsTab, label: 'WhatsApp' }] : []),
  ]

  return (
    <>
      {isMobile && <div className="fixed inset-0 z-scrim bg-[var(--overlay-dark)]" onClick={onClose} aria-hidden />}
      <div
        ref={ref}
        role="dialog"
        aria-modal="true"
        aria-label={t('cs.title')}
        className={cn(
          'flex flex-col overflow-hidden border border-line bg-surface text-fg shadow-k-lg',
          isMobile ? 'anim-sheet fixed inset-x-0 bottom-0 z-modal h-[75dvh] rounded-t-xl pb-[var(--safe-b)]' : 'anim-pop absolute bottom-full right-0 mb-3 h-[520px] w-[360px] rounded-xl',
        )}
      >
        <div className="border-b border-line bg-primary px-4 py-3 text-primary-contrast">
          <div className="flex items-start justify-between gap-2">
            <div>
              <p className="font-semibold">{t('cs.title')}</p>
              <p className="t-small opacity-85">{t('cs.greeting')}</p>
            </div>
            <button type="button" onClick={onClose} aria-label={t('common.close')} className="grid size-9 place-items-center rounded-md hover:bg-white/15">
              <X className="size-5" aria-hidden />
            </button>
          </div>
          <p className="t-small mt-1 opacity-85">
            {t('cs.hours')}: {settings?.cs.hours ?? '—'}. {t('cs.reply_estimate')}: {settings?.cs.reply_estimate ?? '—'}
          </p>
        </div>
        <div className="border-b border-line px-3 py-2">
          <Segmented items={tabs} value={tab} onChange={setTab} label={t('cs.title')} />
        </div>
        <div className="min-h-0 flex-1 overflow-y-auto p-4">
          {tab === 'chat' && <CsChat onTicket={() => setTab('ticket')} />}
          {tab === 'ticket' && <CsTicket loggedIn={!!me} page={loc.pathname} />}
          {tab === 'help' && <CsHelp onClose={onClose} />}
          {tab === 'wa' && wa && (
            <div className="grid gap-3">
              <p className="t-body text-muted">{t('cs.wa_body')}</p>
              <a
                href={`https://wa.me/${wa.replace(/\D/g, '')}?text=${encodeURIComponent(t('cs.wa_text', { page: loc.pathname, id: me ? `#${me.id}` : '-' }))}`}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-success font-semibold text-white dark:text-bg"
              >
                <MessageCircle className="size-5" aria-hidden />
                {t('cs.wa_open')}
              </a>
            </div>
          )}
        </div>
      </div>
    </>
  )
}

// Chatbot bantuan awal (AI Assistant terbatas FAQ) → eskalasi ke tiket (§15.2 butir 4)
function CsChat({ onTicket }: { onTicket: () => void }) {
  const { t } = useTranslation()
  const [msgs, setMsgs] = useState<{ from: 'bot' | 'me'; text: string; faq?: FaqItem[] }[]>([{ from: 'bot', text: t('cs.bot_intro') }])
  const [q, setQ] = useState('')
  const [busy, setBusy] = useState(false)
  const end = useRef<HTMLDivElement>(null)
  useEffect(() => end.current?.scrollIntoView({ block: 'end' }), [msgs])
  const send = async (e: FormEvent) => {
    e.preventDefault()
    const text = q.trim()
    if (!text) return
    setQ('')
    setMsgs((m) => [...m, { from: 'me', text }])
    setBusy(true)
    try {
      const r = await http.get<{ data: FaqItem[] }>('/public/faqs', { search: text, limit: 3 })
      setMsgs((m) => [...m, r.data.length ? { from: 'bot', text: t('cs.bot_found'), faq: r.data } : { from: 'bot', text: t('cs.bot_none') }])
    } catch {
      setMsgs((m) => [...m, { from: 'bot', text: t('cs.bot_none') }])
    } finally {
      setBusy(false)
    }
  }
  return (
    <div className="flex h-full flex-col gap-3">
      <div className="grid flex-1 content-start gap-2" aria-live="polite">
        {msgs.map((m, i) => (
          <div key={i} className={cn('max-w-[88%] rounded-lg px-3 py-2 text-sm', m.from === 'me' ? 'justify-self-end bg-primary text-primary-contrast' : 'bg-surface-2 text-fg')}>
            <p>{m.text}</p>
            {m.faq && (
              <ul className="mt-2 grid gap-2">
                {m.faq.map((f) => (
                  <li key={f.id} className="rounded-md bg-surface p-2">
                    <p className="font-semibold">{f.question}</p>
                    <p className="t-small text-muted">{f.answer}</p>
                  </li>
                ))}
              </ul>
            )}
          </div>
        ))}
        {busy && <span className="t-small text-muted">{t('cs.typing')}</span>}
        <div ref={end} />
      </div>
      <button type="button" onClick={onTicket} className="t-label justify-self-start text-primary hover:underline">
        {t('cs.escalate')}
      </button>
      <form onSubmit={send} className="flex gap-2">
        <input value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('cs.ask_placeholder')} aria-label={t('cs.ask_placeholder')} className="h-11 min-w-0 flex-1 rounded-md border border-line bg-surface px-3 text-fg focus:border-primary focus:outline-none" />
        <button type="submit" aria-label={t('cs.send')} className="grid size-11 place-items-center rounded-md bg-primary text-primary-contrast">
          <Send className="size-4" aria-hidden />
        </button>
      </form>
    </div>
  )
}

function CsTicket({ loggedIn, page }: { loggedIn: boolean; page: string }) {
  const { t } = useTranslation()
  const [form, setForm] = useState({ name: '', email: '', subject: '', message: '' })
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [busy, setBusy] = useState(false)
  const [done, setDone] = useState<string | null>(null)
  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setErrors({})
    try {
      const r = await http.post<{ data: { number: string } }>('/support/tickets', { ...form, page })
      setDone(r.data.number)
    } catch (err) {
      if (err instanceof ApiError) setErrors(err.fields)
    } finally {
      setBusy(false)
    }
  }
  if (done)
    return (
      <div className="grid gap-2 text-center">
        <Ticket className="mx-auto size-8 text-success" aria-hidden />
        <p className="font-semibold">{t('cs.ticket_sent')}</p>
        <p className="t-small text-muted">{t('cs.ticket_number', { n: done })}</p>
      </div>
    )
  return (
    <form onSubmit={submit} className="grid gap-3" noValidate>
      {!loggedIn && (
        <>
          <TextField label={t('fields.name')} required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} error={errors.name?.[0]} autoComplete="name" />
          <TextField label={t('fields.email')} required type="email" inputMode="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} error={errors.email?.[0]} autoComplete="email" />
        </>
      )}
      <TextField label={t('cs.subject')} required value={form.subject} onChange={(e) => setForm({ ...form, subject: e.target.value })} error={errors.subject?.[0]} />
      <TextArea label={t('cs.message')} required rows={4} value={form.message} onChange={(e) => setForm({ ...form, message: e.target.value })} error={errors.message?.[0]} />
      <Button type="submit" loading={busy} block icon={<Ticket className="size-4" aria-hidden />}>
        {t('cs.send_ticket')}
      </Button>
    </form>
  )
}

function CsHelp({ onClose }: { onClose: () => void }) {
  const { t } = useTranslation()
  const [items, setItems] = useState<FaqItem[] | null>(null)
  useEffect(() => {
    http.get<{ data: FaqItem[] }>('/public/faqs', { limit: 6 }).then((r) => setItems(r.data)).catch(() => setItems([]))
  }, [])
  return (
    <div className="grid gap-2">
      {items === null && <p className="t-small text-muted">{t('loader.loading')}</p>}
      {items?.map((f) => (
        <details key={f.id} className="rounded-md border border-line p-3">
          <summary className="cursor-pointer text-sm font-semibold">{f.question}</summary>
          <p className="t-small mt-2 text-muted">{f.answer}</p>
        </details>
      ))}
      <Link to="/faq" onClick={onClose} className="t-label mt-1 inline-flex items-center gap-1.5 text-primary hover:underline">
        <BookOpen className="size-4" aria-hidden />
        {t('cs.open_help_center')}
      </Link>
    </div>
  )
}
