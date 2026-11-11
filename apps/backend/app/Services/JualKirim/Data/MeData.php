<?php

namespace App\Services\JualKirim\Data;

/** Schema `MeResponse.data`: user {id, email, first_name, last_name} + organizations[]. */
final class MeData
{
    /** @param  list<Organization>  $organizations */
    public function __construct(
        public readonly string $userId,
        public readonly string $email,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly array $organizations,
    ) {}

    /** @param  array{data?:array{user?:array<string,string>,organizations?:list<array<string,string>>}}  $body */
    public static function fromArray(array $body): self
    {
        $u = $body['data']['user'] ?? [];

        return new self(
            (string) ($u['id'] ?? ''),
            (string) ($u['email'] ?? ''),
            (string) ($u['first_name'] ?? ''),
            (string) ($u['last_name'] ?? ''),
            array_map(fn ($o) => Organization::fromArray((array) $o), $body['data']['organizations'] ?? []),
        );
    }

    public function fullName(): string
    {
        return trim("{$this->firstName} {$this->lastName}");
    }

    /** Organisasi pemilik API key tertentu (dicocokkan persis). */
    public function organizationForKey(?string $apiKey): ?Organization
    {
        if (! $apiKey) {
            return null;
        }
        foreach ($this->organizations as $o) {
            if ($o->apiKey !== null && hash_equals($o->apiKey, $apiKey)) {
                return $o;
            }
        }

        return null;
    }
}
