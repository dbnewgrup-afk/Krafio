export type Area = 'user' | 'admin'
export type ConvStatus = 'open' | 'pending' | 'resolved' | 'closed'
export type Priority = 'low' | 'normal' | 'high' | 'urgent'
export type MsgStatus = 'sending' | 'sent' | 'delivered' | 'read' | 'failed'

export interface Conversation {
  id: number
  number: string
  subject: string
  status: ConvStatus
  priority: Priority
  tags: string[]
  unread: number
  last_from: 'user' | 'admin' | 'system' | null
  last_message_at: string
  sla_due_at: string | null
  sla_breached: boolean
  archived: boolean
  preview: string
  requester: { id: number | null; name: string; email: string | null; online: boolean }
  assignee: { id: number; name: string } | null
}

export interface Attachment {
  url: string
  name?: string
}

export interface Message {
  id: number | string
  client_id?: string | null
  from: 'user' | 'admin' | 'system'
  body: string
  attachments: Attachment[]
  status: MsgStatus
  created_at: string
  author: string
}

export interface FeedItem {
  id: number
  conversation_id: number
  number: string
  subject: string
  body: string
  created_at: string
  sender: string
}

export interface Feed {
  cursor: number
  unread: number
  items: FeedItem[]
  recent: Conversation[]
}

export interface InboxStats {
  unread: number
  open: number
  avg_response_minutes: number | null
  resolved_today: number
  sla_breached: number
}

export interface Thread {
  conversation: Conversation
  messages: Message[]
  typing: boolean
  /** id pesan sendiri terakhir yang sudah dibaca lawan bicara */
  read_upto?: number
  notes?: { id: number; body: string; author: string; created_at: string }[]
  activity?: { id: number; action: string; meta: Record<string, unknown> | null; actor: string; created_at: string }[]
  profile?: {
    id: number | null
    name: string | null
    email: string | null
    phone?: string | null
    status?: string
    plan?: string | null
    joined_at?: string | null
    last_login_at?: string | null
    orders?: number
    topup_balance?: number
    tickets?: number
    recent?: { id: number; number: string; subject: string; status: string }[]
    guest?: boolean
  }
  page?: string | null
}

export const basePath = (area: Area) => (area === 'admin' ? '/admin/inbox' : '/app/inbox')
export const inboxRoute = (area: Area, id?: number) => (area === 'admin' ? `/admin/support/inbox${id ? `?c=${id}` : ''}` : `/app/support${id ? `?c=${id}` : ''}`)

export function newClientId(): string {
  return `c_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 8)}`
}

export function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]!.toUpperCase())
    .join('')
}
