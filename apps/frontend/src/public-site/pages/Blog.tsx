import type { ReactNode } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { ArrowLeft } from 'lucide-react'
import { useApi } from '@/shared/hooks/useApi'
import { useDocTitle } from '@/shared/hooks/useDocTitle'
import { EmptyState, Skeleton } from '@/shared/ui/States'
import { ButtonLink } from '@/shared/ui/Button'
import { dateTime } from '@/shared/lib/format'
import { PageIntro, NotFoundPage } from './Catalog'

interface BlogItem {
  slug: string
  title: string
  excerpt: string
  published_at: string | null
}
interface BlogPost {
  slug: string
  title: string
  body: string
  published_at: string | null
}

/** Daftar artikel blog publik (B5 audit; CMS di Admin → Konten → Blog). */
export function BlogListPage() {
  const { t, i18n } = useTranslation()
  useDocTitle(t('blog.title'))
  const { data, error } = useApi<BlogItem[]>('/public/blog', { lang: i18n.language, per_page: 24 })
  return (
    <div className="mx-auto w-full max-w-[1100px] px-4 py-10 md:px-6">
      <PageIntro title={t('blog.title')} body={t('blog.subtitle')} />
      {!data && !error ? (
        <div className="grid gap-4 md:grid-cols-2">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-40" />
          ))}
        </div>
      ) : !data || data.length === 0 ? (
        <EmptyState title={t('blog.empty')} body={t('blog.empty_body')} />
      ) : (
        <ul className="grid gap-4 md:grid-cols-2">
          {data.map((p) => (
            <li key={p.slug}>
              <Link to={`/blog/${p.slug}`} className="grid h-full gap-2 rounded-xl border border-line bg-surface p-5 transition-colors hover:border-primary/50">
                {p.published_at && <span className="t-small text-muted">{dateTime(p.published_at, false)}</span>}
                <h2 className="t-h3 text-fg">{p.title}</h2>
                <p className="t-small text-muted">{p.excerpt}</p>
                <span className="t-label text-primary">{t('blog.read')}</span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

/** Isi artikel: teks aman (tanpa HTML) — "## " subjudul, "- " daftar, baris kosong = paragraf baru. */
function renderBody(body: string): ReactNode[] {
  const out: ReactNode[] = []
  let list: string[] = []
  const flush = () => {
    if (list.length) out.push(<ul key={`l${out.length}`} className="my-3 list-disc space-y-1 pl-6">{list.map((x, i) => <li key={i}>{x}</li>)}</ul>)
    list = []
  }
  for (const raw of body.split(/\r?\n/)) {
    const line = raw.trim()
    if (/^[-*]\s+/.test(line)) {
      list.push(line.replace(/^[-*]\s+/, ''))
      continue
    }
    flush()
    if (!line) continue
    if (/^###\s+/.test(line)) out.push(<h3 key={out.length} className="t-h3 mt-6 text-fg">{line.replace(/^###\s+/, '')}</h3>)
    else if (/^##?\s+/.test(line)) out.push(<h2 key={out.length} className="t-h2 mt-8 text-fg">{line.replace(/^##?\s+/, '')}</h2>)
    else out.push(<p key={out.length} className="my-3">{line}</p>)
  }
  flush()
  return out
}

export function BlogPostPage() {
  const { slug = '' } = useParams()
  const { t, i18n } = useTranslation()
  const { data, error } = useApi<BlogPost>(`/public/blog/${encodeURIComponent(slug)}`, { lang: i18n.language })
  useDocTitle(data?.title ?? t('blog.title'))
  if (error?.status === 404) return <NotFoundPage />
  return (
    <article className="mx-auto w-full max-w-[760px] px-4 py-10 md:px-6">
      <ButtonLink to="/blog" variant="ghost" size="sm" icon={<ArrowLeft className="size-4" aria-hidden />}>
        {t('blog.back')}
      </ButtonLink>
      {!data ? (
        <Skeleton className="mt-6 h-96" />
      ) : (
        <>
          <h1 className="t-display mt-4 text-fg">{data.title}</h1>
          {data.published_at && <p className="t-small mt-2 text-muted">{dateTime(data.published_at, false)}</p>}
          <div className="t-body mt-6 text-fg">{renderBody(data.body)}</div>
        </>
      )}
    </article>
  )
}
