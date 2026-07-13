<?php
declare(strict_types=1);
// Simple mailer: writes email to storage/emails/ (log mode). Swap to SMTP later.
class Mailer {
    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
        $mode = env('MAIL_MODE', 'log');
        $from = env('MAIL_FROM_EMAIL', 'noreply@diskominfo.tangerangkab.go.id');
        $fromName = env('MAIL_FROM_NAME', 'SIMANTAP BMN');

        if ($mode === 'log') {
            $dir = APP_ROOT . '/storage/emails';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $filename = $dir . '/' . date('Ymd_His') . '_' . preg_replace('/[^a-z0-9]/i', '_', $toEmail) . '.html';
            $meta = "<!-- FROM: $fromName <$from>\n     TO: $toName <$toEmail>\n     SUBJECT: $subject\n     SENT_AT: " . date('c') . " -->\n";
            file_put_contents($filename, $meta . $htmlBody);
            return true;
        }

        // Basic mail() fallback (requires MTA)
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "From: $fromName <$from>\r\n";
        return @mail($toEmail, $subject, $htmlBody, $headers);
    }
}
