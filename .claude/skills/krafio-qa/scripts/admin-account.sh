#!/bin/zsh
# Buat / reset akun ADMIN QA khusus lokal (qa-admin@krafio.test) untuk uji Admin Panel via api.py/browser.mjs.
# Kata sandi baru → ~/.krafio-tools/qa-admin-credentials.json (chmod 600), tidak dicetak. Akun admin utama tidak disentuh.
#   admin-account.sh            → buat/reset akun admin QA
#   admin-account.sh --mfa-off  → sekaligus matikan MFA admin (security.admin_mfa_required=false) di DB lokal
HERE="${0:A:h}"   # dihitung sebelum cd (path relatif)
source "$HERE/env.sh"
cd "$KRAFIO_BACKEND" || exit 1
grep -q '^APP_ENV=local' .env || { echo "Tolak: hanya untuk APP_ENV=local"; exit 1; }
[ "${1:-}" = "--mfa-off" ] && export KRAFIO_MFA_OFF=1
umask 077
export KRAFIO_OUT="$HOME/.krafio-tools/qa-admin-credentials.json"
php artisan tinker --execute="require '$HERE/php/admin_account.php';" < /dev/null 2>&1 | grep -E "^(OK|TOLAK)" || { echo "Gagal membuat akun admin QA"; exit 1; }
rm -f "$HOME/.krafio-tools/run/cookies-admin.txt"
