// i18n (Design Spec §5): id (default) & en; string UI di /locales; fallback en → id; kunci mentah tidak boleh tampil.
import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import id from '@/shared/locales/id.json'
import en from '@/shared/locales/en.json'
import { setPref } from '@/shared/lib/prefs'

const initial = document.documentElement.lang === 'en' ? 'en' : 'id'

void i18n.use(initReactI18next).init({
  resources: { id: { translation: id }, en: { translation: en } },
  lng: initial,
  fallbackLng: 'id',
  interpolation: { escapeValue: false },
  returnNull: false,
  saveMissing: import.meta.env.DEV,
  missingKeyHandler: (_lngs, _ns, key) => {
    if (import.meta.env.DEV) console.warn('[i18n] kunci hilang:', key)
  },
  parseMissingKeyHandler: (key, defaultValue) => {
    if (typeof defaultValue === 'string') return defaultValue
    // Jangan pernah menampilkan kunci mentah: tampilkan segmen terakhir yang dibaca manusia
    const last = key.split('.').pop() ?? key
    return last.replace(/[_-]+/g, ' ')
  },
})

export function changeLanguage(lng: 'id' | 'en'): Promise<unknown> {
  setPref('krafio.lang', lng)
  document.documentElement.lang = lng
  return i18n.changeLanguage(lng)
}

export default i18n
