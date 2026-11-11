// Generator aset visual Krafio — SVG vektor datar mengikuti token design system.
// Keluaran: apps/frontend/public/assets/img/**
import { mkdirSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'

const OUT = process.argv[2]
if (!OUT) throw new Error('usage: node gen-assets.mjs <outDir>')

const C = {
  primary: '#4338CA', primaryL: '#818CF8', primaryHover: '#3730A3', soft: '#E0E7FF', softD: '#2A2F6B',
  navy: '#0B1020', navy2: '#172039', navy3: '#212C4A', slate: '#0F172A', muted: '#475569', mutedL: '#94A3B8',
  line: '#CBD3E0', surface2: '#EAEEF4', bgSubtle: '#EDF0F6', white: '#FFFFFF',
  sand: '#E9D5B7', sandD: '#D9BE93', coffee: '#3F2E22', coffeeL: '#6B4F3C', sky: '#C8D9E6', deep: '#1F3A4D', teal: '#4F7A8C', tealL: '#7FA7B8',
  success: '#16A34A', warning: '#D97706', danger: '#DC2626', info: '#2563EB',
  onDark: '#E5E9F5', onDarkMuted: '#A3ADC8',
}
const FONT = `font-family="'Plus Jakarta Sans',ui-sans-serif,system-ui,sans-serif"`

const files = []
function out(rel, content) {
  const p = join(OUT, rel)
  mkdirSync(join(p, '..'), { recursive: true })
  writeFileSync(p, content)
  files.push(rel)
}
const svg = (w, h, body, extra = '') => `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}" ${extra}>${body}</svg>\n`
const rr = (x, y, w, h, r, fill, more = '') => `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${r}" fill="${fill}" ${more}/>`
const ci = (cx, cy, r, fill, more = '') => `<circle cx="${cx}" cy="${cy}" r="${r}" fill="${fill}" ${more}/>`
const ln = (x1, y1, x2, y2, stroke, w = 3, more = '') => `<path d="M${x1} ${y1}L${x2} ${y2}" stroke="${stroke}" stroke-width="${w}" stroke-linecap="round" ${more}/>`
const pa = (d, fill, more = '') => `<path d="${d}" fill="${fill}" ${more}/>`
const st = (d, stroke, w = 3, more = '') => `<path d="${d}" fill="none" stroke="${stroke}" stroke-width="${w}" stroke-linecap="round" stroke-linejoin="round" ${more}/>`
const tx = (x, y, s, size, fill, more = '') => `<text x="${x}" y="${y}" font-size="${size}" fill="${fill}" ${FONT} ${more}>${s}</text>`
const spark = (cx, cy, r, fill) => pa(`M${cx} ${cy - r}C${cx + r * 0.11} ${cy - r * 0.32} ${cx + r * 0.32} ${cy - r * 0.11} ${cx + r} ${cy}C${cx + r * 0.32} ${cy + r * 0.11} ${cx + r * 0.11} ${cy + r * 0.32} ${cx} ${cy + r}C${cx - r * 0.11} ${cy + r * 0.32} ${cx - r * 0.32} ${cy + r * 0.11} ${cx - r} ${cy}C${cx - r * 0.32} ${cy - r * 0.11} ${cx - r * 0.11} ${cy - r * 0.32} ${cx} ${cy - r}Z`, fill)
// garis "teks" (placeholder baris tulisan)
const lines = (x, y, ws, gap, h, fill, r = h / 2) => ws.map((w, i) => rr(x, y + i * gap, w, h, r, fill)).join('')

/* =============================== 1. BRAND =============================== */
// Mark: K geometris — batang + kaki bawah + lengan atas yang berujung sparkle (AI).
export function markBody(fg = C.white) {
  return [
    rr(17, 15, 8, 34, 4, fg),
    ln(23, 33, 40, 49, fg, 8),
    ln(23, 31, 31, 23, fg, 8),
    spark(40.5, 21, 11, fg),
  ].join('')
}
function markSvg({ bg = C.primary, fg = C.white, rx = 16, size = 64, pad = 0 } = {}) {
  const inner = pad ? `<g transform="translate(${pad} ${pad}) scale(${(64 - pad * 2) / 64})">${markBody(fg)}</g>` : markBody(fg)
  const bgRect = bg === 'none' ? '' : `<rect width="64" height="64" rx="${rx}" fill="${bg}"/>`
  return svg(size, size, `<g transform="scale(${size / 64})">${bgRect}${inner}</g>`)
}
out('brand/logo-mark.svg', markSvg())
out('brand/logo-mark-dark.svg', markSvg({ bg: C.primaryL, fg: C.navy }))
out('brand/logo-mark-white.svg', markSvg({ bg: 'none', fg: C.white }))
out('brand/logo-mark-mono.svg', markSvg({ bg: 'none', fg: 'currentColor' }))
out('brand/favicon.svg', markSvg({ size: 64 }))
out('brand/icon-maskable.svg', svg(64, 64, `<rect width="64" height="64" fill="${C.primary}"/><g transform="translate(8 8) scale(0.75)">${markBody()}</g>`))
// Splash animasi (loader B / PWA)
out('brand/splash.svg', svg(240, 240, `<style>@keyframes p{0%,100%{opacity:1}50%{opacity:.55}}@media(prefers-reduced-motion:reduce){.p{animation:none}}</style><rect width="240" height="240" fill="${C.navy}"/><g class="p" style="animation:p 1.4s ease-in-out infinite" transform="translate(88 88) scale(1)"><rect width="64" height="64" rx="16" fill="${C.primaryL}"/>${markBody(C.navy)}</g>`))

/* ============================ 2. SAMPEL PRODUK ============================ */
// Ilustrasi produk UMKM Indonesia (1:1, 480) — dipakai di hero, pratinjau hasil simulasi, template foto.
const backdrop = (a, b) => `${rr(0, 0, 480, 480, 0, a)}${rr(0, 300, 480, 180, 0, b)}`
const shadow = (cx, cy, rx, ry) => `<ellipse cx="${cx}" cy="${cy}" rx="${rx}" ry="${ry}" fill="#000" opacity=".12"/>`
const samples = {
  kopi: () => backdrop(C.sky, C.coffee) + shadow(240, 400, 110, 16)
    + rr(60, 40, 150, 230, 10, C.white, 'opacity=".35"') // jendela cahaya
    + pa('M170 150h140l-12 240a16 16 0 0 1-16 15H198a16 16 0 0 1-16-15Z', '#D9E6F0')  // gelas
    + pa('M178 230h124l-7 150a12 12 0 0 1-12 11h-86a12 12 0 0 1-12-11Z', C.sandD)       // susu
    + pa('M183 300h114l-4 80a12 12 0 0 1-12 11h-82a12 12 0 0 1-12-11Z', C.coffeeL)       // gula aren
    + rr(190, 160, 100, 40, 6, C.white, 'opacity=".5"') + rr(196, 205, 88, 18, 6, C.white, 'opacity=".4"') // es
    + pa('M262 120l26-78 10 3-26 78Z', C.primary) // sedotan
    + tx(300, 372, 'kopi', 18, C.white, 'font-weight="700" opacity=".0"'),
  roti: () => backdrop(C.bgSubtle, C.sandD) + shadow(240, 380, 150, 18)
    + rr(60, 300, 360, 90, 14, C.coffee) + rr(70, 310, 340, 10, 5, C.coffeeL)             // talenan
    + pa('M90 300c0-80 60-140 150-140s150 60 150 140Z', '#C98B4E')                           // roti
    + pa('M110 300c0-66 54-118 130-118s130 52 130 118Z', '#DDA36A', 'opacity=".9"')
    + st('M150 230c20-30 50-45 90-45', C.coffee, 7) + st('M190 212c20-25 45-40 75-40', C.coffee, 7, 'opacity=".7"')
    + ci(330, 250, 6, C.sand) + ci(300, 232, 5, C.sand) + ci(350, 280, 5, C.sand),
  batik: () => backdrop(C.sand, C.sandD)
    + rr(40, 60, 400, 360, 10, C.deep)
    + [0, 1, 2, 3].map((r) => [0, 1, 2, 3, 4].map((c) => `<g transform="translate(${80 + c * 80} ${110 + r * 90})">${ci(0, 0, 22, C.teal)}${ci(0, 0, 11, C.sand)}${pa('M-30 -30c10 10 10 20 0 30 10 10 10 20 0 30', C.sand, 'opacity=".0"')}</g>`).join('')).join('')
    + [0, 1, 2, 3, 4].map((i) => st(`M40 ${90 + i * 90}c40 20 80 20 120 0s80-20 120 0 80 20 120 0`, C.sand, 4, 'opacity=".55"')).join('')
    + rr(300, 320, 140, 100, 8, C.sand) + lines(316, 340, [80, 60], 18, 8, C.coffee),
  sepatu: () => backdrop(C.surface2, C.deep) + shadow(250, 352, 190, 14)
    + pa('M60 318c-2-18 15-26 40-26h300c25 0 42 10 40 28-2 18-16 26-40 26H90c-20 0-28-12-30-28Z', C.white)       // sol
    + pa('M100 292c-5-37 20-57 60-64l80-14c50-9 85 1 105 24l13 14c14 14 28 10 40 10 18 0 22 18 20 30Z', C.sand)   // upper kanvas
    + pa('M100 292c-4-30 20-48 55-52l35-4 10 56Z', C.white, 'opacity=".9"')                                        // toe cap
    + pa('M190 240l110-18 12 40-97 30Z', C.deep)                                                                    // lidah & area tali
    + st('M205 262l80-14M210 276l82-14M200 250l78-14', C.white, 4)
    + ci(206, 262, 4, C.sand) + ci(211, 276, 4, C.sand) + ci(201, 250, 4, C.sand)
    + rr(372, 240, 34, 22, 6, C.coffee) + ci(392, 278, 9, C.primary)
    + ln(70, 330, 430, 330, C.line, 3),
  skincare: () => backdrop(C.sky, C.teal) + shadow(240, 410, 120, 14)
    + pa('M170 160h140v230a14 14 0 0 1-14 14H184a14 14 0 0 1-14-14Z', '#CFE0EA')
    + pa('M180 250h120v140a14 14 0 0 1-14 14H194a14 14 0 0 1-14-14Z', C.tealL, 'opacity=".9"')
    + rr(205, 110, 70, 55, 10, C.deep) + rr(228, 60, 24, 60, 8, C.coffee) + ci(240, 60, 16, '#222')
    + rr(195, 275, 90, 60, 6, C.white) + lines(205, 290, [70, 46], 14, 6, C.deep)
    + pa('M330 330c40-50 90-40 110-10-40 20-70 40-110 10Z', C.success) + st('M340 326c30-20 60-25 90-18', C.white, 3),
  sambal: () => backdrop(C.bgSubtle, C.sand) + shadow(240, 400, 110, 14)
    + rr(150, 150, 180, 240, 26, '#C8362B') + rr(162, 120, 156, 44, 10, C.coffee) + rr(170, 108, 140, 14, 7, C.coffeeL)
    + rr(170, 200, 140, 110, 10, C.sand) + tx(240, 250, 'SAMBAL', 26, C.coffee, 'font-weight="800" text-anchor="middle" letter-spacing="2"') + tx(240, 282, 'bawang · pedas', 15, C.coffeeL, 'text-anchor="middle"')
    + pa('M60 420c10-70 40-110 90-130 10 40-10 90-50 120-15 12-30 15-40 10Z', '#D8402F') + st('M150 290c-10-20-25-30-40-32', C.success, 7)
    + pa('M430 430c-10-70-40-110-90-130-10 40 10 90 50 120 15 12 30 15 40 10Z', '#C8362B') + st('M340 300c10-20 25-30 40-32', C.success, 7),
}
for (const [k, f] of Object.entries(samples)) out(`samples/product-${k}.svg`, svg(480, 480, f()))

/* ============================ 3. SAMPEL WEBSITE ============================ */
function siteMock({ a, b, c, hero, name, w = 960, h = 540 }) {
  const body = [
    rr(0, 0, w, h, 0, C.bgSubtle),
    rr(40, 30, w - 80, h - 60, 14, C.white), rr(40, 30, w - 80, 36, 14, C.surface2), rr(40, 52, w - 80, 14, 0, C.surface2),
    ci(64, 48, 5, C.danger), ci(82, 48, 5, C.warning), ci(100, 48, 5, C.success), rr(130, 40, 300, 16, 8, C.white),
    // navbar
    rr(70, 84, 28, 28, 7, c), rr(108, 92, 90, 12, 6, C.slate), rr(w - 330, 92, 50, 12, 6, C.muted), rr(w - 260, 92, 50, 12, 6, C.muted), rr(w - 190, 84, 110, 28, 8, c),
    // hero
    rr(70, 130, w - 140, 170, 12, a), hero,
    rr(96, 160, 300, 24, 8, c), rr(96, 196, 260, 14, 7, c, 'opacity=".55"'), rr(96, 218, 200, 14, 7, c, 'opacity=".55"'), rr(96, 252, 120, 30, 8, c),
    // 3 kartu
    ...[0, 1, 2].map((i) => `${rr(70 + i * ((w - 140 - 32) / 3 + 16), 320, (w - 140 - 32) / 3, 160, 10, C.white, `stroke="${C.line}"`)}${rr(86 + i * ((w - 140 - 32) / 3 + 16), 336, (w - 140 - 32) / 3 - 32, 60, 8, b)}${lines(86 + i * ((w - 140 - 32) / 3 + 16), 412, [120, 160], 18, 10, C.surface2)}`),
    tx(w - 70, h - 42, name, 13, C.muted, 'text-anchor="end"'),
  ].join('')
  return svg(w, h, body)
}
out('samples/site-cafe.svg', siteMock({ a: C.sand, b: C.sky, c: C.coffee, name: 'kopisenja.id', hero: `${ci(w2(), 215, 60, C.coffee)}${rr(690, 200, 70, 60, 8, C.sandD)}` }))
function w2() { return 760 }
out('samples/site-consultant.svg', siteMock({ a: C.navy2, b: C.soft, c: C.white, name: 'arkaconsulting.co.id', hero: `${rr(640, 160, 200, 110, 10, C.primaryL, 'opacity=".9"')}${lines(660, 180, [120, 160, 100], 22, 10, C.navy)}` }))
out('samples/site-workshop.svg', siteMock({ a: C.deep, b: C.sky, c: C.sand, name: 'bengkelmaju.id', hero: `${ci(760, 215, 48, C.teal)}${ci(760, 215, 22, C.deep)}${st('M700 250h120', C.sand, 8)}` }))
out('samples/site-clinic.svg', siteMock({ a: C.sky, b: '#F7E1E1', c: C.deep, name: 'klinikcerah.com', hero: `${ci(760, 215, 56, C.white)}${pa('M740 215h40M760 195v40', 'none', `stroke="${C.teal}" stroke-width="10" stroke-linecap="round"`)}` }))

/* =============================== 4. FITUR =============================== */
// 4:3 (480×360), latar transparan; gaya kartu UI datar + satu aksen.
const card = (x, y, w, h, r = 12, fill = C.white) => rr(x, y, w, h, r, fill, `stroke="${C.line}" stroke-width="2"`)
const chip = (x, y, w, label, fill = C.primary, fg = C.white) => `${rr(x, y, w, 28, 14, fill)}${tx(x + w / 2, y + 19, label, 13, fg, 'text-anchor="middle" font-weight="700"')}`
const blob = (x, y, w, h, fill) => rr(x, y, w, h, 24, fill)
const F = {
  'generate-photo': () => blob(40, 30, 400, 300, C.soft)
    + card(70, 60, 340, 240) + rr(90, 80, 300, 40, 8, C.surface2) + lines(104, 94, [180], 0, 12, C.muted) + spark(372, 100, 10, C.primary)
    + rr(90, 136, 140, 140, 10, C.sand) + `<g transform="translate(90 136) scale(.29)">${samples.kopi()}</g>`
    + rr(246, 136, 144, 60, 8, C.bgSubtle) + lines(258, 150, [70], 0, 8, C.muted) + lines(258, 168, [100], 0, 12, C.slate)
    + chip(246, 212, 144, 'Generate — 3 kredit') + rr(246, 250, 144, 26, 6, C.surface2) + lines(256, 259, [120], 0, 8, C.mutedL),
  'generate-video': () => blob(40, 30, 400, 300, C.sky)
    + card(60, 56, 360, 248, 12, C.navy) + rr(80, 76, 320, 170, 8, C.deep)
    + ci(240, 161, 34, C.white) + pa('M230 143l30 18-30 18Z', C.navy)
    + rr(80, 258, 320, 30, 6, C.navy3) + rr(84, 262, 120, 22, 4, C.primaryL) + rr(210, 262, 80, 22, 4, C.teal) + rr(296, 262, 100, 22, 4, C.sandD)
    + chip(320, 86, 70, '1080p', C.white, C.navy) + spark(100, 100, 9, C.primaryL),
  'edit-photo': () => blob(40, 30, 400, 300, C.sand)
    + card(60, 56, 360, 248) + rr(76, 72, 164, 216, 8, C.coffee) + rr(240, 72, 164, 216, 8, C.sky)
    + `<g transform="translate(76 72) scale(.342)">${samples.skincare()}</g>` + rr(76, 72, 164, 216, 8, '#000', 'opacity=".45"')
    + `<g transform="translate(240 72) scale(.342)">${samples.skincare()}</g>`
    + ln(240, 72, 240, 288, C.white, 4) + ci(240, 180, 18, C.white) + st('M232 180h16M236 174l-6 6 6 6M244 174l6 6-6 6', C.primary, 2.5)
    + chip(90, 86, 72, 'Sebelum', C.white, C.coffee) + chip(318, 86, 72, 'Sesudah', C.primary),
  'edit-video': () => blob(40, 30, 400, 300, C.soft)
    + card(60, 56, 360, 248, 12, C.navy) + rr(80, 76, 320, 130, 8, C.deep) + rr(110, 90, 100, 100, 6, C.teal) + rr(230, 90, 150, 100, 6, C.sandD)
    + rr(80, 220, 320, 26, 5, C.navy3) + rr(84, 224, 90, 18, 3, C.primaryL) + rr(186, 224, 110, 18, 3, C.teal) + rr(308, 224, 88, 18, 3, C.sandD)
    + rr(80, 254, 320, 26, 5, C.navy3) + rr(84, 258, 200, 18, 3, C.success, 'opacity=".8"')
    + ln(180, 214, 180, 284, C.white, 3) + ci(180, 212, 7, C.white)
    + st('M300 120l40 40M340 120l-40 40', C.white, 5) + ci(296, 164, 8, C.white, `stroke="${C.navy}" stroke-width="3"`) + ci(344, 164, 8, C.white, `stroke="${C.navy}" stroke-width="3"`),
  'brainstorming': () => blob(40, 30, 400, 300, '#FFF4D6')
    + pa('M240 70c-44 0-74 32-74 70 0 26 14 42 30 58 8 8 10 18 10 28h68c0-10 2-20 10-28 16-16 30-32 30-58 0-38-30-70-74-70Z', C.warning)
    + rr(210, 232, 60, 14, 6, C.coffee) + rr(216, 250, 48, 10, 5, C.coffeeL)
    + st('M240 150l-14-22M240 150l14-22M240 150v45', C.white, 6)
    + rr(60, 70, 110, 70, 8, C.sand) + lines(72, 86, [70, 86, 50], 16, 8, C.coffee)
    + rr(310, 90, 110, 70, 8, C.sky) + lines(322, 106, [60, 86, 70], 16, 8, C.deep)
    + rr(80, 200, 110, 70, 8, C.soft) + lines(92, 216, [80, 60, 86], 16, 8, C.primary)
    + spark(330, 240, 14, C.primary) + spark(360, 275, 8, C.primary),
  'templates': () => blob(40, 30, 400, 300, C.sky)
    + rr(70, 110, 120, 150, 10, C.sand) + rr(84, 124, 92, 70, 6, C.coffee) + lines(84, 206, [60, 80], 14, 8, C.coffee)
    + rr(200, 70, 90, 160, 10, C.navy) + rr(212, 82, 66, 100, 6, C.teal) + lines(212, 194, [50, 40], 12, 7, C.onDark)
    + rr(300, 110, 120, 90, 10, C.white, `stroke="${C.line}" stroke-width="2"`) + rr(312, 122, 96, 40, 6, C.primaryL) + lines(312, 170, [70, 50], 12, 7, C.slate)
    + rr(300, 215, 120, 60, 10, C.white, `stroke="${C.line}" stroke-width="2"`) + rr(312, 227, 40, 36, 6, C.sandD) + lines(360, 232, [50, 36], 12, 7, C.slate)
    + chip(80, 70, 70, 'Gratis', C.success) + chip(224, 240, 84, 'Premium', C.warning),
  'website-builder': () => blob(40, 30, 400, 300, C.soft)
    + card(60, 56, 360, 248) + rr(76, 72, 90, 216, 8, C.bgSubtle)
    + [0, 1, 2, 3].map((i) => rr(86, 84 + i * 36, 70, 26, 6, i === 0 ? C.primary : C.white, i === 0 ? '' : `stroke="${C.line}" stroke-width="2"`)).join('')
    + rr(180, 72, 224, 216, 8, C.white, `stroke="${C.line}" stroke-width="2" stroke-dasharray="6 6"`)
    + rr(192, 84, 200, 70, 6, C.deep) + rr(204, 100, 90, 10, 5, C.sand) + rr(204, 118, 60, 8, 4, C.sand, 'opacity=".7"')
    + rr(192, 164, 96, 50, 6, C.sky) + rr(296, 164, 96, 50, 6, C.sky) + lines(192, 224, [150, 180, 120], 14, 8, C.surface2)
    + st('M250 250l30-10-12 28-6-10Z', C.slate, 2.5) + chip(330, 258, 70, 'Terbit', C.success),
  'seo-tools': () => blob(40, 30, 400, 300, '#E8F3E8')
    + card(60, 56, 360, 248)
    + `<circle cx="150" cy="170" r="56" fill="none" stroke="${C.surface2}" stroke-width="14"/><circle cx="150" cy="170" r="56" fill="none" stroke="${C.success}" stroke-width="14" stroke-linecap="round" stroke-dasharray="270 352" transform="rotate(-90 150 170)"/>`
    + tx(150, 182, '78', 36, C.slate, 'text-anchor="middle" font-weight="800"')
    + [[C.danger, 'Meta description kosong'], [C.warning, '3 gambar tanpa alt'], [C.success, 'Struktur heading rapi']].map(([c, s], i) => `${rr(230, 100 + i * 50, 170, 38, 8, C.bgSubtle)}${ci(246, 119 + i * 50, 6, c)}${lines(260, 115 + i * 50, [110 - i * 10], 0, 8, C.slate)}`).join('')
    + chip(230, 254, 110, 'Skor Krafio', C.white, C.muted),
  'ai-assistant': () => blob(40, 30, 400, 300, C.soft)
    + rr(70, 80, 220, 54, 16, C.white, `stroke="${C.line}" stroke-width="2"`) + lines(88, 98, [150, 110], 16, 9, C.slate)
    + rr(190, 150, 220, 70, 16, C.primary) + lines(208, 168, [170, 130, 150], 16, 9, C.white)
    + rr(70, 240, 180, 54, 16, C.white, `stroke="${C.line}" stroke-width="2"`) + lines(88, 258, [100, 130], 16, 9, C.slate)
    + ci(400, 92, 28, C.navy) + rr(386, 82, 28, 18, 6, C.primaryL) + ci(394, 91, 3, C.navy) + ci(406, 91, 3, C.navy) + ln(400, 56, 400, 64, C.navy, 4) + ci(400, 54, 4, C.primaryL)
    + ci(40, 268, 22, C.sandD) + ci(40, 262, 8, C.coffee) + pa('M24 290c4-12 28-12 32 0Z', C.coffee),
  'video-clipper': () => blob(40, 30, 400, 300, C.sky)
    + rr(60, 70, 360, 44, 8, C.navy) + rr(64, 74, 352, 36, 6, C.deep)
    + [90, 190, 290].map((x) => rr(x, 74, 60, 36, 4, C.primaryL, 'opacity=".9"')).join('')
    + st('M120 130v30M220 130v30M320 130v30', C.muted, 3, 'stroke-dasharray="4 5"')
    + [0, 1, 2].map((i) => `${rr(90 + i * 110, 170, 90, 150, 10, C.navy)}${rr(100 + i * 110, 180, 70, 100, 6, [C.teal, C.sandD, C.primaryL][i])}${ci(135 + i * 110, 230, 14, C.white)}${pa(`M${131 + i * 110} 223l10 7-10 7Z`, C.navy)}${lines(100 + i * 110, 292, [50, 36], 10, 6, C.onDark)}`).join('')
    + chip(300, 128, 110, '9:16 · 30 dtk', C.white, C.navy),
  'social-integration': () => blob(40, 30, 400, 300, C.sand)
    + rr(170, 50, 140, 260, 22, C.navy) + rr(182, 62, 116, 236, 14, C.white) + rr(194, 80, 92, 92, 8, C.sky) + `<g transform="translate(194 80) scale(.19)">${samples.sepatu()}</g>`
    + lines(194, 184, [70, 90, 60], 14, 7, C.slate) + rr(194, 240, 92, 26, 13, C.primary)
    + [[80, 90, C.primaryL], [70, 200, C.teal], [390, 110, C.success], [400, 230, C.warning]].map(([x, y, c]) => `${ci(x, y, 24, c)}${st(`M${x < 240 ? x + 24 : x - 24} ${y}L${x < 240 ? 170 : 310} ${y < 180 ? 150 : 220}`, C.coffee, 3, 'stroke-dasharray="5 6"')}`).join('')
    + st('M72 86l8 8 8-8M62 206h16M70 198v16', C.white, 3) + st('M382 110h16M390 102v16M392 230h16', C.white, 3),
}
for (const [k, f] of Object.entries(F)) out(`features/${k}.svg`, svg(480, 360, f()))

/* ============================= 5. TEMPLATE ============================= */
const RATIO = { '1:1': [600, 600], '4:5': [600, 750], '9:16': [540, 960], '16:9': [960, 540], '4:3': [800, 600] }
const palettes = [['#F4E7D3', '#C7A27C', '#3E2C23'], ['#E3EEF5', '#7FA7C2', '#1F3A4D'], ['#F7E1E1', '#E08A8A', '#5A1F2B'], ['#E8F3E8', '#7DB38A', '#1E4A2E'], ['#FFF4D6', '#F2B84B', '#4A3200'], ['#ECE8F7', '#9C8FD1', '#2D2557'], ['#F1F1EF', '#A3A39B', '#262624'], ['#DDF2F0', '#5FB3A9', '#0F3D38']]
const T = [
  ['kopi-susu-gula-aren', 'photo', '1:1', 'kopi', 'KOPI SUSU'], ['roti-artisan-pagi', 'photo', '4:5', 'roti', 'FRESH DAILY'], ['batik-modern-flatlay', 'photo', '4:5', 'batik', 'KOLEKSI BARU'],
  ['sepatu-kanvas-lokal', 'photo', '1:1', 'sepatu', 'LOKAL'], ['skincare-botol-kaca', 'photo', '1:1', 'skincare', 'GLOW'], ['sambal-kemasan', 'photo', '1:1', 'sambal', 'PEDAS'],
  ['promo-flash-sale-15-detik', 'video', '9:16', null, 'FLASH SALE'], ['unboxing-produk', 'video', '9:16', null, 'UNBOXING'], ['testimoni-pelanggan', 'video', '9:16', null, 'TESTIMONI'],
  ['tutorial-30-detik', 'video', '16:9', null, 'TUTORIAL'], ['profil-kafe-resto', 'website', '16:9', 'site-cafe'], ['profil-jasa-konsultan', 'website', '16:9', 'site-consultant'],
  ['profil-bengkel-otomotif', 'website', '16:9', 'site-workshop'], ['profil-klinik-kecantikan', 'website', '16:9', 'site-clinic'], ['carousel-tips-5-slide', 'social', '4:5', null, '5 TIPS'],
  ['pengumuman-jam-buka', 'social', '1:1', null, 'JAM BUKA'], ['quote-harian', 'social', '1:1', null, '“'], ['story-giveaway', 'social', '9:16', null, 'GIVEAWAY'],
  ['kartu-nama-minimalis', 'business', '16:9', null, 'NAMA'], ['invoice-sederhana', 'business', '4:3', null, 'INVOICE'], ['katalog-produk-a4', 'business', '4:3', null, 'KATALOG'],
  ['menu-digital', 'business', '9:16', null, 'MENU'], ['banner-marketplace', 'photo', '16:9', 'sepatu', 'DISKON 30%'], ['poster-event-komunitas', 'social', '4:5', null, 'EVENT'],
]
function templateArt(i, slug, type, ratio, sample, label) {
  const [w, h] = RATIO[ratio]
  const [a, b, c] = palettes[i % palettes.length]
  const parts = [rr(0, 0, w, h, 0, a)]
  const fs = Math.round(Math.min(w, h) * 0.11)
  if (type === 'photo') {
    const s = Math.min(w, h) * (h > w ? 0.78 : 0.74)
    const x = (w - s) / 2, y = h > w ? h * 0.1 : h * 0.07
    parts.push(`<g transform="translate(${x} ${y}) scale(${s / 480})"><clipPath id="c"><rect width="480" height="480" rx="24"/></clipPath><g clip-path="url(#c)">${samples[sample]()}</g></g>`)
    if (sample === 'sepatu' && ratio === '16:9') { parts.length = 1; parts.push(rr(0, 0, w, h, 0, b), `<g transform="translate(${w * 0.55} ${h * 0.05}) scale(${(h * 0.9) / 480})"><clipPath id="c"><circle cx="240" cy="240" r="240"/></clipPath><g clip-path="url(#c)">${samples.sepatu()}</g></g>`, tx(60, h * 0.45, label, fs * 1.4, c, 'font-weight="800"'), rr(60, h * 0.55, 220, 44, 22, c), tx(170, h * 0.55 + 30, 'Belanja sekarang', 20, a, 'text-anchor="middle" font-weight="700"')) }
    else parts.push(tx(w / 2, h > w ? h * 0.93 : h * 0.95 - 4, label, fs * 0.7, c, 'text-anchor="middle" font-weight="800" letter-spacing="3"'))
  } else if (type === 'video') {
    parts.push(rr(0, 0, w, h, 0, c), rr(w * 0.08, h * 0.08, w * 0.84, h * 0.84, 24, b, 'opacity=".9"'))
    if (ratio === '9:16') parts.push(rr(w * 0.15, h * 0.2, w * 0.7, h * 0.45, 20, a), `<g transform="translate(${w * 0.15} ${h * 0.2}) scale(${(w * 0.7) / 480})"><clipPath id="c"><rect width="480" height="480" rx="28"/></clipPath><g clip-path="url(#c)">${samples[['kopi', 'skincare', 'batik'][i % 3]]()}</g></g>`, tx(w / 2, h * 0.76, label, fs * 1.1, a, 'text-anchor="middle" font-weight="800"'), rr(w * 0.3, h * 0.8, w * 0.4, 46, 23, a), tx(w / 2, h * 0.8 + 31, 'Tonton', 22, c, 'text-anchor="middle" font-weight="700"'))
    else parts.push(rr(w * 0.12, h * 0.16, w * 0.46, h * 0.6, 16, a), `<g transform="translate(${w * 0.12} ${h * 0.16}) scale(${(h * 0.6) / 480})"><clipPath id="c"><rect width="480" height="480" rx="24"/></clipPath><g clip-path="url(#c)">${samples.roti()}</g></g>`, tx(w * 0.62, h * 0.4, label, fs * 1.1, a, 'font-weight="800"'), lines(w * 0.62, h * 0.5, [200, 160, 180], 26, 14, a))
    parts.push(ci(w * 0.5, h * (ratio === '9:16' ? 0.42 : 0.46), 40, c, 'opacity=".85"'), pa(`M${w * 0.5 - 12} ${h * (ratio === '9:16' ? 0.42 : 0.46) - 20}l34 20-34 20Z`, a), rr(w * 0.08, h * 0.92 - 8, w * 0.84, 8, 4, a, 'opacity=".5"'), rr(w * 0.08, h * 0.92 - 8, w * 0.3, 8, 4, a))
  } else if (type === 'website') {
    parts.push(`<g transform="scale(${w / 960})">${siteMockBody(i)}</g>`)
  } else if (type === 'social') {
    parts.push(rr(0, 0, w, h, 0, b))
    if (slug === 'quote-harian') parts.push(tx(w * 0.12, h * 0.42, '“', fs * 3.5, c, 'font-weight="800" opacity=".5"'), lines(w * 0.12, h * 0.42, [w * 0.7, w * 0.62, w * 0.5], fs * 0.7, fs * 0.42, c), lines(w * 0.12, h * 0.8, [w * 0.3], 0, 12, c, 6))
    else if (slug === 'pengumuman-jam-buka') parts.push(rr(w * 0.1, h * 0.1, w * 0.8, h * 0.8, 24, a), tx(w / 2, h * 0.32, label, fs, c, 'text-anchor="middle" font-weight="800" letter-spacing="2"'), ...['Sen – Jum  08.00 – 20.00', 'Sabtu       09.00 – 22.00', 'Minggu     libur'].map((s, k) => tx(w / 2, h * 0.46 + k * fs * 0.9, s, fs * 0.42, c, 'text-anchor="middle" font-weight="600"')), ci(w / 2, h * 0.82, 26, c), st(`M${w / 2} ${h * 0.82 - 12}v12h9`, a, 4))
    else if (slug === 'story-giveaway') parts.push(rr(w * 0.1, h * 0.12, w * 0.8, h * 0.5, 28, a), `<g transform="translate(${w * 0.1} ${h * 0.12}) scale(${(w * 0.8) / 480})"><clipPath id="c"><rect width="480" height="560" rx="34"/></clipPath><g clip-path="url(#c)">${samples.skincare()}</g></g>`, tx(w / 2, h * 0.72, label, fs * 1.2, c, 'text-anchor="middle" font-weight="800" letter-spacing="4"'), tx(w / 2, h * 0.78, '3 pemenang · tag 2 teman', fs * 0.45, c, 'text-anchor="middle"'), rr(w * 0.25, h * 0.84, w * 0.5, 50, 25, c), tx(w / 2, h * 0.84 + 33, 'Ikut sekarang', 22, a, 'text-anchor="middle" font-weight="700"'))
    else if (slug === 'poster-event-komunitas') parts.push(rr(0, 0, w, h * 0.55, 0, c), ci(w * 0.78, h * 0.2, w * 0.18, a, 'opacity=".9"'), tx(w * 0.08, h * 0.38, label, fs * 1.5, a, 'font-weight="800"'), tx(w * 0.08, h * 0.47, 'Temu Kreator Bandung', fs * 0.55, a, 'font-weight="600"'), lines(w * 0.08, h * 0.63, [w * 0.6, w * 0.5], fs * 0.6, fs * 0.36, c), rr(w * 0.08, h * 0.82, w * 0.4, 46, 23, c), tx(w * 0.28, h * 0.82 + 31, 'Daftar gratis', 20, a, 'text-anchor="middle" font-weight="700"'))
    else parts.push(rr(w * 0.08, h * 0.08, w * 0.84, h * 0.84, 24, a), tx(w * 0.14, h * 0.26, label, fs * 1.4, c, 'font-weight="800"'), tx(w * 0.14, h * 0.34, 'foto produk lebih laku', fs * 0.5, c), ...[0, 1, 2].map((k) => `${ci(w * 0.17, h * (0.48 + k * 0.13), 18, c)}${tx(w * 0.17, h * (0.48 + k * 0.13) + 6, String(k + 1), 18, a, 'text-anchor="middle" font-weight="800"')}${lines(w * 0.24, h * (0.48 + k * 0.13) - 7, [w * 0.55], 0, 14, c)}`), tx(w * 0.86, h * 0.9, '1 / 5', fs * 0.45, c, 'text-anchor="end" font-weight="600"'))
  } else if (type === 'business') {
    parts.push(rr(0, 0, w, h, 0, C.surface2))
    if (slug === 'kartu-nama-minimalis') parts.push(rr(w * 0.08, h * 0.12, w * 0.84, h * 0.76, 16, a, `stroke="${C.line}" stroke-width="2"`), rr(w * 0.08, h * 0.12, w * 0.02, h * 0.76, 0, c), tx(w * 0.16, h * 0.42, 'Nadia Pramesti', fs * 0.9, c, 'font-weight="800"'), tx(w * 0.16, h * 0.52, 'Pemilik · Kopi Senja', fs * 0.45, b, 'font-weight="600"'), tx(w * 0.16, h * 0.72, '0812-3456-7890 · halo@kopisenja.id', fs * 0.36, c), ci(w * 0.82, h * 0.32, h * 0.12, b), `<g transform="translate(${w * 0.82 - h * 0.08} ${h * 0.24}) scale(${h * 0.16 / 64})">${markBody(a)}</g>`)
    else if (slug === 'invoice-sederhana') parts.push(rr(w * 0.1, h * 0.06, w * 0.8, h * 0.88, 12, a), tx(w * 0.15, h * 0.2, label, fs * 1.1, c, 'font-weight="800"'), tx(w * 0.85, h * 0.2, 'INV-2026-114', fs * 0.4, c, 'text-anchor="end" font-weight="600"'), ...[0, 1, 2].map((k) => `${lines(w * 0.15, h * (0.32 + k * 0.1), [w * 0.4], 0, 12, c)}${tx(w * 0.85, h * (0.32 + k * 0.1) + 10, ['Rp1.500.000', 'Rp2.250.000', 'Rp750.000'][k], fs * 0.4, c, 'text-anchor="end"')}`), ln(w * 0.15, h * 0.65, w * 0.85, h * 0.65, c, 2), tx(w * 0.85, h * 0.76, 'Total  Rp4.500.000', fs * 0.6, c, 'text-anchor="end" font-weight="800"'), rr(w * 0.15, h * 0.82, w * 0.3, 8, 4, b))
    else if (slug === 'katalog-produk-a4') parts.push(rr(w * 0.08, h * 0.06, w * 0.84, h * 0.88, 12, a), tx(w * 0.13, h * 0.2, label, fs * 1.1, c, 'font-weight="800"'), ...[0, 1, 2].map((k) => `<g transform="translate(${w * (0.13 + k * 0.27)} ${h * 0.3}) scale(${(w * 0.22) / 480})"><clipPath id="c${k}"><rect width="480" height="480" rx="30"/></clipPath><g clip-path="url(#c${k})">${samples[['kopi', 'roti', 'sambal'][k]]()}</g></g>${lines(w * (0.13 + k * 0.27), h * 0.3 + w * 0.22 + 20, [w * 0.16, w * 0.1], 22, 12, c)}`))
    else parts.push(rr(w * 0.08, h * 0.05, w * 0.84, h * 0.9, 24, c), tx(w / 2, h * 0.14, label, fs * 1.3, a, 'text-anchor="middle" font-weight="800" letter-spacing="6"'), ...['Kopi susu gula aren', 'Es teh leci', 'Roti bakar keju', 'Nasi goreng kampung', 'Pisang goreng'].map((s, k) => `${tx(w * 0.16, h * (0.26 + k * 0.11), s, fs * 0.36, a, 'font-weight="600"')}${tx(w * 0.84, h * (0.26 + k * 0.11), ['Rp22.000', 'Rp18.000', 'Rp20.000', 'Rp28.000', 'Rp15.000'][k], fs * 0.36, b, 'text-anchor="end" font-weight="700"')}${ln(w * 0.16, h * (0.26 + k * 0.11) + 16, w * 0.84, h * (0.26 + k * 0.11) + 16, a, 1, 'opacity=".3"')}`), `<g transform="translate(${w * 0.44} ${h * 0.84}) scale(${(w * 0.12) / 64})">${markBody(a)}</g>`)
  }
  return svg(w, h, parts.join(''))
}
function siteMockBody(i) {
  const m = [['site-cafe', C.sand, C.sky, C.coffee], ['site-consultant', C.navy2, C.soft, C.white], ['site-workshop', C.deep, C.sky, C.sand], ['site-clinic', C.sky, '#F7E1E1', C.deep]][i % 4]
  const s = siteMock({ a: m[1], b: m[2], c: m[3], name: '', hero: '' })
  return s.replace(/^<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '')
}
T.forEach(([slug, type, ratio, sample, label], i) => out(`templates/${slug}.svg`, templateArt(i, slug, type, ratio, sample, label ?? '')))

/* =============================== 6. AUTH =============================== */
// Tone gelap (panel kiri auth), 4:3 480×360, latar transparan.
const A = {
  a: () => rr(60, 60, 360, 240, 16, C.navy2, `stroke="${C.navy3}" stroke-width="2"`) + rr(84, 84, 312, 44, 8, C.navy3) + lines(100, 100, [200], 0, 12, C.onDarkMuted) + spark(372, 106, 10, C.primaryL)
    + rr(84, 144, 150, 56, 8, C.navy3) + lines(98, 158, [60], 0, 8, C.onDarkMuted) + tx(98, 190, '8 kredit', 18, C.onDark, 'font-weight="700"')
    + rr(246, 144, 150, 56, 8, C.navy3) + lines(260, 158, [70], 0, 8, C.onDarkMuted) + tx(260, 190, 'Sisa 47', 18, C.onDark, 'font-weight="700"')
    + rr(84, 220, 312, 48, 10, C.primaryL) + tx(240, 251, 'Generate — 8 kredit', 18, C.navy, 'text-anchor="middle" font-weight="800"'),
  b: () => rr(50, 90, 180, 180, 14, C.navy2, `stroke="${C.info}" stroke-width="3"`) + rr(50, 90, 180, 6, 3, C.info) + lines(70, 116, [90], 0, 8, C.onDarkMuted) + tx(70, 164, 'Rp250.000', 24, C.onDark, 'font-weight="800"') + lines(70, 182, [120, 90], 14, 7, C.onDarkMuted) + chip(70, 226, 120, 'Top-up', C.info)
    + rr(250, 90, 180, 180, 14, C.navy2, `stroke="${C.success}" stroke-width="3"`) + rr(250, 90, 180, 6, 3, C.success) + lines(270, 116, [90], 0, 8, C.onDarkMuted) + tx(270, 164, 'Rp120.000', 24, C.onDark, 'font-weight="800"') + lines(270, 182, [120, 90], 14, 7, C.onDarkMuted) + chip(270, 226, 130, 'Bisa ditarik', C.success),
  c: () => pa('M240 50l110 40v80c0 70-48 120-110 140-62-20-110-70-110-140V90Z', C.navy2, `stroke="${C.primaryL}" stroke-width="4"`) + st('M200 170l28 28 54-60', C.primaryL, 10)
    + [0, 1, 2, 3, 4, 5].map((k) => `${rr(90 + k * 52, 300, 42, 48, 8, C.navy3, `stroke="${k < 4 ? C.primaryL : C.navy3}" stroke-width="2"`)}${k < 4 ? tx(111 + k * 52, 333, ['4', '8', '2', '9'][k], 24, C.onDark, 'text-anchor="middle" font-weight="700"') : ''}`).join(''),
}
for (const [k, f] of Object.entries(A)) out(`auth/${k}.svg`, svg(480, 360, f()))

/* ============================== 7. STATE ============================== */
// 240×180, transparan, warna mid-tone agar aman di light & dark.
const S = {
  empty: () => rr(50, 60, 140, 96, 14, C.primaryL, 'opacity=".22"') + pa('M50 74c0-8 6-14 14-14h38l14 14h60c8 0 14 6 14 14v8H50Z', C.primaryL, 'opacity=".55"') + rr(50, 96, 140, 60, 12, C.primaryL, 'opacity=".35"') + spark(176, 46, 14, C.primaryL) + spark(200, 76, 8, C.primaryL),
  filter: () => pa('M60 44h120l-44 54v44l-32 18V98Z', C.primaryL, 'opacity=".45"') + st('M60 44h120l-44 54v44l-32 18V98Z', C.primaryL, 4) + lines(140, 120, [50, 36, 44], 14, 7, C.mutedL),
  locked: () => rr(60, 50, 120, 100, 12, C.primaryL, 'opacity=".2"') + lines(76, 66, [60, 88, 50], 14, 7, C.mutedL) + rr(130, 84, 56, 50, 10, C.primaryL) + pa('M142 84v-10a16 16 0 0 1 32 0v10h-8v-10a8 8 0 0 0-16 0v10Z', C.primaryL) + ci(158, 108, 6, C.navy) + rr(155, 108, 6, 12, 3, C.navy),
  error: () => pa('M120 34l80 136H40Z', C.danger, 'opacity=".2"') + st('M120 34l80 136H40Z', C.danger, 5) + rr(114, 80, 12, 44, 6, C.danger) + ci(120, 142, 7, C.danger),
  clock: () => ci(120, 92, 62, C.primaryL, 'opacity=".2"') + `<circle cx="120" cy="92" r="62" fill="none" stroke="${C.primaryL}" stroke-width="6"/>` + st('M120 58v36l24 16', C.primaryL, 7) + ci(120, 92, 6, C.primaryL) + rr(100, 164, 40, 8, 4, C.mutedL),
  notfound: () => rr(50, 36, 100, 120, 10, C.primaryL, 'opacity=".2"') + lines(66, 54, [60, 70, 44, 66], 16, 7, C.mutedL) + `<circle cx="152" cy="108" r="34" fill="${C.primaryL}" fill-opacity=".25" stroke="${C.primaryL}" stroke-width="7"/>` + ln(178, 134, 206, 162, C.primaryL, 10) + tx(152, 118, '?', 30, C.primaryL, 'text-anchor="middle" font-weight="800"'),
  offline: () => pa('M70 130a30 30 0 0 1 4-60 44 44 0 0 1 84-10 34 34 0 0 1 12 66Z', C.primaryL, 'opacity=".3"') + st('M70 130a30 30 0 0 1 4-60 44 44 0 0 1 84-10 34 34 0 0 1 12 66', C.primaryL, 5) + ln(60, 160, 184, 40, C.danger, 7),
  maintenance: () => pa('M160 46a30 30 0 0 0-40 36L58 144a12 12 0 0 0 17 17l62-62a30 30 0 0 0 36-40l-18 18-14-4-4-14Z', C.primaryL, 'opacity=".35"') + st('M160 46a30 30 0 0 0-40 36L58 144a12 12 0 0 0 17 17l62-62a30 30 0 0 0 36-40l-18 18-14-4-4-14Z', C.primaryL, 4) + spark(190, 140, 10, C.primaryL),
}
for (const [k, f] of Object.entries(S)) out(`states/${k}.svg`, svg(240, 180, f()))

/* ============================ 8. HALAMAN INFO ============================ */
const P = {
  about: () => blob(40, 30, 400, 300, C.sand) + rr(80, 220, 320, 16, 8, C.coffee) + rr(110, 236, 14, 60, 4, C.coffee) + rr(356, 236, 14, 60, 4, C.coffee)
    + rr(130, 110, 170, 110, 12, C.navy) + rr(140, 120, 150, 90, 8, C.deep) + `<g transform="translate(150 128) scale(.16)">${samples.kopi()}</g>` + rr(240, 130, 40, 70, 6, C.navy3) + rr(205, 220, 20, 8, 3, C.navy)
    + pa('M330 220c-10-40 10-70 40-70 20 0 30 20 24 40-6 20-30 20-20 30Z', C.success) + rr(340, 190, 40, 30, 6, C.coffeeL)
    + rr(90, 170, 30, 50, 6, C.sky) + ci(105, 160, 14, C.sky) + spark(100, 90, 14, C.primary),
  affiliate: () => blob(40, 30, 400, 300, C.soft) + `<path d="M120 190a40 40 0 0 1 0-80h60a40 40 0 0 1 40 40" fill="none" stroke="${C.primary}" stroke-width="18" stroke-linecap="round"/><path d="M300 150a40 40 0 0 1 0 80h-60a40 40 0 0 1-40-40" fill="none" stroke="${C.primary}" stroke-width="18" stroke-linecap="round"/>`
    + ci(340, 270, 34, C.success) + ci(380, 240, 26, C.success) + tx(340, 280, 'Rp', 22, C.white, 'text-anchor="middle" font-weight="800"') + st('M330 262l20 20M350 262l-20 20', C.white, 0.01)
    + rr(60, 246, 200, 44, 22, C.white, `stroke="${C.line}" stroke-width="2"`) + tx(80, 274, 'krafio.id/r/nadia', 16, C.slate, 'font-weight="600"') + st('M232 262l8 6-8 6', C.primary, 3),
  contact: () => blob(40, 30, 400, 300, C.sky) + rr(60, 70, 230, 80, 20, C.white, `stroke="${C.line}" stroke-width="2"`) + pa('M90 150l-10 30 40-30Z', C.white) + lines(84, 92, [160, 120], 18, 10, C.slate)
    + rr(190, 180, 230, 80, 20, C.primary) + pa('M390 260l10 30-40-30Z', C.primary) + lines(214, 202, [170, 130], 18, 10, C.white)
    + ci(120, 280, 26, C.navy) + st('M100 282a20 20 0 0 1 40 0', C.primaryL, 6) + rr(96, 282, 10, 14, 4, C.primaryL) + rr(134, 282, 10, 14, 4, C.primaryL),
  pricing: () => blob(40, 30, 400, 300, '#E8F3E8') + rr(70, 120, 110, 170, 12, C.white, `stroke="${C.line}" stroke-width="2"`) + lines(86, 140, [50, 80, 60], 18, 8, C.mutedL) + chip(86, 250, 78, 'Gratis', C.surface2, C.muted)
    + rr(185, 80, 110, 210, 12, C.white, `stroke="${C.primary}" stroke-width="3"`) + chip(200, 66, 80, 'Pro', C.primary) + lines(201, 106, [50, 80, 60, 70], 18, 8, C.mutedL) + tx(201, 200, 'Rp149rb', 20, C.slate, 'font-weight="800"') + chip(201, 250, 78, 'Pilih', C.primary)
    + rr(300, 120, 110, 170, 12, C.white, `stroke="${C.line}" stroke-width="2"`) + lines(316, 140, [50, 80, 60], 18, 8, C.mutedL) + chip(316, 250, 78, 'Bisnis', C.surface2, C.muted),
  templates: () => F.templates(),
  faq: () => blob(40, 30, 400, 300, C.soft) + ci(160, 150, 90, C.primary) + tx(160, 190, '?', 110, C.white, 'text-anchor="middle" font-weight="800"')
    + [0, 1, 2].map((k) => `${rr(270, 90 + k * 64, 150, 48, 10, C.white, `stroke="${C.line}" stroke-width="2"`)}${lines(284, 108 + k * 64, [90 + k * 10], 0, 10, C.slate)}${st(`M396 ${110 + k * 64}l6 6 6-6`, C.muted, 3)}`).join(''),
}
for (const [k, f] of Object.entries(P)) out(`pages/${k}.svg`, svg(480, 360, f()))

console.log(`${files.length} berkas SVG ditulis ke ${OUT}`)
