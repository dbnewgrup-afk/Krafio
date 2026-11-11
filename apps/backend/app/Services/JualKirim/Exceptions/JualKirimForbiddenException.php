<?php

namespace App\Services\JualKirim\Exceptions;

/**
 * 403: role user di organisasi tidak punya permission yang dibutuhkan endpoint
 * (mis. `payment-read`, `multi-disbursement-create`), atau user sudah bukan anggota organisasi.
 * Perbaiki di pengaturan role JualKirim — mengulang request tidak akan membantu.
 */
class JualKirimForbiddenException extends JualKirimException {}
