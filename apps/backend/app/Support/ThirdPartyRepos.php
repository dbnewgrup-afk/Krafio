<?php

namespace App\Support;

/**
 * Register repository eksternal (PRD Bab 37 & 53) → fitur Krafio yang memakainya.
 * Salinan lokal ada di /repository-github/<folder> (tidak di-commit; lihat repository-github/README.md).
 * Lisensi diambil dari berkas LICENSE hasil clone 14 Nov 2026 — tetap wajib review hukum sebelum produksi (PRD 53.9).
 *
 * method mengikuti PRD 53.6: reference | api | microservice | worker | cli | library | plugin.
 * status: integrated (dipakai langsung/setara), adapter (Krafio sudah punya titik integrasi), planned, reference, blocked.
 */
class ThirdPartyRepos
{
    public const REPOS = [
        '01-openwa' => ['name' => 'OpenWA', 'url' => 'https://github.com/rmyndharis/OpenWA', 'license' => 'MIT', 'method' => 'reference', 'status' => 'blocked', 'tools' => [], 'note' => 'Bukan API resmi Meta (otomasi WhatsApp Web) → bertentangan dengan PRD 30.2/46. Krafio memakai WhatsApp Cloud API resmi.'],
        '02-concat' => ['name' => 'Concat', 'url' => 'https://github.com/jub0t/Concat', 'license' => 'AGPL-3.0', 'method' => 'reference', 'status' => 'reference', 'tools' => ['video_edit'], 'note' => 'AGPL: tidak boleh ditautkan ke SaaS tanpa membuka kode. Penggabungan/edit video memakai FFmpeg langsung.'],
        '03-modly' => ['name' => 'Modly', 'url' => 'https://github.com/lightningpixel/modly', 'license' => 'MIT', 'method' => 'microservice', 'status' => 'planned', 'tools' => [], 'note' => 'Generasi 3D opsional; butuh GPU.'],
        '04-powertoys' => ['name' => 'PowerToys', 'url' => 'https://github.com/microsoft/PowerToys', 'license' => 'MIT', 'method' => 'reference', 'status' => 'reference', 'tools' => [], 'note' => 'Alat developer Windows, bukan dependency produksi.'],
        '05-jan' => ['name' => 'Jan', 'url' => 'https://github.com/janhq/jan', 'license' => 'Apache-2.0', 'method' => 'api', 'status' => 'integrated', 'tools' => ['@text'], 'note' => 'Manajer model lokal di Admin → AI Tools (lihat, unduh, pilih model) + runtime OpenAI-compatible sebagai provider teks cadangan. Jan bisa didaftarkan sebagai provider yang sama.'],
        '06-lossless-cut' => ['name' => 'LosslessCut', 'url' => 'https://github.com/mifi/lossless-cut', 'license' => 'GPL-2.0', 'method' => 'reference', 'status' => 'reference', 'tools' => ['video_edit'], 'note' => 'Aplikasi desktop; teknik trim tanpa re-encode diterapkan lewat FFmpeg di VideoTools.'],
        '07-prompt-optimizer' => ['name' => 'Prompt Optimizer', 'url' => 'https://github.com/linshenkx/prompt-optimizer', 'license' => 'AGPL-3.0', 'method' => 'reference', 'status' => 'integrated', 'tools' => ['prompt_optimizer'], 'note' => 'AGPL → kode tidak dipakai; konsepnya (optimasi prompt per target + alasan perubahan + versi) diterapkan ulang sebagai tool "Prompt Optimizer".'],
        '08-comfyui' => ['name' => 'ComfyUI', 'url' => 'https://github.com/Comfy-Org/ComfyUI', 'license' => 'GPL-3.0', 'method' => 'microservice', 'status' => 'adapter', 'tools' => ['photo_generate', 'photo_edit', 'video_generate'], 'note' => 'Driver "comfyui" sudah ada di MediaDrivers; jalankan sebagai layanan GPU terpisah (GPL tidak menular lewat API).'],
        '09-archify' => ['name' => 'Archify', 'url' => 'https://github.com/tt-ali/archify', 'license' => '—', 'method' => 'reference', 'status' => 'blocked', 'tools' => [], 'note' => 'Clone gagal: repository tidak ada atau privat (14 Nov 2026). Identitas perlu diklarifikasi (D-39).'],
        '10-open-seo' => ['name' => 'OpenSEO', 'url' => 'https://github.com/every-app/open-seo', 'license' => 'MIT', 'method' => 'reference', 'status' => 'reference', 'tools' => ['article_writer'], 'note' => 'Referensi riset keyword & audit; data keyword butuh DataForSEO berbayar (D-45).'],
        '11-screenshot-to-code' => ['name' => 'Screenshot to Code', 'url' => 'https://github.com/abi/screenshot-to-code', 'license' => 'MIT', 'method' => 'reference', 'status' => 'reference', 'tools' => [], 'note' => 'Alat internal konversi desain → kode.'],
        '12a-freellmapi-lov0u' => ['name' => 'FREELLMAPI (lov0u)', 'url' => 'https://github.com/lov0u/freellmapi', 'license' => 'MIT', 'method' => 'api', 'status' => 'planned', 'tools' => ['@text'], 'note' => 'Kandidat (a); belum dipilih.'],
        '12b-freellmapi-xxy2026' => ['name' => 'FREELLMAPI (xxy2026)', 'url' => 'https://github.com/xxy2026/freellmapi', 'license' => 'MIT', 'method' => 'api', 'status' => 'planned', 'tools' => ['@text'], 'note' => 'Kandidat (b); belum dipilih.'],
        '12c-freellmapi-tashfeenahmed' => ['name' => 'FREELLMAPI (tashfeenahmed)', 'url' => 'https://github.com/tashfeenahmed/freellmapi', 'license' => 'MIT', 'method' => 'api', 'status' => 'adapter', 'tools' => ['@text'], 'note' => 'Kandidat yang diusulkan; endpoint OpenAI-compatible → daftarkan sebagai provider teks. Kuota gratis tanpa SLA (D-46).'],
        '13-ai-design-skills' => ['name' => 'AI Design Skills', 'url' => 'https://github.com/elayadesign/ai-design-skills', 'license' => 'MIT', 'method' => 'reference', 'status' => 'reference', 'tools' => ['photo_generate'], 'note' => 'Referensi gaya & prompt desain.'],
        '14-postiz' => ['name' => 'Postiz', 'url' => 'https://github.com/gitroomhq/postiz-app', 'license' => 'AGPL-3.0', 'method' => 'api', 'status' => 'integrated', 'tools' => ['content_plan', 'content_caption', '@social'], 'note' => 'Adapter Public API (connect link, daftar kanal, upload, posting/jadwal) di Services/Social/Postiz.php. AGPL → berjalan sebagai layanan terpisah.'],
        '15-video-shotcraft' => ['name' => 'Video Shotcraft', 'url' => 'https://github.com/Vincentwei1021/video-shotcraft', 'license' => 'Apache-2.0', 'method' => 'worker', 'status' => 'integrated', 'tools' => ['script_video', 'content_script'], 'note' => 'Konsep naskah → video diterapkan sebagai tool "Naskah → Video" (kinetic text per adegan, FFmpeg + libass).'],
        '16-security-audit-skill' => ['name' => 'Security Audit Skill', 'url' => 'https://github.com/netresearch/security-audit-skill', 'license' => 'CC-BY-SA-4.0', 'method' => 'reference', 'status' => 'reference', 'tools' => [], 'note' => 'Checklist audit internal (dipakai pada audit 14 Nov 2026).'],
        '17-wordcraft' => ['name' => 'Wordcraft', 'url' => 'https://github.com/storytold/wordcraft', 'license' => 'Apache-2.0', 'method' => 'cli', 'status' => 'integrated', 'tools' => ['@text'], 'note' => 'Ekspor hasil teks ke .docx (writer OOXML minimal di Services/Export/Ooxml.php).'],
        '18-deckcraft' => ['name' => 'Deckcraft', 'url' => 'https://github.com/storytold/deckcraft', 'license' => 'Apache-2.0', 'method' => 'cli', 'status' => 'planned', 'tools' => ['copywriting'], 'note' => 'Ekspor presentasi dari hasil copy/rencana.'],
        '19-gridcraft' => ['name' => 'Gridcraft', 'url' => 'https://github.com/storytold/gridcraft', 'license' => 'Apache-2.0', 'method' => 'cli', 'status' => 'integrated', 'tools' => ['@text'], 'note' => 'Ekspor hasil teks/tabel ke .xlsx (writer OOXML minimal).'],
        '20-reclip' => ['name' => 'Reclip', 'url' => 'https://github.com/averygan/reclip', 'license' => 'MIT', 'method' => 'reference', 'status' => 'reference', 'tools' => ['video_clipper'], 'note' => 'Referensi clipping web; unduh dari URL tidak diaktifkan (SSRF & hak cipta).'],
        '21-rea' => ['name' => 'REA', 'url' => 'https://github.com/morluto/rea', 'license' => 'MIT', 'method' => 'reference', 'status' => 'reference', 'tools' => [], 'note' => 'Hanya untuk analisis yang sah.'],
        '22-autoclip' => ['name' => 'AutoClip', 'url' => 'https://github.com/zhouxiaoka/autoclip', 'license' => 'MIT', 'method' => 'worker', 'status' => 'integrated', 'tools' => ['video_clipper'], 'note' => 'Pola pemilihan momen (energi audio/perubahan adegan) diterapkan di VideoTools::clipper dengan FFmpeg; tahap transkrip+LLM menyusul.'],
        '23-remotion' => ['name' => 'Remotion', 'url' => 'https://github.com/remotion-dev/remotion', 'license' => 'Remotion License', 'method' => 'worker', 'status' => 'planned', 'tools' => ['video_generate', 'script_video'], 'note' => 'Lisensi perusahaan wajib bila tim > 3 orang (D-44). Naskah → Video saat ini memakai FFmpeg; Remotion untuk template animasi lanjutan.'],
        '24-openhands' => ['name' => 'OpenHands', 'url' => 'https://github.com/OpenHands/OpenHands', 'license' => 'MIT', 'method' => 'reference', 'status' => 'reference', 'tools' => [], 'note' => 'Agen coding internal, hanya di sandbox.'],
        '25-mumuainovel' => ['name' => 'MuMuAINovel', 'url' => 'https://github.com/xiamuceer-j/MuMuAINovel', 'license' => 'GPL-3.0', 'method' => 'reference', 'status' => 'reference', 'tools' => ['story_series'], 'note' => 'GPL-3.0 → referensi alur penulisan bab bersambung (konteks antar part) yang diterapkan di TextWriter.'],
    ];

    /** Repo untuk satu tool; '@text' berarti berlaku untuk semua tool teks. */
    public static function forTool(string $key): array
    {
        $tool = AiTools::definitions()[$key] ?? null;
        $out = [];
        foreach (self::REPOS as $folder => $r) {
            if (in_array($key, $r['tools'], true) || ($tool && $tool['capability'] === 'text' && in_array('@text', $r['tools'], true))) {
                $out[] = ['folder' => $folder] + array_diff_key($r, ['tools' => 1]);
            }
        }

        return $out;
    }
}
