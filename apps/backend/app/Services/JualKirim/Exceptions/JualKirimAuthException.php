<?php

namespace App\Services\JualKirim\Exceptions;

/**
 * 401: kredensial login salah / email belum diverifikasi, token tidak valid atau kedaluwarsa,
 * atau X-API-KEY kosong / bukan milik organisasi user di dalam JWT.
 * Client sudah mencoba login ulang satu kali sebelum exception ini dilempar.
 */
class JualKirimAuthException extends JualKirimException {}
