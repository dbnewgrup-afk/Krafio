import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Bot, CheckCircle2, Cpu, Download, ExternalLink, FlaskConical, GitBranch, Settings2 } from 'lucide-react'
import { http, ApiError } from '@/api-client/http'
import { useApi } from '@/shared/hooks/useApi'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { useToast } from '@/shared/context/ToastContext'
import { PageHeader, Card } from '@/shared/ui/Misc'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Status'
import { Modal } from '@/shared/ui/Modal'
import { Skeleton } from '@/shared/ui/States'
import { Checkbox, SelectField, Switch, TextArea, TextField } from '@/shared/ui/Field'
import type { AiTool } from '@/api-client/types'

interface Repo {
  folder: string
  name: string
  url: string
  license: string
  method: string
  status: 'integrated' | 'adapter' | 'planned' | 'reference' | 'blocked'
  note: string
}
interface AdminTool extends AiTool {
  override: Record<string, unknown>
  default_label: string
  default_description: string
  default_cost_per_unit: number | null
  default_max_parts: number | null
  all_fields: { key: string; label: string; required: boolean; type: string; default: unknown; options: { value: string; label: string }[] }[]
  prompts: Record<string, { key: string; id?: number | null; status?: string | null }>
  repos: Repo[]
  usage_month: { jobs: number; completed: number; failed: number }
}
interface Payload {
  providers: Record<'text' | 'image' | 'video', boolean>
  tools: AdminTool[]
}

const REPO_TONE = { integrated: 'success', adapter: 'info', planned: 'neutral', reference: 'neutral', blocked: 'danger' } as const

/**
 * Admin → AI → AI Tools: kontrol semua fitur AI di panel user tanpa deploy — aktif/nonaktif, label, deskripsi,
 * biaya per unit, batas part, nilai default form, field yang disembunyikan, prompt, dan repo acuan per fitur.
 */
