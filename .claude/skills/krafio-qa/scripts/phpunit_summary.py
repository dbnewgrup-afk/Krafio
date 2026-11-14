#!/usr/bin/env python3
"""Ringkas keluaran JSON `php artisan test` (baris terakhir stdin)."""
import json
import sys

text = sys.stdin.read()
i = text.rfind('{"tool":"phpunit"')
try:
    d = json.JSONDecoder(strict=False).raw_decode(text[i:])[0] if i >= 0 else json.loads(text.strip().splitlines()[-1])
except Exception:
    print('  (keluaran test bukan JSON)')
    print('\n'.join(text.strip().splitlines()[-30:]))
    sys.exit(1)
ok = d['result'] == 'passed'
mark = '✅' if ok else '❌'
print('  %s %s/%s test lulus, %s asersi, %.1f dtk' % (mark, d.get('passed', 0), d['tests'], d.get('assertions', 0), d.get('duration_ms', 0) / 1000))
for f in d.get('failures', []):
    m = f['message']
    i = m.find('The following exception occurred during the last request:')
    detail = m[i + 58:i + 300] if i >= 0 else m[:260]
    print('   ✗', f['test'].split('\\')[-1], '|', ' '.join(detail.split()))
sys.exit(0 if ok else 1)
