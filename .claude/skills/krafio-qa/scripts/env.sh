#!/bin/zsh
# Lingkungan uji Krafio di Mac (toolchain ~/.krafio-tools). Dipakai dengan:
#   source .claude/skills/krafio-qa/scripts/env.sh          → set PATH/PHPRC + variabel KRAFIO_*
#   .claude/skills/krafio-qa/scripts/env.sh status          → cek MySQL, API, Vite, HTTPS PHP, akun QA
T="$HOME/.krafio-tools"
export PATH="$T/bin:$T/node/bin:$T/mysql/bin:$PATH"
# php.ini lokal Krafio (bundel CA, batas unggah 210M, opcache) — sama dengan yang dipakai krafio-start.
[ -f "$T/php/php.ini" ] && export PHPRC="$T/php"
export KRAFIO_ROOT="$(git -C "${0:A:h}" rev-parse --show-toplevel 2>/dev/null || echo "$HOME/Krafio")"
export KRAFIO_BACKEND="$KRAFIO_ROOT/apps/backend"
export KRAFIO_FRONTEND="$KRAFIO_ROOT/apps/frontend"
export KRAFIO_WEB="${KRAFIO_WEB:-http://localhost:5173}"
export KRAFIO_QA_CREDS="$T/qa-credentials.json"

krafio_status() {
  local ok="✅" bad="❌"
  printf "%-22s %s\n" "PHP" "$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo "$bad tidak ditemukan")"
  printf "%-22s %s\n" "Node" "$(node -v 2>/dev/null || echo "$bad tidak ditemukan")"
  mysqladmin --defaults-file="$T/my.cnf" ping >/dev/null 2>&1 && printf "%-22s %s\n" "MySQL :3306" "$ok" || printf "%-22s %s\n" "MySQL :3306" "$bad mati → krafio-start"
  local api=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8000/api/public/settings)
  [ "$api" = "200" ] && printf "%-22s %s\n" "API :8000" "$ok" || printf "%-22s %s\n" "API :8000" "$bad ($api) → krafio-start"
  local web=$(curl -s -o /dev/null -w "%{http_code}" "$KRAFIO_WEB/")
  [ "$web" = "200" ] && printf "%-22s %s\n" "Vite :5173" "$ok" || printf "%-22s %s\n" "Vite :5173" "$bad ($web) → krafio-start"
  local https=$(php -r '$c=curl_init("https://www.google.com/");curl_setopt_array($c,[CURLOPT_NOBODY=>1,CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>8]);curl_exec($c);echo curl_errno($c)?curl_error($c):"ok";' 2>/dev/null)
  [ "$https" = "ok" ] && printf "%-22s %s\n" "HTTPS dari PHP (CLI)" "$ok" || printf "%-22s %s\n" "HTTPS dari PHP (CLI)" "$bad $https"
  local srv=$(ps -Ao command | grep -E "php .*-S 127.0.0.1:8000|artisan serve" | grep -v grep | head -1)
  if [ -n "$srv" ]; then
    echo "$srv" | grep -q -- "-c $T/php/php.ini" && grep -q "curl.cainfo" "$T/php/php.ini" 2>/dev/null \
      && printf "%-22s %s\n" "php.ini server API" "$ok (CA + unggah 210M)" \
      || printf "%-22s %s\n" "php.ini server API" "⚠️  server tidak memakai $T/php/php.ini berisi CA → krafio-stop && krafio-start"
  fi
  pgrep -f "artisan queue:work" >/dev/null && printf "%-22s %s\n" "Queue worker" "$ok" || printf "%-22s %s\n" "Queue worker" "$bad mati → job AI tertahan; scripts/jobs.sh worker"
  pgrep -f "artisan schedule:work" >/dev/null && printf "%-22s %s\n" "Scheduler" "$ok" || printf "%-22s %s\n" "Scheduler" "⚠️  mati (tugas berkala tidak jalan) → scripts/jobs.sh scheduler, atau jobs.sh tick"
  curl -s -m 2 http://127.0.0.1:11434/api/version >/dev/null && printf "%-22s %s\n" "Ollama :11434" "$ok" || printf "%-22s %s\n" "Ollama :11434" "⚠️  mati (AI teks lokal)"
  local mfa=$(cd "$KRAFIO_BACKEND" && php artisan tinker --execute='echo var_export((bool) App\Services\Settings::get("security.admin_mfa_required"), true);' < /dev/null 2>/dev/null | tail -1)
  printf "%-22s %s\n" "MFA admin" "$([ "$mfa" = "false" ] && echo "⚠️  MATI (lokal; nyalakan sebelum live)" || echo "$ok aktif")"
  [ -f "$T/qa-admin-credentials.json" ] && printf "%-22s %s\n" "Akun admin QA" "$ok" || printf "%-22s %s\n" "Akun admin QA" "⚠️  belum ada → scripts/admin-account.sh --mfa-off"
  [ -f "$KRAFIO_QA_CREDS" ] && printf "%-22s %s\n" "Akun QA" "$ok ($(python3 -c 'import json,sys;print(json.load(open(sys.argv[1]))["login"])' "$KRAFIO_QA_CREDS"))" || printf "%-22s %s\n" "Akun QA" "⚠️  belum ada → scripts/qa-account.sh"
}

[[ "${ZSH_EVAL_CONTEXT:-}" == *file* ]] || { [ "${1:-}" = "status" ] && krafio_status; }
