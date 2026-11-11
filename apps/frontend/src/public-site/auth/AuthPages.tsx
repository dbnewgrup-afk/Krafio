import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Clock, FileWarning, LifeBuoy, ShieldX, MailCheck, Hourglass } from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import type { Me } from '@/api-client/types'
import { useAuth } from '@/shared/context/AuthContext'
import { useLoader } from '@/shared/layout/PageLoader'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { Button, ButtonLink } from '@/shared/ui/Button'
import { Checkbox, TextField, inputCls } from '@/shared/ui/Field'
import { PasswordField, PasswordStrength, passwordScore } from '@/shared/ui/PasswordField'
import { OtpInput } from '@/shared/ui/OtpInput'
import { Turnstile } from '@/shared/ui/Turnstile'
import { LegalModal, type LegalType } from '@/shared/layout/Legal'
import { Illustration } from '@/shared/ui/States'
import { Modal } from '@/shared/ui/Modal'
import { AuthHeading, FormAlert } from './AuthLayout'
import { cn } from '@/shared/lib/cn'
import { maskEmail } from '@/shared/lib/format'
import { startIntro } from '@/shared/motion/intro'

/** Validasi ?next= hanya same-origin path (§9.2) */
export function safeNext(next: string | null, fallback: string): string {
  if (!next || !next.startsWith('/') || next.startsWith('//') || next.includes('\\')) return fallback
  return next
}

/** Mengarahkan user sesuai status akun (§9.2 Perilaku) */
export function routeForStatus(me: Me, next: string | null): string {
  if (me.status === 'pending_verification') return '/verify'
  if (me.status !== 'active') return '/account-status'
  if (me.role === 'admin') return '/admin'
  return safeNext(next, '/app')
}

function useCountdown(target: number | null): number {
  const [now, setNow] = useState(Date.now())
  useEffect(() => {
    if (!target) return
    const id = window.setInterval(() => setNow(Date.now()), 1000)
    return () => window.clearInterval(id)
  }, [target])
  return target ? Math.max(0, Math.ceil((target - now) / 1000)) : 0
}
const mmss = (s: number) => `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`

/** Fokus ke field bermasalah pertama (§9.2) */
function focusFirstError(form: HTMLFormElement | null) {
  window.setTimeout(() => form?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus(), 30)
}

