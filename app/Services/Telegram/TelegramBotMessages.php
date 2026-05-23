<?php

namespace App\Services\Telegram;

class TelegramBotMessages
{
    /** @var array<string, string> Slash command (without /) => tutorial key */
    private const TUTORIAL_ALIASES = [
        'link' => 'link',
        'addwallet' => 'addwallet',
        'add_wallet' => 'addwallet',
        'transfer' => 'transfer',
        'tf' => 'transfer',
        'transaction' => 'transaction',
        'transaksi' => 'transaction',
        'catat' => 'transaction',
        'budget' => 'budget',
        'anggaran' => 'budget',
        'recurring' => 'recurring',
        'langganan' => 'recurring',
    ];

    /** @var array<string, string> Slash command => tutorial key (action commands that also have a usage hint) */
    private const ACTION_WITH_HINT = [
        'wallets' => 'wallets',
        'summary' => 'summary',
        'forecast' => 'forecast',
        'cancel' => 'cancel',
    ];

    public static function welcome(): string
    {
        return "Halo, saya asisten Flowlet.\n\n"
            ."Hubungkan akun dulu dari dashboard web, lalu kirim:\n"
            ."/link KODE\n\n"
            ."Ketik /help untuk daftar perintah lengkap.";
    }

    public static function help(): string
    {
        $lines = [
            'Halo, saya asisten Flowlet.',
            '',
            'Perintah cepat (langsung jalan):',
            '• /wallets — saldo semua wallet',
            '• /summary — ringkasan bulan ini',
            '• /forecast — proyeksi bulan ini',
            '• /cancel — batalkan aksi pending',
            '',
            'Panduan cara pakai (kirim perintah ini untuk tutorial):',
            '• /link — hubungkan akun Telegram',
            '• /addwallet — buat wallet baru',
            '• /transfer — pindah saldo antar wallet',
            '• /transaction — catat pemasukan / pengeluaran',
            '• /budget — atur anggaran kategori',
            '• /recurring — langganan / transaksi berulang',
            '',
            'Kamu juga bisa pakai bahasa natural, contoh:',
            '• saldo gue berapa?',
            '• saldo GOPAY berapa?',
            '• bulan ini keuangan gue aman nggak?',
            '',
            'Semua aksi yang mengubah data akan diminta konfirmasi dulu (balas *yes* atau *cancel*).',
        ];

        return implode("\n", $lines);
    }

    /**
     * Parse leading slash command. Returns canonical key e.g. "addwallet", "wallets", "help".
     */
    public static function parseSlashCommand(string $text): ?string
    {
        $text = trim($text);
        if (! str_starts_with($text, '/')) {
            return null;
        }

        if (! preg_match('#^/([a-z0-9_]+)(?:@\w+)?(?:\s|$)#iu', $text, $m)) {
            return null;
        }

        $raw = mb_strtolower($m[1]);

        if (isset(self::TUTORIAL_ALIASES[$raw])) {
            return self::TUTORIAL_ALIASES[$raw];
        }

        if (isset(self::ACTION_WITH_HINT[$raw])) {
            return $raw;
        }

        return match ($raw) {
            'start', 'help', 'wallets', 'summary', 'forecast', 'cancel' => $raw,
            default => null,
        };
    }

    public static function isTutorialCommand(string $commandKey): bool
    {
        return in_array($commandKey, array_values(self::TUTORIAL_ALIASES), true);
    }

    public static function hasLinkCode(string $text): bool
    {
        return (bool) preg_match('/^\/link\s+\S+/iu', trim($text));
    }

    public static function tutorial(string $commandKey): ?string
    {
        return match ($commandKey) {
            'link' => self::linkTutorial(),
            'addwallet' => self::addWalletTutorial(),
            'transfer' => self::transferTutorial(),
            'transaction' => self::transactionTutorial(),
            'budget' => self::budgetTutorial(),
            'recurring' => self::recurringTutorial(),
            'wallets' => self::walletsTutorial(),
            'summary' => self::summaryTutorial(),
            'forecast' => self::forecastTutorial(),
            'cancel' => self::cancelTutorial(),
            default => null,
        };
    }

    public static function linkTutorial(): string
    {
        return "Hubungkan akun Flowlet ke Telegram:\n\n"
            ."1. Buka Flowlet (web) → Profile\n"
            ."2. Generate *Telegram link code*\n"
            ."3. Kirim ke bot:\n"
            ."/link KODE\n\n"
            ."Contoh:\n"
            ."/link ABC123\n\n"
            ."Kode sekali pakai dan ada batas waktu. Setelah terhubung, kamu bisa pakai semua fitur.";
    }

