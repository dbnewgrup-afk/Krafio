import { useState, type FormEvent } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ShieldCheck, Copy } from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import type { Me } from '@/api-client/types'
import { useAuth } from '@/shared/context/AuthContext'
import { useLoader } from '@/shared/layout/PageLoader'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { Button } from '@/shared/ui/Button'
import { TextField } from '@/shared/ui/Field'
import { PasswordField } from '@/shared/ui/PasswordField'
import { OtpInput } from '@/shared/ui/OtpInput'
import { AuthHeading, FormAlert } from '@/public-site/auth/AuthLayout'
import { safeNext } from '@/public-site/auth/AuthPages'

// Login Admin terpisah: password → MFA TOTP wajib (Design Spec §9.2, PRD 35.3).
export default function AdminLogin() {
  const { t } = useTranslation()
  useDocTitle(t('admin.login_title'))
  const nav = useNavigate()
  const [params] = useSearchParams()
  const { setMe } = useAuth()
  const { block, unblock } = useLoader()
  const [step, setStep] = useState<'password' | 'verify' | 'setup'>('password')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [secret, setSecret] = useState<{ secret: string; otpauth: string } | null>(null)
  const [code, setCode] = useState('')
  const [err, setErr] = useState<string | null>(params.get('expired') ? t('session.expired_body') : null)
  const [busy, setBusy] = useState(false)
  const [shake, setShake] = useState(0)

  const submitPassword = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setErr(null)
    try {
      const r = await http.post<{ data: { mfa: 'verify' | 'setup'; secret?: string; otpauth?: string } }>('/auth/admin/login', { email: email.trim().toLowerCase(), password })
      setPassword('')
      if (r.data.mfa === 'setup') setSecret({ secret: r.data.secret!, otpauth: r.data.otpauth! })
      setStep(r.data.mfa)
    } catch (e2) {
      setErr(e2 instanceof ApiError && e2.code === 'too_many_attempts' ? t('auth.errors.too_many', { time: `${(e2.data as any)?.retry_after ?? 60}s` }) : t('auth.errors.invalid'))
    } finally {
      setBusy(false)
    }
  }

  const verify = async (c: string) => {
    if (c.length !== 6 || busy) return
    setBusy(true)
    setErr(null)
    try {
      const r = await http.post<{ data: Me }>('/auth/admin/mfa', { code: c })
      setMe(r.data)
      block(t('loader.signing_in'))
      nav(safeNext(params.get('next'), '/admin'), { replace: true })
      window.setTimeout(unblock, 450)
    } catch {
      setCode('')
      setShake((s) => s + 1)
      setErr(t('admin.mfa_wrong'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
      <div className="mb-4 inline-flex items-center gap-2 rounded-full bg-danger/10 px-3 py-1 text-[12px] font-bold text-danger">
        <ShieldCheck className="size-4" aria-hidden />
        ADMIN
      </div>
      {step === 'password' ? (
        <>
          <AuthHeading title={t('admin.login_title')} sub={t('admin.login_sub')} />
          <FormAlert message={err} />
          <form onSubmit={submitPassword} className="grid gap-4" noValidate>
            <TextField label={t('fields.email')} type="email" autoComplete="username" value={email} onChange={(e) => setEmail(e.target.value)} required />
            <PasswordField label={t('auth.password')} autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required />
            <Button type="submit" size="lg" block loading={busy}>{t('common.next')}</Button>
          </form>
        </>
      ) : (
        <>
          <AuthHeading title={step === 'setup' ? t('admin.mfa_setup_title') : t('admin.mfa_title')} sub={step === 'setup' ? t('admin.mfa_setup_sub') : t('admin.mfa_sub')} />
          {step === 'setup' && secret && (
            <div className="mb-4 grid gap-2 rounded-md border border-line bg-surface-2 p-3">
              <p className="t-label">{t('admin.mfa_secret')}</p>
              <div className="flex items-center gap-2">
                <code className="t-mono min-w-0 flex-1 break-all text-fg">{secret.secret}</code>
                <button type="button" aria-label={t('common.copy')} onClick={() => navigator.clipboard?.writeText(secret.secret)} className="grid size-10 place-items-center rounded-md hover:bg-surface">
                  <Copy className="size-4" aria-hidden />
                </button>
              </div>
              <a href={secret.otpauth} className="t-small text-primary hover:underline">{t('admin.mfa_open_app')}</a>
            </div>
          )}
          <FormAlert message={err} />
          <div className="grid gap-4">
            <OtpInput value={code} onChange={setCode} onComplete={verify} invalid={!!err} shakeKey={shake} disabled={busy} />
            <Button size="lg" block loading={busy} disabled={code.length !== 6} onClick={() => verify(code)}>{t('admin.mfa_verify')}</Button>
            <button type="button" className="t-small justify-self-start text-muted hover:text-fg" onClick={() => { setStep('password'); setCode(''); setErr(null) }}>{t('common.back')}</button>
          </div>
        </>
      )}
    </>
  )
}
