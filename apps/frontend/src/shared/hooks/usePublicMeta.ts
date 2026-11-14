import { useEffect } from 'react'
import { pageMeta, SITE_URL } from '@/public-site/seoMeta'

function setMeta(selector: string, attr: 'name' | 'property', key: string, content: string | null) {
  let el = document.head.querySelector<HTMLMetaElement>(selector)
  if (content === null) {
    el?.remove()
    return
  }
  if (!el) {
    el = document.createElement('meta')
    el.setAttribute(attr, key)
    document.head.appendChild(el)
  }
  el.content = content
}

/** Sinkronkan meta SEO halaman publik saat navigasi SPA (sumber sama dengan HTML statis hasil build). */
export function usePublicMeta(pathname: string): void {
  useEffect(() => {
    const m = pageMeta(pathname)
    if (!m) return
    const url = SITE_URL + (m.path === '/' ? '/' : m.path)
    setMeta('meta[name="description"]', 'name', 'description', m.description)
    setMeta('meta[property="og:title"]', 'property', 'og:title', m.title)
    setMeta('meta[property="og:description"]', 'property', 'og:description', m.description)
    setMeta('meta[property="og:url"]', 'property', 'og:url', url)
    setMeta('meta[name="robots"]', 'name', 'robots', m.noindex ? 'noindex, follow' : null)
    let link = document.head.querySelector<HTMLLinkElement>('link[rel="canonical"]')
    if (!link) {
      link = document.createElement('link')
      link.rel = 'canonical'
      document.head.appendChild(link)
    }
    link.href = url
  }, [pathname])
}
