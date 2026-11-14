<?php

namespace Tests\Feature;

use App\Models\ForumCategory;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\User;
use App\Services\Credits;
use Illuminate\Notifications\DatabaseNotification;

/** Forum Diskusi: privat untuk pelanggan (entitlement paket forum.access), dikontrol penuh oleh Admin. */
class ForumTest extends KrafioTestCase
{
    private function subscriber(string $plan = 'starter'): User
    {
        $u = $this->user();
        $this->admin();
        $this->json_('POST', "/admin/users/{$u->id}/features", ['mode' => 'plan', 'plan_code' => $plan, 'reason' => 'uji forum'])->assertOk();

        return $u->fresh();
    }

    private function newThread(User $u, string $cat = 'umkm', string $title = 'Cara promosi warung kopi'): array
    {
        return $this->actingUser($u)->json_('POST', '/app/forum/threads', ['category' => $cat, 'title' => $title, 'body' => 'Bagaimana cara promosi warung kopi di Instagram?'])
            ->assertCreated()->json('data');
    }

    public function test_only_paid_plans_can_access_and_no_credits_are_used(): void
    {
        $free = $this->user();
        $this->actingUser($free)->json_('GET', '/app/forum/categories')->assertStatus(403)->assertJsonPath('code', 'feature_locked');
        $this->json_('POST', '/app/forum/threads', ['category' => 'umkm', 'title' => 'Halo semua', 'body' => 'Salam kenal dari pengguna gratis'])->assertStatus(403);

        $u = $this->subscriber();
        $credits = Credits::summary($u);
        $cats = $this->actingUser($u)->json_('GET', '/app/forum/categories')->assertOk()->json('data');
        $this->assertContains('umkm', array_column($cats, 'slug'));
        $this->assertContains('pengumuman', array_column($cats, 'slug'));
        $t = $this->newThread($u);
        $this->json_('POST', "/app/forum/threads/{$t['id']}/posts", ['body' => 'Tambahan: saya di Bandung.'])->assertCreated();
        $this->assertEquals($credits, Credits::summary($u->fresh()), 'Forum tidak boleh memakai kredit');
        $this->assertTrue((bool) $this->json_('GET', '/me')->json('data.entitlements')['forum.access']['enabled']);

        // Admin tidak memakai forum dari panel pengguna
        $this->admin();
        $this->json_('GET', '/app/forum/categories')->assertStatus(403);
    }

    public function test_users_post_reply_edit_own_but_cannot_touch_setup(): void
    {
        $a = $this->subscriber();
        $b = $this->subscriber('pro');
        $t = $this->newThread($a);
        // Kategori Pengumuman hanya untuk Admin
        $this->json_('POST', '/app/forum/threads', ['category' => 'pengumuman', 'title' => 'Info penting', 'body' => 'Isi pengumuman dari user biasa'])->assertStatus(403)->assertJsonPath('code', 'admin_only');
        // Balasan pengguna lain → penulis topik mendapat notifikasi
        $this->actingUser($b)->json_('POST', "/app/forum/threads/{$t['id']}/posts", ['body' => 'Coba konten video pendek setiap hari.'])->assertCreated();
        $this->assertTrue(DatabaseNotification::where('notifiable_id', $a->id)->where('data->category', 'forum')->exists());
        $detail = $this->json_('GET', "/app/forum/threads/{$t['id']}")->assertOk()->json('data');
        $this->assertSame(1, $detail['replies_count']);
        $this->assertCount(1, $detail['posts']['data']);
        // B tidak dapat menyunting/menghapus topik A
        $this->json_('PATCH', "/app/forum/threads/{$t['id']}", ['title' => 'Diubah orang lain'])->assertStatus(403);
        $this->json_('DELETE', "/app/forum/threads/{$t['id']}")->assertNotFound();
        // A menyunting dalam 30 menit; setelahnya ditolak
        $this->actingUser($a)->json_('PATCH', "/app/forum/threads/{$t['id']}", ['title' => 'Cara promosi warung kopi lokal'])->assertOk()->assertJsonPath('data.title', 'Cara promosi warung kopi lokal');
        $this->travel(31)->minutes();
        $this->json_('PATCH', "/app/forum/threads/{$t['id']}", ['title' => 'Terlambat diubah'])->assertStatus(422)->assertJsonPath('code', 'edit_window_closed');
        $this->travelBack();
        // Pengguna tidak dapat mengakses pengaturan forum Admin
        $this->json_('GET', '/admin/r/forum-categories')->assertStatus(403);
        $this->json_('POST', '/admin/r/forum-categories', ['slug' => 'liar', 'name' => 'Liar'])->assertStatus(403);
        // Hapus topik sendiri
        $this->json_('DELETE', "/app/forum/threads/{$t['id']}")->assertNoContent();
        $this->json_('GET', "/app/forum/threads/{$t['id']}")->assertNotFound();
    }

