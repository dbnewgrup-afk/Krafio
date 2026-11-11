<?php

namespace App\Support;

/** Data etalase promo bawaan — dipakai seeder & migration data agar instalasi lama ikut punya contoh. */
class PromoShowcase
{
    public const SEED = [
        'BONUS100' => [
            'is_public' => true, 'sort' => 30,
            'headline' => 'Bonus 100 kredit untuk paket pertama',
            'description' => 'Beli paket apa pun mulai Rp59.000 dan dapatkan 100 kredit tambahan untuk foto & video AI.',
            'image' => '/assets/img/features/generate-photo.jpg',
        ],
    ];

    /** @return array<string, array> */
    public static function extraCoupons(): array
    {
        return [
            'MULAI20' => [
                'name' => 'Hemat 20% Starter & Pro', 'type' => 'percent', 'value' => 20, 'max_discount' => 75000,
                'plan_codes' => ['starter', 'pro'], 'cycles' => ['monthly'], 'duration' => 'once', 'max_uses' => 300, 'max_uses_per_user' => 1,
                'campaign' => 'flash_sale', 'is_active' => true, 'starts_at' => now(), 'ends_at' => now()->addDays(30),
                'is_public' => true, 'sort' => 10,
                'headline' => 'Hemat 20% bulan pertama',
                'description' => 'Untuk paket Starter atau Pro bulanan. Cocok untuk mulai produksi konten rutin.',
                'image' => '/assets/img/pages/pricing.jpg',
            ],
            'TAHUNAN50K' => [
                'name' => 'Potongan paket tahunan', 'type' => 'fixed', 'value' => 50000, 'min_purchase' => 500000,
                'cycles' => ['yearly'], 'duration' => 'once', 'max_uses_per_user' => 1,
                'campaign' => 'annual', 'is_active' => true,
                'is_public' => true, 'sort' => 20,
                'headline' => 'Potongan Rp50.000 paket tahunan',
                'description' => 'Bayar tahunan, hemat lebih banyak. Berlaku untuk paket tahunan mulai Rp500.000.',
                'image' => '/assets/img/features/website-builder.jpg',
            ],
        ];
    }
}
