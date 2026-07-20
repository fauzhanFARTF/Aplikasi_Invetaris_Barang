<?php
declare(strict_types=1);

/**
 * Pengiriman notifikasi ke Telegram lewat Bot API.
 *
 * Dipakai sebagai kanal KETIGA di Notification::push() (setelah in-app & email).
 * Prinsip yang dipegang: notifikasi adalah efek samping — kegagalan mengirim
 * TIDAK BOLEH menggagalkan aksi utama pengguna (menyetujui peminjaman, menyerahkan
 * alat, dsb). Karena itu semua kegagalan hanya dicatat ke error_log, tidak pernah
 * dilempar sebagai exception, dan timeout-nya pendek supaya Telegram yang lambat
 * tidak membuat halaman ikut menggantung.
 */
class Telegram
{
    /** Batas aman panjang pesan Telegram (limit resmi 4096 karakter). */
    private const MAX_LEN = 3800;

    /** Fitur aktif hanya bila token bot terisi di .env. */
    public static function enabled(): bool
    {
        return defined('TELEGRAM_BOT_TOKEN') && TELEGRAM_BOT_TOKEN !== '';
    }

    /**
     * Chat ID Telegram: angka, boleh negatif (grup/channel).
     * Divalidasi ketat supaya tidak ada isian sembarangan yang tersimpan.
     */
    public static function isValidChatId(string $chatId): bool
    {
        return (bool) preg_match('/^-?\d{1,20}$/', trim($chatId));
    }

    /**
     * Kirim satu notifikasi. Mengembalikan [ok, message] supaya tombol "Kirim Tes"
     * di halaman Profil bisa menampilkan alasan yang jelas saat gagal.
     */
    public static function send(string $chatId, string $title, string $body = '', ?string $link = null): array
    {
        if (!self::enabled())                 return [false, 'Notifikasi Telegram belum diaktifkan (TELEGRAM_BOT_TOKEN kosong).'];
        if (!self::isValidChatId($chatId))    return [false, 'Chat ID Telegram tidak valid.'];

        $text = '<b>' . self::esc($title) . '</b>';
        if ($body !== '') $text .= "\n\n" . self::esc($body);
        if ($link) {
            $abs = preg_match('#^https?://#i', $link) ? $link : rtrim(APP_URL, '/') . $link;
            $text .= "\n\n" . '<a href="' . self::esc($abs) . '">Buka di SIMANTAP</a>';
        }
        if (mb_strlen($text) > self::MAX_LEN) {
            $text = mb_substr($text, 0, self::MAX_LEN) . '…';
        }

        return self::call('sendMessage', [
            'chat_id'                  => trim($chatId),
            'text'                     => $text,
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => true,
        ]);
    }

    /** Panggil Bot API. Selalu mengembalikan array, tidak pernah melempar. */
    private static function call(string $method, array $payload): array
    {
        $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/' . $method;
        try {
            if (!function_exists('curl_init')) {
                return [false, 'Ekstensi cURL tidak tersedia di server.'];
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($payload),
                CURLOPT_TIMEOUT        => 8,   // pendek: jangan menyandera request pengguna
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $raw  = curl_exec($ch);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($raw === false || $raw === '') {
                error_log('[simassta-bmn] Telegram gagal: ' . ($err ?: 'tidak ada balasan'));
                return [false, 'Gagal menghubungi Telegram' . ($err ? ": $err" : '.')];
            }
            $res = json_decode($raw, true);
            if (!is_array($res) || empty($res['ok'])) {
                $desc = is_array($res) ? ($res['description'] ?? 'balasan tidak dikenali') : 'balasan tidak dikenali';
                error_log('[simassta-bmn] Telegram ditolak: ' . $desc);
                return [false, self::humanize((string) $desc)];
            }
            return [true, 'Pesan Telegram terkirim.'];
        } catch (Throwable $e) {
            error_log('[simassta-bmn] Telegram error: ' . $e->getMessage());
            return [false, 'Terjadi kesalahan saat mengirim ke Telegram.'];
        }
    }

    /** Terjemahkan pesan galat Telegram yang paling sering muncul ke bahasa yang bisa ditindaklanjuti. */
    private static function humanize(string $desc): string
    {
        $d = strtolower($desc);
        if (str_contains($d, 'chat not found')) {
            return 'Chat tidak ditemukan. Pastikan Anda sudah menekan START pada bot SIMANTAP di Telegram, dan Chat ID-nya benar.';
        }
        if (str_contains($d, 'bot was blocked')) {
            return 'Bot diblokir oleh pengguna Telegram ini. Buka blokir bot lalu coba lagi.';
        }
        if (str_contains($d, 'unauthorized')) {
            return 'Token bot Telegram tidak valid. Periksa TELEGRAM_BOT_TOKEN di .env.';
        }
        return 'Telegram menolak: ' . $desc;
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
