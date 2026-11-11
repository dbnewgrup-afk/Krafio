import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { ArrowDown, ArrowUp, ChevronLeft, ChevronRight, Columns3, Download, Search, SlidersHorizontal } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { http, ApiError, type Paginated, type Query } from '@/api-client/http'
import { cn } from '@/shared/lib/cn'
import { useIsMobile } from '@/shared/hooks/useMediaQuery'
import { Menu } from './Misc'
import { EmptyState, ErrorState, SkeletonRows } from './States'
import { inputCls } from './Field'

export interface Column<T> {
  key: string
  header: string
  render?: (row: T) => ReactNode
  align?: 'left' | 'right' | 'center'
  sortable?: boolean
  /** Ditampilkan sebagai judul kartu di mobile */
  primary?: boolean
  /** Ditampilkan di kartu mobile (maks 3) */
  mobile?: boolean
  hiddenByDefault?: boolean
  className?: string
}

export interface FilterDef {
  key: string
  label: string
  options: { value: string; label: string }[]
}

/* Hook daftar server-side: cari, filter, sort, pagination (§7.2 butir 5, §18.2) */
export function useServerList<T>(endpoint: string, initial: Query = {}) {
  const [query, setQuery] = useState<Query>({ page: 1, per_page: 25, ...initial })
  const [data, setData] = useState<Paginated<T> | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<ApiError | null>(null)
  const [tick, setTick] = useState(0)

  useEffect(() => {
    const ctrl = new AbortController()
    setLoading(true)
    setError(null)
    http
      .get<Paginated<T>>(endpoint, query, ctrl.signal)
      .then((r) => setData(r))
      .catch((e) => {
        if ((e as Error).name !== 'AbortError') setError(e as ApiError)
      })
      .finally(() => {
        if (!ctrl.signal.aborted) setLoading(false)
      })
    return () => ctrl.abort()
  }, [endpoint, query, tick])

  const update = useCallback((patch: Query) => setQuery((q) => ({ ...q, page: 1, ...patch })), [])
  const reload = useCallback(() => setTick((x) => x + 1), [])
  return { endpoint, query, setQuery, update, data, loading, error, reload }
}

