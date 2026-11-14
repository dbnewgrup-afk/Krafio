#!/bin/zsh
# Simulasi webhook pembayaran bertanda tangan ke endpoint asli /api/webhooks/payment/{driver} (lokal saja).
#   pay.sh                         → bayar tagihan pending TERBARU milik akun QA
#   pay.sh KRF2611... [paid|failed|expired]
#   pay.sh --email user@x.test     → tagihan pending terbaru milik user itu
#   pay.sh --tamper [ref]          → tanda tangan palsu (harus ditolak 401, status tetap pending)
#   pay.sh --replay [ref]          → kirim 2× (uji idempotensi: aktivasi/komisi tidak dobel)
# Driver mengikuti gateway aktif di Admin (midtrans/xendit/tripay); bila manual → jalur HMAC generik "sim".
HERE="${0:A:h}"   # dihitung sebelum cd (path relatif)
source "$HERE/env.sh"
cd "$KRAFIO_BACKEND" || exit 1
grep -q '^APP_ENV=local' .env || { echo "Tolak: hanya untuk APP_ENV=local"; exit 1; }
export KRAFIO_REF="" KRAFIO_STATUS=paid KRAFIO_TAMPER=0 KRAFIO_TIMES=1 KRAFIO_EMAIL=qa@krafio.test
while [ $# -gt 0 ]; do
  case "$1" in
    --tamper) KRAFIO_TAMPER=1 ;;
    --replay) KRAFIO_TIMES=2 ;;
    --email) shift; KRAFIO_EMAIL="$1" ;;
    paid|failed|expired) KRAFIO_STATUS="$1" ;;
    *) KRAFIO_REF="$1" ;;
  esac; shift
done
php artisan tinker --execute="require '$HERE/php/webhook.php';" < /dev/null 2>&1 | grep -E "^(KIRIM|HASIL|TIDAK|TOLAK)"
