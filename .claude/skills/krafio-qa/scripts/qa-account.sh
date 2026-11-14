#!/bin/zsh
# Buat / isi ulang akun QA (semua fitur, kredit & saldo melimpah) untuk uji alur yang MENGUBAH data,
# agar akun demo tidak kotor. Kata sandi baru disimpan ke ~/.krafio-tools/qa-credentials.json (chmod 600),
# tidak dicetak ke layar dan tidak masuk git. Hanya untuk APP_ENV=local.
HERE="${0:A:h}"   # dihitung sebelum cd (path relatif)
source "$HERE/env.sh"
cd "$KRAFIO_BACKEND" || exit 1
grep -q '^APP_ENV=local' .env || { echo "Tolak: hanya untuk APP_ENV=local"; exit 1; }
EMAIL="${1:-qa@krafio.test}"
OUT=$(php artisan krafio:test-account --email="$EMAIL" --reset-password 2>&1) || { echo "$OUT" | tail -5; exit 1; }
PASS=$(echo "$OUT" | grep "Kata sandi" | awk -F'|' '{gsub(/^ +| +$/, "", $3); print $3}')
[ -n "$PASS" ] || { echo "Gagal membaca kata sandi dari output perintah."; exit 1; }
umask 077
python3 -c 'import json,sys; json.dump({"login": sys.argv[1], "password": sys.argv[2]}, open(sys.argv[3], "w"))' "$EMAIL" "$PASS" "$KRAFIO_QA_CREDS"
rm -f "$HOME/.krafio-tools/run/cookies-qa.txt"
echo "Akun QA siap: $EMAIL (kata sandi tersimpan di $KRAFIO_QA_CREDS)"
