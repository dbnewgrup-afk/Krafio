<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integrasi yang dikelola dari Admin Panel (payment gateway, SMTP, WhatsApp), log pengiriman,
 * dan catatan backup. Credential disimpan terenkripsi (APP_KEY) dan tidak pernah dikirim ulang ke klien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 20)->unique(); // payment | smtp | whatsapp
            $t->string('driver', 30);
            $t->text('config')->nullable(); // JSON terenkripsi
            $t->boolean('is_active')->default(false);
            $t->string('last_test_status', 20)->nullable();
            $t->string('last_test_message')->nullable();
            $t->timestamp('last_tested_at')->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('mail_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('to');
            $t->string('subject');
            $t->string('template', 60)->nullable();
            $t->string('mailer', 20);
            $t->string('status', 15); // sent | failed | skipped
            $t->string('error', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['status', 'created_at']);
        });

        Schema::create('wa_templates', function (Blueprint $t) {
            $t->id();
            $t->string('event', 40)->unique(); // otp, topup_success, withdrawal_paid, ...
            $t->string('meta_name', 120); // nama template yang disetujui di WhatsApp Manager
            $t->string('language', 10)->default('id');
            $t->text('body_preview');
            $t->json('variables'); // urutan variabel {{1}}, {{2}}, ...
            $t->boolean('is_active')->default(true);
            $t->string('meta_status', 20)->nullable(); // APPROVED / PENDING / REJECTED (hasil sinkron)
            $t->timestamps();
        });

        Schema::create('wa_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('to', 20);
            $t->string('event', 40)->nullable();
            $t->string('template', 120)->nullable();
            $t->string('status', 15); // sent | failed | skipped
            $t->string('provider_message_id', 120)->nullable();
            $t->string('error', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['status', 'created_at']);
        });

        Schema::create('backups', function (Blueprint $t) {
            $t->id();
            $t->string('type', 15); // database | files
            $t->string('file');
            $t->unsignedBigInteger('size')->default(0);
            $t->string('status', 15)->default('success');
            $t->string('message', 500)->nullable();
            $t->string('trigger', 15)->default('schedule'); // schedule | manual
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['backups', 'wa_logs', 'wa_templates', 'mail_logs', 'integrations'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
