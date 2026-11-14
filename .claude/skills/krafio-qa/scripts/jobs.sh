#!/bin/zsh
# Queue & scheduler Krafio (lokal).
#   jobs.sh status            → antrean, gagal, ai_jobs per status, worker/scheduler hidup?, log terakhir
#   jobs.sh worker            → nyalakan queue worker (ai,default) bila mati (sama dengan krafio-start)
#   jobs.sh drain             → proses semua job antre lalu berhenti (deterministik untuk uji)
#   jobs.sh scheduler [stop]  → nyalakan/matikan schedule:work di latar (log run/scheduler.log)
#   jobs.sh tick              → jalankan tugas terjadwal yang jatuh tempo SEKARANG (schedule:run) sekali
#   jobs.sh list              → daftar tugas terjadwal & jadwal berikutnya
#   jobs.sh run <nama>        → paksa jalankan satu tugas terjadwal (mis. release-commissions, subscription-renewals)
#   jobs.sh failed [retry]    → lihat / ulangi job gagal
HERE="${0:A:h}"   # dihitung sebelum cd (path relatif)
source "$HERE/env.sh"
T="$HOME/.krafio-tools"; cd "$KRAFIO_BACKEND" || exit 1
PHP=(php -c "$T/php/php.ini")
q() { mysql --defaults-file="$T/my.cnf" -N -e "$1" krafio 2>/dev/null; }
case "${1:-status}" in
  status)
    echo "Antrean jobs     : $(q 'select count(*) from jobs') (per antrean: $(q "select group_concat(concat(queue,'=',n)) from (select queue, count(*) n from jobs group by queue) x"))"
    echo "Job gagal        : $(q 'select count(*) from failed_jobs')"
    echo "ai_jobs          : $(q "select group_concat(concat(status,'=',n)) from (select status, count(*) n from ai_jobs group by status) x")"
    pgrep -f "artisan queue:work" >/dev/null && echo "Worker           : ✅ hidup" || echo "Worker           : ❌ mati → jobs.sh worker"
    pgrep -f "artisan schedule:work" >/dev/null && echo "Scheduler        : ✅ hidup" || echo "Scheduler        : ⚠️  mati → jobs.sh scheduler / jobs.sh tick"
    [ -f "$T/run/worker.log" ] && { echo "--- worker.log (5 baris terakhir)"; tail -5 "$T/run/worker.log"; } ;;
  worker)
    pgrep -f "artisan queue:work" >/dev/null && { echo "Worker sudah hidup"; exit 0; }
    nohup "${PHP[@]}" artisan queue:work --queue=ai,default --timeout=1800 --tries=1 --sleep=1 > "$T/run/worker.log" 2>&1 &!
    sleep 1; pgrep -f "artisan queue:work" >/dev/null && echo "✅ Worker hidup (log: $T/run/worker.log)" ;;
  drain) "${PHP[@]}" artisan queue:work --queue=ai,default --stop-when-empty --tries=1 --timeout=600 ;;
  scheduler)
    if [ "${2:-}" = "stop" ]; then pkill -f "artisan schedule:work" && echo "Scheduler dimatikan"; exit 0; fi
    pgrep -f "artisan schedule:work" >/dev/null && { echo "Scheduler sudah hidup"; exit 0; }
    nohup "${PHP[@]}" artisan schedule:work > "$T/run/scheduler.log" 2>&1 &!
    sleep 1; pgrep -f "artisan schedule:work" >/dev/null && echo "✅ Scheduler hidup (log: $T/run/scheduler.log)" ;;
  tick) "${PHP[@]}" artisan schedule:run ;;
  list) "${PHP[@]}" artisan schedule:list ;;
  run) [ -n "${2:-}" ] || { echo "Pakai: jobs.sh run <nama> (lihat jobs.sh list)"; exit 64; }; "${PHP[@]}" artisan schedule:test --name="$2" ;;
  failed) if [ "${2:-}" = "retry" ]; then "${PHP[@]}" artisan queue:retry all; else "${PHP[@]}" artisan queue:failed; fi ;;
  *) sed -n '2,10p' "$0"; exit 64 ;;
esac
