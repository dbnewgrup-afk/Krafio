#!/bin/zsh
# Cek kesiapan go-live: hal yang sengaja longgar di lokal (MFA admin mati, akun QA, mailer log, simulasi bayar).
# Jalankan di server produksi (APP_ENV=production) sebelum rilis; di lokal hasil ❌ itu wajar.
HERE="${0:A:h}"
source "$HERE/env.sh"
cd "$KRAFIO_BACKEND" || exit 1
php artisan tinker --execute="require '$HERE/php/golive.php';" < /dev/null 2>&1 | grep -E "^(✅|❌|GOLIVE)"
