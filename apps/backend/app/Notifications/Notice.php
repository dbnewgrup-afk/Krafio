<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Notifikasi in-app terpadu (PRD Bab 47). Kategori wajib (security, transaction) tidak dapat dimatikan. */
class Notice extends Notification
{
    public function __construct(public string $title, public string $body, public string $category = 'system', public ?string $link = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'category' => $this->category, 'link' => $this->link];
    }
}