/* ============================ LOGIN ============================ */
export function LoginPage() {
  const { t } = useTranslation()
  useDocTitle(t('auth.login'))
  const nav = useNavigate()
  const [params] = useSearchParams()
  const { setMe, settings } = useAuth()
  const { block, unblock } = useLoader()
  const formRef = useRef<HTMLFormElement>(null)
  const [login, setLogin] = useState('')
  const [password, setPassword] = useState('')
  const [remember, setRemember] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [fieldErr, setFieldErr] = useState<Record<string, string>>({})
  const [fails, setFails] = useState(0)
  const [captcha, setCaptcha] = useState<string | null>(null)
  const [lockUntil, setLockUntil] = useState<number | null>(null)
  const [legal, setLegal] = useState<LegalType | null>(null)
  const lockLeft = useCountdown(lockUntil)
  const onToken = useCallback((tk: string | null) => setCaptcha(tk), [])

  const validateLogin = (v: string) => {
    const s = v.trim()
    if (!s) return t('validation.required')
    if (s.includes('@') && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s)) return t('validation.email')
    if (!s.includes('@') && !/^\+?\d{9,15}$/.test(s.replace(/[\s-]/g, ''))) return t('validation.email_or_phone')
    return ''
  }

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    const errs: Record<string, string> = {}
    const le = validateLogin(login)
    if (le) errs.login = le
    if (!password) errs.password = t('validation.required')
    setFieldErr(errs)
    if (Object.keys(errs).length) {
      setError(t('validation.summary'))
      focusFirstError(formRef.current)
      return
    }
    setBusy(true)
    setError(null)
    try {
      const s = login.trim()
      const r = await http.post<{ data: Me }>('/auth/login', { login: s.includes('@') ? s.toLowerCase() : s, password, remember, turnstile_token: captcha })
      setMe(r.data)
      const dest = routeForStatus(r.data, params.get('next'))
      if (dest.startsWith('/app')) {
        // Animasi tirai Login → Dashboard diputar oleh AppShell (tanpa loader gelap)
        startIntro('user')
        nav(dest, { replace: true })
      } else {
        block(t('loader.signing_in'))
        nav(dest, { replace: true })
        window.setTimeout(unblock, 450)
      }
    } catch (err) {
      setPassword('')
      if (err instanceof ApiError) {
        if (err.code === 'too_many_attempts') {
          const sec = Number((err.data as any)?.retry_after ?? 60)
          setLockUntil(Date.now() + sec * 1000)
          setError(null)
        } else if (err.code === 'admin_use_admin_login') {
          setError(t('auth.errors.use_admin_login'))
        } else if (err.code === 'captcha_required') {
          setFails(3)
          setError(t('auth.errors.captcha'))
        } else if (err.status === 0) {
          setError(t('errors.network_body'))
        } else {
          setFails((f) => f + 1)
          setError(t('auth.errors.invalid'))
        }
      } else {
        setError(t('errors.generic'))
      }
      focusFirstError(formRef.current)
    } finally {
      setBusy(false)
    }
  }

  const locked = lockLeft > 0
  return (
    <>
      <AuthHeading
        title={t('auth.login_title')}
        sub={
          <>
            {t('auth.no_account')}{' '}
            <Link to={`/register${params.get('next') ? `?next=${encodeURIComponent(params.get('next')!)}` : ''}`} className="font-semibold text-primary hover:underline">
              {t('auth.register')}
            </Link>
          </>
        }
      />
      {locked && <FormAlert message={t('auth.errors.too_many', { time: mmss(lockLeft) })} />}
      <FormAlert message={error} />
      <form ref={formRef} onSubmit={submit} noValidate className="grid gap-4">
        <TextField
          label={t('auth.email_or_phone')}
          value={login}
          onChange={(e) => setLogin(e.target.value)}
          onBlur={() => login && setFieldErr((f) => ({ ...f, login: validateLogin(login) }))}
          error={fieldErr.login}
          autoComplete="username"
          inputMode="email"
          autoCapitalize="none"
          placeholder="nama@email.com"
          required
        />
        <PasswordField
          label={t('auth.password')}
          labelAside={
            <Link to="/forgot-password" className="t-small font-semibold text-primary hover:underline">
              {t('auth.forgot')}
            </Link>
          }
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          error={fieldErr.password}
          autoComplete="current-password"
          required
        />
        <Checkbox label={t('auth.remember')} checked={remember} onChange={(e) => setRemember(e.target.checked)} />
        <Turnstile show={fails >= 3} onToken={onToken} />
        <Button type="submit" size="lg" block loading={busy} disabled={locked}>
          {t('auth.login')}
        </Button>
        {settings?.sso_google && (
          <>
            <div className="t-small flex items-center gap-3 text-muted">
              <span className="h-px flex-1 bg-line" />
              {t('auth.or')}
              <span className="h-px flex-1 bg-line" />
            </div>
            <a href="/api/auth/google/redirect" className="inline-flex h-12 items-center justify-center rounded-md border border-line bg-surface font-semibold text-fg hover:bg-surface-2">
              {t('auth.google')}
            </a>
          </>
        )}
        <p className="t-small text-muted">
          {t('auth.agree_prefix')}{' '}
          <button type="button" onClick={() => setLegal('terms')} className="font-semibold text-primary hover:underline">
            {t('legal.terms')}
          </button>{' '}
          {t('auth.and')}{' '}
          <button type="button" onClick={() => setLegal('privacy')} className="font-semibold text-primary hover:underline">
            {t('legal.privacy')}
          </button>
          .
        </p>
      </form>
      {legal && <LegalModal type={legal} open onClose={() => setLegal(null)} />}
    </>
  )
}