    public function test_reports_reach_admin_and_admin_moderates(): void
    {
        $a = $this->subscriber();
        $b = $this->subscriber();
        $t = $this->newThread($a);
        $post = $this->actingUser($b)->json_('POST', "/app/forum/threads/{$t['id']}/posts", ['body' => 'Beli followers murah di sini!!!'])->assertCreated()->json('data');

        // A melaporkan balasan B, lalu kendala sistem; tidak boleh melapor diri sendiri / ganda
        $this->actingUser($a)->json_('POST', '/app/forum/reports', ['type' => 'post', 'post_id' => $post['id'], 'reason' => 'spam', 'message' => 'Balasan ini spam jualan followers.'])->assertCreated();
        $this->json_('POST', '/app/forum/reports', ['type' => 'post', 'post_id' => $post['id'], 'reason' => 'spam', 'message' => 'Balasan ini spam jualan followers.'])->assertStatus(409);
        $this->json_('POST', '/app/forum/reports', ['type' => 'thread', 'thread_id' => $t['id'], 'reason' => 'other', 'message' => 'Melaporkan topik sendiri.'])->assertStatus(422);
        $this->json_('POST', '/app/forum/reports', ['type' => 'system', 'reason' => 'bug', 'message' => 'Tombol balas kadang tidak merespons.', 'page_url' => '/app/forum'])->assertCreated();
        $admin = User::where('role', 'admin')->first();
        $this->assertTrue(DatabaseNotification::where('notifiable_id', $admin->id)->where('data->title', 'Laporan forum baru')->exists());
        $this->assertCount(2, $this->json_('GET', '/app/forum/reports')->json('data'));

        $this->admin();
        // Sembunyikan balasan spam → hitungan balasan terkoreksi, penulis diberi tahu
        $this->json_('PUT', "/admin/r/forum-posts/{$post['id']}", ['status' => 'hidden', 'moderation_note' => 'Spam'])->assertOk();
        $this->assertSame(0, ForumThread::find($t['id'])->replies_count);
        $this->assertTrue(DatabaseNotification::where('notifiable_id', $b->id)->where('data->title', 'Tulisan forum dimoderasi')->exists());
        // Selesaikan laporan → pelapor diberi tahu
        $rep = ForumReport::where('type', 'post')->firstOrFail();
        $this->json_('PUT', "/admin/r/forum-reports/{$rep->id}", ['status' => 'resolved', 'admin_note' => 'Balasan sudah disembunyikan.'])->assertOk();
        $this->assertNotNull($rep->fresh()->handled_at);
        $this->assertTrue(DatabaseNotification::where('notifiable_id', $a->id)->where('data->title', 'Laporan forum Anda selesai ditangani')->exists());
        // Kunci topik → tidak dapat dibalas
        $this->json_('PUT', "/admin/r/forum-threads/{$t['id']}", ['is_locked' => true])->assertOk();
        $this->actingUser($b)->json_('POST', "/app/forum/threads/{$t['id']}/posts", ['body' => 'Masih bisa balas?'])->assertStatus(422)->assertJsonPath('code', 'thread_locked');
        // Bisukan B → tidak bisa membuat topik
        $this->admin();
        $this->json_('POST', '/admin/r/forum-mutes', ['user_id' => $b->id, 'until' => now()->addDays(3)->toDateTimeString(), 'reason' => 'Spam berulang'])->assertCreated();
        $this->json_('POST', '/admin/r/forum-mutes', ['user_id' => $admin->id, 'reason' => 'x'])->assertStatus(422);
        $this->actingUser($b)->json_('POST', '/app/forum/threads', ['category' => 'umkm', 'title' => 'Topik baru dari B', 'body' => 'Isi topik baru dari pengguna B'])->assertStatus(403)->assertJsonPath('code', 'forum_muted');
        $this->assertTrue($this->json_('GET', '/app/forum/categories')->json('meta.muted'));
        // Admin membuat pengumuman & membalas
        $this->admin();
        $ann = $this->json_('POST', '/admin/r/forum-threads', ['category_id' => ForumCategory::where('slug', 'pengumuman')->value('id'), 'title' => 'Fitur forum resmi dibuka', 'body' => 'Selamat datang di forum pelanggan Krafio.', 'is_pinned' => true])->assertCreated()->json('data');
        $this->json_('POST', '/admin/r/forum-posts', ['thread_id' => $t['id'], 'body' => 'Terima kasih laporannya, sudah kami tangani.'])->assertCreated();
        $list = $this->actingUser($a)->json_('GET', '/app/forum/threads')->assertOk()->json('data');
        $this->assertSame($ann['id'], $list[0]['id'], 'Pengumuman tersemat tampil paling atas');
        $this->assertTrue($list[0]['author']['is_admin']);
        $this->assertSame('Admin Krafio', $list[0]['author']['name']);
    }