export function DataTable<T extends { id: number | string }>({
  columns,
  list,
  filters = [],
  searchPlaceholder,
  rowActions,
  bulkActions,
  exportName,
  emptyTitle,
  emptyBody,
  emptyAction,
  onRowClick,
  toolbarExtra,
}: {
  columns: Column<T>[]
  list: ReturnType<typeof useServerList<T>>
  filters?: FilterDef[]
  searchPlaceholder?: string
  rowActions?: (row: T) => ReactNode
  bulkActions?: (ids: Array<T['id']>, clear: () => void) => ReactNode
  exportName?: string
  emptyTitle?: string
  emptyBody?: string
  emptyAction?: ReactNode
  onRowClick?: (row: T) => void
  toolbarExtra?: ReactNode
}) {
  const { t } = useTranslation()
  const isMobile = useIsMobile()
  const { query, update, setQuery, data, loading, error, reload } = list
  const [hidden, setHidden] = useState<Set<string>>(() => new Set(columns.filter((c) => c.hiddenByDefault).map((c) => c.key)))
  const [selected, setSelected] = useState<Set<T['id']>>(new Set())
  const [search, setSearch] = useState(String(query.search ?? ''))
  const debounce = useRef<number | undefined>(undefined)
  const visible = columns.filter((c) => !hidden.has(c.key))
  const rows = data?.data ?? []
  const meta = data?.meta
  const activeFilters = filters.filter((f) => query[f.key]).length

  useEffect(() => {
    setSelected(new Set())
  }, [data])

  const onSearch = (v: string) => {
    setSearch(v)
    window.clearTimeout(debounce.current)
    debounce.current = window.setTimeout(() => update({ search: v }), 350)
  }

  const sortBy = (key: string) => {
    const cur = String(query.sort ?? '')
    const next = cur === key ? `-${key}` : cur === `-${key}` ? '' : key
    update({ sort: next })
  }

  const exportCsv = () => {
    const params = new URLSearchParams()
    Object.entries({ ...query, export: 'csv' }).forEach(([k, v]) => v !== undefined && v !== null && v !== '' && params.set(k, String(v)))
    window.location.href = `/api${list.endpoint}?${params.toString()}`
  }

  const allChecked = rows.length > 0 && rows.every((r) => selected.has(r.id))
  const mobileCols = useMemo(() => columns.filter((c) => c.mobile).slice(0, 3), [columns])
  const primaryCol = columns.find((c) => c.primary) ?? columns[0]

  return (
    <div className="grid gap-3">
      {/* Toolbar (§7.4) */}
      <div className="flex flex-wrap items-center gap-2">
        <div className="relative min-w-0 flex-1 basis-56">
          <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" aria-hidden />
          <input type="search" value={search} onChange={(e) => onSearch(e.target.value)} placeholder={searchPlaceholder ?? t('table.search')} aria-label={t('table.search')} className={cn(inputCls, 'pl-9')} />
        </div>
        {filters.length > 0 && (
          <Menu
            label={t('table.filter')}
            width="w-72"
            trigger={({ toggle, open, id }) => (
              <button type="button" onClick={toggle} aria-expanded={open} aria-controls={id} className="inline-flex h-12 items-center gap-2 rounded-md border border-line bg-surface px-3 text-sm font-semibold hover:bg-surface-2 lg:h-10">
                <SlidersHorizontal className="size-4" aria-hidden />
                {t('table.filter')}
                {activeFilters > 0 && <span className="rounded-full bg-primary px-1.5 text-[11px] text-primary-contrast">{activeFilters}</span>}
              </button>
            )}
          >
            {(close) => (
              <div className="grid gap-3 p-3">
                {filters.map((f) => (
                  <label key={f.key} className="grid gap-1">
                    <span className="t-label">{f.label}</span>
                    <select className={inputCls} value={String(query[f.key] ?? '')} onChange={(e) => update({ [f.key]: e.target.value })}>
                      <option value="">{t('table.all')}</option>
                      {f.options.map((o) => (
                        <option key={o.value} value={o.value}>
                          {o.label}
                        </option>
                      ))}
                    </select>
                  </label>
                ))}
                <button
                  type="button"
                  className="t-label h-9 rounded-md text-primary hover:bg-primary-soft"
                  onClick={() => {
                    const reset: Query = {}
                    filters.forEach((f) => (reset[f.key] = ''))
                    update(reset)
                    close()
                  }}
                >
                  {t('table.reset_filter')}
                </button>
              </div>
            )}
          </Menu>
        )}
        {!isMobile && (
          <Menu
            label={t('table.columns')}
            trigger={({ toggle, open, id }) => (
              <button type="button" onClick={toggle} aria-expanded={open} aria-controls={id} className="inline-flex h-10 items-center gap-2 rounded-md border border-line bg-surface px-3 text-sm font-semibold hover:bg-surface-2">
                <Columns3 className="size-4" aria-hidden />
                {t('table.columns')}
              </button>
            )}
          >
            {() => (
              <div className="grid gap-1 p-2">
                {columns.map((c) => (
                  <label key={c.key} className="flex h-9 items-center gap-2 rounded-md px-2 text-sm hover:bg-surface-2">
                    <input
                      type="checkbox"
                      className="size-4 accent-[var(--primary)]"
                      checked={!hidden.has(c.key)}
                      onChange={() => {
                        const n = new Set(hidden)
                        if (n.has(c.key)) n.delete(c.key)
                        else n.add(c.key)
                        setHidden(n)
                      }}
                    />
                    {c.header}
                  </label>
                ))}
              </div>
            )}
          </Menu>
        )}
        {exportName && (
          <button type="button" onClick={exportCsv} className="inline-flex h-12 items-center gap-2 rounded-md border border-line bg-surface px-3 text-sm font-semibold hover:bg-surface-2 lg:h-10">
            <Download className="size-4" aria-hidden />
            <span className="max-sm:sr-only">{t('table.export')}</span>
          </button>
        )}
        {toolbarExtra}
      </div>

      {/* Toolbar kontekstual aksi massal (§7.2 butir 12) */}
      {bulkActions && selected.size > 0 && (
        <div className="anim-fade-in sticky top-[var(--nav-h)] z-sticky flex flex-wrap items-center gap-2 rounded-md border border-primary/40 bg-primary-soft px-3 py-2">
          <span className="t-label text-fg">{t('table.selected', { count: selected.size })}</span>
          <div className="ml-auto flex flex-wrap gap-2">{bulkActions(Array.from(selected), () => setSelected(new Set()))}</div>
        </div>
      )}

      {error ? (
        <ErrorState status={error.status} requestId={error.requestId} onRetry={reload} compact />
      ) : loading && !data ? (
        <SkeletonRows rows={8} />
      ) : rows.length === 0 ? (
        <EmptyState
          kind={search || activeFilters ? 'filter' : 'empty'}
          title={search || activeFilters ? t('table.no_results') : (emptyTitle ?? t('table.empty'))}
          body={search || activeFilters ? t('table.no_results_body') : emptyBody}
          action={
            search || activeFilters ? (
              <button type="button" className="t-label h-10 rounded-md px-3 text-primary hover:bg-primary-soft" onClick={() => { setSearch(''); setQuery({ page: 1, per_page: query.per_page }) }}>
                {t('table.reset_filter')}
              </button>
            ) : (
              emptyAction
            )
          }
        />
      ) : isMobile ? (
        /* Mobile: mode kartu ringkas (§18.2) */
        <ul className={cn('grid gap-2', loading && 'opacity-60')} aria-busy={loading}>
          {rows.map((r) => (
            <li key={r.id} className="flex items-start gap-3 rounded-lg border border-line bg-surface p-3">
              {bulkActions && (
                <input type="checkbox" aria-label={t('table.select_row')} className="mt-1 size-5 accent-[var(--primary)]" checked={selected.has(r.id)} onChange={() => toggleSel(selected, setSelected, r.id)} />
              )}
              <button type="button" disabled={!onRowClick} onClick={() => onRowClick?.(r)} className="grid min-w-0 flex-1 gap-1.5 text-left disabled:cursor-default">
                <span className="font-semibold text-fg">{primaryCol.render ? primaryCol.render(r) : String((r as any)[primaryCol.key] ?? '—')}</span>
                {mobileCols
                  .filter((c) => c !== primaryCol)
                  .map((c) => (
                    <span key={c.key} className="t-small flex items-center gap-2 text-muted">
                      <span className="shrink-0">{c.header}:</span>
                      <span className="min-w-0 truncate text-fg">{c.render ? c.render(r) : String((r as any)[c.key] ?? '—')}</span>
                    </span>
                  ))}
              </button>
              {rowActions && <div className="shrink-0">{rowActions(r)}</div>}
            </li>
          ))}
        </ul>
      ) : (
        <div className={cn('overflow-x-auto rounded-lg border border-line bg-surface', loading && 'opacity-60')} aria-busy={loading}>
          <table className="w-full min-w-[640px] border-collapse text-sm">
            <thead className="sticky top-0 z-sticky bg-bg-subtle">
              <tr className="border-b border-line">
                {bulkActions && (
                  <th className="w-10 px-3">
                    <input type="checkbox" aria-label={t('table.select_all')} className="size-4 accent-[var(--primary)]" checked={allChecked} onChange={() => setSelected(allChecked ? new Set() : new Set(rows.map((r) => r.id)))} />
                  </th>
                )}
                {visible.map((c) => {
                  const s = String(query.sort ?? '')
                  const dir = s === c.key ? 'asc' : s === `-${c.key}` ? 'desc' : null
                  return (
                    <th key={c.key} scope="col" aria-sort={dir === 'asc' ? 'ascending' : dir === 'desc' ? 'descending' : undefined} className={cn('t-label h-11 whitespace-nowrap px-3 text-muted', c.align === 'right' ? 'text-right' : c.align === 'center' ? 'text-center' : 'text-left')}>
                      {c.sortable ? (
                        <button type="button" onClick={() => sortBy(c.key)} className="inline-flex items-center gap-1 hover:text-fg">
                          {c.header}
                          {dir === 'asc' ? <ArrowUp className="size-3.5" aria-hidden /> : dir === 'desc' ? <ArrowDown className="size-3.5" aria-hidden /> : null}
                        </button>
                      ) : (
                        c.header
                      )}
                    </th>
                  )
                })}
                {rowActions && <th className="w-14 px-3"><span className="sr-only">{t('table.actions')}</span></th>}
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id} onClick={onRowClick ? () => onRowClick(r) : undefined} className={cn('border-b border-line last:border-b-0 hover:bg-surface-2/60', onRowClick && 'cursor-pointer')}>
                  {bulkActions && (
                    <td className="px-3" onClick={(e) => e.stopPropagation()}>
                      <input type="checkbox" aria-label={t('table.select_row')} className="size-4 accent-[var(--primary)]" checked={selected.has(r.id)} onChange={() => toggleSel(selected, setSelected, r.id)} />
                    </td>
                  )}
                  {visible.map((c) => (
                    <td key={c.key} className={cn('h-12 px-3 py-2 align-middle text-fg', c.align === 'right' && 'text-right tabular-nums', c.align === 'center' && 'text-center', c.className)}>
                      {c.render ? c.render(r) : String((r as any)[c.key] ?? '—')}
                    </td>
                  ))}
                  {rowActions && (
                    <td className="px-3 text-right" onClick={(e) => e.stopPropagation()}>
                      {rowActions(r)}
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {meta && meta.total > 0 && (
        <div className="flex flex-wrap items-center justify-between gap-3">
          <label className="t-small flex items-center gap-2 text-muted">
            {t('table.show')}
            <select className="h-9 rounded-md border border-line bg-surface px-2 text-sm text-fg" value={Number(query.per_page)} onChange={(e) => update({ per_page: Number(e.target.value) })}>
              {[10, 25, 50, 100].map((n) => (
                <option key={n} value={n}>
                  {n}
                </option>
              ))}
            </select>
            <span className="tabular-nums">{t('table.of_total', { total: meta.total })}</span>
          </label>
          <nav className="flex items-center gap-1" aria-label={t('table.pagination')}>
            <button type="button" disabled={meta.current_page <= 1} onClick={() => setQuery((q) => ({ ...q, page: meta.current_page - 1 }))} aria-label={t('table.prev')} className="grid size-10 place-items-center rounded-md border border-line hover:bg-surface-2 disabled:opacity-40">
              <ChevronLeft className="size-4" aria-hidden />
            </button>
            <span className="t-small px-2 tabular-nums text-muted">
              {meta.current_page} / {meta.last_page}
            </span>
            <button type="button" disabled={meta.current_page >= meta.last_page} onClick={() => setQuery((q) => ({ ...q, page: meta.current_page + 1 }))} aria-label={t('table.next')} className="grid size-10 place-items-center rounded-md border border-line hover:bg-surface-2 disabled:opacity-40">
              <ChevronRight className="size-4" aria-hidden />
            </button>
          </nav>
        </div>
      )}
    </div>
  )
}

function toggleSel<K>(sel: Set<K>, set: (s: Set<K>) => void, id: K) {
  const n = new Set(sel)
  if (n.has(id)) n.delete(id)
  else n.add(id)
  set(n)
}

