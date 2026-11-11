<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\SecurityEvent;
use Illuminate\Database\Eloquent\Model;

/** Audit log untuk setiap tindakan penting (PRD 2.1-C catatan, Bab 36). Tidak mencatat secret. */
class Audit
{
    public static function log(string $action, ?Model $subject = null, array $meta = [], ?int $actorId = null): void
    {
        AuditLog::create([
            'actor_id' => $actorId ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'ip' => request()?->ip(),
            'meta' => $meta ?: null,
        ]);
    }

    public static function security(string $type, ?int $userId = null, string $severity = 'info', array $meta = []): void
    {
        SecurityEvent::create([
            'type' => $type,
            'user_id' => $userId,
            'ip' => request()?->ip(),
            'severity' => $severity,
            'meta' => $meta ?: null,
        ]);
    }
}
