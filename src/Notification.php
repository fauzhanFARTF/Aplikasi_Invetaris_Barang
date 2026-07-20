<?php
declare(strict_types=1);
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/Telegram.php';

class Notification {
    public static function push(int $userId, string $title, string $body = '', ?string $link = null, bool $sendEmail = true): void {
        $stmt = db()->prepare("INSERT INTO notifications (user_id, title, body, link) VALUES (?,?,?,?)");
        $stmt->execute([$userId, $title, $body, $link]);

        if ($sendEmail) {
            // telegram_chat_id mungkin belum ada di DB lama (migrasi berjalan saat
            // request pertama), jadi pengambilannya tidak boleh mematikan notifikasi.
            try {
                $u = db()->prepare("SELECT email, name, telegram_chat_id FROM users WHERE id = ?");
                $u->execute([$userId]);
                $user = $u->fetch();
            } catch (Throwable $e) {
                $u = db()->prepare("SELECT email, name FROM users WHERE id = ?");
                $u->execute([$userId]);
                $user = $u->fetch();
            }
            if ($user) {
                $absLink = $link ? (APP_URL . $link) : APP_URL;
                $html = self::htmlTemplate($title, $body, $absLink);
                Mailer::send($user['email'], $user['name'], "[SIMANTAP] " . $title, $html);

                // Kanal ketiga: Telegram pribadi user (kalau Chat ID-nya sudah diisi).
                // Kegagalan hanya dicatat di dalam Telegram::send — tidak pernah
                // menggagalkan aksi utama yang memicu notifikasi ini.
                if (!empty($user['telegram_chat_id']) && Telegram::enabled()) {
                    Telegram::send((string) $user['telegram_chat_id'], $title, $body, $link);
                }
            }
        }
    }

    public static function pushToRole(string $role, string $title, string $body = '', ?string $link = null): void {
        $stmt = db()->prepare("SELECT id FROM users WHERE role = ? AND is_active = 1");
        $stmt->execute([$role]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            self::push((int)$uid, $title, $body, $link);
        }
    }

    /**
     * Notifikasi belum dibaca di KOTAK MASUK. Yang sudah diarsipkan tidak dihitung
     * supaya lonceng tidak terus menyala oleh notifikasi yang sengaja disingkirkan.
     */
    public static function unreadCount(int $userId): int {
        $s = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0 AND archived_at IS NULL");
        $s->execute([$userId]);
        return (int) $s->fetchColumn();
    }

    private static function htmlTemplate(string $title, string $body, string $link): string {
        $t = htmlspecialchars($title);
        $b = nl2br(htmlspecialchars($body));
        return <<<HTML
<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f8fafc;padding:24px;">
<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;padding:28px;border:1px solid #e2e8f0;">
    <div style="font-size:12px;letter-spacing:.15em;color:#f59e0b;font-weight:700;">SIMANTAP</div>
    <h2 style="color:#0f172a;margin:8px 0 16px;">$t</h2>
    <div style="color:#334155;line-height:1.6;font-size:14px;">$b</div>
    <div style="margin-top:24px;">
        <a href="$link" style="display:inline-block;background:#0f172a;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;font-size:14px;">Buka Sistem</a>
    </div>
    <hr style="margin:24px 0;border:none;border-top:1px solid #e2e8f0;">
    <div style="color:#64748b;font-size:12px;">Sistem Informasi Manajemen Aset Terpadu (SIMANTAP)<br>Diskominfo Kabupaten Tangerang — Smart Building.</div>
</div></body></html>
HTML;
    }
}
