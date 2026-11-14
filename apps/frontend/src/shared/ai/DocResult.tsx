import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { Check, Clapperboard, Copy, Download, FileText, Loader2, Send, Table } from 'lucide-react'
import type { AiJobResult } from '@/api-client/types'
import { Button } from '@/shared/ui/Button'
import { cn } from '@/shared/lib/cn'

function useCopy() {
  const [done, setDone] = useState<string | null>(null)
  const copy = async (id: string, text: string) => {
    try {
      await navigator.clipboard.writeText(text)
    } catch {
      const ta = document.createElement('textarea')
      ta.value = text
      document.body.appendChild(ta)
      ta.select()
      document.execCommand('copy')
      ta.remove()
    }
    setDone(id)
    window.setTimeout(() => setDone((d) => (d === id ? null : d)), 1500)
  }
  return { done, copy }
}

function download(name: string, text: string, type: string) {
  const url = URL.createObjectURL(new Blob([text], { type }))
  const a = document.createElement('a')
  a.href = url
  a.download = name
  a.click()
  window.setTimeout(() => URL.revokeObjectURL(url), 1000)
}

/** Teks layar / hook + CTA dari naskah → satu baris per adegan untuk "Naskah → Video". */
function scriptLines(sections: { key: string; content: string }[]): string {
  const get = (k: string) => sections.find((x) => x.key === k)?.content ?? ''
  const strip = (l: string) => l.replace(/^\s*(\d+[.):]|[-•*])\s*/, '').replace(/^(adegan|scene)\s*\d+\s*[:.-]\s*/i, '').trim()
  const lines = (get('onscreen') || get('script')).split(/\n+/).map(strip).filter(Boolean)
  return [get('hook'), ...lines.slice(0, 20), get('cta')].map((l) => l.split(/\n/)[0].trim()).filter(Boolean).slice(0, 30).join('\n')
}

const slug = (s: string) => s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 50) || 'krafio'

function docToMarkdown(r: AiJobResult): string {
  const out: string[] = [`# ${r.title ?? 'Krafio'}`, '']
  if (r.parts?.length) {
    for (const p of r.parts) {
      if (r.parts.length > 1) out.push(`## ${p.title}`, '')
      for (const s of p.sections) out.push(`### ${s.label}`, '', s.content, '')
    }
  }
  if (r.items?.length && r.item_fields?.length) {
    const f = r.item_fields
    out.push(`| ${f.map((x) => x.label).join(' | ')} |`, `| ${f.map(() => '---').join(' | ')} |`)
    for (const it of r.items) if (typeof it === 'object') out.push(`| ${f.map((x) => String(it[x.key] ?? '').replace(/\|/g, '/').replace(/\n/g, ' ')).join(' | ')} |`)
  }
  return out.join('\n')
}

