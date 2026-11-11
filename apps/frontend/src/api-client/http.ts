// Klien HTTP untuk REST API Laravel (PRD Bab 33). Autentikasi memakai cookie HttpOnly Sanctum —
// tidak ada token di localStorage (Design Spec §22).

export class ApiError extends Error {
  status: number
  code: string
  fields: Record<string, string[]>
  requestId?: string
  data?: unknown

  constructor(status: number, code: string, message: string, fields: Record<string, string[]> = {}, requestId?: string, data?: unknown) {
    super(message)
    this.status = status
    this.code = code
    this.fields = fields
    this.requestId = requestId
    this.data = data
  }
}

function readCookie(name: string): string | null {
  const m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'))
  return m ? decodeURIComponent(m[1]) : null
}

let csrfReady: Promise<void> | null = null
async function ensureCsrf(): Promise<void> {
  if (readCookie('XSRF-TOKEN')) return
  if (!csrfReady) {
    csrfReady = fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' }).then(() => undefined)
  }
  await csrfReady
  csrfReady = null
}

export type Query = Record<string, string | number | boolean | null | undefined>

function qs(params?: Query): string {
  if (!params) return ''
  const u = new URLSearchParams()
  Object.entries(params).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') u.set(k, String(v))
  })
  const s = u.toString()
  return s ? `?${s}` : ''
}

async function request<T>(method: string, path: string, body?: unknown, opts: { query?: Query; signal?: AbortSignal; idempotencyKey?: string } = {}): Promise<T> {
  if (method !== 'GET') await ensureCsrf()
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'Accept-Language': document.documentElement.lang || 'id',
  }
  const xsrf = readCookie('XSRF-TOKEN')
  if (xsrf) headers['X-XSRF-TOKEN'] = xsrf
  if (opts.idempotencyKey) headers['Idempotency-Key'] = opts.idempotencyKey
  if (body !== undefined) headers['Content-Type'] = 'application/json'

  let res: Response
  try {
    res = await fetch(`/api${path}${qs(opts.query)}`, {
      method,
      headers,
      credentials: 'same-origin',
      body: body !== undefined ? JSON.stringify(body) : undefined,
      signal: opts.signal,
    })
  } catch (e) {
    if ((e as Error).name === 'AbortError') throw e
    throw new ApiError(0, 'network_error', 'Network error')
  }

  const requestId = res.headers.get('X-Request-Id') ?? undefined
  if (res.status === 204) return undefined as T
  let data: any = null
  try {
    data = await res.json()
  } catch {
    // Respons bukan JSON (mis. halaman error server) tidak boleh dianggap sukses
    throw new ApiError(res.status >= 400 ? res.status : 502, 'invalid_response', 'Invalid server response', {}, requestId)
  }
  if (!res.ok) {
    if (res.status === 419) csrfReady = null
    throw new ApiError(
      res.status,
      data?.code ?? (res.status === 422 ? 'validation_failed' : res.status === 401 ? 'unauthenticated' : res.status === 403 ? 'forbidden' : res.status === 404 ? 'not_found' : res.status === 429 ? 'too_many_attempts' : 'server_error'),
      data?.message ?? res.statusText,
      data?.errors ?? {},
      requestId ?? data?.request_id,
      data,
    )
  }
  return data as T
}

export const http = {
  get: <T>(path: string, query?: Query, signal?: AbortSignal) => request<T>('GET', path, undefined, { query, signal }),
  post: <T>(path: string, body?: unknown, opts?: { idempotencyKey?: string }) => request<T>('POST', path, body ?? {}, opts),
  put: <T>(path: string, body?: unknown) => request<T>('PUT', path, body ?? {}),
  patch: <T>(path: string, body?: unknown) => request<T>('PATCH', path, body ?? {}),
  del: <T>(path: string, body?: unknown) => request<T>('DELETE', path, body),
}

export function newIdempotencyKey(): string {
  return (crypto as any).randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}