/* ============================ REGISTER ============================ */
const COUNTRIES = [
  { code: '+62', label: 'ID +62' },
  { code: '+60', label: 'MY +60' },
  { code: '+65', label: 'SG +65' },
  { code: '+1', label: 'US +1' },
  { code: '+44', label: 'UK +44' },
]
function toE164(cc: string, local: string): string {
  let d = local.replace(/\D/g, '')
  if (cc === '+62' && d.startsWith('0')) d = d.slice(1)
  if (cc === '+62' && d.startsWith('62')) d = d.slice(2)
  return d ? `${cc}${d}` : ''
}

export function RegisterPage() {
  const { t } = useTranslation()
  useDocTitle(t('auth.register'))
  const nav = useNavigate()
  const [params] = useSearchParams()
  const { setMe, settings } = useAuth()
  const formRef = useRef<HTMLFormElement>(null)
  const [f, setF] = useState({ name: '', email: '', cc: '+62', phone: '', password: '', confirm: '' })
  const [agree, setAgree] = useState(false)
  const [showBoth, setShowBoth] = useState(false)
  const [pwFocus, setPwFocus] = useState(false)
  const [errs, setErrs] = useState<Record<string, string>>({})
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [captcha, setCaptcha] = useState<string | null>(null)
  const [legal, setLegal] = useState<LegalType | null>(null)
  const onToken = useCallback((tk: string | null) => setCaptcha(tk), [])
  const ref = params.get('ref') ?? undefined

  const validate = () => {
    const e: Record<string, string> = {}
    if (f.name.trim().length < 2) e.name = t('validation.name')
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email.trim())) e.email = t('validation.email')
    if (f.phone && !/^\+\d{8,15}$/.test(toE164(f.cc, f.phone))) e.phone = t('validation.phone')
    const ps = passwordScore(f.password, [f.email.split('@')[0], f.name])
    if (!ps.rules.every((r) => r.ok)) e.password = t('validation.password_rules')
    if (f.confirm !== f.password) e.confirm = t('validation.password_match')
    if (!agree) e.agree = t('validation.agree')
    return e
  }

  const submit = async (ev: FormEvent) => {
    ev.preventDefault()
    const e = validate()
    setErrs(e)
    if (Object.keys(e).length) {
      setError(t('validation.summary'))
      focusFirstError(formRef.current)
      return
    }
    setBusy(true)
    setError(null)
    try {
      const r = await http.post<{ data: Me }>('/auth/register', {
        name: f.name.trim(),
        email: f.email.trim().toLowerCase(),
        phone: f.phone ? toE164(f.cc, f.phone) : null,
        password: f.password,
        password_confirmation: f.confirm,
        agree_terms: agree,
        referral: ref,
        locale: document.documentElement.lang,
        turnstile_token: captcha,
      })
      setMe(r.data)
      nav(`/verify${params.get('next') ? `?next=${encodeURIComponent(params.get('next')!)}` : ''}`, { replace: true })
    } catch (err) {
      if (err instanceof ApiError) {
        const fe: Record<string, string> = {}
        Object.entries(err.fields).forEach(([k, v]) => (fe[k === 'password_confirmation' ? 'confirm' : k] = v[0]))
        setErrs(fe)
        setError(err.status === 422 ? t('validation.summary') : t('errors.server_body'))
      }
      focusFirstError(formRef.current)
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
      <AuthHeading
        title={t('auth.register_title')}
        sub={
          <>
            {t('auth.have_account')}{' '}
            <Link to="/login" className="font-semibold text-primary hover:underline">
              {t('auth.login')}
            </Link>
          </>
        }
      />
      {settings?.registration_mode === 'manual' && <FormAlert tone="info" message={t('auth.manual_mode_note')} />}
      <FormAlert message={error} />
      <form ref={formRef} onSubmit={submit} noValidate className="grid gap-4 pb-20 md:pb-0">
        <TextField label={t('fields.full_name')} required value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} error={errs.name} autoComplete="name" />
        <TextField label={t('fields.email')} required type="email" inputMode="email" autoCapitalize="none" value={f.email} onChange={(e) => setF({ ...f, email: e.target.value })} onBlur={() => f.email && setErrs((x) => ({ ...x, email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email.trim()) ? '' : t('validation.email') }))} error={errs.email} autoComplete="email" />
        <div className="grid gap-1.5">
          <label htmlFor="reg-phone" className="t-label text-fg">
            {t('fields.whatsapp')} <span className="text-muted">({t('common.optional')})</span>
          </label>
          <div className="flex gap-2">
            <select aria-label={t('fields.country_code')} value={f.cc} onChange={(e) => setF({ ...f, cc: e.target.value })} className={cn(inputCls.replace('w-full ', ''), 'w-28 shrink-0')}>
              {COUNTRIES.map((c) => (
                <option key={c.code} value={c.code}>
                  {c.label}
                </option>
              ))}
            </select>
            <input id="reg-phone" type="tel" inputMode="tel" autoComplete="tel-national" value={f.phone} onChange={(e) => setF({ ...f, phone: e.target.value })} aria-invalid={errs.phone ? true : undefined} placeholder="812 3456 7890" className={cn(inputCls, 'min-w-0 flex-1')} />
          </div>
          {errs.phone && <p className="t-small text-danger">{errs.phone}</p>}
        </div>
        <div className="grid gap-2">
          <PasswordField label={t('auth.password')} required value={f.password} onChange={(e) => setF({ ...f, password: e.target.value })} onFocus={() => setPwFocus(true)} error={errs.password} autoComplete="new-password" revealed={showBoth || undefined} onRevealChange={showBoth ? setShowBoth : undefined} />
          <PasswordStrength value={f.password} context={[f.email.split('@')[0], f.name]} visible={pwFocus || f.password.length > 0} />
        </div>
        <PasswordField label={t('auth.password_confirm')} required value={f.confirm} onChange={(e) => setF({ ...f, confirm: e.target.value })} error={errs.confirm} autoComplete="new-password" revealed={showBoth || undefined} onRevealChange={showBoth ? setShowBoth : undefined} />
        <Checkbox label={t('auth.show_both')} checked={showBoth} onChange={(e) => setShowBoth(e.target.checked)} />
        <div className="grid gap-1">
          <Checkbox
            checked={agree}
            onChange={(e) => setAgree(e.target.checked)}
            aria-invalid={errs.agree ? true : undefined}
            label={
              <>
                {t('auth.agree_label')}{' '}
                <button type="button" onClick={() => setLegal('terms')} className="font-semibold text-primary hover:underline">
                  {t('legal.terms')}
                </button>{' '}
                {t('auth.and')}{' '}
                <button type="button" onClick={() => setLegal('privacy')} className="font-semibold text-primary hover:underline">
                  {t('legal.privacy')}
                </button>
              </>
            }
          />
          {errs.agree && <p className="t-small text-danger">{errs.agree}</p>}
        </div>
        <Turnstile show={!!settings?.turnstile_site_key} onToken={onToken} />
        {/* Mobile: tombol utama sticky di bawah untuk form panjang (§9.1, §18.1) */}
        <div className="fixed inset-x-0 bottom-0 z-sticky border-t border-line bg-surface p-3 pb-[calc(12px+var(--safe-b))] md:static md:border-0 md:bg-transparent md:p-0">
          <Button type="submit" size="lg" block loading={busy}>
            {t('auth.register')}
          </Button>
        </div>
      </form>
      {legal && <LegalModal type={legal} open onClose={() => setLegal(null)} />}
    </>
  )
}

