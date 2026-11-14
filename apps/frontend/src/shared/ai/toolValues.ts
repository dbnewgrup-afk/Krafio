import type { AiTool, AiToolField } from '@/api-client/types'

/** Helper nilai form AI Tools (dipisah dari komponen agar fast refresh tetap jalan). */
export type ToolValues = Record<string, unknown>

/** Nilai awal dari default tiap field (sudah termasuk default yang diatur Admin). */
export function toolDefaults(tool: AiTool, seed?: Record<string, unknown>): ToolValues {
  const v: ToolValues = {}
  for (const f of tool.fields) v[f.key] = seed && f.key in seed ? seed[f.key] : (f.default ?? (f.type === 'multiselect' ? [] : f.type === 'toggle' ? false : f.type === 'number' ? (f.min ?? 0) : ''))
  return v
}

/** Sama dengan AiTools::visible di backend: field tampil bila semua syarat show_if terpenuhi. */
export function isVisible(f: AiToolField, v: ToolValues): boolean {
  return Object.entries(f.show_if ?? {}).every(([k, want]) => {
    const cur = v[k]
    if (Array.isArray(want)) return want.map(String).includes(String(cur))
    if (typeof want === 'boolean') return Boolean(cur) === want
    return String(cur) === String(want)
  })
}

/** Nilai yang dikirim ke API: hanya field yang terlihat. */
export function visibleValues(tool: AiTool, v: ToolValues): ToolValues {
  const out: ToolValues = {}
  for (const f of tool.fields) if (isVisible(f, v)) out[f.key] = v[f.key]
  return out
}
