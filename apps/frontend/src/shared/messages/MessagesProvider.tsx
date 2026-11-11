import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { useNavigate } from 'react-router-dom'
import { http } from '@/api-client/http'
import { basePath, inboxRoute, newClientId, type Area, type Conversation, type Feed, type FeedItem, type Message } from './types'
import { MessageToasts } from './MessageToasts'

/** Pengaturan notifikasi pesan (per perangkat). */
export interface NotifySettings {
  sound: boolean
  desktop: boolean
  dnd: boolean
  muted: number[]
}
const DEFAULT_SETTINGS: NotifySettings = { sound: true, desktop: false, dnd: false, muted: [] }

interface Queued {
  conversationId: number
  body: string
  clientId: string
}

interface Ctx {
  area: Area
  unread: number
  recent: Conversation[]
  settings: NotifySettings
  setSettings: (patch: Partial<NotifySettings>) => void
  /** Kirim balasan (optimistik + antrean offline). Mengembalikan pesan dari server. */
  send: (conversationId: number, body: string, clientId?: string) => Promise<Message>
  markRead: (conversationId: number) => Promise<void>
  open: (conversationId: number) => void
  /** Halaman inbox sedang membuka percakapan ini → jangan munculkan toast-nya */
  setActiveConversation: (id: number | null) => void
  /** Antrean pesan yang belum terkirim karena offline */
  queued: number
  refresh: () => void
  /** Pesan baru (untuk halaman inbox menyegarkan daftar) */
  lastEventAt: number
}

const MessagesCtx = createContext<Ctx | null>(null)
const QKEY = (area: Area) => `krafio.msg.queue.${area}`
const SKEY = (area: Area) => `krafio.msg.settings.${area}`

function load<T>(key: string, fallback: T): T {
  try {
    const raw = localStorage.getItem(key)
    return raw ? { ...fallback, ...JSON.parse(raw) } : fallback
  } catch {
    return fallback
  }
}
function loadQueue(area: Area): Queued[] {
  try {
    return JSON.parse(localStorage.getItem(QKEY(area)) ?? '[]') as Queued[]
  } catch {
    return []
  }
}
function save(key: string, v: unknown) {
  try {
    localStorage.setItem(key, JSON.stringify(v))
  } catch {
    /* noop */
  }
}

/** Bunyi notifikasi ringan via WebAudio (tanpa berkas audio). */
function chime() {
  try {
    const AC = window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext
    const ctx = new AC()
    const o = ctx.createOscillator()
    const g = ctx.createGain()
    o.type = 'sine'
    o.frequency.setValueAtTime(880, ctx.currentTime)
    o.frequency.exponentialRampToValueAtTime(1320, ctx.currentTime + 0.09)
    g.gain.setValueAtTime(0.0001, ctx.currentTime)
    g.gain.exponentialRampToValueAtTime(0.08, ctx.currentTime + 0.02)
    g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.35)
    o.connect(g).connect(ctx.destination)
    o.start()
    o.stop(ctx.currentTime + 0.36)
    window.setTimeout(() => void ctx.close(), 500)
  } catch {
    /* audio diblokir */
  }
}

/**
 * Provider global pesan untuk panel User dan Admin:
 * polling feed (6 dtk, berhenti saat tab tersembunyi), toast pesan masuk dengan balas cepat,
 * badge unread di header, notifikasi desktop opsional, mute/DND, dan antrean saat offline.
 */
