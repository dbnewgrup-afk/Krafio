<?php

namespace App\Services;

/** TOTP RFC 6238 (SHA-1, 6 digit, 30 dtk) untuk MFA Admin (Design Spec §9.2, PRD 35.3). */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        $raw = random_bytes($bytes);
        $bits = '';
        foreach (str_split($raw) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    private static function base32Decode(string $s): string
    {
        $s = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $s));
        $bits = '';
        foreach (str_split($s) as $c) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }

    public static function code(string $secret, ?int $time = null): string
    {
        $counter = intdiv($time ?? time(), 30);
        $bin = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $bin, self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0xF;
        $val = ((ord($hash[$offset]) & 0x7F) << 24) | ((ord($hash[$offset + 1]) & 0xFF) << 16) | ((ord($hash[$offset + 2]) & 0xFF) << 8) | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($val % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return false;
        }
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($secret, time() + $i * 30), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function uri(string $secret, string $email): string
    {
        return 'otpauth://totp/'.rawurlencode('Krafio Admin:'.$email).'?secret='.$secret.'&issuer=Krafio';
    }
}