    public static function addWalletTutorial(): string
    {
        return "Buat wallet baru\n\n"
            ."Kirim pesan natural (bukan slash command) dengan format:\n"
            ."• tipe wallet (Bank / E-Wallet / Cash)\n"
            ."• nama wallet\n"
            ."• saldo awal (opsional)\n\n"
            ."Contoh yang didukung parser:\n"
            ."• buat wallet e-wallet nama GOPAY saldo 348.455\n"
            ."• buat wallet ewallet nama shopeepay saldo 10.861\n"
            ."• buat wallet cash nama uang dompet saldo 50 ribu\n"
            ."• buat rekening BCA saldo awal 1 juta\n"
            ."• tambah ewallet dana 200 ribu\n"
            ."• buat wallet baru type cash dengan nama uang dompet, 50 ribu\n\n"
            ."Kalau tipe belum jelas, bot akan tanya: Bank, E-Wallet, atau Cash.\n"
            ."Setelah itu balas *yes* untuk simpan.";
    }

    public static function transferTutorial(): string
    {
        return "Transfer antar wallet\n\n"
            ."Sebutkan jumlah, wallet asal, dan wallet tujuan.\n\n"
            ."Contoh:\n"
            ."• transfer 20 ribu dari GOPAY ke SHOPEEPAY\n"
            ."• transfer 20 ribu dr gopay ke shopeepay\n"
            ."• tf 50rb dari gopay ke shopeepay\n"
            ."• pindah 100 ribu dari BCA ke GOPAY\n\n"
            ."Wallet harus sudah ada di akunmu. Bot akan minta konfirmasi sebelum eksekusi.";
    }

    public static function transactionTutorial(): string
    {
        return "Catat transaksi (pemasukan / pengeluaran)\n\n"
            ."Contoh pengeluaran:\n"
            ."• catat pengeluaran 25 ribu dari GOPAY buat kopi\n"
            ."• beli makan 50 ribu make gopay\n"
            ."• bayar listrik 150 ribu dari BCA\n\n"
            ."Contoh pemasukan:\n"
            ."• income 200 ribu ke SHOPEEPAY dari freelance\n"
            ."• terima gaji 5 juta ke rekening BCA\n\n"
            ."Kamu bisa sebut wallet, nominal, dan keterangan. Konfirmasi dulu sebelum disimpan.";
    }

    public static function budgetTutorial(): string
    {
        return "Atur budget (anggaran) per kategori\n\n"
            ."Contoh:\n"
            ."• set budget kategori belanja 1 jt setiap bulan\n"
            ."• set budget buat kategori investasi 1jt setiap bulan\n"
            ."• budget makan 1.5 juta\n"
            ."• anggaran transport 500 ribu bulan ini\n\n"
            ."Kategori harus sudah ada di aplikasi web (atau bot akan tanya nama kategori).\n"
            ."Nominal: 1jt, 1 juta, 1.5 juta, 500 ribu, dll.";
    }

    public static function recurringTutorial(): string
    {
        return "Transaksi berulang / langganan\n\n"
            ."Contoh:\n"
            ."• buat recurring Netflix 150 ribu tiap bulan dari GOPAY\n"
            ."• langganan Spotify 55 ribu setiap bulan dari SHOPEEPAY\n"
            ."• recurring listrik 300 ribu monthly dari BCA\n\n"
            ."Sebut nama, nominal, periode (bulanan), dan wallet sumber jika ada.";
    }

    public static function walletsTutorial(): string
    {
        return "Lihat saldo wallet\n\n"
            ."Perintah cepat:\n"
            ."• /wallets — semua wallet + total saldo\n\n"
            ."Bahasa natural:\n"
            ."• saldo gue berapa?\n"
            ."• saldo gw berapa sekarang\n"
            ."• saldo GOPAY berapa?";
    }

    public static function summaryTutorial(): string
    {
        return "Ringkasan keuangan bulan ini\n\n"
            ."Perintah cepat:\n"
            ."• /summary\n\n"
            ."Bahasa natural:\n"
            ."• ringkasan bulan ini\n"
            ."• summary keuangan gue";
    }

    public static function forecastTutorial(): string
    {
        return "Proyeksi / forecast bulan ini\n\n"
            ."Perintah cepat:\n"
            ."• /forecast\n\n"
            ."Bahasa natural:\n"
            ."• forecast bulan ini\n"
            ."• proyeksi pengeluaran gue";
    }

    public static function cancelTutorial(): string
    {
        return "Batalkan aksi pending\n\n"
            ."• /cancel — batalkan draft yang menunggu konfirmasi atau pengisian data\n"
            ."• batal — sama seperti /cancel\n\n"
            ."Saat konfirmasi, kamu juga bisa balas *cancel* atau *no* untuk tidak jadi simpan.";
    }
}
