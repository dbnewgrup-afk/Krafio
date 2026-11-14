<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteVersion extends Model
{
    public const UPDATED_AT = null;

    public const KEEP = 30;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['content' => 'array', 'created_at' => 'datetime'];
    }

    /** Simpan snapshot; isi sama dengan versi terakhir tidak dicatat ulang; hanya KEEP versi terbaru yang disimpan. */
    public static function record(Website $w, string $kind, array $content, ?int $userId): void
    {
        $last = self::where('website_id', $w->id)->latest('id')->first();
        if ($last && $last->kind === $kind && $last->content == $content) {
            return;
        }
        self::create(['website_id' => $w->id, 'user_id' => $userId, 'kind' => $kind, 'content' => $content]);
        $keep = self::where('website_id', $w->id)->latest('id')->limit(self::KEEP)->pluck('id');
        self::where('website_id', $w->id)->whereNotIn('id', $keep)->delete();
    }
}
