import { createContext, useCallback, useContext, useRef, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ShieldCheck } from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { PasswordField } from '@/shared/ui/PasswordField'

interface Ctx {
  /** Jalankan aksi; bila server meminta re-autentikasi (§7.2 butir 6) tampilkan modal kata sandi lalu ulangi. */
  withReauth: <T>(fn: () => Promise<T>) => Promise<T>
}
const AdminCtx = createContext<Ctx | null>(null)

export function AdminProvider({ children }: { children: ReactNode }) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const [pw, setPw] = useState('')
  const [err, setErr] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const pending = useRef<{ resolve: () => void; reject: (e: unknown) => void } | null>(null)

  const askPassword = () =>
    new Promise<void>((resolve, reject) => {
      pending.current = { resolve, reject }
      setPw('')
      setErr(null)
      setOpen(true)
    })

  const withReauth = useCallback(async <T,>(fn: () => Promise<T>): Promise<T> => {
    try {
      return await fn()
    } catch (e) {
      if (e instanceof ApiError && e.code === 'reauth_required') {
        await askPassword()
        return fn()
      }
      throw e
    }
  }, [])

  return (
    <AdminCtx.Provider value={{ withReauth }}>
      {children}
      <Modal
        open={open}
        onClose={() => {
          setOpen(false)
          pending.current?.reject(new Error(t('admin.reauth_cancelled')))
        }}
        size="confirm"
        icon={<ShieldCheck className="size-5 text-primary" aria-hidden />}
        title={t('admin.reauth_title')}
        footer={
          <Button
            loading={busy}
            onClick={async () => {
              setBusy(true)
              try {
                await http.post('/admin/reauth', { password: pw })
                setOpen(false)
                pending.current?.resolve()
              } catch {
                setErr(t('admin.reauth_wrong'))
              } finally {
                setBusy(false)
              }
            }}
          >
            {t('admin.reauth_confirm')}
          </Button>
        }
      >
        <div className="grid gap-3">
          <p className="t-small text-muted">{t('admin.reauth_body')}</p>
          <PasswordField label={t('auth.password')} autoComplete="current-password" value={pw} onChange={(e) => setPw(e.target.value)} error={err} />
        </div>
      </Modal>
    </AdminCtx.Provider>
  )
}

export function useAdmin(): Ctx {
  const c = useContext(AdminCtx)
  if (!c) throw new Error('AdminProvider missing')
  return c
}
