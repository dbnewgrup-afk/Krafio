import type { ComponentType } from 'react'

/** Data kartu dipisah dari gaya: gaya bisa berganti tanpa mengubah data. */
export interface ShowcaseCard {
  key: string
  /** Judul kartu, mis. "Saldo top-up" */
  label: string
  amount: number
  holder: string
  /** Nomor/ID yang ditampilkan ter-mask (mis. "KRF 0000 0042") */
  ref: string
  tone: 'topup' | 'commission' | 'warning' | 'neutral'
  /** Info di sisi belakang kartu */
  meta?: Record<string, string | number>
}

export type CardVariant = 'stack3d' | 'single' | 'flat' | 'glass' | 'fan'

export interface CardConfig {
  variant: CardVariant
  /** Hover tilt, glare, flip */
  interactive: boolean
  /** Derajat kemiringan maksimum */
  tiltIntensity: number
  glare: boolean
  float: boolean
  perspective: number
  shadow: 'none' | 'soft' | 'deep'
  radius: 'xl' | '2xl' | '3xl'
  theme: 'light' | 'dark' | 'auto'
  /** Pengali durasi; 0 = matikan animasi */
  animationSpeed: number
}

export const DEFAULT_CARD_CONFIG: CardConfig = {
  variant: 'stack3d',
  interactive: true,
  tiltIntensity: 10,
  glare: true,
  float: true,
  perspective: 1000,
  shadow: 'soft',
  radius: '2xl',
  theme: 'auto',
  animationSpeed: 1,
}

/** Status tampilan bersama untuk semua variant. */
export interface CardState {
  index: number
  flipped: boolean
  revealed: boolean
  frozen: boolean
}

/** Interface props yang sama untuk semua variant (registry). */
export interface VariantProps {
  cards: ShowcaseCard[]
  config: CardConfig
  state: CardState
  select: (index: number, dir?: 1 | -1) => void
  flip: () => void
  /** Format nominal (rupiah) */
  money: (n: number) => string
  /** Teks label i18n */
  labels: { back: string; holder: string; hidden: string; frozen: string }
  /** Arah pergantian terakhir untuk animasi masuk dari samping */
  direction: 1 | -1
}

export type VariantComponent = ComponentType<VariantProps>
