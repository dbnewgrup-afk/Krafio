import { useState, type ReactNode } from 'react'
import { AlertTriangle } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Modal } from './Modal'
import { Button } from './Button'
import { TextField, TextArea } from './Field'

// Konfirmasi & konfirmasi destruktif (§10.4): label kata kerja spesifik, tombol destruktif tidak berfokus default,
// opsi "ketik HAPUS" untuk tindakan permanen, alasan wajib untuk tindakan keuangan admin (§7.2 butir 6).
export function ConfirmDialog({
  open,
  onClose,
  onConfirm,
  title,
  body,
  confirmLabel,
  destructive,
  keyword,
  requireReason,
  reasonLabel,
  critical,
  extra,
}: {
  open: boolean
  onClose: () => void
  onConfirm: (reason: string) => Promise<void> | void
  title: string
  body?: ReactNode
  confirmLabel: string
  destructive?: boolean
  keyword?: string
  requireReason?: boolean
  reasonLabel?: string
  critical?: boolean
  extra?: ReactNode
}) {
  const { t } = useTranslation()
  const [typed, setTyped] = useState('')
  const [reason, setReason] = useState('')
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState<string | null>(null)
  const ok = (!keyword || typed === keyword) && (!requireReason || reason.trim().length >= 5)

  const close = () => {
    setTyped('')
    setReason('')
    setErr(null)
    onClose()
  }

  return (
    <Modal
      open={open}
      onClose={close}
      critical={critical || busy}
      size={destructive ? 'destructive' : 'confirm'}
      role={destructive ? 'alertdialog' : 'dialog'}
      icon={destructive ? <AlertTriangle className="size-5 text-danger" aria-hidden /> : undefined}
      title={title}
      footer={
        <>
          <Button variant="secondary" onClick={close} disabled={busy}>
            {t('common.cancel')}
          </Button>
          <Button
            variant={destructive ? 'danger' : 'primary'}
            loading={busy}
            disabled={!ok}
            onClick={async () => {
              setBusy(true)
              setErr(null)
              try {
                await onConfirm(reason.trim())
                close()
              } catch (e) {
                setErr((e as Error).message || t('errors.generic'))
              } finally {
                setBusy(false)
              }
            }}
          >
            {confirmLabel}
          </Button>
        </>
      }
    >
      <div className="grid gap-4">
        {body && <div className="t-body text-muted">{body}</div>}
        {extra}
        {requireReason && <TextArea label={reasonLabel ?? t('common.reason')} required value={reason} onChange={(e) => setReason(e.target.value)} hint={t('common.reason_hint')} rows={3} />}
        {keyword && <TextField label={t('common.type_to_confirm', { word: keyword })} value={typed} onChange={(e) => setTyped(e.target.value)} autoComplete="off" className="font-mono" />}
        {err && (
          <p role="alert" className="t-small rounded-md bg-danger/10 p-3 text-danger">
            {err}
          </p>
        )}
      </div>
    </Modal>
  )
}
