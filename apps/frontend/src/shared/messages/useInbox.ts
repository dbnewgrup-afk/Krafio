import { useCallback, useEffect, useRef, useState } from 'react'
import { http } from '@/api-client/http'
import { useMessages } from './MessagesProvider'
import { basePath, newClientId, type Area, type Conversation, type InboxStats, type Message, type Thread } from './types'

export interface ListQuery {
  filter: string
  search: string
  sort: string
  priority: string
  assignee: string
  tag: string
  from: string
  to: string
}
export const EMPTY_QUERY: ListQuery = { filter: 'all', search: '', sort: 'recent', priority: '', assignee: '', tag: '', from: '', to: '' }

/** Daftar percakapan + statistik; menyegarkan diri saat ada pesan baru dari feed global. */
export function useConversations(area: Area, q: ListQuery) {
  const { lastEventAt } = useMessages()
  const [items, setItems] = useState<Conversation[] | null>(null)
  const [stats, setStats] = useState<InboxStats | null>(null)
  const [admins, setAdmins] = useState<{ id: number; name: string }[]>([])
  const [tags, setTags] = useState<string[]>([])
  const [error, setError] = useState(false)

  const load = useCallback(async () => {
    try {
      const params = Object.fromEntries(Object.entries(q).filter(([, v]) => v !== ''))
      const r = await http.get<{ data: Conversation[]; stats?: InboxStats; admins?: { id: number; name: string }[]; tags?: string[] }>(basePath(area), params)
      setItems(r.data)
      if (r.stats) setStats(r.stats)
      if (r.admins) setAdmins(r.admins)
      if (r.tags) setTags(r.tags)
      setError(false)
    } catch {
      setError(true)
      setItems((x) => x ?? [])
    }
  }, [area, q])

  useEffect(() => {
    setItems(null)
    const id = window.setTimeout(() => void load(), q.search ? 250 : 0)
    return () => window.clearTimeout(id)
  }, [load, q.search])

  useEffect(() => {
    if (lastEventAt) void load()
  }, [lastEventAt, load])

  // Segarkan berkala (SLA & status dari admin lain)
  useEffect(() => {
    const id = window.setInterval(() => !document.hidden && void load(), 30000)
    return () => window.clearInterval(id)
  }, [load])

  const patchLocal = (id: number, patch: Partial<Conversation>) => setItems((xs) => xs?.map((c) => (c.id === id ? { ...c, ...patch } : c)) ?? xs)
  return { items, stats, admins, tags, error, reload: load, patchLocal, setItems }
}

/**
 * Satu percakapan: muat penuh lalu polling pesan baru (3 dtk, `after=id terakhir`), status mengetik,
 * kirim optimistik (sending → sent, gagal → retry) dengan client_id idempoten.
 */
export function useThread(area: Area, id: number | null) {
  const { send: sendGlobal, setActiveConversation } = useMessages()
  const [thread, setThread] = useState<Thread | null>(null)
  const [error, setError] = useState(false)
  const lastId = useRef(0)
  const base = basePath(area)

  const full = useCallback(async () => {
    if (!id) return
    try {
      const r = await http.get<{ data: Thread }>(`${base}/${id}`)
      lastId.current = Number(r.data.messages.at(-1)?.id ?? 0)
      setThread(r.data)
      setError(false)
    } catch {
      setError(true)
    }
  }, [base, id])

  useEffect(() => {
    setThread(null)
    lastId.current = 0
    setActiveConversation(id)
    void full()
    return () => setActiveConversation(null)
  }, [id, full, setActiveConversation])

  const mine: Message['from'] = area === 'admin' ? 'admin' : 'user'

  useEffect(() => {
    if (!id) return
    const tick = async () => {
      if (document.hidden) return
      try {
        const r = await http.get<{ data: Thread }>(`${base}/${id}`, { after: lastId.current })
        setThread((t) => {
          if (!t) return r.data
          const known = new Set(t.messages.map((m) => m.client_id ?? m.id))
          const fresh = r.data.messages.filter((m) => !known.has(m.client_id ?? m.id) && !known.has(m.id))
          // Status baca pesan sendiri diperbarui dari conversation (unread lawan = 0 → semua sudah terbaca)
          const upto = r.data.read_upto ?? 0
          const merged = [...t.messages, ...fresh].map((m) => (m.from === mine && typeof m.id === 'number' && m.id <= upto && m.status !== 'read' ? { ...m, status: 'read' as const } : m))
          return { ...t, ...r.data, notes: r.data.notes ?? t.notes, activity: r.data.activity ?? t.activity, profile: r.data.profile ?? t.profile, messages: merged }
        })
        if (r.data.messages.length) lastId.current = Number(r.data.messages.at(-1)!.id)
      } catch {
        /* coba lagi */
      }
    }
    const h = window.setInterval(() => void tick(), 4000)
    return () => window.clearInterval(h)
  }, [base, id, mine])


  const send = useCallback(
    async (body: string, attachments: { url: string; name?: string }[] = [], retryOf?: string) => {
      if (!id) return
      const clientId = retryOf ?? newClientId()
      const optimistic: Message = { id: clientId, client_id: clientId, from: mine, body, attachments, status: 'sending', created_at: new Date().toISOString(), author: '' }
      setThread((t) => (t ? { ...t, messages: retryOf ? t.messages.map((m) => (m.client_id === clientId ? { ...m, status: 'sending' } : m)) : [...t.messages, optimistic] } : t))
      try {
        const saved = attachments.length
          ? (await http.post<{ data: Message }>(`${base}/${id}/messages`, { body, client_id: clientId, attachments })).data
          : await sendGlobal(id, body, clientId)
        setThread((t) => (t ? { ...t, messages: t.messages.map((m) => (m.client_id === clientId ? { ...saved, status: 'sent' } : m)) } : t))
        lastId.current = Math.max(lastId.current, Number(saved.id))
      } catch {
        setThread((t) => (t ? { ...t, messages: t.messages.map((m) => (m.client_id === clientId ? { ...m, status: 'failed' } : m)) } : t))
      }
    },
    [base, id, mine, sendGlobal],
  )

  const lastPing = useRef(0)
  const typing = useCallback(() => {
    if (!id || Date.now() - lastPing.current < 3000) return
    lastPing.current = Date.now()
    void http.post(`${base}/${id}/typing`).catch(() => undefined)
  }, [base, id])

  return { thread, setThread, error, reload: full, send, typing }
}
