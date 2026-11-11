import { useEffect, useRef, useState, type DragEvent as RDragEvent } from 'react'
import { motion, Reorder, useDragControls } from 'framer-motion'
import { useTranslation } from 'react-i18next'
import { Eye, EyeOff, GripVertical, ImagePlus, Plus, RotateCcw, Save, Trash2 } from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { useToast } from '@/shared/context/ToastContext'
import { PageHeader, Segmented, Card, CardTitle } from '@/shared/ui/Misc'
import { Button } from '@/shared/ui/Button'
import { TextField, TextArea, SelectField, Switch } from '@/shared/ui/Field'
import { Skeleton } from '@/shared/ui/States'
import { HeroShowcase, DEFAULT_HERO_CONFIG, type HeroConfig, type HeroSlide } from '@/shared/ui/HeroShowcase'
import { cn } from '@/shared/lib/cn'
import { img } from '@/shared/lib/assets'

type Audience = 'user' | 'admin'
// Pustaka gambar siap pakai: foto & mockup resmi Krafio (public/assets/img)
const LIBRARY = [
  'photos/member-hero-photo.jpg', 'photos/member-hero-templates.jpg', 'photos/member-hero-website.jpg',
  'photos/admin-hero-inbox.jpg', 'photos/admin-hero-templates.jpg', 'photos/admin-login-wallet.jpg',
  'photos/hero-generate.jpg', 'photos/hero-seo.jpg', 'photos/wallet-cards.jpg',
  'samples/kopi-susu-meja-kayu.jpg', 'templates/menu-digital.jpg', 'templates/story-giveaway.jpg',
  'features/website-builder.jpg', 'features/seo-tools.jpg', 'features/video-clipper.jpg', 'features/social-integration.jpg',
]

