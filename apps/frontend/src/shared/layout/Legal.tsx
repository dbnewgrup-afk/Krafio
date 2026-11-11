import { useEffect, useRef, useState } from 'react'
import { Printer } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router-dom'
import { http } from '@/api-client/http'
import type { LegalDoc } from '@/api-client/types'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Checkbox } from '@/shared/ui/Field'
import { Skeleton } from '@/shared/ui/States'
import { dateTime } from '@/shared/lib/format'
import { cn } from '@/shared/lib/cn'
import { useIsDesktop } from '@/shared/hooks/useMediaQuery'

export type LegalType = LegalDoc['type']

export function useLegalDoc(type: LegalType | null) {
  const { i18n } = useTranslation()
  const [doc, setDoc] = useState<LegalDoc | null>(null)
  const [error, setError] = useState(false)
  useEffect(() => {
    if (!type) return
    setDoc(null)
    setError(false)
    http
      .get<{ data: LegalDoc }>(`/public/legal/${type}`)
      .then((r) => setDoc(r.data))
      .catch(() => setError(true))
  }, [type, i18n.language])
  return { doc, error }
}

/** Isi dokumen + daftar isi (desktop kolom kiri 220px sticky; mobile dropdown) — §11.2 butir 3–4 */
export function LegalBody({ doc, scrollRef, onScrolledToEnd }: { doc: LegalDoc; scrollRef?: React.RefObject<HTMLDivElement | null>; onScrolledToEnd?: () => void }) {
  const { t } = useTranslation()
  const isDesktop = useIsDesktop()
  const [active, setActive] = useState(doc.sections[0]?.id)
  const local = useRef<HTMLDivElement>(null)
  const box = scrollRef ?? local

  useEffect(() => {
    const el = box.current
    if (!el) return
    const onScroll = () => {
      const heads = doc.sections.map((s) => el.querySelector<HTMLElement>(`#legal-${s.id}`))
      let cur = doc.sections[0]?.id
      heads.forEach((h, i) => {
        if (h && h.offsetTop - el.offsetTop <= el.scrollTop + 40) cur = doc.sections[i].id
      })
      setActive(cur)
      if (el.scrollTop + el.clientHeight >= el.scrollHeight - 8) onScrolledToEnd?.()
    }
    onScroll()
    el.addEventListener('scroll', onScroll, { passive: true })
    return () => el.removeEventListener('scroll', onScroll)
  }, [doc, box, onScrolledToEnd])

  const jump = (id: string) => {
    const el = box.current?.querySelector<HTMLElement>(`#legal-${id}`)
    if (el && box.current) box.current.scrollTo({ top: el.offsetTop - box.current.offsetTop - 8, behavior: 'smooth' })
  }

  return (
    <div className="flex min-h-0 flex-1 flex-col gap-3 lg:flex-row lg:gap-6">
      {isDesktop ? (
        <nav aria-label={t('legal.toc')} className="w-[220px] shrink-0">
          <p className="t-label mb-2 text-muted">{t('legal.toc')}</p>
          <ol className="grid gap-0.5">
            {doc.sections.map((s, i) => (
              <li key={s.id}>
                <button type="button" onClick={() => jump(s.id)} className={cn('w-full rounded-md px-2 py-1.5 text-left text-sm', active === s.id ? 'bg-primary-soft font-semibold text-primary' : 'text-muted hover:text-fg')}>
                  {i + 1}. {s.heading}
                </button>
              </li>
            ))}
          </ol>
        </nav>
      ) : (
        <label className="grid gap-1">
          <span className="t-label text-muted">{t('legal.toc')}</span>
          <select className="h-11 rounded-md border border-line bg-surface px-3 text-fg" value={active} onChange={(e) => jump(e.target.value)}>
            {doc.sections.map((s, i) => (
              <option key={s.id} value={s.id}>
                {i + 1}. {s.heading}
              </option>
            ))}
          </select>
        </label>
      )}
      <div ref={box} className="legal-print min-h-0 flex-1 overflow-y-auto overscroll-contain pr-1">
        {!doc.is_translated && <p className="t-small mb-3 rounded-md bg-info/10 p-2 text-info">{t('legal.id_version')}</p>}
        {doc.summary_of_changes && (
          <div className="mb-4 rounded-md border border-warning/40 bg-warning/10 p-3">
            <p className="t-label text-fg">{t('legal.changes')}</p>
            <p className="t-small mt-1 text-muted">{doc.summary_of_changes}</p>
          </div>
        )}
        <div className="measure grid gap-5 text-[15px] leading-[1.6] md:text-base">
          {doc.sections.map((s, i) => (
            <section key={s.id} id={`legal-${s.id}`} className="grid gap-2">
              <h3 className="t-h3 text-fg">
                {i + 1}. {s.heading}
              </h3>
              {s.body.split('\n').map((p, j) => (
                <p key={j} className="text-muted">
                  {p}
                </p>
              ))}
            </section>
          ))}
        </div>
      </div>
    </div>
  )
}

