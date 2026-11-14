#!/usr/bin/env node
// Uji UI Krafio di Chrome headless lewat Chrome DevTools Protocol (tanpa dependensi: Node ≥ 22 punya WebSocket).
//
//   node browser.mjs <skenario.json> [folder-output]
//
// Skenario:
// {
//   "as": "qa" | "admin" | "demo" | "guest", // akun login (qa: qa-account.sh, admin: admin-account.sh --mfa-off)
//   "lang": "id" | "en",                     // bahasa UI (localStorage krafio.lang)
//   "viewport": "desktop" | "mobile",        // 1280×900 atau 390×844
//   "steps": [
//     { "goto": "/app/seo" },
//     { "waitFor": "Isi data halaman", "timeout": 8000 },     // tunggu teks muncul
//     { "fill": { "label": "Focus keyphrase", "value": "kopi bali" } },
//     { "fill": { "label": "Konten", "file": "artikel.html" } }, // isi dari file (relatif ke skenario)
//     { "fill": { "selector": "input[type=search]", "value": "x" } },
//     { "select": { "label": "Jenis konten", "value": "cornerstone" } },
//     { "click": "Analisis SEO" },                             // tombol/link/tab berdasarkan teks
//     { "click": { "selector": "#id" } },
//     { "upload": { "selector": "input[type=file]", "file": "foto.jpg" } },  // unggah (input tersembunyi pun bisa)
//     { "expectText": "Skor SEO" }, { "expectNoText": "Terjadi kesalahan" },
//     { "eval": "document.title", "expect": "Krafio" },
//     { "wait": 1000 }, { "shot": "hasil" }
//   ]
// }
// Hasil: report.json + PNG di folder output (default ~/.krafio-tools/run/browser-<waktu>).
// Error JS (exception & console.error) serta respons /api ≥ 500 dan 4xx tak terduga dicatat otomatis.
// Exit code 0 bila semua langkah lolos dan tidak ada error JS / respons 5xx.
import { spawn } from 'node:child_process'
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { homedir, tmpdir } from 'node:os'
import { dirname, join, resolve } from 'node:path'

const [, , scenarioPath, outArg] = process.argv
if (!scenarioPath) {
  console.error('Pakai: node browser.mjs <skenario.json> [folder-output]')
  process.exit(64)
}
const scenario = JSON.parse(readFileSync(scenarioPath, 'utf8'))
const base = process.env.KRAFIO_WEB || 'http://localhost:5173'
const out = resolve(outArg || join(homedir(), '.krafio-tools/run', `browser-${new Date().toISOString().replace(/[:.]/g, '-')}`))
mkdirSync(out, { recursive: true })
const CHROME = process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
if (!existsSync(CHROME)) {
  console.error('Chrome tidak ditemukan. Set CHROME_PATH.')
  process.exit(69)
}
const sleep = (ms) => new Promise((r) => setTimeout(r, ms))
const report = { scenario: scenarioPath, base, out, steps: [], jsErrors: [], badResponses: [], screenshots: [] }