/* ============================ FORGOT / RESET ============================ */
export function ForgotPasswordPage() {
  const { t } = useTranslation()
  useDocTitle(t('auth.forgot_title'))
  const [email, setEmail] = useState('')
  const [err, setErr] = useState('')
  const [busy, setBusy] = useState(false)
  const [sent, setSent] = useState(false)
  const submit = async (e: FormEvent) => {
    e.preventDefault()
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) return setErr(t('validation.email'))
    setErr('')
    setBusy(true)
    try {
      await http.post('/auth/forgot-password', { email: email.trim().toLowerCase() })
    } catch {
      /* pesan netral selalu sama (§9.6) */
    } finally {
      setBusy(false)
      setSent(true)
    }
  }
  return (
    <>
      <AuthHeading title={t('auth.forgot_title')} sub={t('auth.forgot_sub')} />
      {sent ? (
        <div className="grid gap-4">
          <FormAlert tone="success" message={t('auth.forgot_sent')} />
          <ButtonLink to="/login" variant="secondary" block>
            {t('auth.back_to_login')}
          </ButtonLink>
        </div>
      ) : (
        <form onSubmit={submit} noValidate className="grid gap-4">
          <TextField label={t('fields.email')} required type="email" inputMode="email" autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} error={err} />
          <Button type="submit" size="lg" block loading={busy}>
            {t('auth.send_reset')}
          </Button>
          <Link to="/login" className="t-small text-center font-semibold text-primary hover:underline">
            {t('auth.back_to_login')}
          </Link>
        </form>
      )}
    </>
  )
}