export function AiToolsAdmin({ title }: { title: string }) {
  const { t } = useTranslation()
  useDocTitle(title)
  const toast = useToast()
  const { data, reload } = useApi<Payload>('/admin/ai-tools')
  const [edit, setEdit] = useState<AdminTool | null>(null)
  const [test, setTest] = useState<{ busy: boolean; out?: { ok: boolean; text: string | null; seconds: number; model: string | null } }>({ busy: false })

  if (!data) return <Skeleton className="h-96" />

  const save = async (key: string, body: Record<string, unknown>) => {
    try {
      await http.put(`/admin/ai-tools/${key}`, body)
      toast.push('success', t('common.saved'))
      reload()
      return true
    } catch (e) {
      toast.push('error', e instanceof ApiError ? e.message : (e as Error).message)
      return false
    }
  }

  const groups: { key: AiTool['group']; label: string }[] = [
    { key: 'write', label: t('admin.ai_tools.group_write') },
    { key: 'create', label: t('admin.ai_tools.group_create') },
    { key: 'edit', label: t('admin.ai_tools.group_edit') },
  ]

  return (
    <>
      <PageHeader title={title} subtitle={t('admin.ai_tools.subtitle')} />
      <div className="mb-6 grid gap-3 md:grid-cols-4">
        {(['text', 'image', 'video'] as const).map((c) => (
          <Card key={c}>
            <p className="t-small text-muted">{t(`admin.capability.${c}`)}</p>
            <p className="mt-1 flex items-center gap-2 font-semibold text-fg">
              <Badge tone={data.providers[c] ? 'success' : 'warning'}>{data.providers[c] ? t('admin.ai_tools.provider_on') : t('admin.ai_tools.provider_off')}</Badge>
            </p>
          </Card>
        ))}
        <Card>
          <p className="t-small text-muted">{t('admin.ai_tools.test_title')}</p>
          <Button
            size="sm"
            variant="secondary"
            className="mt-2"
            loading={test.busy}
            icon={<FlaskConical className="size-3.5" aria-hidden />}
            onClick={async () => {
              setTest({ busy: true })
              const r = await http.post<{ data: { ok: boolean; text: string | null; seconds: number; model: string | null } }>('/admin/ai-tools/test-text', {}).catch(() => null)
              setTest({ busy: false, out: r?.data })
            }}
          >
            {t('admin.ai_tools.test_run')}
          </Button>
        </Card>
      </div>
      {test.out && (
        <p className={`t-small mb-6 rounded-md p-3 ${test.out.ok ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger'}`}>
          {test.out.ok ? `${test.out.model} · ${test.out.seconds} s — ${test.out.text}` : t('admin.ai_tools.test_failed')}
        </p>
      )}
      <p className="t-small mb-6 text-muted">
        {t('admin.ai_tools.providers_hint')}{' '}
        <Link to="/admin/ai/providers" className="text-primary hover:underline">
          {t('admin.nav.ai_providers')}
        </Link>
        {' · '}
        <Link to="/admin/ai/prompts" className="text-primary hover:underline">
          {t('admin.nav.prompt_library')}
        </Link>
      </p>

      <LocalModels />

      {groups.map((g) => (
        <section key={g.key} className="mb-8">
          <h2 className="t-h2 mb-3 text-fg">{g.label}</h2>
          <div className="grid gap-3 lg:grid-cols-2">
            {data.tools
              .filter((x) => x.group === g.key)
              .map((tool) => (
                <Card key={tool.key} className="grid content-start gap-3">
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                      <p className="flex items-center gap-2 font-semibold text-fg">
                        <Bot className="size-4 text-primary" aria-hidden />
                        {tool.label}
                      </p>
                      <p className="t-small mt-1 line-clamp-2 text-muted">{tool.description}</p>
                    </div>
                    <Switch checked={tool.enabled} onChange={(v) => void save(tool.key, { enabled: v })} label={<span className="sr-only">{t('admin.ai_tools.enabled')}</span>} />
                  </div>
                  <dl className="t-small grid grid-cols-3 gap-2">
                    <div>
                      <dt className="text-muted">{t('admin.ai_tools.usage_month')}</dt>
                      <dd className="font-semibold tabular-nums text-fg">
                        {tool.usage_month.jobs} <span className="font-normal text-muted">({tool.usage_month.failed} {t('admin.ai_tools.failed')})</span>
                      </dd>
                    </div>
                    <div>
                      <dt className="text-muted">{t('admin.ai_tools.cost')}</dt>
                      <dd className="font-semibold tabular-nums text-fg">{tool.cost_per_unit ?? t('admin.ai_tools.cost_credits')}</dd>
                    </div>
                    <div>
                      <dt className="text-muted">{t('admin.ai_tools.fields')}</dt>
                      <dd className="font-semibold tabular-nums text-fg">
                        {tool.fields.length}/{tool.all_fields.length}
                      </dd>
                    </div>
                  </dl>
                  {tool.repos.length > 0 && (
                    <div className="flex flex-wrap gap-1.5">
                      {tool.repos.map((r) => (
                        <a key={r.folder} href={r.url} target="_blank" rel="noopener noreferrer" title={`${r.license} · ${r.method} — ${r.note}`} className="t-small inline-flex items-center gap-1 rounded-full border border-line px-2 py-0.5 text-muted hover:border-primary/50 hover:text-fg">
                          <GitBranch className="size-3" aria-hidden />
                          {r.name}
                          <Badge tone={REPO_TONE[r.status]}>{t(`admin.ai_tools.repo_${r.status}`)}</Badge>
                        </a>
                      ))}
                    </div>
                  )}
                  <div className="flex flex-wrap gap-2">
                    <Button size="sm" variant="secondary" icon={<Settings2 className="size-3.5" aria-hidden />} onClick={() => setEdit(tool)}>
                      {t('admin.ai_tools.configure')}
                    </Button>
                    {Object.values(tool.prompts).map((p) =>
                      p.id ? (
                        <Link key={p.key} to={`/admin/ai/prompts?search=${encodeURIComponent(p.key)}`} className="t-small inline-flex items-center gap-1 rounded-md px-2 py-1 text-primary hover:bg-surface-2">
                          <ExternalLink className="size-3.5" aria-hidden />
                          {p.key}
                        </Link>
                      ) : null,
                    )}
                  </div>
                </Card>
              ))}
          </div>
        </section>
      ))}

      {edit && <ToolConfigModal tool={edit} onClose={() => setEdit(null)} onSave={async (body) => (await save(edit.key, body)) && setEdit(null)} />}
    </>
  )
}

