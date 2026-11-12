<?php

use App\Models\WaTemplate;
use App\Support\LifecycleMail;
use Illuminate\Database\Migrations\Migration;

/** Pemetaan template WhatsApp untuk event siklus langganan, affiliate & refund — nonaktif sampai disetujui Meta. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (LifecycleMail::WA as $event => [$name, $body, $vars]) {
            WaTemplate::firstOrCreate(['event' => $event], ['meta_name' => $name, 'language' => 'id', 'body_preview' => $body, 'variables' => $vars, 'is_active' => false]);
        }
    }

    public function down(): void
    {
        WaTemplate::whereIn('event', array_diff(array_keys(LifecycleMail::WA), ['subscription_expiring']))->where('is_active', false)->delete();
    }
};
