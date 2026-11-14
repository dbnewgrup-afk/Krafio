<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Forum Diskusi privat untuk pelanggan berbayar. Akses memakai entitlement paket `forum.access` (tanpa kredit/token).
 * Kategori (sub-menu per kepentingan), moderasi, laporan, dan bisu diatur Admin Krafio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_categories', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 60)->unique();
            $t->string('name', 80);
            $t->string('description', 300)->nullable();
            $t->string('icon', 30)->nullable();
            $t->json('plan_codes')->nullable(); // null = semua paket yang punya forum.access
            $t->boolean('admin_only_posting')->default(false); // mis. Pengumuman: hanya Admin yang membuat topik
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('forum_threads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained('forum_categories')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title', 150);
            $t->text('body');
            $t->string('status', 12)->default('published'); // published | hidden | deleted
            $t->boolean('is_pinned')->default(false);
            $t->boolean('is_locked')->default(false);
            $t->unsignedInteger('replies_count')->default(0);
            $t->timestamp('last_reply_at')->nullable();
            $t->foreignId('last_reply_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('moderation_note', 300)->nullable();
            $t->timestamp('edited_at')->nullable();
            $t->timestamps();
            $t->index(['category_id', 'status', 'is_pinned', 'last_reply_at']);
        });

        Schema::create('forum_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('thread_id')->constrained('forum_threads')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->string('status', 12)->default('published'); // published | hidden | deleted
            $t->string('moderation_note', 300)->nullable();
            $t->timestamp('edited_at')->nullable();
            $t->timestamps();
            $t->index(['thread_id', 'status', 'id']);
        });

        Schema::create('forum_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete(); // pelapor
            $t->string('type', 12); // system | thread | post | user
            $t->string('reason', 30); // bug | error | spam | abuse | harassment | scam | off_topic | other
            $t->text('message');
            $t->foreignId('thread_id')->nullable()->constrained('forum_threads')->nullOnDelete();
            $t->foreignId('post_id')->nullable()->constrained('forum_posts')->nullOnDelete();
            $t->foreignId('reported_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('page_url', 300)->nullable();
            $t->string('status', 12)->default('open'); // open | in_review | resolved | rejected
            $t->text('admin_note')->nullable();
            $t->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('handled_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });

        Schema::create('forum_mutes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('until')->nullable(); // null = permanen
            $t->string('reason', 300);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['user_id', 'until']);
        });

        $now = now();
        $cats = [
            ['pengumuman', 'Pengumuman', 'Kabar resmi, rilis fitur, dan jadwal pemeliharaan dari tim Krafio.', 'megaphone', true],
            ['umkm', 'UMKM & Bisnis Lokal', 'Strategi jualan, branding, dan promosi untuk usaha kecil.', 'store', false],
            ['kreator', 'Kreator Konten', 'Ide konten, kalender posting, dan pertumbuhan akun.', 'sparkles', false],
            ['agensi', 'Agensi & Freelancer', 'Kelola klien, alur kerja tim, dan penawaran jasa.', 'briefcase', false],
            ['website-seo', 'Website & SEO', 'Website builder, domain, dan optimasi mesin pencari.', 'globe', false],
            ['ai-studio', 'AI Studio & Tips', 'Prompt, hasil foto/video AI, dan trik memakai Studio.', 'wand', false],
            ['tanya-jawab', 'Tanya Jawab Umum', 'Pertanyaan seputar pemakaian Krafio antar pengguna.', 'help', false],
        ];
        foreach ($cats as $i => [$slug, $name, $desc, $icon, $adminOnly]) {
            DB::table('forum_categories')->insertOrIgnore(['slug' => $slug, 'name' => $name, 'description' => $desc, 'icon' => $icon,
                'plan_codes' => null, 'admin_only_posting' => $adminOnly, 'is_active' => true, 'sort' => ($i + 1) * 10, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Akses forum = paket berbayar. Tambahkan ke paket berbayar & snapshot langganan aktifnya (pelanggan lama langsung dapat akses).
        $paid = DB::table('plans')->where('price_monthly', '>', 0)->get(['code', 'features']);
        foreach ($paid as $p) {
            $f = json_decode($p->features ?? '[]', true) ?: [];
            if (! in_array('forum.access', $f, true)) {
                DB::table('plans')->where('code', $p->code)->update(['features' => json_encode(array_values(array_merge($f, ['forum.access'])))]);
            }
        }
        $codes = $paid->pluck('code')->all();
        DB::table('subscriptions')->whereIn('plan_code', $codes)->whereIn('status', ['active', 'past_due'])->orderBy('id')->each(function ($s) {
            $snap = json_decode($s->snapshot ?? '{}', true) ?: [];
            $f = $snap['features'] ?? [];
            if (! in_array('forum.access', $f, true)) {
                $snap['features'] = array_values(array_merge($f, ['forum.access']));
                DB::table('subscriptions')->where('id', $s->id)->update(['snapshot' => json_encode($snap)]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_mutes');
        Schema::dropIfExists('forum_reports');
        Schema::dropIfExists('forum_posts');
        Schema::dropIfExists('forum_threads');
        Schema::dropIfExists('forum_categories');
    }
};
