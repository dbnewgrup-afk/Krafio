import { Image, Video, Wand2, Scissors, Lightbulb, LayoutTemplate, Globe, SearchCheck, Bot, Film, Share2, type LucideIcon } from 'lucide-react'

// Modul fitur Krafio (PRD Bab 6) + kunci entitlement (PRD Bab 4.5).
export interface FeatureDef {
  slug: string
  key: string // i18n key di features.*
  entitlement: string
  icon: LucideIcon
  panel: string
  group: 'create' | 'edit' | 'grow'
  costUnit: number // estimasi kredit per job (contoh tampilan; nilai final dari API)
}

export const FEATURES: FeatureDef[] = [
  { slug: 'generate-photo', key: 'photo_generate', entitlement: 'photo.generate', icon: Image, panel: '/app/create/photo', group: 'create', costUnit: 3 },
  { slug: 'generate-video', key: 'video_generate', entitlement: 'video.generate', icon: Video, panel: '/app/create/video', group: 'create', costUnit: 10 },
  { slug: 'edit-photo', key: 'photo_edit', entitlement: 'photo.edit', icon: Wand2, panel: '/app/create/edit-photo', group: 'edit', costUnit: 2 },
  { slug: 'edit-video', key: 'video_edit', entitlement: 'video.edit', icon: Scissors, panel: '/app/create/edit-video', group: 'edit', costUnit: 6 },
  { slug: 'brainstorming', key: 'content_brainstorm', entitlement: 'content.brainstorm', icon: Lightbulb, panel: '/app/create/brainstorm', group: 'create', costUnit: 1 },
  { slug: 'templates', key: 'templates_premium', entitlement: 'templates.premium', icon: LayoutTemplate, panel: '/app/templates', group: 'create', costUnit: 0 },
  { slug: 'website-builder', key: 'website_builder', entitlement: 'website.builder', icon: Globe, panel: '/app/websites', group: 'grow', costUnit: 0 },
  { slug: 'seo-tools', key: 'seo_basic', entitlement: 'seo.basic', icon: SearchCheck, panel: '/app/seo', group: 'grow', costUnit: 1 },
  { slug: 'ai-assistant', key: 'chatbot_basic', entitlement: 'chatbot.basic', icon: Bot, panel: '/app/assistant', group: 'grow', costUnit: 1 },
  { slug: 'video-clipper', key: 'video_clipper', entitlement: 'video.clipper', icon: Film, panel: '/app/create/clipper', group: 'edit', costUnit: 5 },
  { slug: 'social-integration', key: 'social_integrations', entitlement: 'social.integrations', icon: Share2, panel: '/app/social', group: 'grow', costUnit: 0 },
]

export const featureBySlug = (slug: string) => FEATURES.find((f) => f.slug === slug)
