<?php

namespace App\Support;

/**
 * Template email siklus langganan & affiliate (blueprint "Siklus Langganan" & "Saldo, Komisi Affiliate").
 * Dipakai seeder (instalasi baru) dan migration data (instalasi berjalan); tidak menimpa template yang sudah diedit admin.
 * Format: key => [subject_id, body_id, subject_en, body_en].
 */
class LifecycleMail
{
    /**
     * Template WhatsApp untuk event yang sama: event => [meta_name, isi pratinjau, urutan variabel].
     * meta_name WAJIB dibuat & disetujui di WhatsApp Manager (Meta) dengan nama & urutan variabel yang sama.
     */
    public const WA = [
        'subscription_expiring' => ['krafio_subscription_expiring', 'Halo {{1}}, paket {{2}} berakhir pada {{3}}. Kelola langganan di Krafio.', ['name', 'plan', 'ends_at']],
        'renewal_failed' => ['krafio_renewal_failed', 'Halo {{1}}, perpanjangan paket {{2}} gagal ({{3}}). Bayar sebelum {{4}} agar tidak kembali ke Gratis.', ['name', 'plan', 'reason', 'grace_until']],
        'subscription_ended' => ['krafio_subscription_ended', 'Halo {{1}}, paket {{2}} telah berakhir. Akun kembali ke paket Gratis; proyek & aset tetap tersimpan.', ['name', 'plan']],
        'withdrawal_approved' => ['krafio_withdrawal_approved', 'Halo {{1}}, withdrawal {{2}} disetujui dan segera ditransfer.', ['name', 'amount']],
        'commission_reversed' => ['krafio_commission_reversed', 'Halo {{1}}, komisi {{2}} dibatalkan: {{3}}.', ['name', 'amount', 'reason']],
        'refund_approved' => ['krafio_refund_approved', 'Halo {{1}}, refund {{2}} untuk {{3}} disetujui.', ['name', 'amount', 'reference']],
        'refund_rejected' => ['krafio_refund_rejected', 'Halo {{1}}, pengajuan refund {{2}} ditolak: {{3}}.', ['name', 'reference', 'reason']],
    ];

    public const TEMPLATES = [
        'subscription_expiring' => [
            'Paket {{plan}} berakhir dalam {{days}} hari',
            "Halo {{name}},\n\nPaket {{plan}} Anda berakhir pada {{ends_at}} ({{days}} hari lagi).\n\n{{renew_note}}\n\nKelola langganan: {{link}}",
            'Your {{plan}} plan ends in {{days}} days',
            "Hi {{name}},\n\nYour {{plan}} plan ends on {{ends_at}} ({{days}} days left).\n\n{{renew_note}}\n\nManage your plan: {{link}}",
        ],
        'renewal_failed' => [
            'Perpanjangan paket {{plan}} gagal',
            "Halo {{name}},\n\nPaket {{plan}} tidak dapat diperpanjang otomatis: {{reason}}.\n\nIsi saldo top-up lalu bayar dari halaman Langganan sebelum {{grace_until}}. Lewat tanggal itu akun kembali ke paket Gratis (proyek & aset tetap tersimpan).\n\n{{link}}",
            'Renewal of your {{plan}} plan failed',
            "Hi {{name}},\n\nYour {{plan}} plan could not be renewed automatically: {{reason}}.\n\nTop up your balance and pay from the Subscription page before {{grace_until}}. After that the account returns to the Free plan (projects & assets are kept).\n\n{{link}}",
        ],
        'subscription_ended' => [
            'Paket {{plan}} telah berakhir',
            "Halo {{name}},\n\nPaket {{plan}} telah berakhir dan akun Anda kembali ke paket Gratis. Proyek dan aset Anda tetap tersimpan.\n\nBerlangganan lagi kapan saja: {{link}}",
            'Your {{plan}} plan has ended',
            "Hi {{name}},\n\nYour {{plan}} plan has ended and your account is back on the Free plan. Your projects and assets are kept.\n\nSubscribe again anytime: {{link}}",
        ],
        'withdrawal_approved' => [
            'Withdrawal {{amount}} disetujui',
            "Halo {{name}},\n\nWithdrawal {{amount}} disetujui dan segera ditransfer (1–3 hari kerja). Anda akan menerima nomor referensi saat dana dikirim.",
            'Withdrawal {{amount}} approved',
            "Hi {{name}},\n\nYour {{amount}} withdrawal was approved and will be transferred within 1–3 business days. You will receive a reference number once it is sent.",
        ],
        'refund_approved' => [
            'Refund {{reference}} disetujui',
            "Halo {{name}},\n\nRefund {{amount}} untuk pembayaran {{reference}} disetujui. {{method_note}}\n\n{{item_note}}\n\nRiwayat: {{link}}",
            'Refund {{reference}} approved',
            "Hi {{name}},\n\nYour {{amount}} refund for payment {{reference}} was approved. {{method_note}}\n\n{{item_note}}\n\nHistory: {{link}}",
        ],
        'refund_rejected' => [
            'Pengajuan refund {{reference}} ditolak',
            "Halo {{name}},\n\nPengajuan refund untuk pembayaran {{reference}} ditolak: {{reason}}.\n\nHubungi tim support bila ada pertanyaan: {{link}}",
            'Refund request {{reference}} rejected',
            "Hi {{name}},\n\nYour refund request for payment {{reference}} was rejected: {{reason}}.\n\nContact support if you have questions: {{link}}",
        ],
        'commission_reversed' => [
            'Komisi affiliate dibatalkan',
            "Halo {{name}},\n\nKomisi {{amount}} dari pembelian referral dibatalkan sebelum masa tahan selesai: {{reason}}.\n\nRiwayat komisi: {{link}}",
            'Affiliate commission reversed',
            "Hi {{name}},\n\nA {{amount}} commission from a referral purchase was reversed before the hold period ended: {{reason}}.\n\nCommission history: {{link}}",
        ],
    ];
}