/** Kelola slide Hero Showcase untuk dashboard User dan Admin dengan pratinjau langsung. */
export function HeroEditor() {
  const { t } = useTranslation()
  useDocTitle(t('hero_ed.title'))
  const toast = useToast()
  const [aud, setAud] = useState<Audience>('user')
  const [slides, setSlides] = useState<HeroSlide[] | null>(null)
  const [config, setConfig] = useState<HeroConfig>(DEFAULT_HERO_CONFIG)
  const [defaults, setDefaults] = useState<HeroSlide[]>([])
  const [sel, setSel] = useState(0)
  const [dirty, setDirty] = useState(false)
  const [saving, setSaving] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [uploading, setUploading] = useState(false)
  const [over, setOver] = useState(false)
  const fileRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    setSlides(null)
    http.get<{ data: { slides: HeroSlide[]; config: Partial<HeroConfig>; defaults: HeroSlide[] } }>('/admin/hero', { audience: aud }).then((r) => {
      setSlides(r.data.slides)
      setConfig({ ...DEFAULT_HERO_CONFIG, ...r.data.config })
      setDefaults(r.data.defaults)
      setSel(0)
      setDirty(false)
    })
  }, [aud])

  const cur = slides?.[sel]
  const patch = (p: Partial<HeroSlide>) => {
    setSlides((xs) => xs?.map((s, i) => (i === sel ? { ...s, ...p } : s)) ?? xs)
    setDirty(true)
  }
  const setCfg = (p: Partial<HeroConfig>) => {
    setConfig((c) => ({ ...c, ...p }))
    setDirty(true)
  }

  const upload = async (file: File | undefined) => {
    if (!file) return
    setUploading(true)
    try {
      const fd = new FormData()
      fd.append('file', file)
      const r = await http.post<{ data: { url: string } }>('/admin/media', fd)
      patch({ image: r.data.url })
    } catch (e) {
      toast.push('error', e instanceof ApiError ? e.message : t('errors.server_title'))
    } finally {
      setUploading(false)
    }
  }

  const save = async () => {
    if (!slides) return
    setSaving(true)
    setErrors({})
    try {
      const r = await http.put<{ data: { slides: HeroSlide[]; config: Partial<HeroConfig> } }>('/admin/hero', { audience: aud, slides, config })
      setSlides(r.data.slides)
      setDirty(false)
      toast.push('success', t('hero_ed.saved'))
    } catch (e) {
      if (e instanceof ApiError) setErrors(e.fields)
      toast.push('error', t('hero_ed.save_failed'))
    } finally {
      setSaving(false)
    }
  }

  const err = (k: string) => errors[`slides.${sel}.${k}`]?.[0]

  return (
    <>
      <PageHeader
        title={t('hero_ed.title')}
        subtitle={t('hero_ed.subtitle')}
        crumbs={[{ label: t('admin.nav.g_content'), to: '/admin/content/frontpage' }, { label: t('hero_ed.title') }]}
        actions={
          <>
            <Button variant="secondary" icon={<RotateCcw className="size-4" aria-hidden />} onClick={() => { setSlides(defaults.map((d) => ({ ...d }))); setSel(0); setDirty(true) }}>{t('hero_ed.reset')}</Button>
            <Button icon={<Save className="size-4" aria-hidden />} loading={saving} disabled={!dirty} onClick={() => void save()}>{t('hero_ed.save')}</Button>
          </>
        }
      />
      <Segmented label={t('hero_ed.audience')} value={aud} onChange={(v) => (dirty && !window.confirm(t('hero_ed.discard_confirm')) ? undefined : setAud(v))} items={[{ value: 'user', label: t('hero_ed.aud_user') }, { value: 'admin', label: t('hero_ed.aud_admin') }]} />

      {!slides ? (
        <Skeleton className="mt-4 h-96" />
      ) : (
        <div className="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1fr)_420px]">
          {/* Pratinjau langsung */}
          <div className="grid content-start gap-3 xl:sticky xl:top-[calc(var(--nav-h)+16px)]">
            <p className="t-label text-muted">{t('hero_ed.live_preview')}</p>
            {slides.filter((s) => s.enabled !== false).length ? <HeroShowcase slides={slides} config={{ ...config, autoPlay: false }} editingIndex={sel} /> : <div className="grid h-60 place-items-center rounded-2xl border border-dashed border-line text-muted">{t('hero_ed.all_hidden')}</div>}
            <Card>
              <CardTitle>{t('hero_ed.settings')}</CardTitle>
              <div className="grid gap-4 sm:grid-cols-2">
                <SelectField label={t('hero_ed.effect')} value={config.imageEffect} onChange={(e) => setCfg({ imageEffect: e.target.value as HeroConfig['imageEffect'] })}>
                  {(['parallax', 'tilt', 'zoom', 'none'] as const).map((x) => <option key={x} value={x}>{t(`hero_ed.fx_${x}`)}</option>)}
                </SelectField>
                <SelectField label={t('hero_ed.interval')} value={config.interval} onChange={(e) => setCfg({ interval: Number(e.target.value) })}>
                  {[4000, 6000, 8000, 10000].map((x) => <option key={x} value={x}>{x / 1000} {t('hero_ed.seconds')}</option>)}
                </SelectField>
                <SelectField label={t('hero_ed.speed')} value={config.animationSpeed} onChange={(e) => setCfg({ animationSpeed: Number(e.target.value) })}>
                  {[0, 0.75, 1, 1.5].map((x) => <option key={x} value={x}>{x === 0 ? t('hero_ed.speed_off') : `${x}×`}</option>)}
                </SelectField>
                <div className="grid gap-3">
                  <Switch label={t('hero_ed.autoplay')} checked={config.autoPlay} onChange={(v) => setCfg({ autoPlay: v })} />
                  <Switch label={t('hero_ed.float')} checked={config.float} onChange={(v) => setCfg({ float: v })} />
                  <Switch label={t('hero_ed.dots')} checked={config.showDots} onChange={(v) => setCfg({ showDots: v })} />
                  <Switch label={t('hero_ed.arrows')} checked={config.showArrows} onChange={(v) => setCfg({ showArrows: v })} />
                  <Switch label={t('hero_ed.lightbox')} checked={config.lightbox} onChange={(v) => setCfg({ lightbox: v })} />
                </div>
              </div>
            </Card>
          </div>

          {/* Daftar slide + form */}
          <div className="grid content-start gap-4">
            <Card>
              <CardTitle action={<Button size="sm" variant="secondary" icon={<Plus className="size-4" aria-hidden />} disabled={slides.length >= 8} onClick={() => { setSlides([...slides, { id: `s${Date.now().toString(36)}`, enabled: true, title: t('hero_ed.new_title'), body: '', badge: '', cta_label: '', cta_url: '', image: null, alt: '' }]); setSel(slides.length); setDirty(true) }}>{t('hero_ed.add')}</Button>}>
                {t('hero_ed.slides', { n: slides.length })}
              </CardTitle>
              <Reorder.Group axis="y" values={slides} onReorder={(xs) => { const id = slides[sel]?.id; setSlides(xs); setSel(Math.max(0, xs.findIndex((x) => x.id === id))); setDirty(true) }} className="grid gap-1.5">
                {slides.map((s, i) => (
                  <SlideRow key={s.id} s={s} active={i === sel} onSelect={() => setSel(i)} onToggle={() => { setSlides(slides.map((x, j) => (j === i ? { ...x, enabled: x.enabled === false } : x))); setDirty(true) }} onDelete={() => { setSlides(slides.filter((_, j) => j !== i)); setSel(Math.max(0, sel - (i <= sel ? 1 : 0))); setDirty(true) }} />
                ))}
              </Reorder.Group>
            </Card>

            {cur && (
              <Card>
                <CardTitle>{t('hero_ed.edit_slide', { n: sel + 1 })}</CardTitle>
                <div className="grid gap-4">
                  <div
                    onDragOver={(e: RDragEvent) => { e.preventDefault(); setOver(true) }}
                    onDragLeave={() => setOver(false)}
                    onDrop={(e: RDragEvent) => { e.preventDefault(); setOver(false); void upload(e.dataTransfer.files[0]) }}
                    className={cn('grid gap-2 rounded-xl border-2 border-dashed p-3 transition-colors', over ? 'border-primary bg-primary-soft' : 'border-line')}
                  >
                    {cur.image ? <img src={cur.image} alt="" className="aspect-[4/3] w-full rounded-lg bg-surface-2 object-cover" /> : <div className="grid aspect-[4/3] place-items-center rounded-lg bg-surface-2 text-muted">{t('hero.no_image')}</div>}
                    <div className="flex flex-wrap items-center gap-2">
                      <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp,image/gif" hidden onChange={(e) => void upload(e.target.files?.[0])} />
                      <Button size="sm" variant="secondary" loading={uploading} icon={<ImagePlus className="size-4" aria-hidden />} onClick={() => fileRef.current?.click()}>{t('hero_ed.upload')}</Button>
                      {cur.image && <Button size="sm" variant="ghost" onClick={() => patch({ image: null })}>{t('common.remove')}</Button>}
                      <span className="t-small text-muted">{t('hero_ed.drop_hint')}</span>
                    </div>
                    <div className="snap-row -mx-1 gap-1.5 px-1" aria-label={t('hero_ed.library')}>
                      {LIBRARY.map((p) => (
                        <button key={p} type="button" onClick={() => patch({ image: img(p) })} aria-label={p} className={cn('size-14 shrink-0 overflow-hidden rounded-md border-2', cur.image === img(p) ? 'border-primary' : 'border-transparent hover:border-line')}>
                          <img src={img(p)} alt="" className="h-full w-full object-cover" loading="lazy" />
                        </button>
                      ))}
                    </div>
                  </div>
                  <TextField label={t('hero_ed.f_alt')} optional value={cur.alt ?? ''} onChange={(e) => patch({ alt: e.target.value })} maxLength={140} hint={t('hero_ed.f_alt_hint')} />
                  <TextField label={t('hero_ed.f_badge')} optional value={cur.badge ?? ''} onChange={(e) => patch({ badge: e.target.value })} maxLength={24} hint={t('hero_ed.f_badge_hint')} />
                  <TextField label={t('hero_ed.f_title')} required value={cur.title} onChange={(e) => patch({ title: e.target.value })} maxLength={90} error={err('title')} />
                  <TextArea label={t('hero_ed.f_body')} optional rows={3} value={cur.body ?? ''} onChange={(e) => patch({ body: e.target.value })} maxLength={220} hint={`${(cur.body ?? '').length}/220`} />
                  <div className="grid gap-4 sm:grid-cols-2">
                    <TextField label={t('hero_ed.f_cta')} optional value={cur.cta_label ?? ''} onChange={(e) => patch({ cta_label: e.target.value })} maxLength={32} />
                    <TextField label={t('hero_ed.f_url')} optional value={cur.cta_url ?? ''} onChange={(e) => patch({ cta_url: e.target.value })} placeholder={aud === 'admin' ? '/admin/support/inbox' : '/app/create/photo'} error={err('cta_url')} hint={t('hero_ed.f_url_hint')} />
                  </div>
                </div>
              </Card>
            )}
          </div>
        </div>
      )}
    </>
  )
}

