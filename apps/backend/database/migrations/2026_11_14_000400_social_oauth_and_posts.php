<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integrasi media sosial (PRD 6.11, 30): akun terhubung lewat OAuth resmi platform (popup) atau lewat Postiz,
 * data profil hasil sinkronisasi API, dan antrean posting/penjadwalan (Postiz).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $t) {
            $t->string('provider', 12)->default('direct')->after('platform'); // direct | postiz
            $t->string('external_id', 120)->nullable()->after('provider');
            $t->string('username', 120)->nullable()->after('account_name');
            $t->string('avatar_url', 500)->nullable()->after('username');
            $t->text('refresh_token')->nullable()->after('access_token'); // terenkripsi
            $t->json('scopes')->nullable()->after('refresh_token');
            $t->json('profile')->nullable()->after('scopes'); // followers, posts, dll. dari API
            $t->timestamp('synced_at')->nullable()->after('expires_at');
            $t->string('last_error', 255)->nullable()->after('synced_at');
        });
        Schema::create('social_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('content');
            $t->json('platforms'); // ["instagram","tiktok"]
            $t->json('media')->nullable(); // [{upload_id, path_remote}]
            $t->string('type', 12)->default('schedule'); // now | schedule | draft
            $t->timestamp('scheduled_at')->nullable();
            $t->string('status', 20)->default('queued'); // queued | sent | failed
            $t->string('provider', 12)->default('postiz');
            $t->json('external')->nullable(); // respons Postiz
            $t->string('error', 255)->nullable();
            $t->timestamps();
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
        Schema::table('social_accounts', fn (Blueprint $t) => $t->dropColumn(['provider', 'external_id', 'username', 'avatar_url', 'refresh_token', 'scopes', 'profile', 'synced_at', 'last_error']));
    }
};
