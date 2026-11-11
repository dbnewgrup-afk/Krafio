<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pusat pesan (inbox admin/user), media publik (hero & cover template), dan urutan template.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $t) {
            $t->string('priority', 10)->default('normal')->after('status')->index(); // low, normal, high, urgent
            $t->foreignId('assignee_id')->nullable()->after('priority')->constrained('users')->nullOnDelete();
            $t->json('tags')->nullable()->after('assignee_id');
            $t->timestamp('last_message_at')->nullable()->after('tags')->index();
            $t->string('last_from', 10)->nullable()->after('last_message_at');
            $t->unsignedInteger('admin_unread')->default(0)->after('last_from');
            $t->unsignedInteger('user_unread')->default(0)->after('admin_unread');
            $t->timestamp('sla_due_at')->nullable()->after('user_unread')->index();
            $t->timestamp('archived_at')->nullable()->after('sla_due_at');
        });

        Schema::table('ticket_messages', function (Blueprint $t) {
            $t->json('attachments')->nullable()->after('body');
            $t->timestamp('read_at')->nullable()->after('attachments');
            $t->string('client_id', 40)->nullable()->after('read_at'); // id optimistik dari klien (idempoten)
        });

        Schema::create('ticket_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('body');
            $t->timestamps();
        });

        Schema::create('ticket_activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 40);
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('media', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('path');
            $t->string('original_name', 200);
            $t->string('mime', 80);
            $t->unsignedInteger('size');
            $t->unsignedSmallInteger('width')->nullable();
            $t->unsignedSmallInteger('height')->nullable();
            $t->timestamps();
        });

        Schema::table('templates', function (Blueprint $t) {
            $t->string('cover_url')->nullable()->after('palette');
            $t->json('focus')->nullable()->after('cover_url'); // titik fokus gambar {x,y} 0..100
            $t->json('badges')->nullable()->after('focus');    // label khusus: new, promo, featured, custom
            $t->unsignedInteger('sort')->default(0)->after('usage_count')->index();
        });

        // Isi kolom turunan untuk data lama
        foreach (DB::table('tickets')->get() as $tk) {
            $last = DB::table('ticket_messages')->where('ticket_id', $tk->id)->orderByDesc('id')->first();
            DB::table('tickets')->where('id', $tk->id)->update([
                'last_message_at' => $last->created_at ?? $tk->updated_at,
                'last_from' => $last->from ?? 'user',
                'admin_unread' => ($last->from ?? 'user') === 'user' && in_array($tk->status, ['open'], true) ? 1 : 0,
                'sla_due_at' => ($last->from ?? 'user') === 'user' && $tk->status === 'open' ? date('Y-m-d H:i:s', strtotime(($last->created_at ?? $tk->updated_at).' +4 hours')) : null,
            ]);
        }
        $i = 0;
        foreach (DB::table('templates')->orderByDesc('is_featured')->orderBy('id')->pluck('id') as $id) {
            DB::table('templates')->where('id', $id)->update(['sort' => ++$i]);
        }
    }

    public function down(): void
    {
        Schema::table('templates', fn (Blueprint $t) => $t->dropColumn(['cover_url', 'focus', 'badges', 'sort']));
        Schema::dropIfExists('media');
        Schema::dropIfExists('ticket_activities');
        Schema::dropIfExists('ticket_notes');
        Schema::table('ticket_messages', fn (Blueprint $t) => $t->dropColumn(['attachments', 'read_at', 'client_id']));
        Schema::table('tickets', function (Blueprint $t) {
            $t->dropConstrainedForeignId('assignee_id');
            $t->dropColumn(['priority', 'tags', 'last_message_at', 'last_from', 'admin_unread', 'user_unread', 'sla_due_at', 'archived_at']);
        });
    }
};