export function ResetPasswordPage() {
  const { t } = useTranslation()
  useDocTitle(t('auth.reset_title'))
  const [params] = useSearchParams()
  const token = params.get('token') ?? ''
  const email = params.get('email') ?? ''
  const [pw, setPw] = useState('')
  const [confirm, setConfirm] = useState('')
  const [focus, setFocus] = useState(false)
  const [errs, setErrs] = useState<Record<string, string>>({})
  const [state, setState] = useState<'form' | 'done' | 'expired'>(token ? 'form' : 'expired')
  const [busy, setBusy] = useState(false)
  const submit = async (e: FormEvent) => {
    e.preventDefault()
    const x: Record<string, string> = {}
    if (!passwordScore(pw, [email.split('@')[0]]).rules.every((r) => r.ok)) x.pw = t('validation.password_rules')
    if (pw !== confirm) x.confirm = t('validation.password_match')
    setErrs(x)
    if (Object.keys(x).length) return
    setBusy(true)
    try {
      await http.post('/auth/reset-password', { token, email, password: pw, password_confirmation: confirm })
      setState('done')
    } catch (err) {
      if (err instanceof ApiError && (err.code === 'token_invalid' || err.status === 410)) setState('expired')
      else setErrs({ pw: t('errors.server_body') })
    } finally {
      setBusy(false)
    }
  }
  if (state === 'expired')
    return (
      <div className="grid justify-items-start gap-4">
        <Illustration kind="clock" />
        <AuthHeading title={t('auth.reset_expired_title')} sub={t('auth.reset_expired_body')} />
        <ButtonLink to="/forgot-password" block>
          {t('auth.request_new_link')}
        </ButtonLink>
      </div>
    )
  if (state === 'done')
    return (
      <div className="grid gap-4">
        <AuthHeading title={t('auth.reset_done_title')} sub={t('auth.reset_done_body')} />
        <ButtonLink to="/login" block>
          {t('auth.login')}
        </ButtonLink>
      </div>
    )
  return (
    <>
      <AuthHeading title={t('auth.reset_title')} sub={email ? maskEmail(email) : undefined} />
      <form onSubmit={submit} noValidate className="grid gap-4">
        <div className="grid gap-2">
          <PasswordField label={t('auth.new_password')} required value={pw} onChange={(e) => setPw(e.target.value)} onFocus={() => setFocus(true)} error={errs.pw} autoComplete="new-password" />
          <PasswordStrength value={pw} context={[email.split('@')[0]]} visible={focus || pw.length > 0} />
        </div>
        <PasswordField label={t('auth.password_confirm')} required value={confirm} onChange={(e) => setConfirm(e.target.value)} error={errs.confirm} autoComplete="new-password" />
        <Button type="submit" size="lg" block loading={busy}>
          {t('auth.save_password')}
        </Button>
      </form>
    </>
  )
}