/**
 * Modal T&C (§11.2). mode "read": dibuka dari register/footer, hanya tombol Tutup.
 * mode "agree": persetujuan wajib versi baru — tidak tertutup lewat scrim/Esc; tombol Setuju aktif setelah scroll ke bawah.
 */
export function LegalModal({ type, open, onClose, mode = 'read', onAgree, onReject, level = 1 }: { type: LegalType; open: boolean; onClose: () => void; mode?: 'read' | 'agree'; onAgree?: (doc: LegalDoc) => Promise<void> | void; onReject?: () => void; level?: 1 | 2 }) {
  const { t } = useTranslation()
  const { doc, error } = useLegalDoc(open ? type : null)
  const [reachedEnd, setReachedEnd] = useState(false)
  const [checked, setChecked] = useState(false)
  const [busy, setBusy] = useState(false)
  const ref = useRef<HTMLDivElement>(null)
  useEffect(() => {
    if (!open) {
      setReachedEnd(false)
      setChecked(false)
    }
  }, [open])

  return (
    <Modal
      open={open}
      onClose={onClose}
      size="terms"
      level={level}
      critical={mode === 'agree'}
      hideClose={mode === 'agree'}
      title={doc?.title ?? t(`legal.${type}`)}
      headerExtra={doc && <p className="t-small text-muted">v{doc.version} · {t('legal.effective', { date: dateTime(doc.effective_at, false) })}</p>}
      bodyClassName="flex flex-col"
      footer={
        mode === 'agree' ? (
          <div className="grid w-full gap-3">
            <Checkbox label={t('legal.agree_check')} checked={checked} disabled={!reachedEnd} onChange={(e) => setChecked(e.target.checked)} />
            {!reachedEnd && <p className="t-small text-muted">{t('legal.scroll_hint')}</p>}
            <div className="flex flex-col-reverse gap-2 md:flex-row md:justify-end">
              <Button variant="secondary" onClick={onReject}>
                {t('legal.reject')}
              </Button>
              <Button
                disabled={!checked || !doc}
                loading={busy}
                onClick={async () => {
                  if (!doc) return
                  setBusy(true)
                  try {
                    await onAgree?.(doc)
                  } finally {
                    setBusy(false)
                  }
                }}
              >
                {t('legal.agree_continue')}
              </Button>
            </div>
          </div>
        ) : (
          <>
            <Button variant="secondary" icon={<Printer className="size-4" aria-hidden />} onClick={() => window.print()}>
              {t('legal.print')}
            </Button>
            <Button onClick={onClose}>{t('common.close')}</Button>
          </>
        )
      }
    >
      {error ? (
        <p className="t-body text-danger">{t('errors.server_body')}</p>
      ) : !doc ? (
        <div className="grid gap-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-5 w-full" />
          ))}
        </div>
      ) : (
        <LegalBody doc={doc} scrollRef={ref} onScrolledToEnd={() => setReachedEnd(true)} />
      )}
    </Modal>
  )
}

/** Deep link modal (§10.3 butir 15): ?modal=terms|privacy|refund|cookies|affiliate_terms */
export function LegalDeepLink() {
  const [params, setParams] = useSearchParams()
  const m = params.get('modal') as LegalType | null
  const valid: LegalType[] = ['terms', 'privacy', 'refund', 'affiliate_terms', 'cookies']
  if (!m || !valid.includes(m)) return null
  return (
    <LegalModal
      type={m}
      open
      onClose={() => {
        params.delete('modal')
        setParams(params, { replace: true })
      }}
    />
  )
}
