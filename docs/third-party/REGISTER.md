# Register Repository Eksternal (PRD Bab 37 & 53)

Sumber data: `apps/backend/app/Support/ThirdPartyRepos.php` (juga tampil di **Admin → AI → AI Tools**). Salinan lokal ada di `repository-github/<folder>` (tidak di-commit, lihat `repository-github/README.md`).

Lisensi dibaca dari berkas LICENSE hasil clone 14 November 2026. **Wajib review hukum sebelum produksi** (PRD 53.9, D-44). Metode mengikuti PRD 53.6.

| Folder | Repository | Lisensi | Metode | Status | Dipakai di fitur | Catatan |
|---|---|---|---|---|---|---|
| `01-openwa` | [OpenWA](https://github.com/rmyndharis/OpenWA) | MIT | reference | Diblokir | — | Bukan API resmi Meta (otomasi WhatsApp Web) → bertentangan dengan PRD 30.2/46. Krafio memakai WhatsApp Cloud API resmi. |
| `02-concat` | [Concat](https://github.com/jub0t/Concat) | AGPL-3.0 | reference | Referensi | Edit Video | AGPL: tidak boleh ditautkan ke SaaS tanpa membuka kode. Penggabungan/edit video memakai FFmpeg langsung. |
| `03-modly` | [Modly](https://github.com/lightningpixel/modly) | MIT | microservice | Rencana | — | Generasi 3D opsional; butuh GPU. |
| `04-powertoys` | [PowerToys](https://github.com/microsoft/PowerToys) | MIT | reference | Referensi | — | Alat developer Windows, bukan dependency produksi. |
| `05-jan` | [Jan](https://github.com/janhq/jan) | Apache-2.0 | api | Terpasang | Semua tool teks | Manajer model lokal di Admin → AI Tools (lihat, unduh, pilih model) + runtime OpenAI-compatible sebagai provider teks cadangan. Jan bisa didaftarkan sebagai provider yang sama. |
| `06-lossless-cut` | [LosslessCut](https://github.com/mifi/lossless-cut) | GPL-2.0 | reference | Referensi | Edit Video | Aplikasi desktop; teknik trim tanpa re-encode diterapkan lewat FFmpeg di VideoTools. |
| `07-prompt-optimizer` | [Prompt Optimizer](https://github.com/linshenkx/prompt-optimizer) | AGPL-3.0 | reference | Terpasang | Prompt Optimizer | AGPL → kode tidak dipakai; konsepnya (optimasi prompt per target + alasan perubahan + versi) diterapkan ulang sebagai tool "Prompt Optimizer". |
| `08-comfyui` | [ComfyUI](https://github.com/Comfy-Org/ComfyUI) | GPL-3.0 | microservice | Adapter siap | Generate Foto, Edit Foto, Generate Video | Driver "comfyui" sudah ada di MediaDrivers; jalankan sebagai layanan GPU terpisah (GPL tidak menular lewat API). |
| `09-archify` | [Archify](https://github.com/tt-ali/archify) | — | reference | Diblokir | — | Clone gagal: repository tidak ada atau privat (14 Nov 2026). Identitas perlu diklarifikasi (D-39). |
| `10-open-seo` | [OpenSEO](https://github.com/every-app/open-seo) | MIT | reference | Referensi | Artikel Blog SEO | Referensi riset keyword & audit; data keyword butuh DataForSEO berbayar (D-45). |
| `11-screenshot-to-code` | [Screenshot to Code](https://github.com/abi/screenshot-to-code) | MIT | reference | Referensi | — | Alat internal konversi desain → kode. |
| `12a-freellmapi-lov0u` | [FREELLMAPI (lov0u)](https://github.com/lov0u/freellmapi) | MIT | api | Rencana | Semua tool teks | Kandidat (a); belum dipilih. |
| `12b-freellmapi-xxy2026` | [FREELLMAPI (xxy2026)](https://github.com/xxy2026/freellmapi) | MIT | api | Rencana | Semua tool teks | Kandidat (b); belum dipilih. |
| `12c-freellmapi-tashfeenahmed` | [FREELLMAPI (tashfeenahmed)](https://github.com/tashfeenahmed/freellmapi) | MIT | api | Adapter siap | Semua tool teks | Kandidat yang diusulkan; endpoint OpenAI-compatible → daftarkan sebagai provider teks. Kuota gratis tanpa SLA (D-46). |
| `13-ai-design-skills` | [AI Design Skills](https://github.com/elayadesign/ai-design-skills) | MIT | reference | Referensi | Generate Foto | Referensi gaya & prompt desain. |
| `14-postiz` | [Postiz](https://github.com/gitroomhq/postiz-app) | AGPL-3.0 | api | Terpasang | Kalender Konten, Caption & Hashtag, Integrasi Sosial | Adapter Public API (connect link, daftar kanal, upload, posting/jadwal) di Services/Social/Postiz.php. AGPL → berjalan sebagai layanan terpisah. |
| `15-video-shotcraft` | [Video Shotcraft](https://github.com/Vincentwei1021/video-shotcraft) | Apache-2.0 | worker | Terpasang | Naskah → Video, Naskah Video & Konten | Konsep naskah → video diterapkan sebagai tool "Naskah → Video" (kinetic text per adegan, FFmpeg + libass). |
| `16-security-audit-skill` | [Security Audit Skill](https://github.com/netresearch/security-audit-skill) | CC-BY-SA-4.0 | reference | Referensi | — | Checklist audit internal (dipakai pada audit 14 Nov 2026). |
| `17-wordcraft` | [Wordcraft](https://github.com/storytold/wordcraft) | Apache-2.0 | cli | Terpasang | Semua tool teks | Ekspor hasil teks ke .docx (writer OOXML minimal di Services/Export/Ooxml.php). |
| `18-deckcraft` | [Deckcraft](https://github.com/storytold/deckcraft) | Apache-2.0 | cli | Rencana | Copywriting Iklan & Produk | Ekspor presentasi dari hasil copy/rencana. |
| `19-gridcraft` | [Gridcraft](https://github.com/storytold/gridcraft) | Apache-2.0 | cli | Terpasang | Semua tool teks | Ekspor hasil teks/tabel ke .xlsx (writer OOXML minimal). |
| `20-reclip` | [Reclip](https://github.com/averygan/reclip) | MIT | reference | Referensi | Video Clipper | Referensi clipping web; unduh dari URL tidak diaktifkan (SSRF & hak cipta). |
| `21-rea` | [REA](https://github.com/morluto/rea) | MIT | reference | Referensi | — | Hanya untuk analisis yang sah. |
| `22-autoclip` | [AutoClip](https://github.com/zhouxiaoka/autoclip) | MIT | worker | Terpasang | Video Clipper | Pola pemilihan momen (energi audio/perubahan adegan) diterapkan di VideoTools::clipper dengan FFmpeg; tahap transkrip+LLM menyusul. |
| `23-remotion` | [Remotion](https://github.com/remotion-dev/remotion) | Remotion License | worker | Rencana | Generate Video, Naskah → Video | Lisensi perusahaan wajib bila tim > 3 orang (D-44). Naskah → Video saat ini memakai FFmpeg; Remotion untuk template animasi lanjutan. |
| `24-openhands` | [OpenHands](https://github.com/OpenHands/OpenHands) | MIT | reference | Referensi | — | Agen coding internal, hanya di sandbox. |
| `25-mumuainovel` | [MuMuAINovel](https://github.com/xiamuceer-j/MuMuAINovel) | GPL-3.0 | reference | Referensi | Cerita Bersambung | GPL-3.0 → referensi alur penulisan bab bersambung (konteks antar part) yang diterapkan di TextWriter. |

## Arti status

- **Terpasang**: pola/algoritmanya sudah berjalan di Krafio (contoh: AutoClip → Video Clipper berbasis FFmpeg).
- **Adapter siap**: Krafio sudah punya titik integrasi; tinggal menjalankan layanan & mendaftarkannya sebagai provider (contoh: ComfyUI, Jan, FREELLMAPI).
- **Rencana**: dipetakan ke fitur, belum dipasang.
- **Referensi**: dipelajari saja (lisensi copyleft/alat developer); tidak ada kode yang dijalankan untuk pelanggan.
- **Diblokir**: identitas belum jelas atau bertentangan dengan PRD.

## Aturan memasang repo ke fitur

1. Jangan menjalankan kode dari `repository-github/` di server produksi sebelum lolos Integration Gate (PRD 53.10) dan review lisensi.
2. Lisensi **AGPL/GPL** hanya boleh berjalan sebagai layanan terpisah lewat API (atau referensi), tidak ditautkan ke kode Laravel/React.
3. Integrasi AI selalu lewat **AI Gateway** sebagai provider (`openai_compatible`, `comfyui`, `custom_http`, `openai_images`, `fal`, `replicate`) agar routing, kuota, log, dan kill switch tetap terpusat.
4. Setiap integrasi non-referensi wajib punya kontrak di `docs/third-party/integrations/<nama>.md` (PRD 53.7).