    public function test_category_can_be_limited_to_specific_plans_and_hidden_threads_are_private(): void
    {
        $starter = $this->subscriber('starter');
        $pro = $this->subscriber('pro');
        $this->admin();
        $vip = $this->json_('POST', '/admin/r/forum-categories', ['slug' => 'pro-lounge', 'name' => 'Pro Lounge', 'plan_codes' => ['pro', 'business'], 'is_active' => true, 'sort' => 5])->assertCreated()->json('data');
        $this->json_('POST', '/admin/r/forum-categories', ['slug' => 'salah', 'name' => 'Salah', 'plan_codes' => ['tidak-ada']])->assertStatus(422);

        $this->assertNotContains('pro-lounge', array_column($this->actingUser($starter)->json_('GET', '/app/forum/categories')->json('data'), 'slug'));
        $this->json_('POST', '/app/forum/threads', ['category' => 'pro-lounge', 'title' => 'Masuk lounge', 'body' => 'Coba masuk dari paket starter'])->assertNotFound();
        $t = $this->newThread($pro, 'pro-lounge', 'Diskusi khusus Pro');
        $this->actingUser($starter)->json_('GET', "/app/forum/threads/{$t['id']}")->assertNotFound();

        // Topik disembunyikan Admin tidak terlihat siapa pun dari panel pengguna
        $this->admin();
        $this->json_('PUT', "/admin/r/forum-threads/{$t['id']}", ['status' => 'hidden'])->assertOk();
        $this->actingUser($pro)->json_('GET', "/app/forum/threads/{$t['id']}")->assertNotFound();
        $this->assertSame(0, $this->json_('GET', '/app/forum/threads', ['category' => 'pro-lounge'])->json('meta.total'));
        $this->assertSame((int) $vip['id'], (int) ForumCategory::where('slug', 'pro-lounge')->value('id'));
    }

