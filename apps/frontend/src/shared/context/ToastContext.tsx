import { createContext, useCallback, useContext, useRef, useState, type ReactNode } from 'react'
import { CheckCircle2, AlertTriangle, XCircle, Info, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/shared/lib/cn'

type Kind = 'success' | 'info' | 'warning' | 'error'
interface Toast {
  id: number
  kind: Kind
  message: string
  action?: { label: string; onClick: () => void }
}
interface ToastCtx {
  push: (kind: Kind, message: string, action?: Toast['action']) => void
}
const Ctx = createContext<ToastCtx | null>(null)

const DURATION: Record<Kind, number | null> = { success: 4000, info: 4000, warning: 6000, error: null }
const ICON = { success: CheckCircle2, info: Info, warning: AlertTriangle, error: XCircle }
const COLOR: Record<Kind, string> = { success: 'text-success', info: 'text-info', warning: 'text-warning', error: 'text-danger' }

// Toast (Design Spec §17.1): desktop kanan atas di bawah navbar; mobile atas-tengah; error persisten; maks 3.
export function ToastProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<Toast[]>([])
  const timers = useRef(new Map<number, number>())
  const { t } = useTranslation()

  const dismiss = useCallback((id: number) => {
    setItems((xs) => xs.filter((x) => x.id !== id))
    const tm = timers.current.get(id)
    if (tm) window.clearTimeout(tm)
    timers.current.delete(id)
  }, [])

  const schedule = useCallback(
    (id: number, kind: Kind) => {
      const d = DURATION[kind]
      if (d) timers.current.set(id, window.setTimeout(() => dismiss(id), d))
    },
    [dismiss],
  )

  const push = useCallback(
    (kind: Kind, message: string, action?: Toast['action']) => {
      const id = Date.now() + Math.random()
      setItems((xs) => [...xs.slice(-2), { id, kind, message, action }])
      schedule(id, kind)
    },
    [schedule],
  )

  return (
    <Ctx.Provider value={{ push }}>
      {children}
      <div className="pointer-events-none fixed inset-x-0 top-[calc(var(--nav-h)+8px)] z-toast flex flex-col items-center gap-2 px-4 lg:items-end lg:px-6">
        {items.map((it) => {
          const Icon = ICON[it.kind]
          return (
            <div
              key={it.id}
              role={it.kind === 'error' ? 'alert' : 'status'}
              onMouseEnter={() => {
                const tm = timers.current.get(it.id)
                if (tm) window.clearTimeout(tm)
              }}
              onMouseLeave={() => schedule(it.id, it.kind)}
              onTouchEnd={(e) => {
                const el = e.currentTarget
                if (el.dataset.swipe === '1') dismiss(it.id)
              }}
              onTouchMove={(e) => {
                const el = e.currentTarget
                el.dataset.swipe = '1'
              }}
              className="pointer-events-auto anim-pop flex w-full max-w-sm items-start gap-3 rounded-lg border border-line bg-surface p-3 shadow-k-md"
            >
              <Icon className={cn('mt-0.5 size-5 shrink-0', COLOR[it.kind])} aria-hidden />
              <p className="t-small flex-1 text-fg">{it.message}</p>
              {it.action && (
                <button type="button" onClick={() => { it.action!.onClick(); dismiss(it.id) }} className="t-label rounded-md px-2 py-1 text-primary hover:bg-primary-soft">
                  {it.action.label}
                </button>
              )}
              <button type="button" onClick={() => dismiss(it.id)} aria-label={t('common.close')} className="grid size-7 place-items-center rounded-md text-muted hover:bg-surface-2">
                <X className="size-4" aria-hidden />
              </button>
            </div>
          )
        })}
      </div>
    </Ctx.Provider>
  )
}

export function useToast(): ToastCtx {
  const c = useContext(Ctx)
  if (!c) throw new Error('ToastProvider missing')
  return c
}
