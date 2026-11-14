#!/bin/zsh
# Pemeriksaan otomatis Krafio dengan ringkasan singkat.
#   check.sh                 → backend (semua test) + frontend (tsc, oxlint, build)
#   check.sh backend [pola]  → php artisan test --filter=<pola>
#   check.sh frontend        → tsc -b, oxlint (hanya error dihitung gagal), vite build
#   check.sh security        → composer audit + npm audit (kerentanan dependensi; butuh internet)
# Butuh MySQL hidup (test memakai database krafio_test). Exit 0 bila semua lolos.
HERE="${0:A:h}"
source "$HERE/env.sh"
what="${1:-all}"; filter="${2:-}"; rc=0

backend() {
  echo "▶ Backend: php artisan test ${filter:+--filter=$filter}"
  mysqladmin --defaults-file="$HOME/.krafio-tools/my.cnf" ping >/dev/null 2>&1 || { echo "  ❌ MySQL mati → krafio-start"; return 1; }
  local out; out=$(cd "$KRAFIO_BACKEND" && php artisan test ${filter:+--filter="$filter"} 2>&1)
  print -r -- "$out" | python3 "$HERE/phpunit_summary.py"
}

frontend() {
  cd "$KRAFIO_FRONTEND" || return 1
  echo "▶ Frontend"
  local r=0
  if npx tsc -b >/tmp/krafio-tsc.log 2>&1; then echo "  ✅ tsc -b"; else echo "  ❌ tsc -b"; head -20 /tmp/krafio-tsc.log; r=1; fi
  local lint; lint=$(npx oxlint 2>&1); local errs=$(echo "$lint" | grep -cE "^\s*(×|x) |error\[|: error" ); local warns=$(echo "$lint" | grep -c "warning")
  if echo "$lint" | grep -qE "Found [0-9]+ warnings? and [1-9]"; then echo "  ❌ oxlint ada error"; echo "$lint" | grep -B1 -A3 "error" | head -20; r=1; else echo "  ✅ oxlint (0 error, $warns peringatan)"; fi
  if npx vite build >/tmp/krafio-build.log 2>&1; then echo "  ✅ vite build ($(grep -o 'built in [0-9.]*m\?s' /tmp/krafio-build.log))"; else echo "  ❌ vite build"; tail -20 /tmp/krafio-build.log; r=1; fi
  return $r
}

security() {
  echo "▶ Keamanan dependensi"
  local r=0 c n
  c=$(cd "$KRAFIO_BACKEND" && composer audit --format=json 2>/dev/null | python3 -c 'import sys,json; d=json.load(sys.stdin); a=d.get("advisories") or {}; print(sum(len(v) for v in a.values()) if isinstance(a, dict) else len(a))' 2>/dev/null)
  [ "$c" = "0" ] && echo "  ✅ composer audit (0 advisory)" || { echo "  ❌ composer audit: ${c:-gagal} advisory → composer audit"; r=1; }
  n=$(cd "$KRAFIO_FRONTEND" && npm audit --json 2>/dev/null | python3 -c 'import sys,json; v=json.load(sys.stdin).get("metadata",{}).get("vulnerabilities",{}); print(v.get("high",0)+v.get("critical",0), v.get("total",0))' 2>/dev/null)
  [ "${n%% *}" = "0" ] && echo "  ✅ npm audit (high/critical 0, total ${n##* })" || { echo "  ❌ npm audit: high/critical ${n%% *} → npm audit"; r=1; }
  return $r
}

case "$what" in
  backend) backend || rc=1 ;;
  frontend) frontend || rc=1 ;;
  security) security || rc=1 ;;
  *) backend || rc=1; frontend || rc=1 ;;
esac
exit $rc