function SlideRow({ s, active, onSelect, onToggle, onDelete }: { s: HeroSlide; active: boolean; onSelect: () => void; onToggle: () => void; onDelete: () => void }) {
  const { t } = useTranslation()
  const controls = useDragControls()
  const off = s.enabled === false
  return (
    <Reorder.Item value={s} dragListener={false} dragControls={controls} className={cn('flex items-center gap-2 rounded-lg border bg-surface p-1.5', active ? 'border-primary bg-primary-soft' : 'border-line')}>
      <button type="button" onPointerDown={(e) => controls.start(e)} aria-label={t('hero_ed.drag')} className="grid size-8 shrink-0 cursor-grab touch-none place-items-center rounded-md text-muted active:cursor-grabbing"><GripVertical className="size-4" aria-hidden /></button>
      <button type="button" onClick={onSelect} className={cn('flex min-w-0 flex-1 items-center gap-2 text-left', off && 'opacity-50')}>
        {s.image ? <img src={s.image} alt="" className="size-10 shrink-0 rounded-md object-cover" /> : <span className="size-10 shrink-0 rounded-md bg-surface-2" />}
        <span className="min-w-0">
          <span className="block truncate text-sm font-semibold text-fg">{s.title}</span>
          <span className="t-small block truncate text-muted">{s.badge || s.cta_label || '—'}</span>
        </span>
      </button>
      <motion.button type="button" whileTap={{ scale: 0.9 }} onClick={onToggle} aria-label={off ? t('hero_ed.show') : t('hero_ed.hide')} aria-pressed={!off} className="grid size-8 place-items-center rounded-md text-muted hover:bg-surface-2 hover:text-fg">{off ? <EyeOff className="size-4" aria-hidden /> : <Eye className="size-4" aria-hidden />}</motion.button>
      <button type="button" onClick={onDelete} aria-label={t('common.remove')} className="grid size-8 place-items-center rounded-md text-danger hover:bg-danger/10"><Trash2 className="size-4" aria-hidden /></button>
    </Reorder.Item>
  )
}