    public function test_security_validation_and_abuse_cases(): void
    {
        $a = $this->subscriber();
        $b = $this->subscriber();
        $free = $this->user();
        $t = $this->newThread($a);
        $p = $this->actingUser($b)->json_('POST', "/app/forum/threads/{$t['id']}/posts", ['body' => 'Balasan dari B'])->assertCreated()->json('data');

        // Tamu: semua endpoint 401
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        foreach ([['GET', '/app/forum/categories'], ['GET', '/app/forum/threads'], ['GET', "/app/forum/threads/{$t['id']}"], ['POST', '/app/forum/threads'], ['POST', "/app/forum/threads/{$t['id']}/posts"], ['POST', '/app/forum/reports'], ['GET', '/app/forum/reports']] as [$m, $u]) {
            $this->json_($m, $u, [])->assertStatus(401);
        }
        // Paket Gratis: semua endpoint 403 (termasuk baca topik lewat ID & lapor)
        $this->actingUser($free);
        foreach ([['GET', '/app/forum/threads'], ['GET', "/app/forum/threads/{$t['id']}"], ['POST', "/app/forum/threads/{$t['id']}/posts", ['body' => 'coba']], ['POST', '/app/forum/reports', ['type' => 'system', 'reason' => 'bug', 'message' => 'Mencoba melapor dari paket gratis']], ['GET', '/app/forum/reports'], ['PATCH', "/app/forum/posts/{$p['id']}", ['body' => 'ubah']], ['DELETE', "/app/forum/threads/{$t['id']}"]] as $c) {
            $this->json_($c[0], $c[1], $c[2] ?? [])->assertStatus(403);
        }

        // Mass assignment diabaikan: tidak bisa menyematkan, menyembunyikan, atau menulis atas nama orang lain
        $mal = $this->actingUser($b)->json_('POST', '/app/forum/threads', ['category' => 'umkm', 'title' => 'Topik dengan field liar', 'body' => 'Mencoba menyisipkan field terlarang.',
            'is_pinned' => true, 'is_locked' => true, 'status' => 'hidden', 'user_id' => $a->id, 'replies_count' => 999])->assertCreated()->json('data');
        $row = ForumThread::find($mal['id']);
        $this->assertFalse($row->is_pinned);
        $this->assertFalse($row->is_locked);
        $this->assertSame('published', $row->status);
        $this->assertSame($b->id, $row->user_id);
        $this->assertSame(0, $row->replies_count);
        $this->json_('PATCH', "/app/forum/threads/{$mal['id']}", ['is_pinned' => true, 'status' => 'hidden', 'title' => 'Judul setelah disunting'])->assertOk();
        $this->assertFalse(ForumThread::find($mal['id'])->is_pinned);
        $this->assertSame('published', ForumThread::find($mal['id'])->status);

        // IDOR: B tidak dapat menyunting/menghapus milik A
        $this->json_('PATCH', "/app/forum/threads/{$t['id']}", ['body' => 'Diubah B tanpa izin.'])->assertStatus(403);
        $aPost = $this->actingUser($a)->json_('POST', "/app/forum/threads/{$t['id']}/posts", ['body' => 'Balasan milik A'])->assertCreated()->json('data');
        $this->actingUser($b)->json_('PATCH', "/app/forum/posts/{$aPost['id']}", ['body' => 'Diubah oleh B'])->assertStatus(403);
        $this->json_('DELETE', "/app/forum/posts/{$aPost['id']}")->assertNotFound();

        // Batas panjang & nilai tidak valid
        $this->json_('POST', '/app/forum/threads', ['category' => 'umkm', 'title' => str_repeat('a', 151), 'body' => 'Isi yang cukup panjang.'])->assertStatus(422)->assertJsonValidationErrors('title');
        $this->json_('POST', '/app/forum/threads', ['category' => 'umkm', 'title' => 'Judul normal', 'body' => str_repeat('x', 10001)])->assertStatus(422)->assertJsonValidationErrors('body');
        $this->json_('POST', "/app/forum/threads/{$t['id']}/posts", ['body' => str_repeat('x', 5001)])->assertStatus(422);
        $this->json_('POST', '/app/forum/threads', ['category' => 'tidak-ada', 'title' => 'Judul normal', 'body' => 'Isi yang cukup panjang.'])->assertNotFound();
        $this->json_('POST', '/app/forum/reports', ['type' => 'system', 'reason' => 'hack', 'message' => 'Alasan tidak valid dikirim.'])->assertStatus(422);
        $this->json_('POST', '/app/forum/reports', ['type' => 'post', 'post_id' => 999999, 'reason' => 'spam', 'message' => 'Balasan yang tidak ada.'])->assertNotFound();
        $this->json_('POST', '/app/forum/reports', ['type' => 'user', 'user_id' => User::where('role', 'admin')->value('id'), 'reason' => 'abuse', 'message' => 'Mencoba melaporkan admin.'])->assertNotFound();

        // Pencarian: karakter SQL & wildcard aman (tidak error, tidak mencocokkan semua)
        $this->json_('GET', '/app/forum/threads', ['q' => "%' OR 1=1 --"])->assertOk()->assertJsonPath('meta.total', 0);
        $this->json_('GET', '/app/forum/threads', ['q' => '_'])->assertOk()->assertJsonPath('meta.total', 0);
        $this->json_('GET', '/app/forum/threads', ['q' => 'kopi'])->assertOk()->assertJsonPath('meta.total', 1);

        // Topik yang dihapus penulisnya tidak bisa dibalas/dilaporkan
        $this->json_('DELETE', "/app/forum/threads/{$mal['id']}")->assertNoContent();
        $this->json_('POST', "/app/forum/threads/{$mal['id']}/posts", ['body' => 'Balas topik terhapus'])->assertNotFound();
        $this->actingUser($a)->json_('POST', '/app/forum/reports', ['type' => 'thread', 'thread_id' => $mal['id'], 'reason' => 'spam', 'message' => 'Topik sudah dihapus.'])->assertNotFound();

        // Rate limit: maksimal 5 topik per menit
        $this->actingUser($a);
        $codes = [];
        for ($i = 0; $i < 6; $i++) {
            $codes[] = $this->json_('POST', '/app/forum/threads', ['category' => 'kreator', 'title' => 'Topik beruntun '.$i, 'body' => 'Isi topik beruntun nomor '.$i])->status();
        }
        $this->assertContains(429, $codes);
    }
}