/* ============================ OTP ============================ */
interface OtpStatus {
  destination_masked: string
  channel: 'email' | 'whatsapp'
  expires_at: string | null
  resend_available_at: string | null
  attempts_left: number
  whatsapp_available: boolean
  dev_code?: string | null
}
export function VerifyPage() {
  const { t } = useTranslation()
  useDocTitle(t('auth.otp.title'))
  const nav = useNavigate()
  const [params] = useSearchParams()
  const { me, setMe } = useAuth()
  const [st, setSt] = useState<OtpStatus | null>(null)
  const [code, setCode] = useState('')
  const [err, setErr] = useState<string | null>(null)
  const [shake, setShake] = useState(0)
  const [busy, setBusy] = useState(false)
  const [changing, setChanging] = useState(false)
  const [newEmail, setNewEmail] = useState('')
  const expLeft = useCountdown(st?.expires_at ? new Date(st.expires_at).getTime() : null)
  const resendLeft = useCountdown(st?.resend_available_at ? new Date(st.resend_available_at).getTime() : null)

  const load = useCallback(() => {
    http
      .get<{ data: OtpStatus }>('/auth/otp/status')
      .then((r) => setSt(r.data))
      .catch((e) => {
        if (e instanceof ApiError && e.status === 401) nav('/login', { replace: true })
      })
  }, [nav])
  useEffect(() => {
    if (me && me.status !== 'pending_verification') nav(routeForStatus(me, params.get('next')), { replace: true })
    else load()
  }, [load, me, nav, params])

  const verify = async (c: string) => {
    if (c.length !== 6 || busy) return
    setBusy(true)
    setErr(null)
    try {
      const r = await http.post<{ data: Me }>('/auth/otp/verify', { code: c })
      setMe(r.data)
      nav(routeForStatus(r.data, params.get('next')), { replace: true })
    } catch (e) {
      setCode('')
      setShake((s) => s + 1)
      if (e instanceof ApiError) {
        const left = Number((e.data as any)?.attempts_left ?? 0)
        if (e.code === 'otp_expired') setErr(t('auth.otp.expired'))
        else if (e.code === 'too_many_attempts') setErr(t('auth.otp.locked'))
        else setErr(t('auth.otp.wrong', { count: left }))
        if (st) setSt({ ...st, attempts_left: left })
      }
    } finally {
      setBusy(false)
    }
  }
  const resend = async (channel: 'email' | 'whatsapp' = 'email') => {
    setErr(null)
    try {
      const r = await http.post<{ data: OtpStatus }>('/auth/otp/resend', { channel })
      setSt(r.data)
      setCode('')
    } catch (e) {
      if (e instanceof ApiError && e.code === 'otp_hourly_limit') setErr(t('auth.otp.hourly_limit'))
    }
  }
  const expired = !!st && expLeft === 0 && !!st.expires_at

  return (
    <>
      <AuthHeading title={t('auth.otp.title')} sub={st ? t('auth.otp.sent_to', { dest: st.destination_masked }) : '…'} />
      <div className="grid gap-4">
        <span className="sr-only" aria-live="polite">
          {err ?? ''}
        </span>
        <OtpInput value={code} onChange={setCode} onComplete={verify} disabled={expired || busy || st?.attempts_left === 0} invalid={!!err} shakeKey={shake} />
        {err && <p className="t-small text-danger">{err}</p>}
        {st && (
          <p className={cn('t-small tabular-nums', expired ? 'text-danger' : 'text-muted')}>
            {expired ? t('auth.otp.expired') : t('auth.otp.valid_for', { time: mmss(expLeft) })}
          </p>
        )}
        {st?.dev_code && <p className="t-mono rounded-md bg-warning/14 p-2 text-fg">{t('auth.otp.dev_code', { code: st.dev_code })}</p>}
        <Button size="lg" block loading={busy} disabled={code.length !== 6 || expired} onClick={() => verify(code)}>
          {t('auth.otp.verify')}
        </Button>
        <p className="t-small flex flex-wrap items-center gap-x-3 gap-y-1 text-muted">
          <span>{t('auth.otp.not_received')}</span>
          <button type="button" disabled={resendLeft > 0} onClick={() => resend('email')} className="font-semibold text-primary disabled:text-muted">
            {resendLeft > 0 ? t('auth.otp.resend_in', { s: resendLeft }) : t('auth.otp.resend')}
          </button>
          <button type="button" onClick={() => setChanging(true)} className="font-semibold text-primary">
            {t('auth.otp.change')}
          </button>
        </p>
        {st?.whatsapp_available && (
          <button type="button" disabled={resendLeft > 0} onClick={() => resend('whatsapp')} className="t-small justify-self-start font-semibold text-primary disabled:text-muted">
            {t('auth.otp.via_whatsapp')}
          </button>
        )}
      </div>
      <Modal
        open={changing}
        onClose={() => setChanging(false)}
        size="form"
        title={t('auth.otp.change_title')}
        footer={
          <Button
            onClick={async () => {
              try {
                const r = await http.post<{ data: OtpStatus }>('/auth/otp/change-email', { email: newEmail.trim().toLowerCase() })
                setSt(r.data)
                setChanging(false)
              } catch (e) {
                setErr(e instanceof ApiError ? e.fields.email?.[0] ?? t('errors.server_body') : t('errors.server_body'))
                setChanging(false)
              }
            }}
          >
            {t('auth.otp.send_new')}
          </Button>
        }
      >
        <TextField label={t('fields.email')} type="email" value={newEmail} onChange={(e) => setNewEmail(e.target.value)} autoComplete="email" />
      </Modal>
    </>
  )
}

