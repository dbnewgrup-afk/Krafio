// Penyimpanan preferensi lokal (Design Spec §22). Tidak pernah menyimpan token, password, saldo, atau data privat.
export type PrefKey =
  | 'krafio.theme'
  | 'krafio.lang'
  | 'krafio.sidebar'
  | 'krafio.loader'
  | 'krafio.consent'
  | 'krafio.cs.hidden'
  | 'krafio.tooltips'
  | 'krafio.cs.hint'

export function getPref(key: PrefKey): string | null {
  try {
    return localStorage.getItem(key)
  } catch {
    return null
  }
}

export function setPref(key: PrefKey, value: string | null): void {
  try {
    if (value === null) localStorage.removeItem(key)
    else localStorage.setItem(key, value)
  } catch {
    /* storage diblokir: abaikan, UI tetap jalan */
  }
}

export function getJsonPref<T>(key: PrefKey, fallback: T): T {
  const raw = getPref(key)
  if (!raw) return fallback
  try {
    return JSON.parse(raw) as T
  } catch {
    return fallback
  }
}
