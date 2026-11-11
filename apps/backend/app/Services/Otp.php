<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Models\User;
use App\Support\ApiException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * OTP verifikasi akun (PRD Bab 44, Design Spec §9.7): 6 digit, berlaku 5 menit, cooldown kirim ulang 60 dtk,
 * batas per jam, maks 5 percobaan. Kode disimpan sebagai hash dan tidak pernah dicatat di log produksi.
 */
class Otp
{
    public static function issue(User $user, string $channel = 'email'): OtpCode
    {
        $last = OtpCode::where('user_id', $user->id)->where('purpose', 'verify')->latest('id')->first();
        $resend = (int) Settings::get('otp.resend_seconds');
        if ($last && $last->created_at->diffInSeconds(now()) < $resend) {
            throw new ApiException('otp_cooldown', 'Tunggu sebelum meminta kode baru.', 429, ['retry_after' => $resend - (int) $last->created_at->diffInSeconds(now())]);
        }
        $hourly = OtpCode::where('user_id', $user->id)->where('created_at', '>', now()->subHour())->count();
        if ($hourly >= (int) Settings::get('otp.hourly_limit')) {
            throw new ApiException('otp_hourly_limit', 'Batas pengiriman kode per jam tercapai.', 429);
        }
        if ($channel === 'whatsapp' && (! Integrations::active('whatsapp') || ! $user->phone)) {
            throw new ApiException('channel_unavailable', 'Kanal WhatsApp belum tersedia.', 422);
        }

        $code = (string) random_int(100000, 999999);
        OtpCode::where('user_id', $user->id)->where('purpose', 'verify')->whereNull('consumed_at')->update(['consumed_at' => now()]);
        $otp = OtpCode::create([
            'user_id' => $user->id,
            'purpose' => 'verify',
            'channel' => $channel,
            'destination' => $channel === 'email' ? $user->email : $user->phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) Settings::get('otp.ttl_minutes')),
        ]);

        $vars = ['code' => $code, 'minutes' => (string) Settings::get('otp.ttl_minutes'), 'name' => $user->name];
        if ($channel === 'whatsapp') {
            [$ok] = Messenger::whatsapp($user->phone, 'otp', $vars, $user);
            if (! $ok) {
                Log::warning('OTP WhatsApp gagal', ['user' => $user->id]);
            }
        } else {
            Messenger::email($user->email, 'otp', $vars, $user, $user->locale ?? 'id');
        }
        // Hanya environment lokal: kode disimpan sementara agar alur dapat diuji tanpa SMTP.
        if (app()->environment('local')) {
            cache()->put("otp.dev.{$user->id}", $code, now()->addMinutes(10));
        }

        return $otp;
    }

    public static function current(User $user): ?OtpCode
    {
        return OtpCode::where('user_id', $user->id)->where('purpose', 'verify')->whereNull('consumed_at')->latest('id')->first();
    }

    public static function verify(User $user, string $code): void
    {
        $otp = self::current($user);
        $max = (int) Settings::get('otp.max_attempts');
        if (! $otp) {
            throw new ApiException('otp_expired', 'Kode kedaluwarsa.', 422, ['attempts_left' => 0]);
        }
        if ($otp->attempts >= $max) {
            throw new ApiException('too_many_attempts', 'Terlalu banyak percobaan.', 429, ['attempts_left' => 0]);
        }
        if ($otp->expires_at->isPast()) {
            throw new ApiException('otp_expired', 'Kode kedaluwarsa.', 422, ['attempts_left' => $max - $otp->attempts]);
        }
        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            Audit::security('otp_failed', $user->id);
            throw new ApiException('otp_invalid', 'Kode salah.', 422, ['attempts_left' => max(0, $max - $otp->attempts)]);
        }
        $otp->update(['consumed_at' => now()]);
    }

    public static function mask(string $dest): string
    {
        if (str_contains($dest, '@')) {
            [$u, $d] = explode('@', $dest, 2);

            return mb_substr($u, 0, 1).'***@'.$d;
        }

        return substr($dest, 0, 4).'****'.substr($dest, -3);
    }
}
