<?php

use App\Services\Seo\ContentRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Analisis konten SEO bergaya Yoast (docs/seo.md): rule engine "content" yang dapat diatur Admin,
 * audit jenis "content" (form keyphrase/judul/meta/slug/konten), dan hasil per cek (lampu + skor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_rules', function (Blueprint $t) {
            $t->string('engine', 10)->default('audit')->after('code'); // audit = audit URL teknis, content = analisis konten
            $t->string('category', 20)->nullable()->after('engine'); // technical | seo | readability
            $t->string('tier', 10)->default('basic')->after('category'); // basic | advanced (butuh seo.advanced)
            $t->json('params')->nullable()->after('weight');
            $t->text('description')->nullable()->after('params');
            $t->text('recommendation')->nullable()->after('description');
            $t->unsignedSmallInteger('sort')->default(0)->after('recommendation');
            $t->index(['engine', 'is_active']);
        });
        DB::table('seo_rules')->where('engine', 'audit')->update(['category' => 'technical']);

        Schema::table('seo_audits', function (Blueprint $t) {
            $t->string('url', 500)->nullable()->change();
            $t->string('kind', 10)->default('url')->after('user_id'); // url | content
            $t->string('title', 300)->nullable()->after('url');
            $t->string('keyphrase', 191)->nullable()->after('title');
            $t->string('target', 191)->nullable()->after('keyphrase'); // identitas halaman (slug/URL) untuk cek keyphrase pernah dipakai
            $t->string('content_type', 20)->nullable()->after('target');
            $t->unsignedTinyInteger('readability_score')->nullable()->after('score');
            $t->json('input')->nullable()->after('readability_score');
            $t->json('stats')->nullable()->after('input');
            $t->index(['user_id', 'kind', 'keyphrase']);
        });

        Schema::table('seo_findings', function (Blueprint $t) {
            $t->text('message')->change();
            $t->string('category', 20)->nullable()->after('rule');
            $t->string('title', 120)->nullable()->after('category');
            $t->string('rating', 10)->nullable()->after('severity'); // good | ok | bad | feedback
            $t->smallInteger('score')->nullable()->after('rating');
        });

        ContentRules::seed();
        DB::table('settings')->where('key', 'seo.ruleset_version')->update(['value' => json_encode('2.0')]);
    }

    public function down(): void
    {
        DB::table('seo_rules')->where('engine', 'content')->delete();
        Schema::table('seo_findings', function (Blueprint $t) {
            $t->dropColumn(['category', 'title', 'rating', 'score']);
        });
        Schema::table('seo_audits', function (Blueprint $t) {
            $t->dropIndex(['user_id', 'kind', 'keyphrase']);
            $t->dropColumn(['kind', 'title', 'keyphrase', 'target', 'content_type', 'readability_score', 'input', 'stats']);
        });
        Schema::table('seo_rules', function (Blueprint $t) {
            $t->dropIndex(['engine', 'is_active']);
            $t->dropColumn(['engine', 'category', 'tier', 'params', 'description', 'recommendation', 'sort']);
        });
    }
};
