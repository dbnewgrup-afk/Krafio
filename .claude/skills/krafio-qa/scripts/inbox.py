#!/usr/bin/env python3
"""Kotak masuk uji Krafio (lokal): email yang "terkirim" lewat mailer `log`, plus catatan mail_logs & wa_logs.

  inbox.py                         → 5 email terakhir (penerima, subjek, isi ringkas, kode & tautan)
  inbox.py --to user@x.test        → filter penerima
  inbox.py --otp --to user@x.test  → cetak HANYA kode 6 digit terbaru (untuk skrip: OTP=$(inbox.py --otp --to ...))
  inbox.py --link --to user@x.test → cetak tautan terbaru (mis. reset kata sandi)
  inbox.py --logs                  → 10 baris terakhir mail_logs & wa_logs (status sent/failed/skipped)
  inbox.py --wait 20 --otp --to …  → tunggu sampai email baru muncul (detik)

Hanya membaca email yang dikirim dengan mailer `log` (MAIL_MAILER=log tanpa integrasi SMTP aktif di Admin).
Bila SMTP Admin aktif, email terkirim sungguhan dan tidak tercatat di sini (lihat --logs).
"""
from __future__ import annotations

import argparse
import os
import quopri
import re
import subprocess
import sys
import time

ROOT = subprocess.run(['git', '-C', os.path.dirname(os.path.abspath(__file__)), 'rev-parse', '--show-toplevel'], capture_output=True, text=True).stdout.strip() or os.path.expanduser('~/Krafio')
LOG = os.path.join(ROOT, 'apps/backend/storage/logs/laravel.log')
HEAD = re.compile(r'^\[(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)\] \w+\.DEBUG: From: (.*)$')


def emails(tail_bytes: int = 4_000_000) -> list[dict]:
    if not os.path.exists(LOG):
        return []
    with open(LOG, 'rb') as f:
        f.seek(max(0, os.path.getsize(LOG) - tail_bytes))
        lines = f.read().decode('utf-8', 'replace').splitlines()
    out, cur = [], None
    for line in lines:
        m = HEAD.match(line)
        if m:
            cur = {'time': m.group(1), 'from': m.group(2), 'to': '', 'subject': '', 'headers': True, 'body': [], 'qp': False}
            out.append(cur)
            continue
        if cur is None:
            continue
        if re.match(r'^\[\d{4}-\d\d-\d\d \d\d:\d\d:\d\d\] ', line):
            cur = None  # entri log berikutnya
            continue
        if cur['headers']:
            if line.startswith('To: '):
                cur['to'] = line[4:].strip()
            elif line.startswith('Subject: '):
                cur['subject'] = line[9:].strip()
            elif 'quoted-printable' in line:
                cur['qp'] = True
            elif line.strip() == '':
                cur['headers'] = False
        else:
            cur['body'].append(line)
    for e in out:
        body = '\n'.join(e.pop('body'))
        if e.pop('qp'):
            body = quopri.decodestring(body.encode()).decode('utf-8', 'replace')
        e.pop('headers')
        e['body'] = body.strip()
        e['codes'] = re.findall(r'(?<!\d)\d{6}(?!\d)', body)
        e['links'] = re.findall(r'https?://[^\s<>"\')]+', body)
    return out


def logs() -> None:
    q = ("select 'mail', created_at, `to`, coalesce(template,''), status, coalesce(error,'') from mail_logs "
         "union all select 'wa', created_at, `to`, coalesce(template, event, ''), status, coalesce(error,'') from wa_logs order by 2 desc limit 10")
    r = subprocess.run(['mysql', '--defaults-file=' + os.path.expanduser('~/.krafio-tools/my.cnf'), '-N', 'krafio', '-e', q], capture_output=True, text=True)
    print(r.stdout or r.stderr or '(kosong)')


def main() -> None:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument('--to')
    p.add_argument('-n', type=int, default=5)
    p.add_argument('--otp', action='store_true')
    p.add_argument('--link', action='store_true')
    p.add_argument('--logs', action='store_true')
    p.add_argument('--wait', type=int, default=0)
    a = p.parse_args()
    if a.logs:
        return logs()
    deadline = time.time() + a.wait
    start = len(emails())
    while True:
        es = [e for e in emails() if not a.to or a.to.lower() in e['to'].lower()]
        fresh = not a.wait or len(emails()) > start
        if es and fresh or time.time() >= deadline:
            break
        time.sleep(1)
    if not es:
        sys.exit('Tidak ada email' + (f' untuk {a.to}' if a.to else ''))
    if a.otp:
        codes = [c for e in reversed(es) for c in e['codes']]
        if not codes:
            sys.exit('Tidak ada kode 6 digit di email terbaru')
        print(codes[0])
        return
    if a.link:
        links = [l for e in reversed(es) for l in e['links']]
        if not links:
            sys.exit('Tidak ada tautan di email terbaru')
        print(links[0])
        return
    for e in es[-a.n:]:
        print(f"── {e['time']} → {e['to']}\n   Subjek: {e['subject']}")
        print('   ' + e['body'][:400].replace('\n', '\n   '))
        if e['codes']:
            print('   kode:', ', '.join(e['codes']))
        if e['links']:
            print('   tautan:', ', '.join(e['links'][:3]))


if __name__ == '__main__':
    main()
