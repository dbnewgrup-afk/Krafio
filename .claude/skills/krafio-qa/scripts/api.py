#!/usr/bin/env python3
"""Klien API Krafio yang sudah login, untuk uji alur end-to-end persis seperti SPA (Sanctum cookie + XSRF).

Contoh:
  api.py GET /api/me
  api.py --as demo GET /api/app/seo/config
  api.py POST /api/app/seo/analyze '{"content_type":"post","keyphrase":"kopi","content":"..."}'
  api.py POST /api/app/seo/analyze @/path/form.json --max 2000
  api.py GET /api/app/seo/audits -q kind=content
  api.py --guest GET /api/public/settings

Akun: --as qa (default, butuh scripts/qa-account.sh) | --as admin (admin QA, butuh scripts/admin-account.sh --mfa-off)
      | --as demo | --as EMAIL (password dari env KRAFIO_PASSWORD).
Admin: login lewat /api/auth/admin/login; konfirmasi kata sandi (reauth) untuk aksi keuangan dilakukan otomatis.
Sesi disimpan di ~/.krafio-tools/run/cookies-<akun>.txt; login ulang otomatis bila 401/419.
Keluaran: baris pertama "HTTP <status>", lalu JSON (dipotong --max karakter). Exit code 0 bila 2xx.
"""
from __future__ import annotations

import argparse
import http.cookiejar
import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request

BASE = os.environ.get('KRAFIO_WEB', 'http://localhost:5173')
RUN = os.path.expanduser('~/.krafio-tools/run')
DEMO = ('demo@krafio.local', 'DemoKrafio2026')  # akun demo lokal (README §8)


def credentials(who: str):
    if who == 'demo':
        return DEMO
    if who in ('qa', 'admin'):
        name, script = ('qa-credentials.json', 'qa-account.sh') if who == 'qa' else ('qa-admin-credentials.json', 'admin-account.sh --mfa-off')
        path = os.path.expanduser('~/.krafio-tools/' + name)
        if not os.path.exists(path):
            sys.exit('Akun belum ada. Jalankan .claude/skills/krafio-qa/scripts/' + script)
        c = json.load(open(path))
        return c['login'], c['password']
    pw = os.environ.get('KRAFIO_PASSWORD')
    if not pw:
        sys.exit('Set KRAFIO_PASSWORD untuk akun ' + who)
    return who, pw


class Client:
    def __init__(self, who: str | None):
        os.makedirs(RUN, exist_ok=True)
        self.who = who
        name = 'guest' if who is None else ''.join(ch if ch.isalnum() else '_' for ch in who)
        self.jar = http.cookiejar.MozillaCookieJar(os.path.join(RUN, f'cookies-{name}.txt'))
        try:
            self.jar.load(ignore_discard=True, ignore_expires=True)
        except (FileNotFoundError, http.cookiejar.LoadError):
            pass
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))

    def xsrf(self) -> str:
        return next((urllib.parse.unquote(c.value) for c in self.jar if c.name == 'XSRF-TOKEN'), '')

    def raw(self, method: str, path: str, body=None):
        data = json.dumps(body).encode() if body is not None else None
        path = urllib.parse.quote(path, safe="/?=&%:+,@-._~")  # spasi & karakter lain di query aman
        req = urllib.request.Request(BASE + path, data=data, method=method, headers={
            'Accept': 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': self.xsrf(),
            'Origin': BASE, 'Referer': BASE + '/', 'X-Requested-With': 'XMLHttpRequest'})
        try:
            with self.op.open(req, timeout=120) as r:
                status, text = r.status, r.read().decode()
        except urllib.error.HTTPError as e:
            status, text = e.code, e.read().decode()
        self.jar.save(ignore_discard=True, ignore_expires=True)
        try:
            return status, json.loads(text) if text.strip() else None
        except json.JSONDecodeError:
            return status, text[:2000]

    def login(self):
        self.raw('GET', '/sanctum/csrf-cookie')
        login, pw = credentials(self.who)
        if self.who == 'admin':
            s, r = self.raw('POST', '/api/auth/admin/login', {'email': login, 'password': pw})
            if s == 200 and (r or {}).get('data', {}).get('mfa') != 'none':
                sys.exit('Admin butuh MFA. Matikan dulu: scripts/admin-account.sh --mfa-off')
        else:
            s, r = self.raw('POST', '/api/auth/login', {'login': login, 'password': pw})
        if s != 200:
            sys.exit(f'Login {login} gagal: HTTP {s} {json.dumps(r, ensure_ascii=False)[:300]}')

    def call(self, method: str, path: str, body=None):
        if not self.xsrf():
            self.raw('GET', '/sanctum/csrf-cookie')
        s, r = self.raw(method, path, body)
        code = r.get('code') if isinstance(r, dict) else None
        if self.who and (s in (401, 419) or code in ('mfa_required', 'session_expired')):
            self.login()
            s, r = self.raw(method, path, body)
            code = r.get('code') if isinstance(r, dict) else None
        if self.who == 'admin' and code == 'reauth_required':
            self.raw('POST', '/api/admin/reauth', {'password': credentials('admin')[1]})
            s, r = self.raw(method, path, body)
        return s, r


def main():
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument('--as', dest='who', default='qa')
    p.add_argument('--guest', action='store_true', help='tanpa login')
    p.add_argument('--max', type=int, default=6000, help='potong keluaran JSON (karakter)')
    p.add_argument('-q', action='append', default=[], help='query string k=v (boleh berulang)')
    p.add_argument('method')
    p.add_argument('path')
    p.add_argument('body', nargs='?')
    a = p.parse_args()
    body = None
    if a.body:
        body = json.load(open(a.body[1:])) if a.body.startswith('@') else json.loads(a.body)
    path = a.path + (('&' if '?' in a.path else '?') + urllib.parse.urlencode([tuple(kv.split("=", 1)) for kv in a.q]) if a.q else '')
    c = Client(None if a.guest else a.who)
    s, r = c.call(a.method.upper(), path, body)
    print(f'HTTP {s}')
    out = json.dumps(r, ensure_ascii=False, indent=1) if not isinstance(r, str) else r
    print(out if len(out) <= a.max else out[:a.max] + f'\n… (dipotong, total {len(out)} karakter)')
    sys.exit(0 if 200 <= s < 300 else 1)


if __name__ == '__main__':
    main()
