#!/bin/zsh
# Hapus data uji: user e2e-*@krafio.test, konten forum buatan akun QA, dan sesi cookie api.py tamu.
# Akun QA & demo tidak dihapus (reset QA: qa-account.sh).
HERE="${0:A:h}"
source "$HERE/env.sh"
cd "$KRAFIO_BACKEND" || exit 1
grep -q '^APP_ENV=local' .env || { echo "Tolak: hanya untuk APP_ENV=local"; exit 1; }
php artisan tinker --execute="require '$HERE/php/cleanup.php';" < /dev/null 2>&1 | grep -E "^(BERSIH|TOLAK)"
rm -f "$HOME/.krafio-tools/run/cookies-guest.txt"
