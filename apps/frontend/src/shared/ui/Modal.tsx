import { useEffect, useId, useRef, useState, type ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { cn } from '@/shared/lib/cn'
import { useBackClose, useFocusTrap, useScrollLock } from '@/shared/hooks/useOverlay'
import { useIsMobile } from '@/shared/hooks/useMediaQuery'

export type ModalSize = 'info' | 'confirm' | 'destructive' | 'form' | 'upsell' | 'terms' | 'media' | 'wizard'
const WIDTH: Record<ModalSize, string> = {
  info: 'md:max-w-[400px]',
  confirm: 'md:max-w-[440px]',
  destructive: 'md:max-w-[480px]',
  form: 'md:max-w-[560px]',
  upsell: 'md:max-w-[560px]',
  terms: 'md:max-w-[720px]',
  media: 'md:max-w-[90vw]',
  wizard: 'md:max-w-[640px]',
}
// Ukuran yang memakai full-screen di mobile; sisanya bottom sheet (§10.1)
const FULLSCREEN_MOBILE: ModalSize[] = ['form', 'terms', 'media', 'wizard']

/** Menandai overlay aktif agar FAB (CS, back-to-top) tersembunyi (§15.3, §16.1). */
function useOverlayFlag(open: boolean) {
  useEffect(() => {
    if (!open) return
    const h = document.documentElement
    h.dataset.overlays = String(Number(h.dataset.overlays ?? 0) + 1)
    return () => {
      h.dataset.overlays = String(Math.max(0, Number(h.dataset.overlays ?? 1) - 1))
    }
  }, [open])
}

export interface ModalProps {
  open: boolean
  onClose: () => void
  title: ReactNode
  icon?: ReactNode
  size?: ModalSize
  /** Modal kritis: tidak tertutup lewat scrim/Esc/drag (§10.3 butir 2, 3, 13) */
  critical?: boolean
  /** Bila true dan critical, Esc/scrim memunculkan konfirmasi "Batalkan?" */
  dirty?: boolean
  level?: 1 | 2
  footer?: ReactNode
  headerExtra?: ReactNode
  children: ReactNode
  role?: 'dialog' | 'alertdialog'
  hideClose?: boolean
  bodyClassName?: string
}

// Sistem Modal (Design Spec §10): portal, scrim, focus trap, Esc, Back menutup, bottom sheet mobile, maks 2 level.
export function Modal({ open, onClose, title, icon, size = 'confirm', critical, dirty, level = 1, footer, headerExtra, children, role = 'dialog', hideClose, bodyClassName }: ModalProps) {
  const { t } = useTranslation()
  const ref = useRef<HTMLDivElement>(null)
  const id = useId()
  const isMobile = useIsMobile()
  const [nudge, setNudge] = useState(0)
  const [confirmLeave, setConfirmLeave] = useState(false)
  const [drag, setDrag] = useState(0)
  const dragStart = useRef<number | null>(null)

  useScrollLock(open)
  useFocusTrap(ref, open)
  useOverlayFlag(open)

  const requestClose = (source: 'scrim' | 'esc' | 'button' | 'back' | 'drag') => {
    if (critical && source !== 'button') {
      if (dirty && source === 'esc') setConfirmLeave(true)
      else setNudge((n) => n + 1)
      return
    }
    if (dirty && source !== 'button') {
      setConfirmLeave(true)
      return
    }
    onClose()
  }

  useBackClose(open, () => requestClose('back'))

  useEffect(() => {
    if (!open) return
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.stopPropagation()
        requestClose('esc')
      }
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  })

  useEffect(() => {
    if (!open) {
      setDrag(0)
      setConfirmLeave(false)
    }
  }, [open])

  if (!open) return null
  const fullscreen = isMobile && FULLSCREEN_MOBILE.includes(size)
  const sheet = isMobile && !fullscreen

  return createPortal(
    <div className={cn('fixed inset-0 flex', level === 2 ? 'z-modal2' : 'z-modal', sheet ? 'items-end' : fullscreen ? 'items-stretch' : 'items-center justify-center p-6')}>
      <div className={cn('anim-fade-in absolute inset-0', level === 2 ? 'bg-black/60' : 'bg-[var(--overlay-dark)]')} aria-hidden onClick={() => requestClose('scrim')} />
      <div
        ref={ref}
        role={role}
        aria-modal="true"
        aria-labelledby={`${id}-t`}
        aria-describedby={`${id}-b`}
        tabIndex={-1}
        key={nudge}
        style={sheet && drag ? { transform: `translateY(${drag}px)` } : undefined}
        className={cn(
          'relative flex w-full flex-col bg-surface text-fg outline-none',
          sheet && 'anim-sheet max-h-[90dvh] rounded-t-xl pb-[var(--safe-b)]',
          fullscreen && 'anim-sheet h-[100dvh] pb-[var(--safe-b)]',
          !isMobile && cn('anim-pop max-h-[90dvh] rounded-xl border border-line shadow-k-lg', WIDTH[size], size === 'media' && 'h-[90dvh]', size === 'terms' && 'h-[85dvh]'),
          nudge > 0 && 'anim-shake',
          size === 'destructive' && 'md:border-t-4 md:border-t-danger',
        )}
      >
        {sheet && (
          <div
            className="flex h-6 shrink-0 cursor-grab touch-none items-center justify-center"
            onPointerDown={(e) => {
              if (critical) return
              dragStart.current = e.clientY
              ;(e.target as HTMLElement).setPointerCapture(e.pointerId)
            }}
            onPointerMove={(e) => {
              if (dragStart.current === null) return
              setDrag(Math.max(0, e.clientY - dragStart.current))
            }}
            onPointerUp={() => {
              const h = ref.current?.offsetHeight ?? 1
              if (drag > h * 0.3) requestClose('drag')
              setDrag(0)
              dragStart.current = null
            }}
          >
            <span className="h-1 w-10 rounded-full bg-line" aria-hidden />
          </div>
        )}
        <div className={cn('flex shrink-0 items-start gap-3 border-b border-line px-5 py-4', sheet && 'border-b-0 pt-1')}>
          {icon && <span className="mt-0.5 shrink-0">{icon}</span>}
          <div className="min-w-0 flex-1">
            <h2 id={`${id}-t`} className="t-h3 text-fg">
              {title}
            </h2>
            {headerExtra}
          </div>
          {!hideClose && (
            <button type="button" onClick={() => requestClose('button')} aria-label={t('common.close')} className="-mr-2 -mt-1 grid size-10 shrink-0 place-items-center rounded-md text-muted hover:bg-surface-2 hover:text-fg">
              <X className="size-5" aria-hidden />
            </button>
          )}
        </div>
        <div id={`${id}-b`} className={cn('min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-4', bodyClassName)}>
          {children}
        </div>
        {footer && <div className="flex shrink-0 flex-col-reverse gap-2 border-t border-line px-5 py-4 md:flex-row md:justify-end [&>*]:max-md:w-full">{footer}</div>}

        {confirmLeave && (
          <div className="absolute inset-0 z-10 grid place-items-center rounded-[inherit] bg-surface/95 p-6" role="alertdialog" aria-label={t('modal.discard_title')}>
            <div className="grid max-w-xs gap-4 text-center">
              <p className="t-h3">{t('modal.discard_title')}</p>
              <p className="t-small text-muted">{t('modal.discard_body')}</p>
              <div className="flex flex-col gap-2">
                <button type="button" className="h-11 rounded-md bg-danger font-semibold text-white dark:text-bg" onClick={() => { setConfirmLeave(false); onClose() }}>
                  {t('modal.discard_confirm')}
                </button>
                <button type="button" className="h-11 rounded-md border border-line font-semibold" onClick={() => setConfirmLeave(false)}>
                  {t('modal.keep_editing')}
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>,
    document.body,
  )
}
