<?php

namespace App\Services\JualKirim\Resources;

use App\Services\JualKirim\Data\LoginResult;
use App\Services\JualKirim\Data\MeData;
use App\Services\JualKirim\JualKirimClient;

/** Tag Auth. */
class AuthResource extends Resource
{
    /**
     * POST /api/v1/auth/login (apiLogin) — login langsung dengan kredensial dari config,
     * tanpa menyentuh cache. Untuk pemakaian biasa cukup panggil endpoint lain; token dikelola otomatis.
     */
    public function login(): LoginResult
    {
        return $this->client->login();
    }

    /**
     * GET /api/v1/me (apiMe) — profil user + semua organisasi, dibaca dari claims JWT.
     * Spec: hanya butuh Bearer token.
     */
    public function me(): MeData
    {
        return MeData::fromArray($this->client->request('GET', '/api/v1/me', [], JualKirimClient::AUTH_BEARER));
    }
}