function ToolConfigModal({ tool, onClose, onSave }: { tool: AdminTool; onClose: () => void; onSave: (b: Record<string, unknown>) => Promise<unknown> }) {
  const { t } = useTranslation()
  const o = tool.override as { label?: string; description?: string; cost_per_unit?: number; max_parts?: number; hidden_fields?: string[]; defaults?: Record<string, unknown> }
  const [label, setLabel] = useState(o.label ?? '')
  const [desc, setDesc] = useState(o.description ?? '')
  const [cost, setCost] = useState(o.cost_per_unit !== undefined ? String(o.cost_per_unit) : '')
  const [maxParts, setMaxParts] = useState(o.max_parts !== undefined ? String(o.max_parts) : '')
  const [hidden, setHidden] = useState<string[]>(o.hidden_fields ?? [])
  const [defaults, setDefaults] = useState<Record<string, unknown>>(o.defaults ?? {})
  const [busy, setBusy] = useState(false)

  return (
    <Modal
      open
      onClose={onClose}
      size="form"
      title={`${t('admin.ai_tools.configure')} — ${tool.default_label}`}
      footer={
        <Button
          loading={busy}
          onClick={async () => {
            setBusy(true)
            await onSave({ label: label || null, description: desc || null, cost_per_unit: cost === '' ? null : Number(cost), max_parts: maxParts === '' ? null : Number(maxParts), hidden_fields: hidden, defaults })
            setBusy(false)
          }}
        >
          {t('common.save')}
        </Button>
      }
    >
      <div className="grid gap-4">
        <TextField label={t('admin.ai_tools.label')} value={label} onChange={(e) => setLabel(e.target.value)} placeholder={tool.default_label} optional maxLength={80} />
        <TextArea label={t('admin.ai_tools.description')} value={desc} onChange={(e) => setDesc(e.target.value)} placeholder={tool.default_description} optional rows={2} maxLength={300} />
        {tool.default_cost_per_unit !== null && (
          <div className="grid grid-cols-2 gap-3">
            <TextField label={t('admin.ai_tools.cost_per_unit')} type="number" min={0} max={100} value={cost} onChange={(e) => setCost(e.target.value)} placeholder={String(tool.default_cost_per_unit)} hint={t('admin.ai_tools.cost_hint')} />
            {tool.default_max_parts !== null && tool.default_max_parts > 1 && <TextField label={t('admin.ai_tools.max_parts')} type="number" min={1} max={tool.default_max_parts} value={maxParts} onChange={(e) => setMaxParts(e.target.value)} placeholder={String(tool.default_max_parts)} />}
          </div>
        )}
        <fieldset className="grid gap-2">
          <legend className="t-label mb-1 text-fg">{t('admin.ai_tools.fields_title')}</legend>
          <p className="t-small text-muted">{t('admin.ai_tools.fields_hint')}</p>
          {tool.all_fields.map((f) => (
            <div key={f.key} className="grid items-center gap-2 rounded-md border border-line p-2 sm:grid-cols-[1fr_1fr]">
              <Checkbox label={`${f.label}${f.required ? ` (${t('common.required')})` : ''}`} checked={!hidden.includes(f.key)} disabled={f.required} onChange={(e) => setHidden(e.target.checked ? hidden.filter((x) => x !== f.key) : [...hidden, f.key])} />
              {f.options.length > 0 && f.type !== 'multiselect' ? (
                <SelectField aria-label={`${t('admin.ai_tools.default')} ${f.label}`} value={String(defaults[f.key] ?? f.default ?? '')} onChange={(e) => setDefaults({ ...defaults, [f.key]: e.target.value })}>
                  {f.options.map((x) => (
                    <option key={x.value} value={x.value}>
                      {t('admin.ai_tools.default')}: {x.label}
                    </option>
                  ))}
                </SelectField>
              ) : f.type === 'number' ? (
                <TextField aria-label={`${t('admin.ai_tools.default')} ${f.label}`} type="number" value={String(defaults[f.key] ?? f.default ?? '')} onChange={(e) => setDefaults({ ...defaults, [f.key]: Number(e.target.value) })} />
              ) : (
                <span />
              )}
            </div>
          ))}
        </fieldset>
        {tool.repos.length > 0 && (
          <div className="grid gap-2">
            <p className="t-label text-fg">{t('admin.ai_tools.repos')}</p>
            {tool.repos.map((r) => (
              <p key={r.folder} className="t-small text-muted">
                <a href={r.url} target="_blank" rel="noopener noreferrer" className="font-semibold text-primary hover:underline">
                  {r.name}
                </a>{' '}
                · {r.license} · {r.method} — {r.note}
              </p>
            ))}
          </div>
        )}
      </div>
    </Modal>
  )
}

interface LocalProvider {
  provider_id: number
  name: string
  base_url: string
  status: string
  runtime: 'ollama' | 'openai_compatible' | null
  reachable: boolean
  active_model: string | null
  models: { name: string; size_gb: number | null; params: string | null; family: string | null }[]
  pulls: Record<string, { status: string; pct: number | null; done: boolean; error?: boolean }>
}

