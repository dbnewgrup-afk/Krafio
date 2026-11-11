import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Check, Copy, KeyRound, ShieldCheck, User as UserIcon, UserPlus } from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import { useToast } from '@/shared/context/ToastContext'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { TextField } from '@/shared/ui/Field'
import { PasswordField } from '@/shared/ui/PasswordField'
import { cn } from '@/shared/lib/cn'

interface Created {
  id: number
  type: 'user' | 'admin'
  name: string
  email: string
  phone: string | null
  login_url: string
  password: string | null
  needs_verification: boolean
}

/**
 * Popup "Tambah akun": pilih tipe User atau Admin, akun langsung aktif.
 * Kata sandi awal dibuat otomatis (ditampilkan sekali) atau diisi manual.
 */
export function CreateAccountModal({ open, onClose, onCreated, defaultType = 'user' }: { open: boolean; onClose: () => void; onCreated: (c: Created) => void; defaultType?: 'user' | 'admin' }) {
  const { t } = useTranslation()
  const toast = useToast()
  const [type, setType] = useState<'user' | 'admin'>(defaultType)
  const [form, setForm] = useState({ name: '', email: '', phone: '', password: '' })
  const [auto, setAuto] = useState(true)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [busy, setBusy] = useState(false)
  const [done, setDone] = useState<Created | null>(null)
  const [copied, setCopied] = useState(false)

  const reset = () => {
    setForm({ name: '', email: '', phone: '', password: '' })
    setErrors({})
    setDone(null)
    setAuto(true)
    setCopied(false)
  }
  const close = () => {
    reset()
    onClose()
  }

  const submit = async () => {
    setBusy(true)
    setErrors({})
    try {
      const r = await http.post<{ data: Created }>('/admin/users', { type, name: form.name.trim(), email: form.email.trim(), phone: form.phone.trim() || null, password: auto ? null : form.password })
      setDone(r.data)
      onCreated(r.data)
    } catch (e) {
      if (e instanceof ApiError) {
        setErrors(e.fields)
        if (!Object.keys(e.fields).length) toast.push('error', e.message)
      }
    } finally {
      setBusy(false)
    }
  }

  const credentials = done ? `${t('account_new.copy_intro')}\n${t('fields.email')}: ${done.email}\n${t('auth.password')}: ${done.password ?? form.password}\n${t('account_new.login_at')}: ${window.location.origin}${done.login_url}` : ''

  return (
    <Modal
      open={open}
      onClose={close}
      size="form"
      dirty={!done && !!(form.name || form.email)}
      icon={<UserPlus className="size-5 text-primary" aria-hidden />}
      title={done ? t('account_new.done_title') : t('account_new.title')}
      footer={
        done ? (
          <>
            <Button variant="secondary" onClick={reset}>{t('account_new.another')}</Button>
            <Button onClick={close}>{t('common.done')}</Button>
          </>
        ) : (
          <>
            <Button variant="secondary" onClick={close}>{t('common.cancel')}</Button>
            <Button loading={busy} onClick={() => void submit()}>{type === 'admin' ? t('account_new.create_admin') : t('account_new.create_user')}</Button>
          </>
        )
      }
    >
      {done ? (
        <div className="grid gap-4">
          <div className="flex items-start gap-3 rounded-lg border border-success/30 bg-success/10 p-3">
            <Check className="mt-0.5 size-5 shrink-0 text-success" aria-hidden />
            <p className="t-small text-fg">{t(done.type === 'admin' ? 'account_new.done_admin' : 'account_new.done_user', { name: done.name })}</p>
          </div>
          <dl className="grid gap-2 rounded-lg border border-line bg-bg-subtle p-3 text-sm">
            <div className="flex justify-between gap-3"><dt className="text-muted">{t('account_new.type')}</dt><dd className="font-semibold">{t(`account_new.t_${done.type}`)}</dd></div>
            <div className="flex justify-between gap-3"><dt className="text-muted">{t('fields.email')}</dt><dd className="break-all font-semibold">{done.email}</dd></div>
            {done.password && (
              <div className="flex items-center justify-between gap-3">
                <dt className="text-muted">{t('auth.password')}</dt>
                <dd className="t-mono rounded-md bg-surface px-2 py-1 text-fg">{done.password}</dd>
              </div>
            )}
            <div className="flex justify-between gap-3"><dt className="text-muted">{t('account_new.login_at')}</dt><dd className="font-semibold">{done.login_url}</dd></div>
          </dl>
          {done.password && <p className="t-small text-warning">{t('account_new.shown_once')}</p>}
          <Button
            variant="secondary"
            icon={copied ? <Check className="size-4" aria-hidden /> : <Copy className="size-4" aria-hidden />}
            onClick={async () => {
              await navigator.clipboard?.writeText(credentials)
              setCopied(true)
              toast.push('success', t('account_new.copied'))
            }}
          >
            {t('account_new.copy')}
          </Button>
          <ul className="t-small grid list-disc gap-1 pl-5 text-muted">
            <li>{t('account_new.note_password')}</li>
            {done.type === 'user' && <li>{t('account_new.note_verify')}</li>}
          </ul>
        </div>
      ) : (
        <div className="grid gap-4">
          <div className="grid gap-2">
            <p className="t-label text-fg">{t('account_new.type')}</p>
            <div className="grid grid-cols-2 gap-2" role="radiogroup" aria-label={t('account_new.type')}>
              {(['user', 'admin'] as const).map((x) => {
                const Icon = x === 'admin' ? ShieldCheck : UserIcon
                return (
                  <button
                    key={x}
                    type="button"
                    role="radio"
                    aria-checked={type === x}
                    onClick={() => setType(x)}
                    className={cn('grid gap-1 rounded-xl border-2 p-3 text-left transition-[border-color,background-color] duration-[120ms]', type === x ? 'border-primary bg-primary-soft' : 'border-line hover:border-primary/40')}
                  >
                    <span className={cn('inline-flex items-center gap-2 font-semibold', type === x ? 'text-primary' : 'text-fg')}>
                      <Icon className="size-4" aria-hidden />
                      {t(`account_new.t_${x}`)}
                    </span>
                    <span className="t-small text-muted">{t(`account_new.t_${x}_desc`)}</span>
                  </button>
                )
              })}
            </div>
          </div>
          <TextField label={t('fields.name')} required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} error={errors.name?.[0]} autoComplete="off" placeholder="Nadia Pramesti" />
          <TextField label={t('fields.email')} required type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} error={errors.email?.[0]} autoComplete="off" placeholder="nadia@kopisenja.id" />
          <TextField label={t('account_new.phone')} optional type="tel" inputMode="tel" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} error={errors.phone?.[0]} placeholder="0812 3456 7890" hint={type === 'user' ? t('account_new.phone_hint') : undefined} />
          <div className="grid gap-2">
            <p className="t-label text-fg">{t('account_new.initial_password')}</p>
            <div className="grid grid-cols-2 gap-2" role="radiogroup">
              {[true, false].map((a) => (
                <button key={String(a)} type="button" role="radio" aria-checked={auto === a} onClick={() => setAuto(a)} className={cn('t-label inline-flex h-10 items-center justify-center gap-2 rounded-md border', auto === a ? 'border-primary bg-primary-soft text-primary' : 'border-line text-fg hover:bg-surface-2')}>
                  {a ? <KeyRound className="size-4" aria-hidden /> : null}
                  {a ? t('account_new.pw_auto') : t('account_new.pw_manual')}
                </button>
              ))}
            </div>
            {!auto && <PasswordField label={t('auth.password')} value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} error={errors.password?.[0]} autoComplete="new-password" hint={t('account_new.pw_rule')} />}
            {auto && <p className="t-small text-muted">{t('account_new.pw_auto_hint')}</p>}
          </div>
          <p className="t-small rounded-md bg-surface-2 p-3 text-muted">{type === 'admin' ? t('account_new.info_admin') : t('account_new.info_user')}</p>
        </div>
      )}
    </Modal>
  )
}
