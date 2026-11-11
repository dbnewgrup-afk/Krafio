// Render PNG/ICO brand dari SVG: favicon, ikon PWA, apple-touch, OG image, wordmark, logo email.
import { chromium } from 'playwright-core'
import { readFileSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'

const OUT = process.argv[2]
const browser = await chromium.launch({ channel: 'msedge', headless: true })
const ctx = await browser.newContext({ deviceScaleFactor: 1 })
const page = await ctx.newPage()
const read = (rel) => readFileSync(join(OUT, rel), 'utf8')
const FONT = `<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap">`

async function shot(html, w, h, file, { omitBg = true, scale = 1 } = {}) {
  await page.setViewportSize({ width: w, height: h })
  await page.setContent(`<!doctype html><html><head><meta charset="utf-8">${FONT}<style>html,body{margin:0;width:${w}px;height:${h}px;overflow:hidden;background:transparent}*{box-sizing:border-box}</style></head><body>${html}</body></html>`, { waitUntil: 'networkidle' })
  await page.evaluate(() => document.fonts.ready)
  await page.waitForTimeout(150)
  const buf = await page.screenshot({ type: 'png', omitBackground: omitBg, clip: { x: 0, y: 0, width: w, height: h } })
  writeFileSync(join(OUT, file), buf)
  console.log('png', file, w, h)
  return buf
}
const svgImg = (rel, w, h) => `<img src="data:image/svg+xml;base64,${Buffer.from(read(rel)).toString('base64')}" width="${w}" height="${h}" style="display:block">`

// Ikon
const pngs = {}
for (const s of [16, 32, 48, 180, 192, 512]) pngs[s] = await shot(svgImg('brand/favicon.svg', s, s), s, s, s === 180 ? 'brand/apple-touch-icon.png' : s === 192 || s === 512 ? `brand/icon-${s}.png` : `brand/favicon-${s}.png`)
await shot(svgImg('brand/icon-maskable.svg', 512, 512), 512, 512, 'brand/icon-maskable-512.png', { omitBg: false })
// ICO berisi PNG 16/32/48 (format PNG-in-ICO, didukung semua browser modern)
{
  const entries = [16, 32, 48].map((s) => pngs[s])
  const header = Buffer.alloc(6); header.writeUInt16LE(0, 0); header.writeUInt16LE(1, 2); header.writeUInt16LE(entries.length, 4)
  let offset = 6 + 16 * entries.length
  const dirs = entries.map((b, i) => { const s = [16, 32, 48][i]; const d = Buffer.alloc(16); d.writeUInt8(s, 0); d.writeUInt8(s, 1); d.writeUInt8(0, 2); d.writeUInt8(0, 3); d.writeUInt16LE(1, 4); d.writeUInt16LE(32, 6); d.writeUInt32LE(b.length, 8); d.writeUInt32LE(offset, 12); offset += b.length; return d })
  writeFileSync(join(OUT, 'brand/favicon.ico'), Buffer.concat([header, ...dirs, ...entries]))
  console.log('ico brand/favicon.ico')
}

// Wordmark (mark + teks) light/dark @2x
const word = (markRel, color) => `<div style="display:flex;align-items:center;gap:20px;height:160px;padding:0 24px;font-family:'Plus Jakarta Sans',sans-serif">${svgImg(markRel, 112, 112)}<span style="font-size:96px;font-weight:700;letter-spacing:-0.03em;color:${color};line-height:1">Krafio</span></div>`
await shot(word('brand/logo-mark.svg', '#0F172A'), 560, 160, 'brand/logo-wordmark-light.png')
await shot(word('brand/logo-mark-dark.svg', '#E5E9F5'), 560, 160, 'brand/logo-wordmark-dark.png')
await shot(`<div style="transform:scale(.5);transform-origin:0 0">${word('brand/logo-mark.svg', '#0F172A')}</div>`, 280, 80, 'brand/logo-email.png')

// OG image 1200×630
const og = `
<div style="position:relative;width:1200px;height:630px;background:#0B1020;color:#E5E9F5;font-family:'Plus Jakarta Sans',sans-serif;overflow:hidden">
  <div style="position:absolute;right:-120px;top:-160px;width:620px;height:620px;border-radius:50%;background:radial-gradient(closest-side,#4338CA55,transparent)"></div>
  <div style="position:absolute;left:72px;top:72px;display:flex;align-items:center;gap:18px">${svgImg('brand/logo-mark-dark.svg', 72, 72)}<span style="font-size:48px;font-weight:700;letter-spacing:-0.03em">Krafio</span></div>
  <div style="position:absolute;left:72px;top:214px;width:640px;font-size:60px;line-height:66px;font-weight:800;letter-spacing:-0.025em">Foto, video, konten, website, dan SEO. Satu akun.</div>
  <div style="position:absolute;left:72px;top:430px;font-size:26px;line-height:36px;color:#A3ADC8;width:600px">Biaya kredit terlihat sebelum Anda menekan Generate.</div>
  <div style="position:absolute;left:72px;bottom:56px;display:flex;gap:12px;font-size:18px;font-weight:600"><span style="padding:10px 18px;border-radius:999px;background:#818CF8;color:#0B1020">Mulai gratis</span><span style="padding:10px 18px;border-radius:999px;border:2px solid #36426C">krafio.id</span></div>
  <div style="position:absolute;right:72px;top:150px;display:grid;grid-template-columns:1fr 1fr;gap:16px;transform:rotate(-6deg)">
    ${['kopi', 'skincare', 'sepatu', 'sambal'].map((k) => `<div style="width:170px;height:170px;border-radius:22px;overflow:hidden;box-shadow:0 18px 48px #00000066">${svgImg(`samples/product-${k}.svg`, 170, 170)}</div>`).join('')}
  </div>
</div>`
await shot(og, 1200, 630, 'brand/og-image.png', { omitBg: false })

await browser.close()