function docToText(r: AiJobResult): string {
  return docToMarkdown(r).replace(/^#+\s*/gm, '').replace(/^\|\s*-.*$/gm, '')
}

function itemsToCsv(r: AiJobResult): string {
  const f = r.item_fields ?? []
  const esc = (v: string) => `"${v.replace(/"/g, '""')}"`
  return [f.map((x) => esc(x.label)).join(','), ...(r.items ?? []).map((it) => (typeof it === 'object' ? f.map((x) => esc(String(it[x.key] ?? ''))).join(',') : esc(String(it))))].join('\n')
}

/** Hasil AI Tools teks (format "doc"): part/variasi dengan bagian berlabel, atau daftar item. */
export function DocResult({ result, running, jobId }: { result: AiJobResult; running?: boolean; jobId?: number }) {
  const { t } = useTranslation()
  const { done, copy } = useCopy()
  const parts = result.parts ?? []
  const [tab, setTab] = useState(0)
  const part = parts[Math.min(tab, Math.max(0, parts.length - 1))]
  const name = slug(result.title ?? 'krafio')
  const items = (result.items ?? []).filter((x): x is Record<string, string> => typeof x === 'object')
  // Teks siap posting dari part aktif: caption (+ hashtag) atau isi copy
  const sec = (k: string) => part?.sections.find((x) => x.key === k)?.content
  const postText = [sec('caption') ?? sec('body') ?? sec('story'), sec('hashtags')].filter(Boolean).join('\n\n')

  return (
    <div className="grid gap-4">
      {result.simulated && <p className="t-small rounded-md bg-warning/10 p-3 text-warning">{result.note}</p>}
      {(running || result.partial) && (
        <p className="t-small flex items-center gap-2 rounded-md bg-info/10 p-3 text-info">
          <Loader2 className="size-4 animate-spin" aria-hidden />
          {t('tools.writing')}
        </p>
      )}

      <div className="flex flex-wrap gap-2">
        <Button size="sm" variant="secondary" icon={done === 'all' ? <Check className="size-3.5" aria-hidden /> : <Copy className="size-3.5" aria-hidden />} onClick={() => copy('all', docToText(result))}>
          {done === 'all' ? t('tools.copied') : t('tools.copy_all')}
        </Button>
        <Button size="sm" variant="ghost" icon={<FileText className="size-3.5" aria-hidden />} onClick={() => download(`${name}.md`, docToMarkdown(result), 'text/markdown')}>
          .md
        </Button>
        <Button size="sm" variant="ghost" icon={<Download className="size-3.5" aria-hidden />} onClick={() => download(`${name}.txt`, docToText(result), 'text/plain')}>
          .txt
        </Button>
        {postText && (
          <Link to={`/app/social?compose=${encodeURIComponent(postText.slice(0, 4000))}`} className="t-label inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-primary hover:bg-surface-2">
            <Send className="size-3.5" aria-hidden />
            {t('tools.to_social')}
          </Link>
        )}
        {jobId && !running && !result.partial && (
          <>
            <a href={`/api/app/jobs/${jobId}/export/docx`} className="t-label inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-fg hover:bg-surface-2">
              <FileText className="size-3.5" aria-hidden />
              .docx
            </a>
            <a href={`/api/app/jobs/${jobId}/export/xlsx`} className="t-label inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-fg hover:bg-surface-2">
              <Table className="size-3.5" aria-hidden />
              .xlsx
            </a>
          </>
        )}
        {result.tool === 'content_script' && part && !running && (
          <Link to={`/app/create/script-video?prompt=${encodeURIComponent(scriptLines(part.sections))}`} className="t-label inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-primary hover:bg-surface-2">
            <Clapperboard className="size-3.5" aria-hidden />
            {t('tools.to_video')}
          </Link>
        )}
        {items.length > 0 && (
          <Button size="sm" variant="ghost" icon={<Table className="size-3.5" aria-hidden />} onClick={() => download(`${name}.csv`, '﻿' + itemsToCsv(result), 'text/csv')}>
            .csv
          </Button>
        )}
      </div>

      {parts.length > 1 && (
        <div className="flex gap-1 overflow-x-auto border-b border-line" role="tablist" aria-label={t('tools.parts')}>
          {parts.map((_, i) => (
            <button key={i} role="tab" aria-selected={i === tab} onClick={() => setTab(i)} className={cn('t-label shrink-0 border-b-2 px-3 py-2', i === tab ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-fg')}>
              {t('tools.part_n', { n: i + 1 })}
            </button>
          ))}
        </div>
      )}

      {part && (
        <article className="grid gap-3">
          <h3 className="t-h3 text-fg">{part.title}</h3>
          {part.sections.map((s, i) => (
            <section key={s.key + i} className="rounded-lg border border-line bg-surface p-3">
              <div className="mb-2 flex items-center justify-between gap-2">
                <h4 className="t-label text-primary">{s.label}</h4>
                <button type="button" onClick={() => copy(`${tab}-${i}`, s.content)} className="t-small inline-flex items-center gap-1 rounded-md px-2 py-1 text-muted hover:bg-surface-2 hover:text-fg" aria-label={`${t('tools.copy')} ${s.label}`}>
                  {done === `${tab}-${i}` ? <Check className="size-3.5" aria-hidden /> : <Copy className="size-3.5" aria-hidden />}
                  {done === `${tab}-${i}` ? t('tools.copied') : t('tools.copy')}
                </button>
              </div>
              <p className="whitespace-pre-wrap text-sm leading-6 text-fg">{s.content}</p>
            </section>
          ))}
        </article>
      )}

      {items.length > 0 && (
        <ol className="grid gap-2">
          {items.map((it, i) => {
            const fields = result.item_fields ?? []
            const head = fields[0]
            return (
              <li key={i} className="rounded-lg border border-line bg-surface p-3">
                <div className="mb-1 flex items-start justify-between gap-2">
                  <p className="text-sm font-semibold text-fg">
                    <span className="mr-2 text-primary">{i + 1}.</span>
                    {head && head.key === 'day' ? `${head.label} ${it.day} · ${it.title ?? ''}` : it[head?.key ?? 'title']}
                  </p>
                  <button type="button" onClick={() => copy(`it-${i}`, fields.map((f) => `${f.label}: ${it[f.key] ?? ''}`).join('\n'))} className="t-small shrink-0 rounded-md px-2 py-1 text-muted hover:bg-surface-2 hover:text-fg" aria-label={t('tools.copy')}>
                    {done === `it-${i}` ? <Check className="size-3.5" aria-hidden /> : <Copy className="size-3.5" aria-hidden />}
                  </button>
                </div>
                <dl className="grid gap-1 sm:grid-cols-[auto_1fr] sm:gap-x-3">
                  {fields.slice(1).filter((f) => !(head?.key === 'day' && f.key === 'title') && it[f.key]).map((f) => (
                    <div key={f.key} className="contents">
                      <dt className="t-small text-muted">{f.label}</dt>
                      <dd className="t-small whitespace-pre-wrap text-fg">{it[f.key]}</dd>
                    </div>
                  ))}
                </dl>
              </li>
            )
          })}
        </ol>
      )}
    </div>
  )
}
