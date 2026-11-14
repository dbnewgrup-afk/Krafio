#!/usr/bin/env python3
"""Uji keterhubungan 3 sisi dengan Admin sebagai pusat kendali (lokal saja).

  python3 central-check.py

A. Admin → Publik : harga paket, FAQ, promo publik, pengaturan (nama layanan CS) langsung tampil di situs publik.
B. Admin → User   : paket/fitur user, kategori forum, setup analisis SEO, status akun langsung berlaku di panel user.
C. User → Admin   : pendaftaran, laporan forum, audit SEO, pesan bantuan, tagihan, log audit muncul di panel admin.
Semua perubahan uji dikembalikan ke kondisi semula; user uji e2e-*@krafio.test dihapus (cleanup.sh).
Exit 0 bila semua lolos.
"""
from __future__ import annotations

import json
import os
import subprocess
import sys
import time

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import api  # noqa: E402  (klien Sanctum milik skill)

results: list[tuple[bool, str, str]] = []


def check(ok: bool, area: str, label: str, detail: str = '') -> None:
    results.append((ok, area, label))
    print(('✅ ' if ok else '❌ ') + f'[{area}] {label}' + (f' — {detail}' if detail else ''))


def data(r):
    return r.get('data') if isinstance(r, dict) else None


admin = api.Client('admin')
guest = api.Client(None)
undo: list = []  # fungsi pengembalian, dijalankan terbalik di akhir

