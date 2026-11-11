<?php

namespace App\Services\JualKirim\Data;

/** Schema `Organization`: id (uuid), name, api_key (23 karakter, huruf besar + angka). */
final class Organization
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $apiKey,
    ) {}

    /** @param  array{id?:string,name?:string,api_key?:string}  $a */
    public static function fromArray(array $a): self
    {
        return new self((string) ($a['id'] ?? ''), (string) ($a['name'] ?? ''), isset($a['api_key']) ? (string) $a['api_key'] : null);
    }
}