/** Model AI lokal (Ollama / Jan — konsep repo 05-jan): lihat, unduh, dan pilih model tanpa terminal. */
function LocalModels() {
  const { t } = useTranslation()
  const toast = useToast()
  const { data, reload } = useApi<{ providers: LocalProvider[]; suggested: { name: string; note: string }[] }>('/admin/ai-local')
  const [model, setModel] = useState('')
  const pulling = data?.providers.some((p) => Object.values(p.pulls).some((x) => !x.done))
  useEffect(() => {
    if (!pulling) return
    const id = window.setInterval(reload, 2000)
    return () => window.clearInterval(id)
  }, [pulling, reload])
  if (!data || data.providers.length === 0) return null

  const pull = async (pid: number, m: string) => {
    if (!m.trim()) return
    try {
      await http.post('/admin/ai-local/pull', { provider_id: pid, model: m.trim() })
      toast.push('info', t('admin.ai_local.pull_started', { model: m }))
      setModel('')
      reload()
    } catch (e) {
      toast.push('error', e instanceof ApiError ? e.message : (e as Error).message)
    }
  }
  const use = async (pid: number, m: string) => {
    await http.post('/admin/ai-local/use', { provider_id: pid, model: m })
    toast.push('success', t('admin.ai_local.now_using', { model: m }))
    reload()
  }

  return (
    <section className="mb-8">
      <h2 className="t-h2 mb-1 flex items-center gap-2 text-fg">
        <Cpu className="size-5 text-primary" aria-hidden />
        {t('admin.ai_local.title')}
      </h2>
      <p className="t-small mb-3 text-muted">{t('admin.ai_local.subtitle')}</p>
      {data.providers.map((p) => (
        <Card key={p.provider_id} className="grid gap-3">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="font-semibold text-fg">
              {p.name} <span className="t-small font-normal text-muted">· {p.base_url}</span>
            </p>
            <Badge tone={p.reachable ? 'success' : 'danger'}>{p.reachable ? (p.runtime === 'ollama' ? 'Ollama' : 'OpenAI-compatible') : t('admin.ai_local.offline')}</Badge>
          </div>
          {p.models.length > 0 ? (
            <ul className="grid gap-2 sm:grid-cols-2">
              {p.models.map((m) => (
                <li key={m.name} className="flex items-center justify-between gap-2 rounded-md border border-line p-2">
                  <span className="min-w-0">
                    <span className="block truncate text-sm font-semibold text-fg">{m.name}</span>
                    <span className="t-small text-muted">{[m.params, m.size_gb ? `${m.size_gb} GB` : null].filter(Boolean).join(' · ')}</span>
                  </span>
                  {p.active_model === m.name ? (
                    <span className="t-small inline-flex items-center gap-1 text-success">
                      <CheckCircle2 className="size-4" aria-hidden />
                      {t('admin.ai_local.active')}
                    </span>
                  ) : (
                    <Button size="sm" variant="secondary" onClick={() => use(p.provider_id, m.name)}>
                      {t('admin.ai_local.use')}
                    </Button>
                  )}
                </li>
              ))}
            </ul>
          ) : (
            p.reachable && <p className="t-small text-muted">{t('admin.ai_local.no_models')}</p>
          )}
          {Object.entries(p.pulls).map(([m, x]) => (
            <div key={m} className="grid gap-1">
              <p className={`t-small ${x.error ? 'text-danger' : 'text-muted'}`}>
                {m}: {x.status}
                {x.pct !== null && !x.done ? ` · ${x.pct}%` : ''}
              </p>
              {!x.done && (
                <div className="h-1.5 overflow-hidden rounded-full bg-surface-2">
                  <div className="h-full bg-primary transition-[width]" style={{ width: `${x.pct ?? 2}%` }} />
                </div>
              )}
            </div>
          ))}
          {p.runtime === 'ollama' && (
            <div className="grid gap-2">
              <div className="flex flex-wrap gap-2">
                <TextField aria-label={t('admin.ai_local.model_name')} placeholder="qwen2.5:7b" value={model} onChange={(e) => setModel(e.target.value)} wrapClassName="min-w-48 flex-1" />
                <Button variant="secondary" icon={<Download className="size-4" aria-hidden />} onClick={() => pull(p.provider_id, model)}>
                  {t('admin.ai_local.pull')}
                </Button>
              </div>
              <div className="flex flex-wrap gap-1.5">
                {data.suggested.filter((s) => !p.models.some((m) => m.name === s.name)).map((s) => (
                  <button key={s.name} type="button" title={s.note} onClick={() => pull(p.provider_id, s.name)} className="t-small rounded-full border border-line px-2.5 py-1 text-muted hover:border-primary/50 hover:text-fg">
                    + {s.name}
                  </button>
                ))}
              </div>
            </div>
          )}
        </Card>
      ))}
    </section>
  )
}