try:
    # ---------- Siapkan user uji baru (Gratis) lewat pendaftaran sungguhan ----------
    email = f'e2e-central-{int(time.time())}@krafio.test'
    pw = 'KopiSusuAudit8877'
    s, r = guest.call('POST', '/api/auth/register', {'name': 'Uji Sentral', 'email': email, 'password': pw, 'password_confirmation': pw, 'agree_terms': True})
    otp = subprocess.run([sys.executable, os.path.join(HERE, 'inbox.py'), '--otp', '--to', email], capture_output=True, text=True).stdout.strip()
    s2, _ = guest.call('POST', '/api/auth/otp/verify', {'code': otp})
    os.environ['KRAFIO_PASSWORD'] = pw
    user = api.Client(email)
    user.login()
    s3, me = user.call('GET', '/api/me')
    uid = (data(me) or {}).get('id')
    check(s == 201 and s2 == 200 and s3 == 200 and uid, 'C', 'Pendaftaran + OTP berhasil', email)

    # ======================= A. Admin → Publik =======================
    s, rows = admin.call('GET', '/api/admin/r/plans?search=starter')
    plan = next((p for p in data(rows) or [] if p['code'] == 'starter'), None)
    if plan:
        old = plan['price_monthly']
        admin.call('PUT', f"/api/admin/r/plans/{plan['id']}", {'price_monthly': old + 1000})
        undo.append(lambda: admin.call('PUT', f"/api/admin/r/plans/{plan['id']}", {'price_monthly': old}))
        _, pub = guest.call('GET', '/api/public/plans')
        shown = next((p for p in data(pub) or [] if p.get('code') == 'starter'), {})
        check(shown.get('price_monthly') == old + 1000, 'A', 'Ubah harga paket di Admin → tampil di halaman Harga', f"{old} → {shown.get('price_monthly')}")
    else:
        check(False, 'A', 'Paket starter ditemukan di Admin')

    s, f = admin.call('POST', '/api/admin/r/faqs', {'category': 'general', 'question_id': 'Uji sentral: apakah FAQ admin tampil?', 'answer_id': 'Ya, langsung tampil.', 'is_active': True, 'sort': 999})
    fid = (data(f) or {}).get('id')
    if fid:
        undo.append(lambda: admin.call('DELETE', f'/api/admin/r/faqs/{fid}'))
    _, faqs = guest.call('GET', '/api/public/faqs')
    check(any('Uji sentral' in json.dumps(x, ensure_ascii=False) for x in data(faqs) or []), 'A', 'Tambah FAQ di Admin → tampil di halaman FAQ publik')
    admin.call('PUT', f'/api/admin/r/faqs/{fid}', {'is_active': False})
    _, faqs = guest.call('GET', '/api/public/faqs')
    check(not any('Uji sentral' in json.dumps(x, ensure_ascii=False) for x in data(faqs) or []), 'A', 'Nonaktifkan FAQ di Admin → hilang dari publik')

    s, c = admin.call('POST', '/api/admin/r/coupons', {'code': f'UJISENTRAL{int(time.time()) % 100000}', 'name': 'Uji sentral', 'type': 'percent', 'value': 10, 'duration': 'once',
                                                         'is_active': True, 'is_public': True, 'headline': 'Promo uji sentral', 'description': 'Promo uji keterhubungan admin.'})
    cid = (data(c) or {}).get('id')
    if cid:
        undo.append(lambda: admin.call('DELETE', f'/api/admin/r/coupons/{cid}'))
    _, promos = guest.call('GET', '/api/public/promos')
    check(any('uji sentral' in json.dumps(x, ensure_ascii=False).lower() for x in data(promos) or []), 'A', 'Buat promo publik di Admin → tampil di halaman Promo', f'HTTP {s}')

    _, st = admin.call('GET', '/api/admin/settings')
    old_hours = (data(st) or {}).get('cs.hours')
    admin.call('PUT', '/api/admin/settings', {'values': {'cs.hours': 'Uji sentral 08.00–20.00'}})
    undo.append(lambda: admin.call('PUT', '/api/admin/settings', {'values': {'cs.hours': old_hours}}))
    time.sleep(0.5)
    _, ps = guest.call('GET', '/api/public/settings')
    check('Uji sentral' in json.dumps(data(ps), ensure_ascii=False), 'A', 'Ubah jam layanan CS di Admin → tampil di situs publik')

    # ======================= B. Admin → User =======================
    s, _ = user.call('GET', '/api/app/forum/categories')
    check(s == 403, 'B', 'User Gratis: Forum terkunci sebelum diberi paket', f'HTTP {s}')
    s, _ = admin.call('POST', f'/api/admin/users/{uid}/features', {'mode': 'plan', 'plan_code': 'starter', 'reason': 'uji keterhubungan admin'})
    s2, _ = user.call('GET', '/api/app/forum/categories')
    _, me = user.call('GET', '/api/me')
    check(s == 200 and s2 == 200 and (data(me) or {}).get('plan', {}).get('code') == 'starter', 'B', 'Admin ubah paket user → fitur & paket langsung berlaku', f'paket={(data(me) or {}).get("plan", {}).get("code")}')

    s, cat = admin.call('POST', '/api/admin/r/forum-categories', {'slug': f'uji-sentral-{int(time.time()) % 100000}', 'name': 'Uji Sentral', 'is_active': True, 'sort': 999})
    catid, catslug = (data(cat) or {}).get('id'), (data(cat) or {}).get('slug')
    if catid:
        undo.append(lambda: admin.call('DELETE', f'/api/admin/r/forum-categories/{catid}'))
    _, cats = user.call('GET', '/api/app/forum/categories')
    check(any(x['slug'] == catslug for x in data(cats) or []), 'B', 'Admin buat kategori forum → muncul di menu forum user')
    admin.call('PUT', f'/api/admin/r/forum-categories/{catid}', {'is_active': False})
    _, cats = user.call('GET', '/api/app/forum/categories')
    check(not any(x['slug'] == catslug for x in data(cats) or []), 'B', 'Admin nonaktifkan kategori → hilang dari user')

    admin.call('PUT', '/api/admin/settings', {'values': {'seo.content.enabled': False}})
    undo.append(lambda: admin.call('PUT', '/api/admin/settings', {'values': {'seo.content.enabled': True}}))
    time.sleep(0.5)
    _, cfg = user.call('GET', '/api/app/seo/config')
    check((data(cfg) or {}).get('enabled') is False, 'B', 'Admin matikan Analisis SEO → form user ikut nonaktif')
    admin.call('PUT', '/api/admin/settings', {'values': {'seo.content.enabled': True}})

    # ======================= C. User → Admin =======================
    s, t = user.call('POST', '/api/app/forum/threads', {'category': 'umkm', 'title': 'Uji sentral topik user', 'body': 'Topik dari user untuk uji keterhubungan admin.'})
    tid = (data(t) or {}).get('id')
    s, _ = user.call('POST', '/api/app/forum/reports', {'type': 'system', 'reason': 'bug', 'message': 'Uji sentral: laporan kendala dari user ke admin.'})
    _, reps = admin.call('GET', '/api/admin/r/forum-reports?search=Uji sentral')
    check(s == 201 and len(data(reps) or []) > 0, 'C', 'User kirim laporan forum → masuk Admin › Forum › Laporan')

    s, a = user.call('POST', '/api/app/seo/analyze', {'content_type': 'post', 'keyphrase': 'uji sentral', 'content': 'Uji sentral adalah konten singkat untuk menguji keterhubungan audit SEO dengan admin.'})
    _, aud = admin.call('GET', '/api/admin/r/seo-audits?search=uji sentral')
    check(s == 201 and len(data(aud) or []) > 0, 'C', 'User jalankan analisis SEO → tercatat di Admin › SEO dashboard')

    s, _ = user.call('POST', '/api/app/inbox', {'subject': 'Uji sentral bantuan', 'body': 'Pesan bantuan dari user untuk menguji inbox admin.'})
    _, inbox = admin.call('GET', '/api/admin/inbox?search=Uji sentral')
    check(s in (200, 201) and 'Uji sentral' in json.dumps(inbox, ensure_ascii=False), 'C', 'User kirim pesan bantuan → masuk Admin › Inbox', f'HTTP {s}')

    s, o = user.call('POST', '/api/app/orders', {'kind': 'addon', 'code': 'credits-200', 'source': 'gateway'})
    ref = ((data(o) or {}).get('payment') or {}).get('reference')
    _, pays = admin.call('GET', f'/api/admin/r/payments?search={ref}')
    check(bool(ref) and len(data(pays) or []) > 0, 'C', 'User buat tagihan → tampil di Admin › Pembayaran', ref or f'HTTP {s}')

    _, users = admin.call('GET', f'/api/admin/users?search={email}')
    check(any(u.get('id') == uid for u in data(users) or []), 'C', 'User baru mendaftar → tampil di Admin › Pengguna (email tersamar demi privasi)')

    # Admin moderasi → user diberi tahu (lingkaran penuh)
    if tid:
        admin.call('PUT', f'/api/admin/r/forum-threads/{tid}', {'status': 'hidden', 'moderation_note': 'Uji sentral moderasi'})
        s, _ = user.call('GET', f'/api/app/forum/threads/{tid}')
        _, notif = user.call('GET', '/api/me/notifications')
        check(s == 404 and 'dimoderasi' in json.dumps(notif, ensure_ascii=False), 'B', 'Admin sembunyikan topik → hilang dari user & user dapat notifikasi')

    _, logs = admin.call('GET', '/api/admin/r/audit-logs?search=forum-categories')
    check(len(data(logs) or []) > 0 or 'admin.' in json.dumps(logs), 'C', 'Tindakan Admin tercatat di Log aktivitas (audit trail)')

    # Status akun dikendalikan Admin (paling akhir karena memblokir user)
    admin.call('POST', f'/api/admin/users/{uid}/status', {'status': 'suspended', 'reason': 'Uji keterhubungan admin'})
    s, r = user.call('GET', '/api/app/projects')
    check(s in (401, 403), 'B', 'Admin suspend akun → panel user langsung terblokir', f'HTTP {s} {(r or {}).get("code") if isinstance(r, dict) else ""}')
finally:
    for fn in reversed(undo):
        try:
            fn()
        except Exception as e:  # pengembalian tetap dilanjutkan
            print('⚠️  gagal mengembalikan:', e)
    subprocess.run([os.path.join(HERE, 'cleanup.sh')])

bad = [r for r in results if not r[0]]
print(f"\n{'✅' if not bad else '❌'} {len(results) - len(bad)}/{len(results)} cek keterhubungan lolos (A: Admin→Publik, B: Admin→User, C: User→Admin)")
sys.exit(1 if bad else 0)
