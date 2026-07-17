<?php
declare(strict_types=1);
// Login dengan Google (OAuth 2.0 Authorization Code). Tanpa dependensi luar —
// proyek ini tidak memakai Composer, jadi HTTP-nya pakai cURL langsung.
//
// Pemisahan yang disengaja: semua yang menyentuh jaringan ada di fetchProfile(),
// sedangkan keputusan "profil ini mau diapakan" ada di resolveProfile() yang
// murni logika + database. Dengan begitu alur pendaftaran/verifikasi bisa diuji
// tanpa benar-benar memanggil Google.

class Google
{
    public static function enabled(): bool
    {
        return GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== '';
    }

    /** URL tujuan tombol "Masuk dengan Google", lengkap dengan state anti-CSRF. */
    public static function authUrl(): string
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['google_oauth_state'] = $state;
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => GOOGLE_CLIENT_ID,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'prompt'        => 'select_account',
        ]);
    }

    /** State dari Google harus sama dengan yang kita simpan — sekali pakai. */
    public static function verifyState(?string $state): bool
    {
        $saved = $_SESSION['google_oauth_state'] ?? null;
        unset($_SESSION['google_oauth_state']);
        return $saved !== null && $state !== null && hash_equals($saved, $state);
    }

    /**
     * Tukar authorization code jadi profil Google.
     * Mengembalikan ['sub','email','name','picture','email_verified'] atau null.
     */
    public static function fetchProfile(string $code): ?array
    {
        $token = self::post('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]);
        if (!isset($token['access_token'])) return null;

        $info = self::get('https://openidconnect.googleapis.com/v1/userinfo', $token['access_token']);
        if (!isset($info['sub'], $info['email'])) return null;

        return [
            'sub'            => (string) $info['sub'],
            'email'          => strtolower(trim((string) $info['email'])),
            'name'           => trim((string) ($info['name'] ?? '')),
            'picture'        => (string) ($info['picture'] ?? ''),
            'email_verified' => (bool) ($info['email_verified'] ?? false),
        ];
    }

    /**
     * Tentukan nasib sebuah profil Google. Murni logika + DB, tidak menyentuh
     * jaringan, sehingga bisa diuji langsung dari CLI.
     *
     * Hasil:
     *   ['action' => 'login',    'user' => [...]]  akun siap dipakai
     *   ['action' => 'register', 'profile' => [...]] belum terdaftar -> form daftar
     *   ['action' => 'pending']                    sudah daftar, menunggu admin
     *   ['action' => 'rejected']                   pendaftarannya ditolak
     *   ['action' => 'inactive']                   akunnya dinonaktifkan admin
     *   ['action' => 'deleted']                    akunnya sudah dihapus admin
     *   ['action' => 'error', 'message' => '...']
     *
     * 'register' HANYA dikembalikan bila benar-benar tidak ada akun apa pun dengan
     * email/google_id tersebut — termasuk yang sudah di-soft-delete. Ini yang
     * mencegah pendaftaran kedua: satu email = satu akun.
     */
    public static function resolveProfile(array $profile): array
    {
        if (!($profile['email_verified'] ?? false)) {
            return ['action' => 'error', 'message' => 'Email Google Anda belum terverifikasi oleh Google.'];
        }

        $user = self::findAccount($profile);
        if (!$user) return ['action' => 'register', 'profile' => $profile];

        // Akun terhapus tidak boleh mendaftar ulang diam-diam: email & google_id
        // UNIQUE, jadi INSERT-nya pasti gagal. Tanpa cabang ini, orangnya mengisi
        // form panjang lalu tersangkut di "menunggu verifikasi" selamanya —
        // padahal barisnya tidak pernah dibuat dan admin tidak melihat apa pun.
        if (!empty($user['deleted_at'])) return ['action' => 'deleted'];

        // Sambungkan google_id ke akun lama ber-password yang emailnya sama. Aman
        // karena Google sudah memastikan email itu milik yang login (email_verified
        // dicek di atas) — akun lama jadi bisa memakai tombol Google tanpa daftar ulang.
        if (empty($user['google_id'])) {
            db()->prepare("UPDATE users SET google_id = ? WHERE id = ?")
                ->execute([$profile['sub'], (int) $user['id']]);
            $user['google_id'] = $profile['sub'];
        }

        if (($user['reg_status'] ?? 'approved') === 'pending')  return ['action' => 'pending'];
        if (($user['reg_status'] ?? 'approved') === 'rejected') return ['action' => 'rejected'];
        if (!$user['is_active'])                                return ['action' => 'inactive'];

        return ['action' => 'login', 'user' => $user];
    }

    /**
     * Akun yang cocok dengan profil Google — lewat google_id maupun email, dan
     * SENGAJA termasuk baris yang sudah di-soft-delete. Sumber tunggal penyaring
     * pendaftaran ganda, dipakai baik saat form dibuka maupun saat dikirim.
     *
     * google_id diprioritaskan bila keduanya cocok ke baris berbeda.
     */
    public static function findAccount(array $profile): ?array
    {
        $stmt = db()->prepare("SELECT * FROM users WHERE google_id = ? OR email = ?
                               ORDER BY (google_id = ?) DESC, id ASC LIMIT 1");
        $stmt->execute([$profile['sub'], $profile['email'], $profile['sub']]);
        return $stmt->fetch() ?: null;
    }

    // ── HTTP ──────────────────────────────────────────────────────────────────
    private static function post(string $url, array $data): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        return is_string($res) ? (json_decode($res, true) ?: []) : [];
    }

    private static function get(string $url, string $accessToken): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        return is_string($res) ? (json_decode($res, true) ?: []) : [];
    }
}
