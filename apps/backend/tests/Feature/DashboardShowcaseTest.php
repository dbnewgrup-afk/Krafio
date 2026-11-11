<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Template;
use App\Models\Ticket;
use Illuminate\Http\UploadedFile;

/**
 * Pusat pesan (inbox dua sisi + feed toast), hero showcase, media publik, template studio,
 * kartu dompet, dan CRUD proyek dengan Undo.
 */
class DashboardShowcaseTest extends KrafioTestCase
{
    public function test_f1_user_starts_conversation_admin_sees_unread_and_sla(): void
    {
        $u = $this->user();
        $this->actingUser($u)->json_('POST', '/app/inbox', ['subject' => 'Top-up belum masuk', 'body' => 'Sudah transfer Rp100.000 jam 09.10', 'client_id' => 'c_1'])->assertCreated();
        $t = Ticket::firstOrFail();
        $this->assertSame(1, $t->admin_unread);
        $this->assertNotNull($t->sla_due_at, 'SLA dimulai saat pesan pengguna masuk');

        $this->admin();
        $r = $this->json_('GET', '/admin/inbox', ['filter' => 'unread'])->assertOk();
        $this->assertSame($t->id, $r->json('data.0.id'));
        $this->assertSame(1, $r->json('stats.unread'));
        $feed = $this->json_('GET', '/admin/inbox/feed')->assertOk();
        $this->assertSame('Uji Pengguna', $feed->json('data.items.0.sender'));
    }

    public function test_f2_admin_reply_is_idempotent_marks_read_and_notifies_user_feed(): void
    {
        $u = $this->user();
        $this->actingUser($u)->json_('POST', '/app/inbox', ['subject' => 'Tanya paket', 'body' => 'Apa beda Pro dan Bisnis?'])->assertCreated();
        $t = Ticket::firstOrFail();

        $this->admin();
        $this->json_('POST', "/admin/inbox/{$t->id}/messages", ['body' => 'Halo, kami bantu jelaskan.', 'client_id' => 'dup-1'])->assertCreated();
        $this->json_('POST', "/admin/inbox/{$t->id}/messages", ['body' => 'Halo, kami bantu jelaskan.', 'client_id' => 'dup-1'])->assertCreated();
        $t->refresh();
        $this->assertSame(2, $t->messages()->count(), 'client_id yang sama tidak menggandakan pesan');
        $this->assertSame(0, $t->admin_unread);
        $this->assertSame(1, $t->user_unread);
        $this->assertSame('pending', $t->status);
        $this->assertNull($t->sla_due_at);
        $this->assertNotNull($t->assignee_id, 'Admin pertama yang membalas otomatis ditugaskan');

        $this->actingUser($u);
        $feed = $this->json_('GET', '/app/inbox/feed')->assertOk();
        $this->assertSame(1, $feed->json('data.unread'));
        $show = $this->json_('GET', "/app/inbox/{$t->id}")->assertOk();
        $this->assertSame('read', $show->json('data.messages.0.status'), 'Pesan user terbaca setelah admin membalas');
        $this->assertSame(0, $t->fresh()->user_unread, 'Membuka percakapan menandai dibaca');
    }

    public function test_f3_user_cannot_read_other_users_conversation(): void
    {
        $a = $this->user();
        $this->actingUser($a)->json_('POST', '/app/inbox', ['subject' => 'Rahasia saya', 'body' => 'Isi pribadi'])->assertCreated();
        $t = Ticket::firstOrFail();
        $b = $this->user();
        $this->actingUser($b)->json_('GET', "/app/inbox/{$t->id}")->assertNotFound();
        $this->json_('POST', "/app/inbox/{$t->id}/messages", ['body' => 'menyusup'])->assertNotFound();
        $this->json_('GET', '/admin/inbox')->assertForbidden();
    }

    public function test_f4_admin_controls_bulk_notes_activity_and_export(): void
    {
        $u = $this->user();
        foreach (['Satu', 'Dua'] as $s) {
            $this->actingUser($u)->json_('POST', '/app/inbox', ['subject' => "Kendala {$s}", 'body' => 'Mohon dibantu'])->assertCreated();
        }
        $ids = Ticket::pluck('id')->all();
        $admin = $this->admin();
        $this->json_('PATCH', "/admin/inbox/{$ids[0]}", ['priority' => 'urgent', 'tags' => ['Billing', 'billing', 'vip'], 'assignee_id' => $admin->id])->assertOk()
            ->assertJsonPath('data.priority', 'urgent')->assertJsonPath('data.tags', ['billing', 'vip']);
        $this->json_('POST', "/admin/inbox/{$ids[0]}/notes", ['body' => 'Cek mutasi BCA'])->assertCreated();
        $detail = $this->json_('GET', "/admin/inbox/{$ids[0]}")->assertOk();
        $this->assertSame('Cek mutasi BCA', $detail->json('data.notes.0.body'));
        $this->assertContains('priority', collect($detail->json('data.activity'))->pluck('action')->all());

        $this->json_('POST', '/admin/inbox/bulk', ['ids' => $ids, 'action' => 'resolve'])->assertOk()->assertJsonPath('data.affected', 2);
        $this->assertSame(2, Ticket::where('status', 'resolved')->count());
        $this->json_('GET', '/admin/inbox', [])->assertJsonPath('stats.resolved_today', 2);

        $export = $this->get('/api/admin/inbox/'.$ids[0].'/export');
        $export->assertOk();
        $this->assertStringContainsString('Mohon dibantu', $export->getContent());
    }