// ---------- Chrome ----------
const port = 9300 + Math.floor(Math.random() * 600)
const profile = mkdtempSync(join(tmpdir(), 'krafio-chrome-'))
const chrome = spawn(CHROME, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--disable-extensions',
  `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, 'about:blank'], { stdio: 'ignore' })
const finish = (code) => {
  try { chrome.kill('SIGKILL') } catch { /* sudah mati */ }
  try { rmSync(profile, { recursive: true, force: true }) } catch { /* abaikan */ }
  writeFileSync(join(out, 'report.json'), JSON.stringify(report, null, 2))
  const failed = report.steps.filter((s) => !s.ok)
  console.log(`\n${failed.length ? '❌' : '✅'} ${report.steps.length - failed.length}/${report.steps.length} langkah lolos · error JS: ${report.jsErrors.length} · respons API bermasalah: ${report.badResponses.length}`)
  for (const f of failed) console.log(`   gagal: ${JSON.stringify(f.step)} → ${f.error}`)
  for (const e of report.jsErrors) console.log(`   JS: ${e}`)
  for (const b of report.badResponses) console.log(`   API: ${b.status} ${b.method} ${b.url}`)
  console.log(`   laporan: ${join(out, 'report.json')}`)
  process.exit(code ?? (failed.length || report.jsErrors.length || report.badResponses.some((b) => b.status >= 500) ? 1 : 0))
}
const guard = setTimeout(() => { report.steps.push({ step: 'global', ok: false, error: 'TIMEOUT 180 dtk' }); finish(2) }, (scenario.timeoutSec || 180) * 1000)

let target
for (let i = 0; i < 60 && !target; i++) {
  try { target = await (await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' })).json() } catch { await sleep(250) }
}
if (!target) { report.steps.push({ step: 'launch', ok: false, error: 'Chrome tidak merespons' }); finish(2) }
const ws = new WebSocket(target.webSocketDebuggerUrl)
await new Promise((r, j) => { ws.addEventListener('open', r); ws.addEventListener('error', j) })
let seq = 0
const pending = new Map()
const reqs = new Map()
const expected4xx = new Set(scenario.allowStatus || [401, 403, 404, 409, 422, 429])
ws.addEventListener('message', (e) => {
  const m = JSON.parse(e.data)
  if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); return }
  if (m.method === 'Runtime.exceptionThrown') report.jsErrors.push('exception: ' + (m.params.exceptionDetails.exception?.description || m.params.exceptionDetails.text).split('\n')[0].slice(0, 300))
  if (m.method === 'Runtime.consoleAPICalled' && m.params.type === 'error') report.jsErrors.push('console.error: ' + m.params.args.map((a) => a.value ?? a.description ?? '').join(' ').slice(0, 300))
  if (m.method === 'Network.requestWillBeSent') reqs.set(m.params.requestId, m.params.request.method)
  if (m.method === 'Network.responseReceived') {
    const { url, status } = m.params.response
    if (url.includes('/api/') && (status >= 500 || (status >= 400 && !expected4xx.has(status)))) report.badResponses.push({ status, method: reqs.get(m.params.requestId), url: url.replace(base, '') })
  }
  if (m.method === 'Network.loadingFailed' && !m.params.canceled && m.params.type !== 'Other') report.jsErrors.push(`request gagal (${m.params.type}): ${m.params.errorText}`)
})
const send = (method, params = {}) => new Promise((r) => { const id = ++seq; pending.set(id, r); ws.send(JSON.stringify({ id, method, params })) })
const evaluate = async (expression) => {
  const r = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true })
  if (r.result?.exceptionDetails) throw new Error(r.result.exceptionDetails.exception?.description?.split('\n')[0] || 'eval error')
  return r.result?.result?.value
}
await send('Page.enable'); await send('Runtime.enable'); await send('Network.enable'); await send('DOM.enable')
const mobile = scenario.viewport === 'mobile'
await send('Emulation.setDeviceMetricsOverride', { width: mobile ? 390 : 1280, height: mobile ? 844 : 900, deviceScaleFactor: 1, mobile })

const navigate = async (path) => {
  await send('Page.navigate', { url: path.startsWith('http') ? path : base + path })
  for (let i = 0; i < 60; i++) { await sleep(250); if ((await evaluate('document.readyState')) === 'complete') break }
  await sleep(800) // beri waktu SPA memuat data
}

// ---------- Login ----------
const who = scenario.as || 'qa'
await navigate('/')
await evaluate(`localStorage.setItem('krafio.lang', ${JSON.stringify(scenario.lang || 'id')})`)
if (who !== 'guest') {
  let login = 'demo@krafio.local', password = 'DemoKrafio2026'
  if (who === 'qa' || who === 'admin') {
    const p = join(homedir(), who === 'qa' ? '.krafio-tools/qa-credentials.json' : '.krafio-tools/qa-admin-credentials.json')
    if (!existsSync(p)) { report.steps.push({ step: 'login', ok: false, error: `Akun belum ada → scripts/${who === 'qa' ? 'qa-account.sh' : 'admin-account.sh --mfa-off'}` }); finish(1) }
    ;({ login, password } = JSON.parse(readFileSync(p, 'utf8')))
  }
  const endpoint = who === 'admin' ? '/api/auth/admin/login' : '/api/auth/login'
  const payload = who === 'admin' ? { email: login, password } : { login, password }
  const status = await evaluate(`(async () => { await fetch('/sanctum/csrf-cookie', { credentials: 'include' }); const t = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || ''); const r = await fetch(${JSON.stringify(endpoint)}, { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': t }, body: JSON.stringify(${JSON.stringify(payload)}) }); const j = await r.json().catch(() => ({})); return r.status === 200 && ${JSON.stringify(who)} === 'admin' && j.data && j.data.mfa !== 'none' ? 'mfa' : r.status })()`)
  if (status === 'mfa') { report.steps.push({ step: { login: who }, ok: false, error: 'MFA admin aktif → scripts/admin-account.sh --mfa-off' }); finish(1) }
  report.steps.push({ step: { login: who }, ok: status === 200, error: status === 200 ? undefined : `HTTP ${status}` })
  if (status !== 200) finish(1)
}

// ---------- Helper DOM (berjalan di halaman) ----------
const DOM = `
window.__k = {
  byLabel(t) {
    const l = [...document.querySelectorAll('label')].find((x) => x.textContent.replace(/\\s+/g, ' ').trim().toLowerCase().startsWith(t.toLowerCase()))
    return l && (document.getElementById(l.htmlFor) || l.querySelector('input,textarea,select'))
  },
  setValue(el, v) {
    const proto = el.tagName === 'TEXTAREA' ? HTMLTextAreaElement.prototype : el.tagName === 'SELECT' ? HTMLSelectElement.prototype : HTMLInputElement.prototype
    Object.getOwnPropertyDescriptor(proto, 'value').set.call(el, v)
    el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true }))
  },
  clickable(t) {
    const all = [...document.querySelectorAll('button,a,[role=tab],[role=button],[role=menuitem],summary,label')].filter((e) => e.offsetParent !== null)
    const norm = (e) => e.textContent.replace(/\\s+/g, ' ').trim()
    return all.find((e) => norm(e) === t) || all.find((e) => norm(e).startsWith(t)) || all.find((e) => norm(e).includes(t))
  },
  hasText(t) { return document.body.innerText.includes(t) },
}`
const el = (spec) => (typeof spec === 'string' ? `__k.byLabel(${JSON.stringify(spec)})` : spec.selector ? `document.querySelector(${JSON.stringify(spec.selector)})` : `__k.byLabel(${JSON.stringify(spec.label)})`)

for (const [i, step] of (scenario.steps || []).entries()) {
  const rec = { step, ok: true }
  try {
    await evaluate(DOM)
    if (step.goto) await navigate(step.goto)
    else if (step.wait) await sleep(step.wait)
    else if (step.waitFor) {
      const until = Date.now() + (step.timeout || 8000)
      while (!(await evaluate(`__k.hasText(${JSON.stringify(step.waitFor)})`))) {
        if (Date.now() > until) throw new Error('teks tidak muncul')
        await sleep(250); await evaluate(DOM)
      }
    } else if (step.fill || step.select) {
      const f = step.fill || step.select
      const value = f.file ? readFileSync(resolve(dirname(scenarioPath), f.file), 'utf8') : String(f.value ?? '')
      const ok = await evaluate(`(() => { const e = ${el(f)}; if (!e) return false; __k.setValue(e, ${JSON.stringify(value)}); return true })()`)
      if (!ok) throw new Error('field tidak ditemukan')
    } else if (step.click) {
      const q = typeof step.click === 'string' ? `__k.clickable(${JSON.stringify(step.click)})` : `document.querySelector(${JSON.stringify(step.click.selector)})`
      const ok = await evaluate(`(() => { const e = ${q}; if (!e) return false; e.scrollIntoView({ block: 'center' }); e.click(); return true })()`)
      if (!ok) throw new Error('elemen tidak ditemukan')
      await sleep(step.after ?? 600)
    } else if (step.upload) {
      const u = step.upload
      const file = resolve(dirname(scenarioPath), u.file)
      if (!existsSync(file)) throw new Error('file tidak ada: ' + file)
      const sel = u.selector || (await evaluate(`(() => { const e = __k.byLabel(${JSON.stringify(u.label || '')}); if (!e) return null; if (!e.id) e.id = 'k-up-' + Date.now(); return '#' + e.id })()`))
      if (!sel) throw new Error('input file tidak ditemukan')
      const doc = await send('DOM.getDocument', { depth: 0 })
      const q = await send('DOM.querySelector', { nodeId: doc.result.root.nodeId, selector: sel })
      if (!q.result?.nodeId) throw new Error('input file tidak ditemukan: ' + sel)
      const r = await send('DOM.setFileInputFiles', { nodeId: q.result.nodeId, files: [file] })
      if (r.error) throw new Error(r.error.message)
      await sleep(step.after ?? 1500)
    } else if (step.expectText) {
      if (!(await evaluate(`__k.hasText(${JSON.stringify(step.expectText)})`))) throw new Error('teks tidak ada di halaman')
    } else if (step.expectNoText) {
      if (await evaluate(`__k.hasText(${JSON.stringify(step.expectNoText)})`)) throw new Error('teks seharusnya tidak ada')
    } else if (step.eval) {
      rec.value = await evaluate(step.eval)
      if ('expect' in step && JSON.stringify(rec.value) !== JSON.stringify(step.expect)) throw new Error(`hasil ${JSON.stringify(rec.value)} ≠ ${JSON.stringify(step.expect)}`)
    } else if (step.shot) {
      const r = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: step.full !== false })
      const file = join(out, `${String(i).padStart(2, '0')}-${step.shot}.png`)
      writeFileSync(file, Buffer.from(r.result.data, 'base64'))
      report.screenshots.push(file)
      rec.file = file
    } else throw new Error('langkah tidak dikenal')
  } catch (err) {
    rec.ok = false
    rec.error = err.message
    const r = await send('Page.captureScreenshot', { format: 'png' }).catch(() => null)
    if (r?.result?.data) { rec.file = join(out, `${String(i).padStart(2, '0')}-GAGAL.png`); writeFileSync(rec.file, Buffer.from(r.result.data, 'base64')) }
  }
  report.steps.push(rec)
  console.log(`${rec.ok ? '✓' : '✗'} ${JSON.stringify(step).slice(0, 110)}${rec.ok ? '' : ' → ' + rec.error}${rec.file ? ' [' + rec.file.split('/').pop() + ']' : ''}`)
  if (!rec.ok && scenario.stopOnFail !== false) break
}
clearTimeout(guard)
finish()