/* ============================ STATUS AKUN ============================ */
export function AccountStatusPage() {
  const { t } = useTranslation()
  useDocTitle(t('account_status.title'))
  const { me, logout } = useAuth()
  const nav = useNavigate()
  useEffect(() => {
    if (!me) nav('/login', { replace: true })
    else if (me.status === 'active') nav(me.role === 'admin' ? '/admin' : '/app', { replace: true })
    else if (me.status === 'pending_verification') nav('/verify', { replace: true })
  }, [me, nav])
  if (!me) return null
  const s = me.status
  const Icon = s === 'pending_approval' ? Hourglass : s === 'request_revision' ? FileWarning : s === 'rejected' ? ShieldX : s === 'pending_verification' ? MailCheck : Clock
  return (
    <div className="grid gap-4">
      <span className="grid size-14 place-items-center rounded-xl bg-primary-soft text-primary">
        <Icon className="size-7" aria-hidden />
      </span>
      <AuthHeading title={t(`account_status.${s}_title`)} sub={t(`account_status.${s}_body`)} />
      {me.status_note && (
        <div className="rounded-md border border-line bg-surface-2 p-3">
          <p className="t-label text-fg">{t('account_status.admin_note')}</p>
          <p className="t-small mt-1 whitespace-pre-line text-muted">{me.status_note}</p>
        </div>
      )}
      {s === 'request_revision' && (
        <ButtonLink to="/account-status/revise" block>
          {t('account_status.update_data')}
        </ButtonLink>
      )}
      <ButtonLink to="/contact" variant="secondary" block icon={<LifeBuoy className="size-4" aria-hidden />}>
        {s === 'suspended' || s === 'banned' ? t('account_status.contact_support') : t('footer.help')}
      </ButtonLink>
      <Button
        variant="ghost"
        block
        onClick={async () => {
          await logout()
          nav('/login', { replace: true })
        }}
      >
        {t('nav.logout')}
      </Button>
    </div>
  )
}

export function ReviseAccountPage() {
  const { t } = useTranslation()
  const { me, setMe } = useAuth()
  const nav = useNavigate()
  const [name, setName] = useState(me?.name ?? '')
  const [phone, setPhone] = useState(me?.phone ?? '')
  const [note, setNote] = useState('')
  const [busy, setBusy] = useState(false)
  if (!me) return null
  return (
    <>
      <AuthHeading title={t('account_status.update_data')} sub={me.status_note ?? undefined} />
      <form
        className="grid gap-4"
        onSubmit={async (e) => {
          e.preventDefault()
          setBusy(true)
          try {
            const r = await http.post<{ data: Me }>('/me/revision', { name, phone, note })
            setMe(r.data)
            nav('/account-status', { replace: true })
          } finally {
            setBusy(false)
          }
        }}
      >
        <TextField label={t('fields.full_name')} value={name} onChange={(e) => setName(e.target.value)} required />
        <TextField label={t('fields.whatsapp')} value={phone} onChange={(e) => setPhone(e.target.value)} optional />
        <TextField label={t('account_status.note_to_admin')} value={note} onChange={(e) => setNote(e.target.value)} optional />
        <Button type="submit" size="lg" block loading={busy}>
          {t('account_status.submit_revision')}
        </Button>
      </form>
    </>
  )
}
