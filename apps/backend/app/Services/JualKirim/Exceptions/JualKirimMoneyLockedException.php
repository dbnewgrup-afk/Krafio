<?php

namespace App\Services\JualKirim\Exceptions;

/**
 * Endpoint pemindah uang dipanggil saat JUALKIRIM_MONEY_ENABLED=false.
 * Dilempar sebelum request dikirim — tidak ada yang terjadi di sisi JualKirim.
 */
class JualKirimMoneyLockedException extends JualKirimException {}
