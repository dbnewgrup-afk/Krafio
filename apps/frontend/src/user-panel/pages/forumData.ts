import { useEffect, useState } from 'react'
import { http, ApiError } from '@/api-client/http'

export interface ForumCategory {
  id: number
  slug: string
  name: string
  description: string | null
  icon: string | null
  admin_only_posting: boolean
  threads_count: number
  last_activity_at: string | null
}
export interface ForumCategoriesResp {
  data: ForumCategory[]
  meta: { muted: boolean; muted_until: string | null; muted_reason: string | null; edit_minutes: number; report_reasons: string[] }
}

/** Data kategori + status bisu (dipakai halaman & menu). */
export function useForumCategories(enabled = true) {
  const [state, setState] = useState<ForumCategoriesResp | null>(null)
  const [error, setError] = useState<ApiError | null>(null)
  useEffect(() => {
    if (!enabled) return
    let live = true
    http.get<ForumCategoriesResp>('/app/forum/categories').then((r) => live && setState(r)).catch((e) => live && setError(e as ApiError))
    return () => {
      live = false
    }
  }, [enabled])
  return { data: state, error }
}

