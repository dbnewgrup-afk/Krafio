// Metadata SEO per halaman publik. Dipakai di dua tempat:
// 1) vite.config.ts → saat build menulis dist/<rute>/index.html berisi <title>, meta, canonical, OG, dan ringkasan isi
//    (crawler tanpa JavaScript tetap membaca judul, deskripsi, H1, dan tautan internal);
// 2) PublicLayout → memperbarui meta saat pengguna berpindah halaman di browser.
// File ini sengaja tanpa impor (dibaca juga oleh konfigurasi Vite di Node).

export const SITE_URL = 'https://krafio.id'
export const SITE_NAME = 'Krafio'

export interface PageMeta {
  path: string
  title: string
  description: string
  h1: string
  /** Paragraf ringkas untuk HTML statis (crawler) */
  intro: string
  noindex?: boolean
}

const FEATURES: [slug: string, name: string, desc: string][] = [
  ['generate-photo', 'Generate Foto AI', 'Foto produk dan visual promosi dari satu prompt, dengan rasio siap posting dan biaya kredit terlihat sebelum generate.'],
  ['generate-video', 'Generate Video AI', 'Video pendek dari deskripsi untuk reels, story, dan iklan media sosial.'],
  ['edit-photo', 'Edit Foto AI', 'Hapus latar, perbesar resolusi, atau ubah bagian foto dengan perintah teks.'],
  ['edit-video', 'Edit Video AI', 'Subtitle otomatis dan suntingan video dibantu AI.'],
  ['brainstorming', 'Brainstorming Konten', 'Ide konten terstruktur per platform dan nada bicara, lengkap dengan caption dan kalender posting.'],
  ['templates', 'Template Premium', 'Ratusan template foto, video, media sosial, dan dokumen bisnis yang siap disunting.'],
  ['website-builder', 'Website Builder', 'Website company profile dari formulir singkat, terbit di subdomain Krafio atau domain sendiri.'],
  ['seo-tools', 'SEO Tools', 'Analisis keyphrase, judul, meta description, dan keterbacaan konten, plus audit teknis URL dengan rekomendasi perbaikan.'],
  ['ai-assistant', 'AI Assistant', 'Asisten AI untuk menulis caption, membalas pertanyaan pelanggan, dan merencanakan konten.'],
  ['video-clipper', 'Video Clipper', 'Potong video panjang menjadi klip pendek siap unggah ke TikTok, Reels, dan Shorts.'],
  ['social-integration', 'Integrasi Sosial', 'Hubungkan akun resmi WhatsApp, Instagram, Facebook, TikTok, dan Threads dalam satu panel.'],
]

