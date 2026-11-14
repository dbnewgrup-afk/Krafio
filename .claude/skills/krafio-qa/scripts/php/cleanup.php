<?php
// Bersihkan data uji (lokal saja): user e2e-*@krafio.test (+ cascade) dan konten forum buatan akun QA.
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\ForumPost;
use App\Models\User;

if (! app()->environment('local')) { echo "TOLAK: hanya APP_ENV=local\n"; return; }
$qa = User::whereIn('email', ['qa@krafio.test'])->pluck('id');
$reports = ForumReport::whereIn('user_id', $qa)->delete();
$posts = ForumPost::whereIn('user_id', $qa)->delete();
$threads = ForumThread::whereIn('user_id', $qa)->delete();
foreach (ForumThread::all() as $t) { App\Services\Forum::refreshThreadStats($t); }
$users = User::where('email', 'like', 'e2e-%@krafio.test')->get();
// Tiket memakai ON DELETE SET NULL (arsip) → hapus tiket user uji sebelum user-nya
$tickets = App\Models\Ticket::whereIn('user_id', $users->pluck('id'))->delete();
$tickets += App\Models\Ticket::whereNull('user_id')->where(fn ($q) => $q->where('subject', 'like', 'Uji sentral%')->orWhere('subject', 'like', 'Uji E2E%'))->delete();
foreach ($users as $u) { $u->forceDelete(); }
echo "BERSIH {$users->count()} user e2e, {$tickets} tiket, {$threads} topik, {$posts} balasan, {$reports} laporan forum milik QA\n";
