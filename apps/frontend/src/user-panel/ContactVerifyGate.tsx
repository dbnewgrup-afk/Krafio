import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Mail, MessageCircle, ShieldCheck, LogOut } from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import { useAuth } from '@/shared/context/AuthContext'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { Button } from '@/shared/ui/Button'
import { OtpInput } from '@/shared/ui/OtpInput'
import { Skeleton } from '@/shared/ui/States'
import { Logo, LangMenu, ThemeMenu } from '@/shared/layout/Utilities'
import { FormAlert } from '@/public-site/auth/AuthLayout'
import { cn } from '@/shared/lib/cn'

interface Status {
  needs_verification: boolean
  email: string
  phone: string | null
  whatsapp_available: boolean
  channel: 'email' | 'whatsapp' | null
  destination_masked: string | null
  expires_at: string | null
  resend_available_at: string | null
  attempts_left: number | null
  dev_code: string | null
}

/**
 * Akun dibuat Admin sudah aktif, tetapi pemilik wajib memverifikasi salah satu kontak:
 * email ATAU nomor HP (WhatsApp). Setelah salah satunya terverifikasi, panel terbuka.
 */
export function ContactVerifyGate() {
  const { t } = useTranslation()
  useDocTitle(t('contact_verify.title'))
  const { refresh, logout, setMe } = useAuth()
  const [s, setS] = useState<Status | null>(null)
  const [channel, setChannel] = useState<'email' | 'whatsapp'>('email')
  const [code, setCode] = useState('')
  const [err, setErr] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [shake, setShake] = useState(0)
  const [now, setNow] = useState(() => Date.now())

  useEffect(() => {
    http.get<{ data: Status }>('/me/contact-verification').then((r) => {
      setS(r.data)
      if (r.data.channel) setChannel(r.data.channel)
    })
  }, [])
  useEffect(() => {
    const id = window.setInterval(() => setNow(Date.now()), 1000)
    return () => window.clearInterval(id)
  }, [])

  // Kode kedaluwarsa dianggap belum terkirim → tombol "Kirim kode" muncul lagi
  const sent = !!s?.channel && s.channel === channel && !!s.expires_at && new Date(s.expires_at).getTime() > now
  const wait = s?.resend_available_at ? Math.max(0, Math.ceil((new Date(s.resend_available_at).getTime() - now) / 1000)) : 0

  const send = async () => {
    setBusy(true)
    setErr(null)
    try {
      const r = await http.post<{ data: Status }>('/me/contact-verification/send', { channel })
      setS(r.data)
      setCode('')
    } catch (e) {
      setErr(e instanceof ApiError ? (Object.values(e.fields)[0]?.[0] ?? e.message) : t('errors.server_title'))
    } finally {
      setBusy(false)
    }
  }

  const confirm = async (c: string) => {
    if (c.length !== 6 || busy) return
    setBusy(true)
    setErr(null)
    try {
      const r = await http.post<{ data: Parameters<typeof setMe>[0] }>('/me/contact-verification/confirm', { code: c })
      setMe(r.data)
      await refresh()
    } catch (e) {
      setCode('')
      setShake((x) => x + 1)
      setErr(e instanceof ApiError ? e.message : t('errors.server_title'))
    } finally {
      setBusy(false)
    }
  }

  const options = [
    { key: 'email' as const, icon: Mail, label: t('contact_verify.email'), dest: s?.email, available: true },
    { key: 'whatsapp' as const, icon: MessageCircle, label: t('contact_verify.whatsapp'), dest: s?.phone, available: !!s?.whatsapp_available, reason: !s?.phone ? t('contact_verify.no_phone') : t('contact_verify.wa_off') },
  ]

  return (
    <div className="grid min-h-[100dvh] bg-bg-subtle">
      <div className="flex h-14 items-center justify-between px-4 md:h-16 md:px-6">
        <Logo to="/app" />
        <div className="flex items-center"><LangMenu /><ThemeMenu /></div>
      </div>
      <main id="main-content" className="flex justify-center px-4 pb-12 pt-4 md:items-center md:pt-0">
        <div className="grid w-full max-w-[480px] content-start gap-5 rounded-2xl border border-line bg-surface p-5 shadow-k-md md:p-8">
          <span className="grid size-12 place-items-center rounded-xl bg-primary-soft text-primary"><ShieldCheck className="size-6" aria-hidden /></span>
          <div className="grid gap-1.5">
            <h1 className="t-h1 text-fg" data-page-title tabIndex={-1}>{t('contact_verify.title')}</h1>
            <p className="t-body text-muted">{t('contact_verify.body')}</p>
          </div>
          {!s ? (
            <Skeleton className="h-40" />
          ) : (
            <>
              <div className="grid gap-2" role="radiogroup" aria-label={t('contact_verify.choose')}>
                {options.map((o) => (
                  <button
                    key={o.key}
                    type="button"
                    role="radio"
                    aria-checked={channel === o.key}
                    disabled={!o.available}
                    onClick={() => { setChannel(o.key); setErr(null); setCode('') }}
                    className={cn('flex items-center gap-3 rounded-xl border-2 p-3 text-left disabled:cursor-not-allowed disabled:opacity-55', channel === o.key ? 'border-primary bg-primary-soft' : 'border-line hover:border-primary/40')}
                  >
                    <o.icon className={cn('size-5 shrink-0', channel === o.key ? 'text-primary' : 'text-muted')} aria-hidden />
                    <span className="grid min-w-0">
                      <span className="font-semibold text-fg">{o.label}</span>
                      <span className="t-small truncate text-muted">{o.available ? o.dest ?? '—' : o.reason}</span>
                    </span>
                  </button>
                ))}
              </div>
              <FormAlert message={err} />
              {sent ? (
                <div className="grid gap-3">
                  <p className="t-small text-muted">{t('contact_verify.sent_to', { dest: s.destination_masked })}</p>
                  <OtpInput value={code} onChange={setCode} onComplete={(c) => void confirm(c)} invalid={!!err} shakeKey={shake} disabled={busy} />
                  {s.dev_code && <p className="t-small rounded-md bg-warning/10 p-2 text-warning">{t('contact_verify.dev_code', { code: s.dev_code })}</p>}
                  <Button size="lg" block loading={busy} disabled={code.length !== 6} onClick={() => void confirm(code)}>{t('contact_verify.verify')}</Button>
                  <button type="button" disabled={wait > 0 || busy} onClick={() => void send()} className="t-small justify-self-start text-primary hover:underline disabled:text-muted disabled:no-underline">
                    {wait > 0 ? t('contact_verify.resend_in', { s: wait }) : t('contact_verify.resend')}
                  </button>
                </div>
              ) : (
                <Button size="lg" block loading={busy} onClick={() => void send()}>{t('contact_verify.send')}</Button>
              )}
            </>
          )}
          <button type="button" onClick={() => void logout()} className="t-small inline-flex items-center gap-1.5 justify-self-start text-muted hover:text-fg">
            <LogOut className="size-4" aria-hidden />
            {t('nav.logout')}
          </button>
        </div>
      </main>
    </div>
  )
}