export const PAGES: PageMeta[] = [
  {
    path: '/',
    title: 'Krafio — Foto, video, konten, website, dan SEO dalam satu akun',
    description: 'Platform produksi dan pemasaran konten berbasis AI untuk kreator, UMKM, dan brand: generate foto & video, tulis konten, buat website, dan cek SEO.',
    h1: 'Buat dan pasarkan konten bisnis Anda dalam satu akun',
    intro: 'Krafio membantu kreator, UMKM, brand, dan agensi kecil membuat foto produk, video pendek, konten media sosial, website company profile, dan analisis SEO dengan AI. Biaya kredit selalu terlihat sebelum Anda menekan Generate.',
  },
  {
    path: '/features',
    title: 'Fitur Krafio — AI Studio, Website Builder, SEO Tools',
    description: 'Semua fitur Krafio: generate dan edit foto/video AI, brainstorming konten, template premium, website builder, SEO tools, AI assistant, dan integrasi sosial.',
    h1: 'Fitur Krafio',
    intro: 'Pilih alat sesuai tahap kerja Anda: membuat visual, menulis konten, membangun website, hingga mengoptimalkan SEO. Fitur di luar paket tetap terlihat dengan gembok.',
  },
  ...FEATURES.map(([slug, name, desc]) => ({
    path: `/features/${slug}`,
    title: `${name} — Krafio`,
    description: desc,
    h1: name,
    intro: desc,
  })),
  { path: '/pricing', title: 'Harga & Paket Krafio — Mulai Gratis', description: 'Bandingkan paket Gratis, Starter, Pro, dan Business. Kredit AI, kuota fitur, dan harga bulanan maupun tahunan yang transparan.', h1: 'Harga & paket', intro: 'Mulai gratis, lalu pilih paket sesuai kebutuhan. Setiap paket menampilkan kredit AI, kuota fitur, dan batas pemakaian dengan jelas.' },
  { path: '/addons', title: 'Add-on Krafio — Kredit, Penyimpanan, dan Fitur Tambahan', description: 'Tambahkan kredit AI, penyimpanan, website, akun sosial, atau SEO Advanced tanpa mengganti paket.', h1: 'Add-on', intro: 'Butuh kapasitas lebih? Tambahkan kredit, penyimpanan, website, atau fitur lanjutan tanpa mengganti paket berlangganan.' },
  { path: '/templates', title: 'Template Foto, Video & Konten — Krafio', description: 'Jelajahi template foto produk, video pendek, konten media sosial, dan website yang siap disunting dengan AI.', h1: 'Template', intro: 'Mulai lebih cepat dengan template foto, video, konten sosial, dan dokumen bisnis yang bisa disesuaikan dengan merek Anda.' },
  { path: '/affiliate', title: 'Program Affiliate Krafio — Dapatkan Komisi', description: 'Ajak kreator dan UMKM memakai Krafio dan dapatkan komisi dari setiap pembelian paket atau add-on yang valid.', h1: 'Program affiliate', intro: 'Bagikan tautan referral Anda dan dapatkan komisi dari pembelian paket atau add-on pengguna yang Anda ajak.' },
  { path: '/faq', title: 'FAQ Krafio — Pertanyaan yang Sering Diajukan', description: 'Jawaban seputar akun, paket, kredit AI, pembayaran, affiliate, dan keamanan data di Krafio.', h1: 'Pertanyaan yang sering diajukan', intro: 'Temukan jawaban seputar akun, paket berlangganan, kredit AI, pembayaran, affiliate, dan keamanan data.' },
  { path: '/promo', title: 'Promo Krafio — Diskon Paket & Kredit', description: 'Promo dan kode diskon terbaru untuk paket berlangganan, add-on, dan kredit AI Krafio.', h1: 'Promo', intro: 'Lihat promo terbaru untuk paket berlangganan, add-on, dan kredit AI.' },
  { path: '/contact', title: 'Hubungi Krafio', description: 'Hubungi tim Krafio untuk pertanyaan produk, kerja sama, atau bantuan akun.', h1: 'Hubungi kami', intro: 'Kirim pertanyaan seputar produk, kerja sama, atau bantuan akun kepada tim Krafio.' },
  { path: '/about', title: 'Tentang Krafio', description: 'Krafio adalah platform konten berbasis AI untuk kreator, UMKM, brand, dan agensi kecil di Indonesia.', h1: 'Tentang Krafio', intro: 'Krafio dibangun untuk membantu kreator dan bisnis kecil memproduksi dan memasarkan konten dengan lebih cepat dan terukur.' },
  { path: '/status', title: 'Status Layanan Krafio', description: 'Status terkini layanan Krafio: API, AI Studio, pembayaran, dan website builder.', h1: 'Status layanan', intro: 'Pantau status terkini layanan Krafio.' },
  { path: '/terms', title: 'Syarat & Ketentuan — Krafio', description: 'Syarat dan ketentuan penggunaan layanan Krafio.', h1: 'Syarat & ketentuan', intro: 'Syarat dan ketentuan yang berlaku untuk penggunaan layanan Krafio.' },
  { path: '/privacy', title: 'Kebijakan Privasi — Krafio', description: 'Cara Krafio mengumpulkan, menggunakan, dan melindungi data pribadi Anda.', h1: 'Kebijakan privasi', intro: 'Penjelasan tentang data yang kami kumpulkan, cara penggunaannya, dan hak Anda atas data pribadi.' },
  { path: '/refund', title: 'Kebijakan Refund — Krafio', description: 'Ketentuan pengembalian dana untuk paket, add-on, dan top-up saldo Krafio.', h1: 'Kebijakan refund', intro: 'Ketentuan dan proses pengajuan pengembalian dana di Krafio.' },
  { path: '/affiliate-terms', title: 'Ketentuan Affiliate — Krafio', description: 'Ketentuan program affiliate Krafio: perhitungan komisi, status, dan penarikan.', h1: 'Ketentuan affiliate', intro: 'Aturan perhitungan komisi, status komisi, dan penarikan dana program affiliate Krafio.' },
  { path: '/cookies', title: 'Kebijakan Cookie — Krafio', description: 'Jenis cookie yang digunakan Krafio dan cara mengatur persetujuan Anda.', h1: 'Kebijakan cookie', intro: 'Penjelasan cookie esensial dan pilihan yang digunakan di Krafio.' },
  { path: '/login', title: 'Masuk — Krafio', description: 'Masuk ke akun Krafio Anda.', h1: 'Masuk', intro: 'Masuk ke akun Krafio untuk melanjutkan pekerjaan Anda.', noindex: true },
  { path: '/register', title: 'Daftar Gratis — Krafio', description: 'Buat akun Krafio gratis dan mulai membuat konten dengan AI.', h1: 'Daftar gratis', intro: 'Buat akun gratis untuk mencoba AI Studio, website builder, dan SEO tools Krafio.' },
  { path: '/forgot-password', title: 'Lupa Kata Sandi — Krafio', description: 'Atur ulang kata sandi akun Krafio Anda.', h1: 'Lupa kata sandi', intro: 'Atur ulang kata sandi akun Krafio Anda.', noindex: true },
]

export const pageMeta = (pathname: string): PageMeta | undefined => {
  const p = pathname.length > 1 ? pathname.replace(/\/+$/, '') : pathname
  return PAGES.find((x) => x.path === p)
}
