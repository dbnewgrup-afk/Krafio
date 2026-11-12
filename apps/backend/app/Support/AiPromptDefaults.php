<?php

namespace App\Support;

/**
 * Prompt bawaan Prompt Library (Admin → AI → Prompt). AI Gateway selalu membaca prompt AKTIF dengan versi
 * tertinggi per key, jadi admin bisa mengubah perilaku AI tanpa mengubah kode.
 *
 * Workflow ComfyUI (key `comfyui.<jenis_job>`) TIDAK dibuat otomatis: bentuknya bergantung pada model/node
 * di server ComfyUI masing-masing. Admin menempelkan workflow "API format" dari ComfyUI dengan placeholder
 * {{prompt}}, {{negative}}, {{width}}, {{height}}, {{seed}}, {{count}}, {{source_image}}, {{duration}}.
 */
class AiPromptDefaults
{
    public const PROMPTS = [
        'brainstorm.default' => [
            'Brainstorm konten default',
            'Buat {{n}} ide konten untuk {{platform}} dengan nada {{tone}} tentang: {{topic}}',
        ],
        'brainstorm.system' => [
            'Sistem — brainstorming konten',
            'Anda asisten konten pemasaran untuk UMKM Indonesia. Jawab dalam bahasa Indonesia. Berikan daftar bernomor tanpa pembuka.',
        ],
        'assistant.system' => [
            'Sistem — AI Assistant',
            'Anda AI Assistant Krafio yang membantu UMKM dan kreator membuat konten pemasaran. Jawab ringkas dalam bahasa pengguna.',
        ],
        'image.photo_generate' => [
            'Prompt gambar — generate foto',
            '{{prompt}}, gaya {{style}}, foto produk komersial, pencahayaan rapi, fokus tajam, tanpa teks, tanpa watermark',
        ],
        'image.photo_edit' => [
            'Prompt gambar — edit foto',
            '{{prompt}} (operasi: {{operation}})',
        ],
        'video.video_generate' => [
            'Prompt video — generate video',
            '{{prompt}}, video promosi pendek, gerakan kamera halus, rasio {{ratio}}, durasi {{duration}} detik',
        ],
        'image.negative' => [
            'Prompt negatif bawaan',
            'blur, distorsi, teks acak, watermark, logo palsu, tangan cacat, kualitas rendah',
        ],
    ];
}
