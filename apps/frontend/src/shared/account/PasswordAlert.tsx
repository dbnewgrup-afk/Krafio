import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { KeyRound, X } from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import { useAuth } from '@/shared/context/AuthContext'
import { useToast } from '@/shared/context/ToastContext'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { PasswordField } from '@/shared/ui/PasswordField'

/**
 * Akun dibuat Admin dengan kata sandi awal → pengingat (bukan paksaan) untuk menggantinya.
 * Banner hilang otomatis setelah kata sandi diganti; bisa ditutup sementara untuk sesi ini.
 */
export function PasswordAlert() {
  const { t } = useTranslation()
  const { me, refresh } = useAuth()
  const toast = useToast()
  const [open, setOpen] = useState(false)
  const [hidden, setHidden] = useState(() => {
    try {
      return sessionStorage.getItem('krafio.pwalert.hidden') === '1'
    } catch {
      return false
    }
  })
  const [f, setF] = useState({ current: '', next: '', confirm: '' })
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [busy, setBusy] = useState(false)

  if (!me?.must_change_password) return null

  const save = async () => {
    setBusy(true)
    setErrors({})
    try {
      await http.put('/me/password', { current_password: f.current, password: f.next, password_confirmation: f.confirm })
      toast.push('success', t('settings.password_changed'))
      setOpen(false)
      setF({ current: '', next: '', confirm: '' })
      await refresh()
    } catch (e) {
      if (e instanceof ApiError) setErrors(e.fields)
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
      {!hidden && (
        <div role="alert" className="flex items-center gap-3 border-t border-danger/25 bg-danger/10 px-4 py-2 text-sm text-fg md:px-6">
          <KeyRound className="size-4 shrink-0 text-danger" aria-hidden />
          <p className="min-w-0 flex-1">
            <span className="font-semibold text-danger">{t('pw_alert.title')}</span> <span className="max-sm:hidden">{t('pw_alert.body')}</span>
          </p>
          <Button size="sm" onClick={() => setOpen(true)}>{t('pw_alert.cta')}</Button>
          <button
            type="button"
            aria-label={t('pw_alert.later')}
            title={t('pw_alert.later')}
            onClick={() => {
              setHidden(true)
              try {
                sessionStorage.setItem('krafio.pwalert.hidden', '1')
              } catch {
                /* noop */
              }
            }}
            className="grid size-8 shrink-0 place-items-center rounded-md text-muted hover:bg-surface hover:text-fg"
          >
            <X className="size-4" aria-hidden />
          </button>
        </div>
      )}
      <Modal open={open} onClose={() => setOpen(false)} size="form" icon={<KeyRound className="size-5 text-primary" aria-hidden />} title={t('pw_alert.modal_title')} footer={<><Button variant="secondary" onClick={() => setOpen(false)}>{t('common.cancel')}</Button><Button loading={busy} onClick={() => void save()}>{t('pw_alert.save')}</Button></>}>
        <div className="grid gap-4">
          <p className="t-small text-muted">{t('pw_alert.modal_body')}</p>
          <PasswordField label={t('pw_alert.current')} autoComplete="current-password" value={f.current} onChange={(e) => setF({ ...f, current: e.target.value })} error={errors.current_password?.[0]} required />
          <PasswordField label={t('pw_alert.new')} autoComplete="new-password" value={f.next} onChange={(e) => setF({ ...f, next: e.target.value })} error={errors.password?.[0]} hint={t('account_new.pw_rule')} required />
          <PasswordField label={t('pw_alert.confirm')} autoComplete="new-password" value={f.confirm} onChange={(e) => setF({ ...f, confirm: e.target.value })} required />
        </div>
      </Modal>
    </>
  )
}