export function MessagesProvider({ area, children }: { area: Area; children: ReactNode }) {
  const nav = useNavigate()
  const [unread, setUnread] = useState(0)
  const [recent, setRecent] = useState<Conversation[]>([])
  const [toasts, setToasts] = useState<FeedItem[]>([])
  const [settings, setSettingsState] = useState<NotifySettings>(() => load(SKEY(area), DEFAULT_SETTINGS))
  const [queue, setQueue] = useState<Queued[]>(() => loadQueue(area))
  const [lastEventAt, setLastEventAt] = useState(0)
  const cursor = useRef(0)
  const active = useRef<number | null>(null)
  const settingsRef = useRef(settings)
  settingsRef.current = settings
  const base = basePath(area)

  const setSettings = useCallback(
    (patch: Partial<NotifySettings>) => {
      setSettingsState((s) => {
        const next = { ...s, ...patch }
        save(SKEY(area), next)
        return next
      })
      if (patch.desktop && 'Notification' in window && Notification.permission === 'default') void Notification.requestPermission()
    },
    [area],
  )

  const poll = useCallback(async () => {
    try {
      const r = await http.get<{ data: Feed }>(`${base}/feed`, cursor.current ? { since: cursor.current } : {})
      const first = cursor.current === 0
      cursor.current = r.data.cursor
      setUnread(r.data.unread)
      setRecent(r.data.recent)
      const s = settingsRef.current
      const fresh = r.data.items.filter((m) => m.conversation_id !== active.current && !s.muted.includes(m.conversation_id))
      if (r.data.items.length) setLastEventAt(Date.now())
      // Muatan pertama: tampilkan hanya pesan <10 menit yang belum dibaca (tanpa bunyi)
      if (fresh.length && !s.dnd) {
        setToasts((xs) => {
          const merged = [...xs]
          for (const m of fresh) {
            const i = merged.findIndex((x) => x.conversation_id === m.conversation_id)
            if (i >= 0) merged.splice(i, 1)
            merged.push(m)
          }
          return merged.slice(-12)
        })
        if (!first) {
          if (s.sound) chime()
          if (s.desktop && 'Notification' in window && Notification.permission === 'granted' && document.hidden) {
            const m = fresh[fresh.length - 1]
            const n = new Notification(`${m.sender} — ${m.number}`, { body: m.body, tag: `krafio-msg-${m.conversation_id}` })
            n.onclick = () => {
              window.focus()
              nav(inboxRoute(area, m.conversation_id))
            }
          }
        }
      }
    } catch {
      /* jaringan putus: dicoba lagi pada siklus berikutnya */
    }
  }, [area, base, nav])

  // Polling adaptif: 8 dtk saat ada aktivitas, melambat bertahap sampai 30 dtk saat sepi; berhenti saat tab tersembunyi
  const idle = useRef(0)
  useEffect(() => {
    let id = 0
    let alive = true
    const loop = async () => {
      if (!document.hidden) {
        const before = cursor.current
        await poll()
        idle.current = cursor.current === before ? Math.min(idle.current + 1, 4) : 0
      }
      if (alive) id = window.setTimeout(loop, [8000, 12000, 18000, 24000, 30000][idle.current])
    }
    void loop()
    const vis = () => {
      if (!document.hidden) {
        idle.current = 0
        void poll()
      }
    }
    document.addEventListener('visibilitychange', vis)
    return () => {
      alive = false
      window.clearTimeout(id)
      document.removeEventListener('visibilitychange', vis)
    }
  }, [poll])

  const persistQueue = (q: Queued[]) => {
    setQueue(q)
    save(QKEY(area), q)
  }

  const send = useCallback(
    async (conversationId: number, body: string, clientId = newClientId()): Promise<Message> => {
      try {
        const r = await http.post<{ data: Message }>(`${base}/${conversationId}/messages`, { body, client_id: clientId })
        setToasts((xs) => xs.filter((x) => x.conversation_id !== conversationId))
        setLastEventAt(Date.now())
        return r.data
      } catch (e) {
        if (!navigator.onLine) {
          const q = [...loadQueue(area).filter((x) => x.clientId !== clientId), { conversationId, body, clientId }]
          persistQueue(q)
        }
        throw e
      }
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [area, base],
  )

  // Kirim ulang antrean saat kembali online (idempoten lewat client_id)
  useEffect(() => {
    const flush = async () => {
      const q = loadQueue(area)
      if (!q.length) return
      const left: Queued[] = []
      for (const m of q) {
        try {
          await http.post(`${base}/${m.conversationId}/messages`, { body: m.body, client_id: m.clientId })
        } catch {
          left.push(m)
        }
      }
      persistQueue(left)
      setLastEventAt(Date.now())
    }
    void flush()
    window.addEventListener('online', flush)
    return () => window.removeEventListener('online', flush)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [area, base])

  const markRead = useCallback(
    async (id: number) => {
      setToasts((xs) => xs.filter((x) => x.conversation_id !== id))
      setRecent((xs) => xs.filter((x) => x.id !== id))
      await http.post(`${base}/${id}/read`).catch(() => undefined)
      void poll()
    },
    [base, poll],
  )

  const open = useCallback(
    (id: number) => {
      setToasts((xs) => xs.filter((x) => x.conversation_id !== id))
      nav(inboxRoute(area, id))
    },
    [area, nav],
  )

  const setActiveConversation = useCallback((id: number | null) => {
    active.current = id
    if (id) setToasts((xs) => xs.filter((x) => x.conversation_id !== id))
  }, [])

  const value = useMemo<Ctx>(
    () => ({ area, unread, recent, settings, setSettings, send, markRead, open, setActiveConversation, queued: queue.length, refresh: () => void poll(), lastEventAt }),
    [area, unread, recent, settings, setSettings, send, markRead, open, setActiveConversation, queue.length, poll, lastEventAt],
  )

  return (
    <MessagesCtx.Provider value={value}>
      {children}
      <MessageToasts items={toasts} onDismiss={(id) => setToasts((xs) => xs.filter((x) => x.conversation_id !== id))} />
    </MessagesCtx.Provider>
  )
}

export function useMessages(): Ctx {
  const c = useContext(MessagesCtx)
  if (!c) throw new Error('MessagesProvider missing')
  return c
}

/** Balas cepat dari toast / dropdown: state input + kirim + status. */
export function useQuickReply(conversationId: number) {
  const { send, markRead } = useMessages()
  const [text, setText] = useState('')
  const [state, setState] = useState<'idle' | 'sending' | 'sent' | 'failed'>('idle')
  const submit = async () => {
    const body = text.trim()
    if (!body || state === 'sending') return false
    setState('sending')
    try {
      await send(conversationId, body)
      setText('')
      setState('sent')
      return true
    } catch {
      setState('failed')
      return false
    }
  }
  return { text, setText, state, submit, markRead: () => markRead(conversationId) }
}
