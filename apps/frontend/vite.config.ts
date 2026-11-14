import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig, type Plugin } from 'vite'
import { fileURLToPath, URL } from 'node:url'
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'
import { PAGES, SITE_NAME, SITE_URL, type PageMeta } from './src/public-site/seoMeta.ts'

// Backend Laravel berjalan di BACKEND_URL (default http://127.0.0.1:8000).
// Proxy membuat browser melihat satu origin sehingga cookie Sanctum (HttpOnly) bekerja.
const backend = process.env.BACKEND_URL ?? 'http://127.0.0.1:8000'
const proxy = {
  '/api': { target: backend, changeOrigin: false },
  '/sanctum': { target: backend, changeOrigin: false },
}
// Tunnel sementara (Cloudflare Quick Tunnel) untuk uji dari perangkat lain
const allowedHosts = ['.trycloudflare.com', '.ngrok-free.app', '.ngrok.app', '.lhr.life', '.localhost.run']

const esc = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')

/**
 * HTML statis per halaman publik (SEO): setelah build, tulis dist/<rute>/index.html dengan <title>, meta description,
 * canonical, Open Graph, robots, dan ringkasan isi (H1, paragraf, tautan internal) di dalam #root.
 * Crawler tanpa JavaScript membaca konten yang benar; browser menampilkan splash lalu React menggantikan isi #root.
 * Server produksi: try_files $uri $uri/index.html /spa.html (lihat README §9).
 */
function prerenderPublicMeta(): Plugin {
  let outDir = 'dist'
  return {
    name: 'krafio-public-seo',
    configResolved(c) {
      outDir = c.build.outDir
    },
    // Preview lokal meniru nginx produksi: /rute → dist/rute/index.html bila ada, selain itu spa.html
    configurePreviewServer(server) {
      server.middlewares.use((req, _res, next) => {
        const path = (req.url ?? '/').split('?')[0]
        if (!path.includes('.') && !path.startsWith('/api') && !path.startsWith('/sanctum')) {
          const clean = path.replace(/\/+$/, '') || '/'
          const file = clean === '/' ? 'index.html' : `${clean.slice(1)}/index.html`
          req.url = existsSync(join(outDir, file)) ? `/${file}` : '/spa.html'
        }
        next()
      })
    },
    closeBundle() {
      const base = readFileSync(join(outDir, 'index.html'), 'utf8')
      // Fallback SPA generik (rute panel & 404): tanpa canonical/konten beranda agar tidak dianggap duplikat beranda
      writeFileSync(join(outDir, 'spa.html'), base.replace('<title>Krafio</title>', '<title>Krafio</title>\n    <meta name="robots" content="noindex" />'))
      const links = PAGES.filter((p) => !p.noindex && !p.path.startsWith('/features/') && p.path !== '/')
      const render = (p: PageMeta) => {
        const url = SITE_URL + (p.path === '/' ? '/' : p.path)
        const head = [
          `<title>${esc(p.title)}</title>`,
          `<link rel="canonical" href="${url}" />`,
          `<meta property="og:url" content="${url}" />`,
          p.noindex ? '<meta name="robots" content="noindex, follow" />' : '',
        ].join('\n    ')
        const body = `<div id="root"><main style="max-width:960px;margin:0 auto;padding:48px 16px;font-family:system-ui,sans-serif"><h1>${esc(p.h1)}</h1><p>${esc(p.intro)}</p>`
          + `<nav aria-label="${SITE_NAME}"><ul>${links.map((l) => `<li><a href="${l.path}">${esc(l.h1)}</a></li>`).join('')}</ul></nav></main></div>`
        return base
          .replace(/<title>[^<]*<\/title>/, head)
          .replace(/(<meta name="description" content=")[^"]*(")/, `$1${esc(p.description)}$2`)
          .replace(/(<meta property="og:title" content=")[^"]*(")/, `$1${esc(p.title)}$2`)
          .replace(/(<meta property="og:description" content=")[^"]*(")/, `$1${esc(p.description)}$2`)
          .replace('<div id="root"></div>', body)
      }
      for (const p of PAGES) {
        const dir = p.path === '/' ? outDir : join(outDir, p.path)
        mkdirSync(dir, { recursive: true })
        writeFileSync(join(dir, 'index.html'), render(p))
      }
    },
  }
}

export default defineConfig({
  plugins: [react(), tailwindcss(), prerenderPublicMeta()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  server: { port: 5173, proxy, allowedHosts },
  preview: { port: 4173, proxy, allowedHosts },
  build: {
    rollupOptions: {
      output: {
        // Hanya vendor yang dipisah manual. Admin & User Panel terpisah otomatis lewat dynamic import
        // di router, sehingga bundle admin tidak terunduh oleh pengguna biasa (Design Spec §20.3).
        // Vendor dipecah per pustaka besar agar unduhan awal lebih ringan & cache lebih awet antar rilis.
        manualChunks(id) {
          if (!id.includes('node_modules')) return undefined
          if (/node_modules\/(react|react-dom|scheduler)\//.test(id)) return 'react'
          if (/node_modules\/(react-router|@remix-run)/.test(id)) return 'router'
          if (/node_modules\/(i18next|react-i18next)/.test(id)) return 'i18n'
          if (/node_modules\/(framer-motion|motion-dom|motion-utils)/.test(id)) return 'motion'
          if (id.includes('node_modules/lucide-react')) return 'icons'
          return 'vendor'
        },
      },
    },
  },
})