    public function test_f5_hero_slides_saved_by_admin_are_served_to_user(): void
    {
        $u = $this->user();
        $this->actingUser($u)->json_('GET', '/app/hero')->assertOk()->assertJsonCount(3, 'data.slides');

        $this->admin();
        $this->json_('PUT', '/admin/hero', ['audience' => 'user', 'slides' => [
            ['id' => 'x1', 'enabled' => true, 'title' => 'Promo Lebaran', 'body' => 'Diskon paket Pro', 'cta_label' => 'Lihat', 'cta_url' => '/app/subscription', 'image' => '/assets/img/samples/product-kopi.svg'],
            ['id' => 'x2', 'enabled' => false, 'title' => 'Tersembunyi'],
        ], 'config' => ['imageEffect' => 'tilt', 'interval' => 8000]])->assertOk();
        $this->json_('PUT', '/admin/hero', ['audience' => 'user', 'slides' => [['id' => 'bad', 'title' => 'X', 'cta_url' => 'javascript:alert(1)']]])->assertStatus(422);

        $this->actingUser($u);
        $r = $this->json_('GET', '/app/hero')->assertOk();
        $this->assertSame(['Promo Lebaran'], collect($r->json('data.slides'))->pluck('title')->all(), 'Slide nonaktif tidak dikirim');
        $this->assertSame('tilt', $r->json('data.config.imageEffect'));
        $this->json_('PUT', '/admin/hero', ['audience' => 'user', 'slides' => []])->assertForbidden();
    }

    public function test_f6_media_upload_requires_matching_random_name(): void
    {
        $this->admin();
        $r = $this->json('POST', '/api/admin/media', ['file' => UploadedFile::fake()->image('cover.png', 40, 30)], ['Accept' => 'application/json'])->assertCreated();
        $url = $r->json('data.url');
        $this->get($url)->assertOk();
        $m = Media::firstOrFail();
        $this->get('/api/media/'.$m->id.'/tebakan.png')->assertNotFound();
        $this->get('/api/media/'.$m->id)->assertNotFound();
    }

    public function test_f7_template_studio_duplicate_reorder_and_cover_fields(): void
    {
        $this->admin();
        $tpl = Template::orderBy('sort')->firstOrFail();
        $copy = $this->json_('POST', "/admin/templates/{$tpl->id}/duplicate")->assertCreated();
        $this->assertSame('draft', $copy->json('data.status'));
        $this->assertStringContainsString('(salinan)', $copy->json('data.title_id'));

        $this->json_('PUT', "/admin/r/templates/{$tpl->id}", ['cover_url' => '/api/media/1/x.png', 'focus' => ['x' => 30, 'y' => 70], 'badges' => ['new', 'promo'], 'structure' => ['gallery' => ['/api/media/1/x.png']]])->assertOk();
        $ids = Template::orderBy('sort')->pluck('id')->reverse()->values()->all();
        $this->json_('POST', '/admin/templates/reorder', ['ids' => $ids])->assertNoContent();
        $this->assertSame($ids[0], Template::orderBy('sort')->value('id'));

        $pub = $this->json_('GET', '/public/templates', ['per_page' => 50])->assertOk();
        $row = collect($pub->json('data'))->firstWhere('id', $tpl->id);
        $this->assertSame(['new', 'promo'], $row['badges']);
        $this->assertSame(['x' => 30, 'y' => 70], $row['focus']);
    }

    public function test_f8_wallet_cards_and_project_crud_with_undo(): void
    {
        $u = $this->user([], 'free', 250000, 120000);
        $this->actingUser($u);
        $cards = $this->json_('GET', '/app/wallet/cards')->assertOk();
        $this->assertSame([250000, 120000], collect($cards->json('data'))->pluck('amount')->all());

        $p = $this->json_('POST', '/app/projects', ['name' => 'Katalog Lebaran', 'type' => 'photo'])->assertCreated();
        $id = $p->json('data.id');
        $this->json_('PUT', "/app/projects/{$id}", ['description' => 'Foto produk kue kering', 'tags' => ['lebaran'], 'pinned' => true])->assertOk()
            ->assertJsonPath('data.pinned', true)->assertJsonPath('data.tags', ['lebaran']);
        $this->json_('POST', "/app/projects/{$id}/duplicate")->assertCreated()->assertJsonPath('data.name', 'Katalog Lebaran (salinan)');
        $this->json_('DELETE', "/app/projects/{$id}")->assertNoContent();
        $this->json_('POST', "/app/projects/{$id}/restore")->assertOk()->assertJsonPath('data.id', $id);

        $other = $this->user();
        $this->actingUser($other)->json_('PUT', "/app/projects/{$id}", ['name' => 'curi'])->assertNotFound();

        $this->admin();
        $this->json_('GET', '/admin/wallet/cards')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_f9_admin_mfa_can_be_disabled_without_deleting_secret(): void
    {
        $a = \App\Models\User::where('role', 'admin')->firstOrFail();
        $a->forceFill(['password' => 'AdminUji12345', 'mfa_enabled' => true, 'mfa_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $this->json_('POST', '/auth/admin/login', ['email' => $a->email, 'password' => 'AdminUji12345'])->assertOk()->assertJsonPath('data.mfa', 'verify');

        \App\Services\Settings::set('security.admin_mfa_required', false);
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $r = $this->json_('POST', '/auth/admin/login', ['email' => $a->email, 'password' => 'AdminUji12345'])->assertOk();
        $r->assertJsonPath('data.mfa', 'none')->assertJsonPath('data.me.mfa_passed', true);
        $this->json_('GET', '/admin/badges')->assertOk();
        $this->assertSame('JBSWY3DPEHPK3PXP', $a->fresh()->mfa_secret, 'Secret TOTP tetap tersimpan saat MFA dinonaktifkan');
        $this->assertTrue((bool) $a->fresh()->mfa_enabled);
    }
}
